<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// --- NOUVEAU : GESTION DES TENTATIVES ---
if (!isset($_SESSION['admin_attempts'])) {
    $_SESSION['admin_attempts'] = 0;
}

// Si l'utilisateur est déjà bloqué, on le renvoie direct à l'index
if ($_SESSION['admin_attempts'] >= 3) {
    header("Location: ../index.php?error=blocked");
    exit();
}
// ----------------------------------------

if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
if (strpos($ip, ':') !== false && strpos($ip, '.') !== false) { $ip = explode(':', $ip)[0]; }
$ip = trim($ip);

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$heure    = date('d/m/Y à H:i:s');

$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $mail = new PHPMailer(true);

    try {
        $smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
        $smtp_pass = getenv('SMTP_PASS') ?: '';

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
            // ✅ SUCCÈS : On réinitialise le compteur
            $_SESSION['admin_attempts'] = 0;

            $otp = rand(100000, 999999);
            $admin_id = $admin['admid'];
            $con->query("UPDATE admin SET otp_code = '$otp' WHERE admid = '$admin_id'");

            $mail->Subject = "🔑 Code de vérification Admin - $otp";
            $mail->Body    = "..."; // (Garde ton code HTML d'origine ici)
            
            $mail->send();
            $_SESSION['temp_admin_id'] = $admin['admid'];
            header("Location: verify_otp.php");
            exit();

        } else {
            // ❌ ÉCHEC : On augmente le compteur
            $_SESSION['admin_attempts']++;

            $mail->Subject = "⚠️ ALERTE : Tentative de connexion échouée";
            $mail->Body    = "..."; // (Garde ton code HTML d'origine ici)
            $mail->send();

            // Si c'est le 3ème échec, on dégage vers l'index
            if ($_SESSION['admin_attempts'] >= 3) {
                header("Location: ../index.php?error=security_lock");
            } else {
                header("Location: login.php?error=wrongpw&retry=" . (3 - $_SESSION['admin_attempts']));
            }
            exit();
        }

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        header("Location: login.php?error=mailfail");
        exit();
    }
} else {
    // Compte inexistant : On compte aussi comme une tentative pour éviter le brute force d'emails
    $_SESSION['admin_attempts']++;
    header("Location: login.php?error=noaccount");
    exit();
}