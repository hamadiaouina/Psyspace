<?php
session_start();

// 1. Diagnostic d'erreurs
ini_set('display_errors', '1');
error_reporting(E_ALL);

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

// 3. Récupération
$nom      = trim($_POST['nom']      ?? '');
$prenom   = trim($_POST['prenom']   ?? '');
$email    = trim($_POST['email']    ?? '');
$dob      = trim($_POST['dob']      ?? '');
$password = $_POST['password']      ?? '';

if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
    header("Location: register.php?error=empty");
    exit();
}

// --- NOUVEAUTÉ : NETTOYAGE DES INSCRIPTIONS EXPIRÉES (5 MIN) ---
// On supprime les comptes 'pending' avec cet email s'ils ont plus de 5 minutes
$cleanup_query = "DELETE FROM doctor WHERE docemail = ? AND status = 'pending' AND created_at < NOW() - INTERVAL 5 MINUTE";
$stmt_cleanup = $con->prepare($cleanup_query);
$stmt_cleanup->bind_param("s", $email);
$stmt_cleanup->execute();
$stmt_cleanup->close();
// --------------------------------------------------------------

$fullName = $prenom . " " . $nom;
$hashed_password = password_hash($password, PASSWORD_ARGON2ID);
$otp = (int)rand(100000, 999999);

// 4. Vérification email unique (Après le nettoyage)
$stmt = $con->prepare("SELECT docid, status FROM doctor WHERE docemail = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        // Le compte est 'pending' mais a MOINS de 5 min (puisqu'il n'a pas été supprimé par le cleanup)
        header("Location: register.php?error=pending_recent"); 
        exit();
    } else {
        // Le compte est déjà 'active'
        header("Location: register.php?error=emailexist");
        exit();
    }
}
$stmt->close();

// 5. Insertion en base
// Note: Assure-toi que ta table doctor a bien la colonne created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
$sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, status, dob) VALUES (?, ?, ?, ?, 'pending', ?)";
$insertStmt = $con->prepare($sql);
$insertStmt->bind_param("sssis", $email, $fullName, $hashed_password, $otp, $dob);

if (!$insertStmt->execute()) {
    die("Erreur SQL : " . $insertStmt->error);
}
$insertStmt->close();

// 6. Envoi de l'OTP
$mail = new PHPMailer(true);
try {
    $smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
    $smtp_pass = getenv('SMTP_PASS') ?: ''; 

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user; 
    $mail->Password   = $smtp_pass; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtp_user, 'PsySpace AI');
    $mail->addAddress($email, $fullName);

    $mail->isHTML(true);
    $mail->Subject = "Votre code de validation PsySpace";
    $mail->Body    = "
    <div style='font-family:sans-serif; text-align:center; padding:40px; background:#f8fafc;'>
        <div style='max-width:500px; margin:0 auto; background:#ffffff; padding:30px; border-radius:20px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);'>
            <h1 style='color:#2563eb; margin-bottom:10px;'>PsySpace</h1>
            <p style='color:#475569; font-size:16px;'>Bonjour <b>$fullName</b>,</p>
            <p style='color:#475569;'>Utilisez le code suivant pour activer votre compte professionnel. Ce code expire dans 5 minutes.</p>
            <div style='font-size:32px; letter-spacing:5px; font-weight:800; color:#2563eb; background:#eff6ff; padding:20px; margin:25px 0; border-radius:12px; border:2px dashed #bfdbfe;'>
                $otp
            </div>
            <p style='color:#94a3b8; font-size:12px;'>Si vous n'avez pas demandé ce code, ignorez cet e-mail.</p>
        </div>
    </div>";

    $mail->send();

    $_SESSION['pending_email'] = $email;
    header("Location: verify.php?email=" . urlencode($email));
    exit();

} catch (Exception $e) {
    // Si l'envoi de mail crash, on supprime l'entrée pour pas bloquer l'user
    $con->query("DELETE FROM doctor WHERE docemail = '" . mysqli_real_escape_string($con, $email) . "'");
    header("Location: register.php?error=mailfail");
    exit();
}