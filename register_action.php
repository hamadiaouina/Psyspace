<?php
session_start();

// 1. Diagnostic d'erreurs
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Chemins PHPMailer - Utilisation de __DIR__ pour la stabilité
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

include "connection.php"; 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// 3. Récupération et validation
$nom      = trim($_POST['nom']      ?? '');
$prenom   = trim($_POST['prenom']   ?? '');
$email    = trim($_POST['email']    ?? '');
$dob      = trim($_POST['dob']      ?? '');
$password = $_POST['password']      ?? '';

if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
    header("Location: register.php?error=empty");
    exit();
}

$fullName = $prenom . " " . $nom;
// Argon2id est excellent pour un PFE, très pro.
$hashed_password = password_hash($password, PASSWORD_ARGON2ID);
$otp = (int)rand(100000, 999999);

// 4. Vérification email unique
$stmt = $con->prepare("SELECT docemail FROM doctor WHERE docemail = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    header("Location: register.php?error=emailexist");
    exit();
}
$stmt->close();

// 5. Insertion en base
// Vérifie bien que ta table 'doctor' possède exactement ces colonnes
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
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'psyspace.all@gmail.com'; 
    $mail->Password   = 'lszg gkpz ylbg ypdt'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('no-reply@psyspace.me', 'PsySpace AI');
    $mail->addAddress($email, $fullName);

    $mail->isHTML(true);
    $mail->Subject = "Votre code de validation PsySpace";
    $mail->Body    = "
    <div style='font-family:sans-serif; text-align:center; padding:20px; border:1px solid #ddd; border-radius:10px;'>
        <h1 style='color:#2563eb;'>PsySpace AI</h1>
        <p>Bonjour <b>$fullName</b>, voici votre code de validation :</p>
        <div style='font-size:30px; font-weight:bold; color:#2563eb; background:#f1f5f9; padding:10px; display:inline-block; border-radius:5px;'>
            $otp
        </div>
        <p style='color:#666;'>Ce code est nécessaire pour activer votre compte professionnel.</p>
    </div>";

    $mail->send();

    $_SESSION['pending_email'] = $email;
    header("Location: verify.php?email=" . urlencode($email));
    exit();

} catch (Exception $e) {
    // Nettoyage si le mail échoue pour permettre de retenter l'inscription
    $con->query("DELETE FROM doctor WHERE docemail = '" . mysqli_real_escape_string($con, $email) . "'");
    header("Location: register.php?error=mailfail");
    exit();
}