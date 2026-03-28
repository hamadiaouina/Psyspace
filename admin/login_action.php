<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Chemins relatifs depuis le dossier /admin
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
        
        // --- BLOC MAIL (On force l'envoi AVANT la redirection) ---
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

            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Security');
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "Connexion Admin Reussie";
            $mail->Body    = "L'admin <b>" . $admin['admname'] . "</b> est en ligne.";

            $mail->send();
            
            // SI LE MAIL EST PARTI, ON CONTINUE ICI
            $_SESSION['admin_id']   = $admin['admid'];
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role']       = 'admin';
            
            header("Location: dashboard.php");
            exit();

        } catch (Exception $e) {
            // SI CA FOIRE, ON ARRÊTE TOUT POUR VOIR POURQUOI
            echo "Erreur lors de l'envoi : " . $mail->ErrorInfo;
            echo "<br><a href='dashboard.php'>Continuer quand meme vers le Dashboard</a>";
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