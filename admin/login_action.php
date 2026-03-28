<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Chemins pour Azure
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // 🚨 CRUCIAL : On garde les identifiants qui MARCHENT (psyspace.all)
            $mail->Username   = 'psyspace.all@gmail.com'; 
            $mail->Password   = 'lszg gkpz ylbg ypdt'; 
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // L'expéditeur doit correspondre au Username pour Gmail
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
            
            // 🎯 DESTINATAIRE : Ton adresse admin personnelle
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "⚠️ ALERTE : Connexion Admin";
            $mail->Body    = "L'administrateur <b>" . $admin['admname'] . "</b> s'est connecté.";

            $mail->send();

            // Une fois le mail envoyé, on crée la session
            $_SESSION['admin_id']   = $admin['admid'];
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role']       = 'admin';
            
            header("Location: dashboard.php");
            exit();

        } catch (Exception $e) {
            // Si ça échoue encore, on affiche l'erreur pour comprendre
            die("Erreur SMTP : " . $mail->ErrorInfo);
        }

    } else {
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}