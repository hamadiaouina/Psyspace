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

// 1. RÉCUPÉRATION ET NETTOYAGE DE LA VRAIE IP (Spécial Azure)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
if (strpos($ip, ':') !== false && strpos($ip, '.') !== false) {
    $ip = explode(':', $ip)[0];
}
$ip = trim($ip);

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$heure    = date('d/m/Y à H:i:s');

/* ══════════════════════════════════════
    RECHERCHE ADMIN
══════════════════════════════════════ */
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = '$smtp_user';
        $mail->Password   = '$smtp_pass';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('$smtp_user', 'PsySpace Shield');
        $mail->addAddress('$smtp_user');
        $mail->isHTML(true);

        if (password_verify($password, $admin['admpassword'])) {
            // 2. CONNEXION RÉUSSIE -> GÉNÉRATION OTP
            $otp = rand(100000, 999999);
            $admin_id = $admin['admid'];
            $con->query("UPDATE admin SET otp_code = '$otp' WHERE admid = '$admin_id'");

            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #4f46e5;border-radius:12px;overflow:hidden;'>
                <div style='background:#4f46e5;padding:20px;text-align:center;'><h2 style='color:#fff;margin:0;'>Code de Sécurité</h2></div>
                <div style='padding:24px;background:#f8fafc;text-align:center;'>
                    <p style='color:#1e293b;font-size:16px;'>Utilisez le code suivant pour accéder au Dashboard Admin :</p>
                    <div style='font-size:32px; font-weight:bold; color:#4f46e5; background:#fff; padding:15px; border-radius:8px; border:1px dashed #4f46e5; display:inline-block; margin:10px 0;'>$otp</div>
                    <p style='color:#64748b;font-size:12px;'>Tentative réussie depuis l'IP : <b>$ip</b> le $heure</p>
                </div>
            </div>";
            
            $mail->send();
            $_SESSION['temp_admin_id'] = $admin['admid'];
            header("Location: verify_otp.php");
            exit();

        } else {
            // 3. ALERTE INTRUSION (Mauvais mot de passe)
            $mail->Subject = "⚠️ ALERTE : Tentative de connexion échouée";
            $mail->Body    = "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #ef4444;border-radius:12px;overflow:hidden;'>
                <div style='background:#ef4444;padding:20px;text-align:center;'><h2 style='color:#fff;margin:0;'>🚫 Accès Refusé</h2></div>
                <div style='padding:24px;background:#fff;'>
                    <p style='color:#1e293b;font-size:16px;'>Une tentative de connexion avec un <b>mot de passe incorrect</b> a été détectée.</p>
                    <hr style='border:0;border-top:1px solid #eee;margin:20px 0;'>
                    <p><b>Compte visé :</b> $email</p>
                    <p><b>Origine (IP) :</b> <span style='color:#ef4444;font-family:monospace;font-weight:bold;'>$ip</span></p>
                    <p><b>Date :</b> $heure</p>
                    <div style='margin-top:20px;padding:15px;background:#fef2f2;border-left:4px solid #ef4444;color:#991b1b;font-size:13px;'>
                        <strong>Attention :</strong> Si ce n'est pas vous, quelqu'un essaie de forcer votre accès admin.
                    </div>
                </div>
            </div>";

            $mail->send();
            header("Location: login.php?error=wrongpw");
            exit();
        }

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        header("Location: login.php?error=mailfail");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}