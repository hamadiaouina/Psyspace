<?php
session_start();
include "connection.php";

if (!isset($con) && isset($conn)) { $con = $conn; }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

/**
 * --- 0) Captcha (Cloudflare Turnstile) ---
 */
$turnstileSecret = getenv('TURNSTILE_SECRET'); 
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (!$turnstileSecret) {
    // Erreur de configuration : la clé secrète n'est pas dans le .env
    header("Location: login.php?error=captchaconfig");
    exit();
}

if (empty($turnstileToken)) {
    // L'utilisateur n'a pas validé le Captcha
    header("Location: login.php?error=captcha");
    exit();
}

// Vérification auprès des serveurs de Cloudflare
$payload = http_build_query([
    'secret'   => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
]);

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response ?: '', true);

// Si Cloudflare dit que le Captcha est invalide
if (!is_array($data) || !($data['success'] ?? false)) {
    header("Location: login.php?error=captcha");
    exit();
}

/**
 * --- 1) Inputs ---
 */
$email    = mysqli_real_escape_string($con, trim($_POST['email']   ?? ''));
$password = trim($_POST['password'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] == 1;

if (!$email || !$password) {
    header("Location: login.php?error=invalid");
    exit();
}

/**
 * --- 2) Rate limit (anti brute-force) ---
 */
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = hash('sha256', strtolower($email) . '|' . $clientIp);

if (!isset($_SESSION['login_rl'])) {
    $_SESSION['login_rl'] = [];
}

$now = time();
$windowSeconds = 10 * 60; // 10 minutes
$maxAttempts = 5;

$entry = $_SESSION['login_rl'][$rateKey] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];

// Si déjà bloqué
if (($entry['blocked_until'] ?? 0) > $now) {
    header("Location: login.php?error=toomany");
    exit();
}

// Reset fenêtre si trop vieille
if (($now - ($entry['first'] ?? $now)) > $windowSeconds) {
    $entry = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
}

function rl_fail(string $rateKey, array $entry, int $now, int $maxAttempts, int $windowSeconds): void {
    $entry['count'] = ($entry['count'] ?? 0) + 1;

    // Blocage progressif
    if ($entry['count'] >= $maxAttempts) {
        $over = $entry['count'] - $maxAttempts;
        $block = min(15 * 60, 30 * (2 ** $over)); // max 15 min
        $entry['blocked_until'] = $now + $block;
    }

    $_SESSION['login_rl'][$rateKey] = $entry;
}

function rl_success(string $rateKey): void {
    // Sur succès: reset compteur
    unset($_SESSION['login_rl'][$rateKey]);
}

/**
 * --- 3) Auth ---
 */

// ── 1. Cherche dans doctor ────────────────────────────────────────────────────
$result = $con->query("SELECT * FROM doctor WHERE docemail = '$email' LIMIT 1");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if ($user['status'] !== 'active') {
        rl_fail($rateKey, $entry, $now, $maxAttempts, $windowSeconds);
        header("Location: login.php?error=notactive&email=" . urlencode($email));
        exit();
    }

    if (password_verify($password, $user['docpassword'])) {
        rl_success($rateKey);

        $_SESSION['id']    = $user['docid'];
        $_SESSION['nom']   = $user['docname'];
        $_SESSION['email'] = $user['docemail'];
        $_SESSION['role']  = 'doctor';

        // ── Rester connecté ───────────────────────────────────────────────────
        if ($remember) {
            $token  = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 jours

            $tokenEsc = mysqli_real_escape_string($con, $token);
            $con->query("UPDATE doctor SET remember_token = '$tokenEsc', token_expiry = '$expiry' WHERE docid = '{$user['docid']}'");

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('remember_token', $token, [
                'expires'  => $expiry,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────

        header("Location: welcome.php");
        exit();
    } else {
        rl_fail($rateKey, $entry, $now, $maxAttempts, $windowSeconds);
        header("Location: login.php?error=wrongpw&email=" . urlencode($email));
        exit();
    }
}

// ── 2. Cherche dans admin ─────────────────────────────────────────────────────
$result2 = $con->query("SELECT * FROM admin WHERE admemail = '$email' LIMIT 1");

if ($result2 && $result2->num_rows > 0) {
    $admin = $result2->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {
        rl_success($rateKey);

        $_SESSION['admin_id']    = $admin['admid'];
        $_SESSION['admin_name']  = $admin['admname'];
        $_SESSION['admin_email'] = $admin['admemail'];
        $_SESSION['role']        = 'admin';

        header("Location: admin/dashboard.php");
        exit();
    } else {
        rl_fail($rateKey, $entry, $now, $maxAttempts, $windowSeconds);
        header("Location: login.php?error=wrongpw&email=" . urlencode($email));
        exit();
    }
}

// ── 3. Introuvable ───────────────────────────────────────────────────────────
rl_fail($rateKey, $entry, $now, $maxAttempts, $windowSeconds);
header("Location: login.php?error=noaccount");
exit();
?>