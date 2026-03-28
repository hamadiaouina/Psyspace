<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. UTILISE DES CHEMINS ABSOLUS (Plus sûr pour Azure)
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Nettoyage des entrées
$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 2. RECHERCHE EN BASE (L'email saisi doit correspondre à admin.psyspace@gmail.com)
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // Identifiants de TON compte Gmail qui envoie (ne change pas si c'est le même)
        $mail->Username   = 'psyspace.all@gmail.com'; 
        $mail->Password   = 'lszg gkpz ylbg ypdt'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('security@psyspace.ai', 'PsySpace Shield');
        
        // 3. DESTINATION : C'est ici qu'on envoie l'alerte !
        // On l'envoie à l'email trouvé dans la base (ton nouvel email admin)
        $mail->addAddress($admin['admemail']); 

        $mail->isHTML(true);
        $mail->Subject = "⚠️ TENTATIVE D'ACCÈS ADMIN : $email";
        $mail->Body    = "
            <div style='background-color: #fff7ed; border: 2px solid #ea580c; padding: 20px; font-family: sans-serif; border-radius: 10px;'>
                <h1 style='color: #ea580c; font-size: 20px;'>🚨 Alerte de sécurité</h1>
                <p>Une tentative de connexion a été détectée sur votre compte admin.</p>
                <hr>
                <ul>
                    <li>🌐 <b>IP :</b> {$_SERVER['REMOTE_ADDR']}</li>
                    <li>⏰ <b>Heure :</b> " . date('d/m/Y H:i:s') . "</li>
                </ul>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        // Log l'erreur si l'envoi échoue
        file_put_contents('mail_error.log', "Erreur PHPMailer : " . $mail->ErrorInfo, FILE_APPEND);
    }

    // Vérification du mot de passe
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