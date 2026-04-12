<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();

// Cacher les erreurs en production (Évite les fuites de structure DB)
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

require_once __DIR__ . "/connection.php"; 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// ==========================================
// 🛡️ SÉCURITÉ 1 : VÉRIFICATION DU POT DE MIEL (HONEYPOT)
// ==========================================
if (!empty($_POST['hp_registration'])) {
    // Un robot a rempli le champ invisible : on le renvoie à l'accueil silencieusement
    header("Location: index.php");
    exit();
}

// ==========================================
// 🛡️ SÉCURITÉ 2 : VÉRIFICATION DU JETON CSRF
// ==========================================
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: register.php?error=csrf");
    exit();
}

// ==========================================
// 🛡️ SÉCURITÉ 3 : VALIDATION CLOUDFLARE TURNSTILE (CAPTCHA)
// ==========================================
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: 'TA_CLE_SECRETE_DE_TEST';
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (empty($turnstileToken)) {
    header("Location: register.php?error=captcha");
    exit();
}

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret'   => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR']
]));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response ?: '', true);
if (!($data['success'] ?? false)) {
    header("Location: register.php?error=captcha");
    exit();
}

// --- 4. ANTI-SPAM (Rate Limiting) ---
if (isset($_SESSION['last_register_attempt']) && (time() - $_SESSION['last_register_attempt']) < 30) {
    header("Location: register.php?error=spam_protection");
    exit();
}
$_SESSION['last_register_attempt'] = time();


// --- 5. RÉCUPÉRATION ET NETTOYAGE DES DONNÉES ---
$nom      = trim($_POST['nom']      ?? '');
$prenom   = trim($_POST['prenom']   ?? '');
$email    = trim($_POST['email']    ?? '');
$dob      = trim($_POST['dob']      ?? '');
$password = $_POST['password']      ?? '';

if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
    header("Location: register.php?error=empty");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=invalid_email");
    exit();
}

// Vérification de la force du mot de passe (Minimum 8 caractères)
if (strlen($password) < 8) {
    header("Location: register.php?error=weak_password");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

try {
    // --- 6. NETTOYAGE DES INSCRIPTIONS EXPIRÉES (5 MIN) ---
    $cleanup_query = "DELETE FROM doctor WHERE docemail = ? AND status = 'pending' AND created_at < NOW() - INTERVAL 5 MINUTE";
    $stmt_cleanup = $con->prepare($cleanup_query);
    $stmt_cleanup->bind_param("s", $email);
    $stmt_cleanup->execute();
    $stmt_cleanup->close();

    // --- 7. VÉRIFICATION SI L'EMAIL EXISTE DÉJÀ ---
    $stmt = $con->prepare("SELECT docid, status FROM doctor WHERE docemail = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['status'] === 'pending') {
            header("Location: register.php?error=pending_recent"); 
            exit();
        } else {
            header("Location: register.php?error=emailexist");
            exit();
        }
    }
    $stmt->close();

    // --- 8. PRÉPARATION DE L'INSERTION ---
    $fullName = $prenom . " " . $nom;
    $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
    $otp = rand(100000, 999999);

      $sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, status, dob) VALUES (?, ?, ?, ?, 'pending', ?)";
    $insertStmt = $con->prepare($sql);
    $insertStmt->bind_param("sssis", $email, $fullName, $hashed_password, $otp, $dob);
    $insertStmt->execute();
    $insertStmt->close();

    // --- 9. ENVOI DU MAIL OTP ---
    $mail = new PHPMailer(true);
    
    $smtp_user = getenv('SMTP_USER') ;
    $smtp_pass = getenv('SMTP_PASS') ?: ''; 

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user; 
    $mail->Password   = $smtp_pass; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtp_user, 'PsySpace Security');
    $mail->addAddress($email, $fullName);
    $mail->isHTML(true);
    $mail->Subject = "🔑 Code d'activation de votre espace praticien";
    
    $mail->Body = "
    <div style='font-family:sans-serif; max-width:500px; margin:0 auto; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;'>
        <div style='background:#4f46e5; padding:20px; text-align:center;'>
            <h2 style='color:#ffffff; margin:0;'>Bienvenue sur PsySpace</h2>
        </div>
        <div style='padding:30px; background:#ffffff; text-align:center;'>
            <p style='color:#475569; font-size:16px;'>Bonjour <b>" . htmlspecialchars($fullName) . "</b>,</p>
            <p style='color:#475569; line-height:1.5;'>Pour activer votre compte praticien, veuillez entrer ce code de sécurité. Il expirera dans 5 minutes.</p>
            <div style='font-size:36px; font-weight:bold; color:#4f46e5; background:#f8fafc; padding:15px; border-radius:8px; border:2px dashed #cbd5e1; margin:20px 0; letter-spacing: 4px;'>
                $otp
            </div>
            <p style='color:#94a3b8; font-size:12px;'>Si vous n'avez pas créé ce compte, veuillez ignorer cet e-mail.</p>
        </div>
    </div>";

    $mail->send();

    // Renouvellement du jeton CSRF après inscription réussie
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // --- 10. REDIRECTION ---
    $_SESSION['pending_email'] = $email;
    header("Location: verify.php");
    exit();

} catch (Exception $e) {
    if (isset($email)) {
        $del_stmt = $con->prepare("DELETE FROM doctor WHERE docemail = ? AND status = 'pending'");
        $del_stmt->bind_param("s", $email);
        $del_stmt->execute();
        $del_stmt->close();
    }
    error_log("Erreur Mail Inscription : " . $e->getMessage());
    header("Location: register.php?error=mailfail");
    exit();

} catch (\mysqli_sql_exception $e) {
    error_log("Erreur DB Inscription : " . $e->getMessage());
    header("Location: register.php?error=dberror");
    exit();
}