<?php
session_start();
require_once __DIR__ . "/../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// --- 1. SÉCURITÉ : VÉRIFICATION DU BADGE ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}

// Bloquer l'accès direct via l'URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// --- 2. SÉCURITÉ : CSRF ---
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: login.php?error=csrf");
    exit();
}

// --- 3. ANTI BRUTE-FORCE ---
if (!isset($_SESSION['admin_attempts'])) {
    $_SESSION['admin_attempts'] = 0;
}
if ($_SESSION['admin_attempts'] >= 3) {
    header("Location: ../index.php?error=security_lock");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

// Récupération des données
$email_attempt  = trim($_POST['email'] ?? '');
$password_attempt = trim($_POST['password'] ?? '');
$ip             = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Inconnue';
$date_heure     = date('d/m/Y à H:i:s');
$user_agent     = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';

// Adresse de réception des alertes (TON adresse fixe)
$alert_email = "psyspace.all@gmail.com";

// --- FONCTION : Envoyer un email ---
function sendAlert(string $to, string $subject, string $body): bool {
    global $alert_email;
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS') ?: '';

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
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- 4. REQUÊTE PRÉPARÉE ---
$stmt = $con->prepare("SELECT * FROM admin WHERE admemail = ? LIMIT 1");
$stmt->bind_param("s", $email_attempt);
$stmt->execute();
$result = $stmt->get_result();

// ============================================================
// SCÉNARIO A : EMAIL INCONNU
// ============================================================
if (!$result || $result->num_rows === 0) {
    $_SESSION['admin_attempts']++;

    sendAlert(
        $alert_email,
        "🚨 INTRUSION : Tentative avec Email Inconnu",
        "
        <div style='border:2px solid #f97316; padding:20px; border-radius:10px; font-family:sans-serif;'>
            <h2 style='color:#f97316;'>⚠️ Alerte Intrusion Admin</h2>
            <p>Tentative de connexion au panel admin avec une adresse email <b>inconnue</b>.</p>
            <hr style='margin:15px 0;'>
            <p>📧 Email tenté : <b>$email_attempt</b></p>
            <p>📅 Date : <b>$date_heure</b></p>
            <p>🌐 IP : <b>$ip</b></p>
            <p>🖥️ Navigateur : <b>$user_agent</b></p>
            <hr style='margin:15px 0;'>
            <p style='color:#f97316; font-size:12px;'>Tentative n°{$_SESSION['admin_attempts']}/3</p>
        </div>"
    );

    header("Location: login.php?error=noaccount");
    exit();
}

// ============================================================
// EMAIL TROUVÉ : on récupère l'admin
// ============================================================
$admin = $result->fetch_assoc();

// ============================================================
// SCÉNARIO B : BON EMAIL, MAUVAIS MOT DE PASSE
// ============================================================
if (!password_verify($password_attempt, $admin['admpassword'])) {
    $_SESSION['admin_attempts']++;

    sendAlert(
        $alert_email,
        "⚠️ ALERTE : Mauvais mot de passe Admin",
        "
        <div style='border:2px solid #ef4444; padding:20px; border-radius:10px; font-family:sans-serif;'>
            <h2 style='color:#ef4444;'>🔐 Tentative avec mauvais mot de passe</h2>
            <p>Quelqu'un connaît l'email admin mais a entré un <b>mauvais mot de passe</b>.</p>
            <hr style='margin:15px 0;'>
            <p>📧 Email ciblé : <b>{$admin['admemail']}</b></p>
            <p>📅 Date : <b>$date_heure</b></p>
            <p>🌐 IP : <b>$ip</b></p>
            <p>🖥️ Navigateur : <b>$user_agent</b></p>
            <hr style='margin:15px 0;'>
            <p style='color:#ef4444; font-size:12px;'>Tentative n°{$_SESSION['admin_attempts']}/3</p>
        </div>"
    );

    header("Location: login.php?error=wrongpw");
    exit();
}

// ============================================================
// SCÉNARIO C : BON EMAIL + BON MDP → Envoi OTP
// ============================================================
$_SESSION['admin_attempts'] = 0;

$otp = rand(100000, 999999);

$update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
$update_stmt->bind_param("si", $otp, $admin['admid']);
$update_stmt->execute();

$sent = sendAlert(
    $alert_email,
    "🔑 Code de vérification Admin — $otp",
    "
    <div style='border:2px solid #4f46e5; padding:20px; border-radius:10px; font-family:sans-serif;'>
        <h2 style='color:#4f46e5; text-align:center;'>Code d'accès Admin</h2>
        <div style='font-size:36px; text-align:center; font-weight:bold; padding:20px; background:#f3f4f6; border-radius:8px; letter-spacing:10px;'>$otp</div>
        <hr style='margin:15px 0;'>
        <p>✅ Connexion réussie le <b>$date_heure</b></p>
        <p>🌐 IP : <b>$ip</b></p>
        <p>🖥️ Navigateur : <b>$user_agent</b></p>
        <hr style='margin:15px 0;'>
        <p style='color:#6b7280; font-size:11px;'>Ce code expire à la prochaine tentative.</p>
    </div>"
);

if (!$sent) {
    // L'email a échoué mais on laisse quand même passer (OTP en base)
    // Tu peux changer ce comportement si tu veux bloquer
    $_SESSION['temp_admin_id'] = $admin['admid'];
    header("Location: verify_otp.php?warn=mailfail");
    exit();
}

$_SESSION['temp_admin_id'] = $admin['admid'];
header("Location: verify_otp.php");
exit();