<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

include "connection.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// 1. Récupération et validation des données
$nom      = trim($_POST['nom']      ?? '');
$prenom   = trim($_POST['prenom']   ?? '');
$email    = trim($_POST['email']    ?? '');
$dob      = trim($_POST['dob']      ?? '');
$password = $_POST['password']      ?? '';

if (!$nom || !$prenom || !$email || !$dob || !$password) {
    header("Location: register.php?error=empty");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=invalidemail");
    exit();
}

$fullName        = $prenom . " " . $nom;
$hashed_password = password_hash($password, PASSWORD_ARGON2ID);
$otp             = rand(100000, 999999);
$otp_expires     = date('Y-m-d H:i:s', time() + 600); // expire dans 10 min

// 2. Vérification email existant
$stmt = $con->prepare("SELECT docemail FROM doctor WHERE docemail = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->get_result()->num_rows > 0 && header("Location: register.php?error=emailexist") && exit();
$stmt->close();

// 3. Insertion en base
$sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, otp_expires, status, dob) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?)";
$insertStmt = $con->prepare($sql);

// Si la colonne otp_expires n'existe pas encore, utilise la version sans :
// $sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, status, dob) VALUES (?, ?, ?, ?, 'pending', ?)";
// $insertStmt->bind_param("sssis", $email, $fullName, $hashed_password, $otp, $dob);

$insertStmt->bind_param("sssiss", $email, $fullName, $hashed_password, $otp, $otp_expires, $dob);

if (!$insertStmt->execute()) {
    error_log("DB insert failed: " . $insertStmt->error);
    header("Location: register.php?error=dbfail");
    exit();
}
$insertStmt->close();

// 4. Envoi de l'email OTP
$mailUser = getenv('MAIL_USER') ?: 'psyspace.all@gmail.com';
$mailPass = getenv('MAIL_PASS') ?: 'lszg gkpz ylbg ypdt';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $mailUser;
    $mail->Password   = $mailPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;

    $mail->setFrom('no-reply@psyspace.me', 'PsySpace');
    $mail->addAddress($email, $fullName);

    $mail->isHTML(true);
    $mail->Subject = "Votre code de validation PsySpace";
    $mail->Body    = "
    <div style='background:#f8fafc;padding:40px 0;font-family:sans-serif;'>
      <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;'>
        <div style='background:#2563eb;padding:30px;text-align:center;'>
          <h1 style='color:#fff;margin:0;font-size:24px;font-weight:bold;'>PsySpace</h1>
        </div>
        <div style='padding:40px;text-align:center;'>
          <h2 style='color:#1e293b;font-size:20px;'>Vérifiez votre adresse email</h2>
          <p style='color:#64748b;'>Bonjour <strong>{$fullName}</strong>, utilisez le code ci-dessous pour finaliser votre inscription :</p>
          <div style='background:#f1f5f9;border-radius:12px;padding:25px;margin:20px 0;'>
            <span style='font-family:monospace;font-size:36px;font-weight:bold;letter-spacing:12px;color:#2563eb;'>{$otp}</span>
          </div>
          <p style='color:#94a3b8;font-size:12px;'>Ce code expire dans <strong>10 minutes</strong>.</p>
        </div>
      </div>
    </div>";

    $mail->AltBody = "Votre code de validation PsySpace : {$otp} (expire dans 10 minutes)";

    $mail->send();

    // Stocker l'email en session pour verify.php
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['pending_email'] = $email;

    header("Location: verify.php?email=" . urlencode($email));
    exit();

} catch (Exception $e) {
    // Log l'erreur réelle sans l'exposer à l'utilisateur
    error_log("PHPMailer Error pour {$email} : " . $mail->ErrorInfo);

    // Supprimer le compte créé si l'email échoue (évite les comptes zombies)
    $del = $con->prepare("DELETE FROM doctor WHERE docemail = ? AND status = 'pending'");
    $del->bind_param("s", $email);
    $del->execute();
    $del->close();

    header("Location: register.php?error=mailfail");
    exit();
}