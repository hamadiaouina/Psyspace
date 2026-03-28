<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Utilisation des chemins du register (en remontant d'un dossier)
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC MAIL (Copié sur le modèle du register) ---
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

            // On utilise exactement le même setFrom que ton register
            $mail->setFrom('no-reply@psyspace.me', 'PsySpace Shield');
            
            // DESTINATAIRE : Ton adresse admin
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "Alerte de connexion Admin";
            $mail->Body    = "
            <div style='font-family:sans-serif; padding:20px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#2563eb;'>Connexion réussie</h2>
                <p>L'administrateur <b>" . $admin['admname'] . "</b> vient de se connecter.</p>
                <p>Email utilisé : <b>$email</b></p>
                <p style='font-size:12px; color:#666;'>IP : " . $_SERVER['REMOTE_ADDR'] . "</p>
            </div>";

            $mail->send();
        } catch (Exception $e) {
            // On ne bloque pas la connexion si le mail échoue
            error_log("Mail Admin Error: " . $mail->ErrorInfo);
        }

        // --- FINALISATION ---
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