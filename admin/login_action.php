<?php
session_start();
// On force l'inclusion de la connexion avec un chemin absolu pour éviter les erreurs Azure
require_once __DIR__ . "/../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

// --- VÉRIFICATION DU BADGE (SÉCURITÉ SUPPLÉMENTAIRE) ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    // Si on essaie de poster ici sans le badge, on dégage à l'accueil
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// 1. RÉCUPÉRATION IP AZURE
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
$ip = filter_var($ip, FILTER_VALIDATE_IP) ?: 'Format IP Invalide';

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
        // CONFIGURATION SMTP (Variables Azure)
        $smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
        $smtp_pass = getenv('SMTP_PASS') ?: '';

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom($smtp_user, 'PsySpace Shield');
        $mail->addAddress($smtp_user);
        $mail->isHTML(true);

        if (password_verify($password, $admin['admpassword'])) {
            // 2. SUCCÈS -> GÉNÉRATION OTP
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
            // 3. ÉCHEC MOT DE PASSE
            $mail->Subject = "⚠️ ALERTE : Tentative de connexion échouée";
            $mail->Body    = "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #ef4444;border-radius:12px;overflow:hidden;'>
                <div style='background:#ef4444;padding:20px;text-align:center;'><h2 style='color:#fff;margin:0;'>🚫 Accès Refusé</h2></div>
                <div style='padding:24px;background:#fff;'>
                    <p style='color:#1e293b;font-size:16px;'>Tentative avec mot de passe incorrect.</p>
                    <p><b>Compte :</b> $email</p>
                    <p><b>IP :</b> $ip</p>
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
    // 4. AUCUN COMPTE TROUVÉ
    header("Location: login.php?error=noaccount");
    exit();
}