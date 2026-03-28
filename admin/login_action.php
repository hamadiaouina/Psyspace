<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Chemins relatifs vers PHPMailer (ajuste selon ton dossier vendor)
require '../vendor/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 1. On cherche si l'admin existe
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 🚨 ALERTE IMMÉDIATE : On envoie le mail AVANT de vérifier le mot de passe
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
        $mail->Priority   = 1;

        $mail->setFrom('security@psyspace.ai', 'PsySpace Shield');
        $mail->addAddress('psyspace.all@gmail.com'); 

        $mail->isHTML(true);
        $mail->Subject = "⚠️ TENTATIVE D'ACCÈS ADMIN : $email";
        $mail->Body    = "
            <div style='background-color: #fff7ed; border: 2px solid #ea580c; padding: 20px; font-family: sans-serif; border-radius: 10px;'>
                <h1 style='color: #ea580c; font-size: 20px;'>🚨 Tentative de connexion détectée</h1>
                <p>Quelqu'un essaie d'accéder au compte admin : <strong>$email</strong></p>
                <hr>
                <p><strong>Détails techniques :</strong></p>
                <ul style='list-style: none; padding: 0;'>
                    <li>🌐 <b>IP :</b> {$_SERVER['REMOTE_ADDR']}</li>
                    <li>⏰ <b>Heure :</b> " . date('d/m/Y H:i:s') . "</li>
                    <li>📱 <b>Appareil :</b> {$_SERVER['HTTP_USER_AGENT']}</li>
                </ul>
                <p style='font-size: 12px; color: #9a3412;'>Si le mot de passe est incorrect, la session ne sera pas ouverte, mais restez vigilant.</p>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        // En cas d'erreur, on enregistre le log pour débugger
        file_put_contents('mail_error.log', $mail->ErrorInfo);
    }

    // 2. Vérification du mot de passe
    if (password_verify($password, $admin['admpassword'])) {
        $_SESSION['admin_id']    = $admin['admid'];
        $_SESSION['admin_name']  = $admin['admname'];
        $_SESSION['role']        = 'admin';
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}
?>