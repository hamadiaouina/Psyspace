<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
$heure    = date('d/m/Y à H:i:s');

/* ══════════════════════════════════════
   FONCTION D'ENVOI D'ALERTE
══════════════════════════════════════ */
function sendAlertMail(string $sujet, string $corps): void {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'psyspace.all@gmail.com';
        $mail->Password   = 'lszg gkpz ylbg ypdt';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;

        $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
        $mail->addAddress('psyspace.all@gmail.com');

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corps;
        $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Alert Error: " . $mail->ErrorInfo);
    }
}

/* ══════════════════════════════════════
   RECHERCHE ADMIN
══════════════════════════════════════ */
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {

        /* ── CONNEXION RÉUSSIE ── */
        sendAlertMail(
            "✅ Connexion Admin Réussie — PsySpace",
            "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #10b981;border-radius:12px;overflow:hidden;'>
                <div style='background:#10b981;padding:20px;text-align:center;'>
                    <h2 style='color:#fff;margin:0;'>✅ Connexion Réussie</h2>
                </div>
                <div style='padding:24px;background:#f0fdf4;'>
                    <p style='color:#065f46;font-size:15px;'>L'administrateur <b>{$admin['admname']}</b> vient de se connecter avec succès.</p>
                    <table style='width:100%;border-collapse:collapse;margin-top:12px;'>
                        <tr><td style='padding:8px;color:#6b7280;font-size:13px;'>👤 Admin</td><td style='padding:8px;font-weight:600;color:#111827;'>{$admin['admname']}</td></tr>
                        <tr style='background:#dcfce7;'><td style='padding:8px;color:#6b7280;font-size:13px;'>📧 Email</td><td style='padding:8px;font-weight:600;color:#111827;'>{$email}</td></tr>
                        <tr><td style='padding:8px;color:#6b7280;font-size:13px;'>🕐 Heure</td><td style='padding:8px;font-weight:600;color:#111827;'>{$heure}</td></tr>
                        <tr style='background:#dcfce7;'><td style='padding:8px;color:#6b7280;font-size:13px;'>🌐 IP</td><td style='padding:8px;font-weight:600;color:#111827;'>{$ip}</td></tr>
                    </table>
                    <p style='color:#065f46;font-size:12px;margin-top:16px;'>Si ce n'était pas vous, changez votre mot de passe immédiatement.</p>
                </div>
            </div>"
        );

        $_SESSION['admin_id']   = $admin['admid'];
        $_SESSION['admin_name'] = $admin['admname'];
        $_SESSION['role']       = 'admin';

        header("Location: dashboard.php");
        exit();

    } else {

        /* ── MOT DE PASSE INCORRECT — EMAIL ADMIN RECONNU ── */
        sendAlertMail(
            "⚠️ Tentative de Connexion Admin — Mot de passe incorrect",
            "
            <div style='font-family:sans-serif;max-width:500px;margin:0 auto;border:2px solid #f59e0b;border-radius:12px;overflow:hidden;'>
                <div style='background:#f59e0b;padding:20px;text-align:center;'>
                    <h2 style='color:#fff;margin:0;'>⚠️ Mot de passe incorrect</h2>
                </div>
                <div style='padding:24px;background:#fffbeb;'>
                    <p style='color:#92400e;font-size:15px;'>Une tentative de connexion avec l'email admin <b>{$email}</b> a échoué (mot de passe incorrect).</p>
                    <table style='width:100%;border-collapse:collapse;margin-top:12px;'>
                        <tr><td style='padding:8px;color:#6b7280;font-size:13px;'>📧 Email tenté</td><td style='padding:8px;font-weight:600;color:#111827;'>{$email}</td></tr>
                        <tr style='background:#fef3c7;'><td style='padding:8px;color:#6b7280;font-size:13px;'>🕐 Heure</td><td style='padding:8px;font-weight:600;color:#111827;'>{$heure}</td></tr>
                        <tr><td style='padding:8px;color:#6b7280;font-size:13px;'>🌐 IP</td><td style='padding:8px;font-weight:600;color:#111827;'>{$ip}</td></tr>
                    </table>
                    <p style='color:#92400e;font-size:12px;margin-top:16px;'>⚠️ Si ce n'était pas vous, votre compte est peut-être ciblé.</p>
                </div>
            </div>"
        );

        header("Location: ../login.php?error=wrongpw");
        exit();
    }

} else {

    /* ── EMAIL INCONNU — pas d'alerte (évite le spam sur tentatives aléatoires) ── */
    header("Location: ../login.php?error=noaccount");
    exit();
}