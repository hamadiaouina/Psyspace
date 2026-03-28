<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Chemins corrigés pour remonter d'un dossier depuis /admin/ vers /vendor/
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 1. Recherche de l'admin dans la base
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 2. Vérification du mot de passe
    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC ENVOI MAIL (AUTO-ENVOI) ---
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

            // Expéditeur et Destinataire IDENTIQUES pour éviter les blocages
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
            $mail->addAddress('psyspace.all@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "ALERTE CONNEXION : " . $admin['admname'];
            $mail->Body    = "
                <div style='font-family:sans-serif; border:2px solid #4f46e5; padding:20px; border-radius:10px;'>
                    <h2 style='color:#4f46e5;'>Sécurité Admin</h2>
                    <p>L'administrateur <b>" . $admin['admname'] . "</b> vient de se connecter.</p>
                    <p><b>Heure :</b> " . date('H:i:s') . "</p>
                    <p><b>IP :</b> " . $_SERVER['REMOTE_ADDR'] . "</p>
                </div>";

            $mail->send();

        } catch (Exception $e) {
            // On enregistre l'erreur en silence pour ne pas bloquer l'accès au dashboard
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
        }

        // 3. Création de la session et redirection
        $_SESSION['admin_id']   = $admin['admid'];
        $_SESSION['admin_name'] = $admin['admname'];
        $_SESSION['role']       = 'admin';
        
        header("Location: dashboard.php");
        exit();

    } else {
        header("Location: ../login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: ../login.php?error=noaccount");
    exit();
}