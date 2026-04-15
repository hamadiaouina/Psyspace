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

// --- 2. SÉCURITÉ : VÉRIFICATION DU JETON CSRF ---
$post_csrf  = $_POST['csrf_token'] ?? '';
$sess_csrf  = $_SESSION['csrf_token'] ?? '';
if (empty($sess_csrf) || !hash_equals($sess_csrf, $post_csrf)) {
    header("Location: login.php?error=csrf");
    exit();
}

// --- 3. SÉCURITÉ : COMPTEUR DE TENTATIVES ANTI BRUTE-FORCE ---
if (!isset($_SESSION['admin_attempts'])) {
    $_SESSION['admin_attempts'] = 0;
}
if ($_SESSION['admin_attempts'] >= 3) {
    header("Location: ../index.php?error=security_lock");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

// Récupération des données et de l'IP
$email_attempt = trim($_POST['email'] ?? '');
$password_attempt = trim($_POST['password'] ?? '');
$ip            = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Inconnue';
$date_heure    = date('d/m/Y à H:i:s');
$user_agent    = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';

// Adresse de notification fixe (votre adresse perso dans .env)
$smtp_user     = getenv('SMTP_USER'); // psyspace.me@gmail.com
$smtp_pass     = getenv('SMTP_PASS') ?: '';
$notify_email  = $smtp_user; // Les alertes arrivent sur cette même adresse

// --- Fonction utilitaire : créer et configurer PHPMailer ---
function buildMailer(string $smtp_user, string $smtp_pass): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($smtp_user, 'PsySpace Shield');
    $mail->isHTML(true);
    return $mail;
}

// --- 4. REQUÊTE PRÉPARÉE ---
$stmt = $con->prepare("SELECT * FROM admin WHERE admemail = ? LIMIT 1");
$stmt->bind_param("s", $email_attempt);
$stmt->execute();
$result = $stmt->get_result();

// ================================================================
// SCÉNARIO 1 : Email trouvé en base
// ================================================================
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc(); // ← défini ICI, avant tout usage

    // -------------------------------------------------------
    // CAS A : Bon email + BON mot de passe → envoi OTP
    // -------------------------------------------------------
    if (password_verify($password_attempt, $admin['admpassword'])) {

        $_SESSION['admin_attempts'] = 0;

        // Génération et sauvegarde de l'OTP
        $otp = rand(100000, 999999);
        $update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
        $update_stmt->bind_param("si", $otp, $admin['admid']);
        $update_stmt->execute();

        try {
            $mail = buildMailer($smtp_user, $smtp_pass);
            $mail->addAddress($admin['admemail']); // envoi du code à l'admin
            $mail->Subject = "🔑 Code de vérification Admin — $otp";
            $mail->Body    = "
            <div style='border:2px solid #4f46e5; padding:20px; border-radius:10px; font-family:sans-serif;'>
                <h2 style='color:#4f46e5; text-align:center;'>Code d'accès Admin</h2>
                <div style='font-size:30px; text-align:center; font-weight:bold; padding:15px; background:#f3f4f6; letter-spacing:0.3em;'>$otp</div>
                <hr>
                <p>Connexion réussie le <b>$date_heure</b></p>
                <p>IP : <b>$ip</b></p>
                <p>Navigateur : <b>$user_agent</b></p>
                <p style='font-size:12px;color:#6b7280;'>Ce code expire dans 10 minutes.</p>
            </div>";
            $mail->send();
        } catch (Exception $e) {
            // Échec d'envoi : on redirige quand même (OTP déjà en BDD)
        }

        $_SESSION['temp_admin_id'] = $admin['admid'];
        header("Location: verify_otp.php");
        exit();

    // -------------------------------------------------------
    // CAS B : Bon email + MAUVAIS mot de passe → alerte
    // -------------------------------------------------------
    } else {
        $_SESSION['admin_attempts']++;
        $tentatives = $_SESSION['admin_attempts'];

        try {
            $mail = buildMailer($smtp_user, $smtp_pass);
            $mail->addAddress($notify_email); // alerte à votre adresse
            $mail->Subject = "⚠️ ALERTE : Mauvais mot de passe Admin (tentative $tentatives/3)";
            $mail->Body    = "
            <div style='border:2px solid #ef4444; padding:20px; border-radius:10px; font-family:sans-serif;'>
                <h2 style='color:#ef4444;'>Tentative de connexion échouée</h2>
                <p>Un mauvais mot de passe a été saisi pour un compte admin <b>existant</b>.</p>
                <hr>
                <p>Email ciblé : <b>" . htmlspecialchars($email_attempt) . "</b></p>
                <p>Tentative : <b>$tentatives / 3</b></p>
                <p>Date : <b>$date_heure</b></p>
                <p>IP : <b>$ip</b></p>
                <p>Navigateur : <b>$user_agent</b></p>
                " . ($tentatives >= 3 ? "<p style='color:#ef4444;font-weight:bold;'>🔒 Compte verrouillé après cette tentative.</p>" : "") . "
            </div>";
            $mail->send();
        } catch (Exception $e) { /* silencieux */ }

        header("Location: login.php?error=wrongpw");
        exit();
    }

// ================================================================
// SCÉNARIO 2 : Email INCONNU → alerte intrusion
// ================================================================
} else {
    $_SESSION['admin_attempts']++;
    $tentatives = $_SESSION['admin_attempts'];

    try {
        $mail = buildMailer($smtp_user, $smtp_pass);
        $mail->addAddress($notify_email);
        $mail->Subject = "🚨 INTRUSION : Tentative avec email inconnu (tentative $tentatives/3)";
        $mail->Body    = "
        <div style='border:2px solid #f97316; padding:20px; border-radius:10px; font-family:sans-serif;'>
            <h2 style='color:#f97316;'>⚠️ Alerte Intrusion Admin</h2>
            <p>Quelqu'un a tenté d'accéder au panel admin avec une adresse e-mail <b>inconnue</b>.</p>
            <hr>
            <p>Email tenté : <b>" . htmlspecialchars($email_attempt) . "</b></p>
            <p>Tentative : <b>$tentatives / 3</b></p>
            <p>Date : <b>$date_heure</b></p>
            <p>IP : <b>$ip</b></p>
            <p>Navigateur : <b>$user_agent</b></p>
            " . ($tentatives >= 3 ? "<p style='color:#ef4444;font-weight:bold;'>🔒 Session verrouillée après cette tentative.</p>" : "") . "
        </div>";
        $mail->send();
    } catch (Exception $e) { /* silencieux */ }

    header("Location: login.php?error=noaccount");
    exit();
}