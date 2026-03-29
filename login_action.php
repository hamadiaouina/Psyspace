<?php
declare(strict_types=1);

// ── Sessions sécurisées ──
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($is_https) ini_set('session.cookie_secure', '1');

session_start();

include "connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

/* ══════════════════════════════════════════════
   FONCTION LOG (Simplifiée pour Docteurs)
══════════════════════════════════════════════ */
function logAction($con, int $user_id, string $action, string $details): void {
    if (!$con) return;
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    // On utilise la table admin_logs pour centraliser la sécurité du PFE
    $stmt = $con->prepare("INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/* ══════════════════════════════════════════════
   1. VALIDATION CLOUDFLARE TURNSTILE
══════════════════════════════════════════════ */
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: '';
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (!empty($turnstileSecret)) {
    if (empty($turnstileToken)) {
        header("Location: login.php?error=captcha");
        exit();
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $turnstileSecret,
            'response' => $turnstileToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $tsResponse = curl_exec($ch);
    curl_close($ch);
    $tsData = json_decode($tsResponse ?: '', true);
    if (!($tsData['success'] ?? false)) {
        logAction($con, 0, 'login_failed', "Turnstile failed — IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
        header("Location: login.php?error=captcha");
        exit();
    }
}

/* ══════════════════════════════════════════════
   2. NETTOYAGE DES INPUTS
══════════════════════════════════════════════ */
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAction($con, 0, 'login_failed', "Format email invalide: $email — IP: $ip");
    header("Location: login.php?error=wrongpw");
    exit();
}

/* ══════════════════════════════════════════════
   3. CONNEXION MÉDECIN (UNIQUEMENT)
══════════════════════════════════════════════ */
$stmtDoc = $con->prepare("SELECT docid, docname, docpassword, status FROM doctor WHERE docemail = ? LIMIT 1");
$stmtDoc->bind_param("s", $email);
$stmtDoc->execute();
$doctor = $stmtDoc->get_result()->fetch_assoc();
$stmtDoc->close();

if ($doctor && password_verify($password, $doctor['docpassword'])) {

    if ($doctor['status'] === 'suspended') {
        logAction($con, 0, 'login_failed', "Tentative compte suspendu: $email — IP: $ip");
        header("Location: login.php?error=suspended");
        exit();
    }
    if ($doctor['status'] === 'pending') {
        header("Location: login.php?error=pending");
        exit();
    }

    // Connexion réussie
    session_regenerate_id(true);
    $_SESSION['id']         = $doctor['docid'];
    $_SESSION['nom']        = $doctor['docname'];
    $_SESSION['role']       = 'doctor';
    $_SESSION['last_login'] = time();

    logAction($con, 0, 'doctor_login', "Doctor login success: $email — IP: $ip");
    header("Location: welcome.php");
    exit();
}

/* ══════════════════════════════════════════════
   4. ÉCHEC (Pour Admin ou Email inconnu)
══════════════════════════════════════════════ */
// Ici, on ne vérifie plus la table Admin. 
// Si un admin met ses identifiants, le script ne trouve rien dans 'doctor' et arrive ici.
logAction($con, 0, 'login_failed', "Identifiants incorrects (Doctor Login): $email — IP: $ip");
header("Location: login.php?error=wrongpw");
exit();