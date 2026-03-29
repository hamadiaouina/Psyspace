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
   FONCTION LOG
══════════════════════════════════════════════ */
function logAction($con, int $admin_id, string $action, string $details): void {
    if (!$con) return;
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $con->prepare("INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
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
$heure    = date('d/m/Y à H:i:s');

if (empty($email) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAction($con, 0, 'login_failed', "Invalid email format: $email — IP: $ip");
    header("Location: login.php?error=wrongpw");
    exit();
}

/* ══════════════════════════════════════════════
   3. CONNEXION MÉDECIN
══════════════════════════════════════════════ */
$stmtDoc = $con->prepare("SELECT docid, docname, docpassword, status FROM doctor WHERE docemail = ? LIMIT 1");
$stmtDoc->bind_param("s", $email);
$stmtDoc->execute();
$doctor = $stmtDoc->get_result()->fetch_assoc();
$stmtDoc->close();

if ($doctor && password_verify($password, $doctor['docpassword'])) {

    if ($doctor['status'] === 'suspended') {
        logAction($con, 0, 'login_failed', "Suspended account attempt: $email — IP: $ip");
        header("Location: login.php?error=suspended");
        exit();
    }
    if ($doctor['status'] === 'pending') {
        header("Location: login.php?error=pending");
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['id']         = $doctor['docid'];
    $_SESSION['nom']        = $doctor['docname'];
    $_SESSION['role']       = 'doctor';
    $_SESSION['last_login'] = time();

    logAction($con, 0, 'doctor_login', "Doctor login: $email — IP: $ip");
    header("Location: welcome.php");
    exit();
}

/* ══════════════════════════════════════════════
   4. CONNEXION ADMIN
══════════════════════════════════════════════ */
$stmtAdm = $con->prepare("SELECT admid, admname, admpassword FROM admin WHERE admemail = ? LIMIT 1");
$stmtAdm->bind_param("s", $email);
$stmtAdm->execute();
$admin = $stmtAdm->get_result()->fetch_assoc();
$stmtAdm->close();

if ($admin) {
    if (password_verify($password, $admin['admpassword'])) {

        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['admid'];
        $_SESSION['admin_name'] = $admin['admname'];
        $_SESSION['role']       = 'admin';
        $_SESSION['last_login'] = time();

        _sendLoginAlert($email, $admin['admname'], $ip, $heure, true);
        logAction($con, (int)$admin['admid'], 'admin_login', "Admin login success — IP: $ip");
        header("Location: admin/dashboard.php");
        exit();

    } else {
        // ⭐ LOG BRUTE FORCE — apparaît dans Security Center
        _sendLoginAlert($email, $admin['admname'], $ip, $heure, false);
        logAction($con, 0, 'login_failed', "Wrong password for admin: $email — IP: $ip");
        header("Location: login.php?error=wrongpw");
        exit();
    }
}

// Email inconnu — on logue pour détecter les scans
logAction($con, 0, 'login_failed', "Unknown email: $email — IP: $ip");
header("Location: login.php?error=wrongpw");
exit();

/* ══════════════════════════════════════════════
   FONCTION MAIL ALERTE
══════════════════════════════════════════════ */
function _sendLoginAlert(string $email, string $name, string $ip, string $heure, bool $success): void {
    $vendorPath = __DIR__ . '/vendor/PHPMailer/src/';
    if (!file_exists($vendorPath . 'PHPMailer.php')) return;

    require_once $vendorPath . 'Exception.php';
    require_once $vendorPath . 'PHPMailer.php';
    require_once $vendorPath . 'SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USER') ?: 'psyspace.all@gmail.com';
        $mail->Password   = getenv('MAIL_PASS') ?: 'lszg gkpz ylbg ypdt';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;

        $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
        $mail->addAddress('psyspace.all@gmail.com');
        $mail->isHTML(true);

        if ($success) {
            $mail->Subject = "✅ Connexion Admin Réussie — PsySpace";
            $mail->Body    = "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #10b981;border-radius:12px;overflow:hidden;'>
              <div style='background:#10b981;padding:20px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>✅ Connexion Admin Réussie</h2>
              </div>
              <div style='padding:24px;background:#f0fdf4;'>
                <table style='width:100%;border-collapse:collapse;'>
                  <tr><td style='padding:8px;color:#6b7280;'>👤 Admin</td><td style='padding:8px;font-weight:600;'>{$name}</td></tr>
                  <tr style='background:#dcfce7;'><td style='padding:8px;color:#6b7280;'>📧 Email</td><td style='padding:8px;font-weight:600;'>{$email}</td></tr>
                  <tr><td style='padding:8px;color:#6b7280;'>🕐 Heure</td><td style='padding:8px;font-weight:600;'>{$heure}</td></tr>
                  <tr style='background:#dcfce7;'><td style='padding:8px;color:#6b7280;'>🌐 IP</td><td style='padding:8px;font-weight:600;'>{$ip}</td></tr>
                </table>
                <p style='color:#065f46;font-size:12px;margin-top:16px;'>Si ce n'était pas vous, changez votre mot de passe immédiatement.</p>
              </div>
            </div>";
        } else {
            $mail->Subject = "⚠️ Tentative Connexion Admin — Mot de passe incorrect";
            $mail->Body    = "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #f59e0b;border-radius:12px;overflow:hidden;'>
              <div style='background:#f59e0b;padding:20px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>⚠️ Mot de passe incorrect</h2>
              </div>
              <div style='padding:24px;background:#fffbeb;'>
                <table style='width:100%;border-collapse:collapse;'>
                  <tr><td style='padding:8px;color:#6b7280;'>📧 Email tenté</td><td style='padding:8px;font-weight:600;'>{$email}</td></tr>
                  <tr style='background:#fef3c7;'><td style='padding:8px;color:#6b7280;'>🕐 Heure</td><td style='padding:8px;font-weight:600;'>{$heure}</td></tr>
                  <tr><td style='padding:8px;color:#6b7280;'>🌐 IP</td><td style='padding:8px;font-weight:600;'>{$ip}</td></tr>
                </table>
                <p style='color:#92400e;font-size:12px;margin-top:16px;'>⚠️ Si ce n'était pas vous, votre compte est peut-être ciblé.</p>
              </div>
            </div>";
        }
        $mail->send();
    } catch (\Exception $e) {
        error_log("PHPMailer login alert error: " . $mail->ErrorInfo);
    }
}