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

// --- 2. SÉCURITÉ : COMPTEUR DE TENTATIVES ANTI BRUTE-FORCE ---
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
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Inconnue';
$date_heure = date('d/m/Y à H:i:s');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';

// --- 3. REQUÊTE PRÉPARÉE ---
$stmt = $con->prepare("SELECT * FROM admin WHERE admemail = ? LIMIT 1");
$stmt->bind_param("s", $email_attempt);
$stmt->execute();
$result = $stmt->get_result();

// --- 4. PRÉPARATION DE L'EMAIL ---
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
    $mail->addAddress($smtp_user);
    $mail->isHTML(true);

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        if (password_verify($password_attempt, $admin['admpassword'])) {
            // ==========================================
            // SCÉNARIO 1 : SUCCÈS (Bon email, bon MDP)
            // ==========================================
            $_SESSION['admin_attempts'] = 0;

            // Génération de l'OTP
            $otp = rand(100000, 999999);

            // Sauvegarde de l'OTP en base
            $update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
            $update_stmt->bind_param("si", $otp, $admin['admid']);
            $update_stmt->execute();

            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "
            <div style='border:2px solid #4f46e5; padding:20px; border-radius:10px; font-family:sans-serif;'>
                <h2 style='color:#4f46e5; text-align:center;'>Code d'accès Admin</h2>
                <div style='font-size:30px; text-align:center; font-weight:bold; padding:15px; background:#f3f4f6;'>$otp</div>
                <hr>
                <p>Connexion réussie le <b>$date_heure</b></p>
                <p>IP : <b>$ip</b></p>
                <p>Navigateur : <b>$user_agent</b></p>
            </div>";

            $mail->send();

            $_SESSION['temp_admin_id'] = $admin['admid'];
            header("Location: verify_otp.php");
            exit();

        } else {
            // ==========================================
            // SCÉNARIO 2 : ÉCHEC (Bon email, Mauvais MDP)
            // ==========================================
            $_SESSION['admin_attempts']++;

            $mail->Subject = "⚠️ ALERTE : Mauvais mot de passe Admin";
            $mail->Body    = "
            <div style='border:2px solid #ef4444; padding:20px; border-radius:10px; font-family:sans-serif;'>
                <h2 style='color:#ef4444;'>Tentative de connexion échouée</h2>
                <p>Quelqu'un a essayé de se connecter à un compte admin existant avec un mauvais mot de passe.</p>
                <hr>
                <p>Date : <b>$date_heure</b></p>
                <p>IP : <b>$ip</b></p>
                <p>Navigateur : <b>$user_agent</b></p>
            </div>";

            $mail->send();

            header("Location: login.php?error=wrongpw");
            exit();
        }
    } else {
        // ==========================================
        // SCÉNARIO 3 : ÉCHEC (Email Inconnu)
        // ==========================================
        $_SESSION['admin_attempts']++;

        $mail->Subject = "🚨 INTRUSION : Tentative avec Email Inconnu";
        $mail->Body    = "
        <div style='border:2px solid #f97316; padding:20px; border-radius:10px; font-family:sans-serif;'>
            <h2 style='color:#f97316;'>Alerte Intrusion Admin</h2>
            <p>Un utilisateur a tenté d'accéder au panel admin avec une adresse email inconnue.</p>
            <hr>
            <p>Email tenté : <b>$email_attempt</b></p>
            <p>Date : <b>$date_heure</b></p>
            <p>IP : <b>$ip</b></p>
            <p>Navigateur : <b>$user_agent</b></p>
        </div>";

        $mail->send();

        header("Location: login.php?error=noaccount");
        exit();
    }
} catch (Exception $e) {
    if (isset($_SESSION['temp_admin_id'])) {
        header("Location: verify_otp.php");
    } else {
        header("Location: login.php?error=mailfail");
    }
    exit();
}