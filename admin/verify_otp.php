<?php
// --- 1. CONFIGURATION DE SÉCURITÉ ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . "/../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

// --- 2. SÉCURITÉ : VÉRIFICATION DU BADGE INVISIBLE ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (empty($admin_secret_key) || !isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}

// --- 3. SÉCURITÉ : VÉRIFICATION DE LA SESSION TEMPORAIRE ---
if (!isset($_SESSION['temp_admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['temp_admin_id'];

// Infos contextuelles pour les alertes
$smtp_user    = getenv('SMTP_USER');
$smtp_pass    = getenv('SMTP_PASS') ?: '';
$notify_email = $smtp_user;
$ip           = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'IP Inconnue';
$date_heure   = date('d/m/Y à H:i:s');
$user_agent   = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';

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

// --- 4. GESTION DES TENTATIVES OTP ---
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Blocage immédiat si on recharge la page après 3 tentatives
if ($_SESSION['otp_attempts'] >= 3) {
    $stmt_lock = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
    $stmt_lock->bind_param("i", $admin_id);
    $stmt_lock->execute();

    session_destroy();
    header("Location: ../index.php?error=security_lock");
    exit();
}

// Génération du jeton CSRF pour ce formulaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = "";

// --- 5. TRAITEMENT DU FORMULAIRE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Vérification CSRF
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $post_csrf)) {
        $error = "Erreur de sécurité (CSRF). Veuillez réessayer.";
    } else {
        $user_otp = trim($_POST['otp'] ?? '');

        $stmt = $con->prepare("SELECT * FROM admin WHERE admid = ? AND otp_code = ?");
        $stmt->bind_param("is", $admin_id, $user_otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();

            // ✅ SUCCÈS : Nettoyage de l'OTP
            $update_stmt = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
            $update_stmt->bind_param("i", $admin_id);
            $update_stmt->execute();

            // 🛡️ Anti-fixation de session
            session_regenerate_id(true);

            $_SESSION['admin_id']   = $admin['admid']; 
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role']       = 'admin';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['otp_attempts']);

            header("Location: dashboard_admin.php");
            exit();
            
        } else {
            // ❌ MAUVAIS CODE OTP
            $_SESSION['otp_attempts']++;
            $tentatives = $_SESSION['otp_attempts'];
            $restant    = 3 - $tentatives;

            // Envoi de la notification d'alerte
            try {
                $mail = buildMailer($smtp_user, $smtp_pass);
                $mail->addAddress($notify_email);

                if ($restant <= 0) {
                    $mail->Subject = "🔒 BLOCAGE : Trop de mauvais codes OTP";
                    $mail->Body    = "
                    <div style='border:2px solid #ef4444; padding:20px; border-radius:10px; font-family:sans-serif;'>
                        <h2 style='color:#ef4444;'>Session Admin Bloquée</h2>
                        <p>3 codes OTP incorrects ont été saisis. La session a été <b>détruite</b>.</p>
                        <hr>
                        <p>Admin ID ciblé : <b>$admin_id</b></p>
                        <p>Date : <b>$date_heure</b></p>
                        <p>IP : <b>$ip</b></p>
                        <p>Navigateur : <b>$user_agent</b></p>
                    </div>";
                } else {
                    $mail->Subject = "⚠️ ALERTE : Mauvais code OTP (tentative $tentatives/3)";
                    $mail->Body    = "
                    <div style='border:2px solid #f59e0b; padding:20px; border-radius:10px; font-family:sans-serif;'>
                        <h2 style='color:#f59e0b;'>Code OTP incorrect</h2>
                        <p>Un code OTP erroné a été soumis sur la page de vérification 2FA.</p>
                        <hr>
                        <p>Admin ID ciblé : <b>$admin_id</b></p>
                        <p>Code saisi : <b>" . htmlspecialchars($user_otp) . "</b></p>
                        <p>Tentative : <b>$tentatives / 3</b></p>
                        <p>Date : <b>$date_heure</b></p>
                        <p>IP : <b>$ip</b></p>
                        <p>Navigateur : <b>$user_agent</b></p>
                    </div>";
                }
                $mail->send();
            } catch (Exception $e) { /* silencieux */ }

            if ($restant <= 0) {
                $stmt_lock = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
                $stmt_lock->bind_param("i", $admin_id);
                $stmt_lock->execute();

                session_destroy();
                header("Location: ../index.php?error=security_lock");
                exit();
            } else {
                $error = "Code incorrect. Il vous reste <b>$restant tentative(s)</b> avant le blocage de sécurité.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA | PsySpace Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { 
                        sans: ['IBM Plex Sans', 'sans-serif'],
                        mono: ['IBM Plex Mono', 'monospace']
                    },
                    colors: {
                        brand: '#3d52a0',
                        brand_hover: '#2d3d80',
                        dark_bg: '#0c0c12',
                        dark_surface: '#14141e',
                        dark_border: '#26263a'
                    }
                } 
            }
        };
        if (localStorage.getItem('psyadmin_dark') === '1' || (!('psyadmin_dark' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        body { font-family: 'IBM Plex Sans', sans-serif; }
        .font-mono { font-family: 'IBM Plex Mono', monospace; }
        .glow { box-shadow: 0 0 40px rgba(61, 82, 160, 0.15); }
        .dark .glow { box-shadow: 0 0 40px rgba(61, 82, 160, 0.08); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-slate-50 dark:bg-dark_bg text-slate-900 dark:text-slate-200 transition-colors duration-300 p-4">

    <div class="w-full max-w-[400px]">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-white dark:bg-dark_surface border border-slate-200 dark:border-dark_border rounded-xl shadow-sm mb-5 relative">
                <div class="absolute inset-0 border border-brand/30 rounded-xl animate-pulse"></div>
                <svg class="w-6 h-6 text-brand dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Authentification 2FA</h1>
            <p class="text-[11px] uppercase tracking-[0.15em] font-mono text-slate-500 dark:text-slate-400 mt-2">Vérification d'identité</p>
        </div>

        <!-- Box Verify -->
        <div class="bg-white dark:bg-dark_surface rounded-xl shadow-2xl dark:shadow-none border border-slate-200 dark:border-dark_border p-8 glow relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-brand"></div>

            <p class="text-[13px] text-slate-500 dark:text-slate-400 text-center mb-6 leading-relaxed">
                Un code de sécurité à 6 chiffres a été envoyé à votre adresse e-mail. Veuillez le saisir ci-dessous.
            </p>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg text-[13px] text-red-600 dark:text-red-400 flex items-start gap-3">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="font-medium leading-tight"><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wider font-mono text-center">Code de sécurité</label>
                    <input type="text" name="otp" placeholder="••••••" maxlength="6" required autocomplete="off" autofocus 
                           class="w-full py-3 bg-slate-50 dark:bg-dark_bg border border-slate-200 dark:border-dark_border rounded-lg text-center text-2xl font-bold tracking-[0.5em] text-brand dark:text-white font-mono focus:ring-1 focus:ring-brand focus:border-brand dark:focus:ring-indigo-500 dark:focus:border-indigo-500 outline-none transition-colors placeholder-slate-300 dark:placeholder-slate-700"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
                
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-2.5 px-4 bg-brand hover:bg-brand_hover text-white text-[13px] font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                        Confirmer l'accès
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>