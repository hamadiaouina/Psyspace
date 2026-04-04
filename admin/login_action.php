<?php
session_start();
require_once __DIR__ . "/../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// 1. Sécurité : Vérification du badge
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}

// 2. Sécurité : Rediriger si on essaie d'accéder à ce fichier directement sans poster de formulaire
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// --- LE RESTE DU TRAITEMENT ---
if (!isset($con)) { $con = $conn ?? null; }

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

$stmt = $con->prepare("SELECT * FROM admin WHERE admemail = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
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

        if (password_verify($password, $admin['admpassword'])) {
            $otp = rand(100000, 999999);
            $update_stmt = $con->prepare("UPDATE admin SET otp_code = ? WHERE admid = ?");
            $update_stmt->bind_param("si", $otp, $admin['admid']);
            $update_stmt->execute();

            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "<h2>Code : $otp</h2>";
            $mail->send();
            
            $_SESSION['temp_admin_id'] = $admin['admid'];
            header("Location: verify_otp.php");
            exit();
        } else {
            header("Location: login.php?error=wrongpw");
            exit();
        }
    } else {
        header("Location: login.php?error=noaccount");
        exit();
    }
} catch (Exception $e) {
    header("Location: login.php?error=mailfail");
    exit();
}