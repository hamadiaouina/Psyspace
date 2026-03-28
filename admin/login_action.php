<?php
session_start();
include "../connection.php";

// PHPMailer - Assure-toi que le chemin est correct (../ car on est dans le dossier admin/)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }
if (!$con) { header("Location: login.php?error=invalid"); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if (!$email || !$password) {
    header("Location: login.php?error=invalid");
    exit();
}

// 1. Recherche de l'admin
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if (!$result || $result->num_rows === 0) {
    header("Location: login.php?error=noaccount");
    exit();
}

$admin = $result->fetch_assoc();

// 2. Vérification mot de passe
if (!password_verify($password, $admin['admpassword'])) {
    header("Location: login.php?error=wrongpw&email=" . urlencode($email));
    exit();
}

// 3. ✅ SUCCÈS — On prépare la session
$_SESSION['admin_id']    = $admin['admid'];
$_SESSION['admin_name']  = $admin['admname'];
$_SESSION['admin_email'] = $admin['admemail'];
$_SESSION['role']        = 'admin';

// 4. 🚨 ENVOI DE L'ALERTE (Maintenant que le login est réussi !)
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
    $mail->Priority   = 1; // URGENCE

    $mail->setFrom('no-reply@psyspace.ai', 'PsySpace Alert');
    $mail->addAddress($admin['admemail']); 

    $mail->isHTML(true);
    $mail->Subject = "⚠️ CONNEXION ADMIN DETECTEE";
    $mail->Body    = "
        <div style='background-color: #fef2f2; border: 2px solid #dc2626; padding: 20px; font-family: sans-serif; border-radius: 10px;'>
            <h1 style='color: #dc2626; font-size: 20px;'>Alerte de Sécurité PsySpace</h1>
            <p>Bonjour <strong>{$admin['admname']}</strong>,</p>
            <p>Une connexion vient d'être établie sur votre compte administrateur.</p>
            <p><strong>IP :</strong> {$_SERVER['REMOTE_ADDR']}</p>
            <p><strong>Heure :</strong> " . date('d/m/Y H:i:s') . "</p>
        </div>";

    $mail->send();
} catch (Exception $e) {
    file_put_contents('mail_error.log', $mail->ErrorInfo);
}

// 5. Redirection finale
header("Location: dashboard.php");
exit();
?>