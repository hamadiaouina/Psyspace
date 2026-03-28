<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Chemins Absolus pour éviter les erreurs de dossier sur Azure
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// Sécurité de connexion
if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Nettoyage des entrées
$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 2. Recherche de l'admin dans la base
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 3. Vérification du mot de passe AVANT l'envoi du mail (Plus logique)
    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC ALERTE MAIL ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // On utilise le compte technique pour ENVOYER
            $mail->Username   = 'psyspace.all@gmail.com'; 
            $mail->Password   = 'lszg gkpz ylbg ypdt'; 
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // L'expéditeur DOIT être psyspace.all pour que Gmail valide l'envoi
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Security');
            
            // LE DESTINATAIRE (Ton nouvel email admin enregistré en BD)
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "🚨 CONNEXION ADMIN RÉUSSIE : " . $admin['admname'];
            $mail->Body    = "
                <div style='background-color: #f0fdf4; border: 2px solid #16a34a; padding: 20px; font-family: sans-serif; border-radius: 10px;'>
                    <h1 style='color: #16a34a; font-size: 20px;'>✅ Accès Dashboard autorisé</h1>
                    <p>L'administrateur <b>{$admin['admname']}</b> s'est connecté avec succès.</p>
                    <hr style='border: 0; border-top: 1px solid #bbf7d0; margin: 20px 0;'>
                    <p><b>Détails de la session :</b></p>
                    <ul style='list-style: none; padding: 0;'>
                        <li>🌐 <b>IP :</b> {$_SERVER['REMOTE_ADDR']}</li>
                        <li>⏰ <b>Heure :</b> " . date('d/m/Y H:i:s') . "</li>
                        <li>📧 <b>Compte :</b> {$email}</li>
                    </ul>
                </div>";

            $mail->send();
        } catch (Exception $e) {
            // On log l'erreur discrètement sans bloquer l'admin
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
        }
        // --- FIN BLOC MAIL ---

        // Création de la session et redirection
        $_SESSION['admin_id']    = $admin['admid'];
        $_SESSION['admin_name']  = $admin['admname'];
        $_SESSION['role']        = 'admin';
        
        header("Location: dashboard.php");
        exit();

    } else {
        // Mot de passe incorrect
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    // Compte inexistant
    header("Location: login.php?error=noaccount");
    exit();
}