<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ON REMONTE D'UN DOSSIER (../) POUR TROUVER VENDOR
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// ... (le reste de ton code SQL)

if (password_verify($password, $admin['admpassword'])) {
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

        $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
        $mail->addAddress('admin.psyspace@gmail.com'); // DESTINATAIRE

        $mail->isHTML(true);
        $mail->Subject = "Alerte de connexion Admin";
        $mail->Body    = "Connexion reussie pour l'admin : " . $admin['admname'];

        $mail->send();
    } catch (Exception $e) {
        // Optionnel : enregistrer l'erreur dans un fichier pour vérifier
        file_put_contents(__DIR__ . '/../mail_error.log', $e->getMessage(), FILE_APPEND);
    }

    // REDIRECTION APRES LE MAIL
    $_SESSION['admin_id'] = $admin['admid'];
    $_SESSION['role'] = 'admin';
    header("Location: dashboard.php");
    exit();
}