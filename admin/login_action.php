<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        
        // --- BLOC ENVOI MAIL (psyspace.all -> admin.psyspace) ---
        $mail = new PHPMailer(true);
        try {
            // ACTIVATION DU DEBUG POUR VOIR LE PROBLÈME EN DIRECT
            $mail->SMTPDebug = 2; 

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // COMPTE QUI ENVOIE (L'expéditeur technique)
            $mail->Username   = 'psyspace.all@gmail.com'; 
            $mail->Password   = 'lszg gkpz ylbg ypdt'; 
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // DOIT ÊTRE LE MÊME QUE USERNAME POUR GMAIL
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
            
            // DESTINATAIRE (Toi)
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "ALERTE CONNEXION : " . $admin['admname'];
            $mail->Body    = "Connexion réussie pour l'admin : <b>" . $admin['admname'] . "</b>";

            $mail->send();
            
            // Si le mail part, on redirige normalement
            $_SESSION['admin_id']   = $admin['admid'];
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role']       = 'admin';
            header("Location: dashboard.php");
            exit();

        } catch (Exception $e) {
            // SI CA ECHOUE, LE DEBUG S'AFFICHERA A L'ECRAN
            echo "<br><b>ERREUR SMTP :</b> " . $mail->ErrorInfo;
            echo "<br><a href='dashboard.php'>Cliquer ici pour accéder quand même au Dashboard</a>";
            die(); 
        }

    } else {
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}