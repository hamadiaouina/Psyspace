<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Chemins PHPMailer (On remonte d'un dossier pour trouver vendor)
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

// On récupère les données du formulaire unique
$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 2. Recherche spécifique dans la table ADMIN
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 3. Vérification du mot de passe Admin
    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC MAIL PRIORITAIRE (Avant toute redirection) ---
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

            // Expéditeur technique
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Security');
            
            // Destinataire (Toi)
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "⚠️ ALERTE : Connexion Admin Detectee";
            $mail->Body    = "
                <div style='padding:20px; border:1px solid #ff0000; border-radius:10px; font-family:sans-serif;'>
                    <h2 style='color:#ff0000;'>Tentative d'accès réussie</h2>
                    <p>L'administrateur <b>" . $admin['admname'] . "</b> vient de se connecter au système.</p>
                    <p><b>Date :</b> " . date('d/m/Y H:i:s') . "</p>
                    <p><b>IP :</b> " . $_SERVER['REMOTE_ADDR'] . "</p>
                </div>";

            // ON FORCE L'ENVOI
            $mail->send();

        } catch (Exception $e) {
            // Si le mail échoue, on écrit l'erreur dans un log pour ne pas bloquer l'admin
            error_log("Erreur Mail Admin : " . $mail->ErrorInfo);
        }

        // 4. Une fois le mail traité (réussi ou loggé), on lance la session
        $_SESSION['admin_id']    = $admin['admid'];
        $_SESSION['admin_name']  = $admin['admname'];
        $_SESSION['role']        = 'admin';
        
        header("Location: dashboard.php");
        exit();

    } else {
        // Mauvais mot de passe
        header("Location: ../login.php?error=wrongpw&email=" . urlencode($email));
        exit();
    }
} else {
    // Si l'email n'est pas dans la table admin, le script s'arrête ici 
    // (ou tu peux ajouter une redirection vers le login des docteurs)
    header("Location: ../login.php?error=noaccount");
    exit();
}