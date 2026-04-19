<?php
// ════════════════════════════════════════════════════════════════
//  PsySpace · contact_dashboard.php — Contact intégré au dashboard
// ════════════════════════════════════════════════════════════════
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) session_start();

// Vérification connexion
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
if (isset($_SESSION['user_ip'], $_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy(); header("Location: login.php?error=hijack"); exit();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$doc_id      = (int)$_SESSION['id'];
$nom_docteur = $_SESSION['nom'] ?? 'Docteur';

// Pré-remplir avec les infos du praticien connecté
$stmt = $conn->prepare("SELECT docname, docprenom, docemail FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doc_email  = $doc['docemail'] ?? '';
$doc_nom    = trim(($doc['docprenom'] ?? '') . ' ' . ($doc['docname'] ?? '')) ?: $nom_docteur;

$message_sent  = false;
$error_message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['hp_contact'])) {
        $message_sent = true; // honeypot
    } elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = "Erreur de sécurité. Veuillez rafraîchir la page.";
    } elseif (isset($_SESSION['last_contact_time']) && (time() - $_SESSION['last_contact_time']) < 60) {
        $error_message = "Veuillez patienter 1 minute entre chaque message.";
    } else {
        $mail = new PHPMailer(true);
        try {
            $smtp_user = getenv('SMTP_USER');
            $smtp_pass = getenv('SMTP_PASS');

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($smtp_user, 'PsySpace Support');
            $mail->addAddress($smtp_user);

            $user_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $user_name  = htmlspecialchars(trim($_POST['name']         ?? $doc_nom), ENT_QUOTES, 'UTF-8');
            $subject    = htmlspecialchars(trim($_POST['subject_type'] ?? 'Demande Contact'), ENT_QUOTES, 'UTF-8');
            $user_msg   = htmlspecialchars(trim($_POST['message']      ?? ''), ENT_QUOTES, 'UTF-8');

            if (!$user_email || empty($user_msg)) {
                $error_message = "Veuillez remplir tous les champs correctement.";
            } else {
                $mail->addReplyTo($user_email, $user_name);
                $mail->isHTML(true);
                $mail->Subject = "[Dashboard] " . $subject . " — " . $user_name;
                $mail->Body = "
                <div style='background:#f8fafc;padding:40px;font-family:sans-serif;color:#0f172a;'>
                    <div style='background:#fff;border:1px solid #e2e8f0;padding:32px;border-radius:16px;max-width:600px;margin:0 auto;'>
                        <div style='background:#4f46e5;color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;'>
                            Message depuis le Dashboard Praticien
                        </div>
                        <h2 style='color:#4f46e5;margin-top:0;'>Nouveau message — PsySpace</h2>
                        <hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0;'>
                        <p><strong>Praticien :</strong> {$user_name}</p>
                        <p><strong>Email :</strong> {$user_email}</p>
                        <p><strong>ID Docteur :</strong> #{$doc_id}</p>
                        <p><strong>Sujet :</strong> {$subject}</p>
                        <div style='background:#f8fafc;padding:20px;margin-top:20px;border-radius:10px;color:#475569;line-height:1.6;border-left:3px solid #4f46e5;'>
                            " . nl2br($user_msg) . "
                        </div>
                    </div>
                </div>";
                $mail->send();
                $_SESSION['last_contact_time'] = time();
                $message_sent = true;
            }
        } catch (Exception $e) {
            $error_message = "Impossible d'envoyer le message. Réessayez plus tard.";
            error_log("Erreur Contact Dashboard: " . $mail->ErrorInfo);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script nonce="<?= $nonce ?>">
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'], serif: ['Merriweather','serif'] } } } };
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
        .dark .sidebar-link.active { background-color: rgba(79,70,229,0.2); color: #818cf8; }
        .input-clean {
            width: 100%; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem;
            transition: all 0.2s; outline: none; color: #0f172a;
        }
        .dark .input-clean { background: #1e293b; border-color: #334155; color: #e2e8f0; }
        .input-clean:focus { background: white; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }
        .dark .input-clean:focus { background: #0f172a; }
        .input-clean::placeholder { color: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">

<div class="flex min-h-screen relative">
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 dark:bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0 print:hidden">
        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">
                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                Archives
            </a>

            <!-- BOUTON CONTACT ASSISTANT AVEC PASTILLE -->
            <a href="chat_cabinet.php" class="sidebar-link flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Contact Assistant
                </div>
                <span id="chat-badge-sidebar" class="hidden bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm transition-all duration-300">0</span>
            </a>
                <a href="contact_dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        Contact
    </a>
        <a href="chatbot_dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        Chatbot
    </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-2 text-slate-500 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <!-- CONTENU PRINCIPAL -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full">

        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Contact & Support</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Une question ? Notre équipe répond sous 24h ouvrées.</p>
                </div>
            </div>
            <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all border border-transparent dark:border-slate-700">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
            </button>
        </div>

        <div class="grid lg:grid-cols-5 gap-8 items-start">

            <!-- FORMULAIRE -->
            <div class="lg:col-span-3">
                <?php if (!empty($error_message)): ?>
                    <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm rounded-xl flex items-center gap-3">
                        <svg class="shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($message_sent && empty($error_message)): ?>
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-12 text-center">
                        <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/40 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <h2 class="font-serif text-2xl font-bold text-slate-900 dark:text-white mb-3">Message envoyé !</h2>
                        <p class="text-slate-500 dark:text-slate-400 mb-6">Notre équipe vous répondra sous 24h ouvrées à l'adresse indiquée.</p>
                        <a href="contact_dashboard.php" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline underline-offset-4">
                            Envoyer un autre message
                        </a>
                    </div>

                <?php else: ?>
                    <form action="contact_dashboard.php" method="POST"
                          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 md:p-8 space-y-5 shadow-sm">

                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div style="display:none;" aria-hidden="true">
                            <input type="text" name="hp_contact" tabindex="-1" autocomplete="off">
                        </div>

                        <div>
                            <h2 class="font-serif text-xl font-bold text-slate-900 dark:text-white mb-1">Envoyez-nous un message</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Vos coordonnées sont pré-remplies depuis votre profil.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nom complet</label>
                                <input type="text" name="name" required
                                       value="<?= htmlspecialchars($doc_nom) ?>"
                                       class="input-clean">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Email</label>
                                <input type="email" name="email" required
                                       value="<?= htmlspecialchars($doc_email) ?>"
                                       placeholder="votre@email.com"
                                       class="input-clean">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sujet</label>
                            <select name="subject_type" class="input-clean cursor-pointer" required>
                                <optgroup label="Assistance & Technique">
                                    <option value="Bug / Erreur">Signaler un bug ou une erreur</option>
                                    <option value="Accès Compte">Problème d'accès ou connexion</option>
                                    <option value="Installation">Aide à l'installation / Configuration</option>
                                    <option value="Performance">Lenteur de la plateforme</option>
                                </optgroup>
                                <optgroup label="Gestion & Cabinet">
                                    <option value="RGPD">Données patients & Confidentialité (RGPD)</option>
                                    <option value="Exports">Exportation des données patient</option>
                                    <option value="Agenda">Problème avec l'agenda / Prise de RDV</option>
                                    <option value="Code Cabinet">Code cabinet / Assistants</option>
                                </optgroup>
                                <optgroup label="Autre">
                                    <option value="Démo">Demander une démonstration</option>
                                    <option value="Feedback">Suggestion d'amélioration</option>
                                    <option value="Autre">Autre demande</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Message</label>
                            <textarea name="message" required rows="6"
                                      placeholder="Décrivez votre besoin en détail..."
                                      class="input-clean resize-none"></textarea>
                        </div>

                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-lg transition-all hover:-translate-y-0.5 shadow-md shadow-indigo-200/50">
                            Envoyer le message
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- INFOS LATÉRALES -->
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                    <h3 class="font-bold text-slate-900 dark:text-white mb-5 text-base">Coordonnées</h3>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-slate-100 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-500 shrink-0">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Email</p>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">psyspace.me@gmail.com</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-slate-100 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-500 shrink-0">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Localisation</p>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Radès, Tunisie</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse inline-block"></span>
                        <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Équipe disponible</p>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Réponse garantie sous <span class="font-semibold text-slate-800 dark:text-slate-200">24h ouvrées.</span> Vos échanges sont confidentiels.
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold px-3 py-1.5 rounded-md">
                        <svg class="text-emerald-500" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        RGPD
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold px-3 py-1.5 rounded-md">
                        <svg class="text-emerald-500" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        HDS
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold px-3 py-1.5 rounded-md">
                        <svg class="text-emerald-500" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        AES-256
                    </span>
                </div>
            </div>
        </div>
    </main>
</div>

<script nonce="<?= $nonce ?>">
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebar-overlay');
const openBtn  = document.getElementById('open-sidebar');
const closeBtn = document.getElementById('close-sidebar');
function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); }
if (openBtn)  openBtn.addEventListener('click', toggleSidebar);
if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
if (overlay)  overlay.addEventListener('click', toggleSidebar);

const themeBtn  = document.getElementById('theme-toggle');
const darkIcon  = document.getElementById('theme-toggle-dark-icon');
const lightIcon = document.getElementById('theme-toggle-light-icon');
if (document.documentElement.classList.contains('dark')) { lightIcon.classList.remove('hidden'); } else { darkIcon.classList.remove('hidden'); }
themeBtn.addEventListener('click', function() {
    darkIcon.classList.toggle('hidden'); lightIcon.classList.toggle('hidden');
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light');
    } else {
        document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark');
    }
});

function checkUnreadMessages() {
    fetch('api_chat_unread.php').then(r => r.json()).then(data => {
        const badge = document.getElementById('chat-badge-sidebar');
        if (badge) {
            if (data.unread > 0) { badge.textContent = '+' + data.unread; badge.classList.remove('hidden'); }
            else { badge.classList.add('hidden'); }
        }
    }).catch(() => {});
}
checkUnreadMessages();
setInterval(checkUnreadMessages, 5000);
</script>
</body>
</html>