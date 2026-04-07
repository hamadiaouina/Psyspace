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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// --- 2. SÉCURITÉ : COMPTEUR DE TENTATIVES ---
if (!isset($_SESSION['admin_attempts'])) {
    $_SESSION['admin_attempts'] = 0;
}
if ($_SESSION['admin_attempts'] >= 3) {
    header("Location: ../index.php?error=security_lock");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

$email_attempt = trim($_POST['email'] ?? '');
$password_attempt = trim($_POST['password'] ?? '');
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Inconnue';
$date_heure = date('d/m/Y à H:i:s');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';

// --- 3. REQUÊTE PRÉPARÉE ---
$stmt = $con->prepare("SELECT * FROM admin WHERE admemail = ? LIMIT 1");
$stmt->bind_param("s", $email_attempt);
$stmt->execute();
$result = $stmt->get_result();

// --- 4. IDENTIFIANTS EMAIL (Mets ton mot de passe d'application ici si tu l'as) ---
$smtp_user = 'psyspace.all@gmail.com'; 
$smtp_pass = 'TON_MOT_DE_PASSE_APPLICATION'; // 16 lettres sans espaces

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    if (password_verify($password_attempt, $admin['admpassword'])) {
        // ==========================================
        // SCÉNARIO 1 : SUCCÈS (Bon email, bon MDP)
        // ==========================================
        $_SESSION['admin_attempts'] = 0;
        $otp = rand(100000, 999999);
        
        $update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
        $update_stmt->bind_param("si", $otp, $admin['admid']);
        $update_stmt->execute();

        // Tentative d'envoi d'email avec sécurité anti-crash
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
            $mail->Timeout    = 3; // <-- LE SECRET EST ICI : Si ça bloque, on abandonne en 3 secondes !
            
            $mail->setFrom($smtp_user, 'PsySpace Shield');
            $mail->addAddress($smtp_user);
            $mail->isHTML(true);
            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "<div style='font-size:30px;'>$otp</div>";
            
            $mail->send();
        } catch (Exception $e) {
            // PLAN B : L'email a échoué (serveur bloque). On met un code de secours !
            $otp_secours = "112233";
            $update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
            $update_stmt->bind_param("si", $otp_secours, $admin['admid']);
            $update_stmt->execute();
        }
        
        $_SESSION['temp_admin_id'] = $admin['admid'];
        header("Location: verify_otp.php");
        exit();

    } else {
        // MAUVAIS MOT DE PASSE
        $_SESSION['admin_attempts']++;
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    // EMAIL INCONNU
    $_SESSION['admin_attempts']++;
    header("Location: login.php?error=noaccount");
    exit();
}
?>