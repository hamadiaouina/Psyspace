<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 1. On cherche l'admin
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 2. Vérification du mot de passe
    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC MAIL PRIORITAIRE ---
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

            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Admin');
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "CONNEXION REUSSIE";
            $mail->Body    = "L'admin vient de se connecter.";

            // ON FORCE L'ENVOI
            $mail->send();
            
            // SI ON ARRIVE ICI, LE MAIL EST PARTI
            $_SESSION['admin_id']   = $admin['admid'];
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role']       = 'admin';
            
            header("Location: dashboard.php");
            exit();

        } catch (Exception $e) {
            // SI CA ECHOUE, ON VEUT VOIR L'ERREUR EN GROS
            die("ERREUR MAIL : " . $mail->ErrorInfo);
        }

    } else {
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}