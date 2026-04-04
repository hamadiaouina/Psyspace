<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

// --- SÉCURITÉ PFE : COMPTEUR DE TENTATIVES ---
if (!isset($_SESSION['admin_attempts'])) { $_SESSION['admin_attempts'] = 0; }
if ($_SESSION['admin_attempts'] >= 3) {
    header("Location: ../index.php?error=security_lock");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
$ip = trim($ip);

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$heure    = date('d/m/Y à H:i:s');

// Configuration SMTP (Une seule fois pour tout le fichier)
$smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
$smtp_pass = getenv('SMTP_PASS') ?: '';

$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $mail = new PHPMailer(true);

    try {
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
            // ✅ SUCCÈS : Reset attempts + Envoi OTP
            $_SESSION['admin_attempts'] = 0;
            $otp = rand(100000, 999999);
            $admin_id = $admin['admid'];
            $con->query("UPDATE admin SET otp_code = '$otp' WHERE admid = '$admin_id'");

            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "<div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #4f46e5;border-radius:12px;overflow:hidden;'>
                <div style='background:#4f46e5;padding:20px;text-align:center;'><h2 style='color:#fff;margin:0;'>Code de Sécurité</h2></div>
                <div style='padding:24px;background:#f8fafc;text-align:center;'>
                    <p style='color:#1e293b;font-size:16px;'>Utilisez le code suivant pour accéder au Dashboard Admin :</p>
                    <div style='font-size:32px; font-weight:bold; color:#4f46e5; background:#fff; padding:15px; border-radius:8px; border:1px dashed #4f46e5; display:inline-block; margin:10px 0;'>$otp</div>
                    <p style='color:#64748b;font-size:12px;'>Tentative réussie depuis l'IP : <b>$ip</b></p>
                </div>
            </div>";
            $mail->send();
            $_SESSION['temp_admin_id'] = $admin['admid'];
            header("Location: verify_otp.php");
            exit();

        } else {
            // ❌ MAUVAIS MOT DE PASSE
            $_SESSION['admin_attempts']++;
            $mail->Subject = "⚠️ ALERTE : Mauvais mot de passe pour $email";
            $mail->Body    = "<h2 style='color:red;'>Tentative échouée</h2><p>IP: $ip</p><p>Mot de passe incorrect pour un compte existant.</p>";
            $mail->send();
            header("Location: login.php?error=wrongpw");
            exit();
        }
    } catch (Exception $e) {
        header("Location: login.php?error=mailfail");
        exit();
    }
} else {
    // ❌ COMPTE INEXISTANT (Le truc que tu voulais !)
    $_SESSION['admin_attempts']++;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
        $mail->Username = $smtp_user; $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
        $mail->setFrom($smtp_user, 'PsySpace Shield'); $mail->addAddress($smtp_user);
        $mail->isHTML(true);
        $mail->Subject = "⚠️ ALERTE : Tentative avec EMAIL INCONNU";
        $mail->Body = "<h2 style='color:orange;'>Alerte Intrusion</h2><p>Un utilisateur a tenté de se connecter avec un email qui n'existe pas : <b>$email</b></p><p>IP: $ip</p>";
        $mail->send();
    } catch (Exception $e) {}
    
    header("Location: login.php?error=noaccount");
    exit();
}