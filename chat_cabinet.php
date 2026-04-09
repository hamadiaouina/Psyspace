<?php
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}
session_start();

if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

$nom_docteur = mb_strtoupper($_SESSION['nom'] ?? 'Docteur', 'UTF-8');
$doc_id      = (int)$_SESSION['id'];

$stmt = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doc_id); 
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doc_photo   = $doc['photo'] ?? '';
$doc_initial = mb_substr($nom_docteur, 0, 1, 'UTF-8');

$cabinet_code = "ERREUR (Non généré)";
$stmt_code = $conn->prepare("SELECT access_code FROM assistant_access WHERE doctor_id = ?");
$stmt_code->bind_param("i", $doc_id);
$stmt_code->execute();
$res_code = $stmt_code->get_result();
if ($row_code = $res_code->fetch_assoc()) {
    $cabinet_code = $row_code['access_code'];
}
$stmt_code->close();

$email_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invite_email'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $email_status = "<div class='text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg text-sm mb-4 font-semibold'>Erreur de sécurité (CSRF).</div>";
    } else {
        $target_email = trim($_POST['assistant_email']);
        if (filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
            try {
                $mail = new PHPMailer(true);
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

                $mail->setFrom($smtp_user, 'Le cabinet du Dr. ' . ucwords(strtolower($nom_docteur)));
                $mail->addAddress($target_email);
                $mail->isHTML(true);
                $mail->Subject = "Vos accès au secrétariat PsySpace";
                
                $mail->Body = "
                <div style='font-family:sans-serif; max-width:500px; margin:0 auto; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;'>
                    <div style='background:#4f46e5; padding:20px; text-align:center;'>
                        <h2 style='color:#ffffff; margin:0;'>Bienvenue dans l'équipe</h2>
                    </div>
                    <div style='padding:30px; background:#ffffff; text-align:center;'>
                        <p style='color:#475569; font-size:16px;'>Bonjour,</p>
                        <p style='color:#475569; line-height:1.5;'>Le Dr. <b>" . htmlspecialchars(ucwords(strtolower($nom_docteur))) . "</b> vous invite à gérer l'agenda du cabinet sur la plateforme PsySpace.</p>
                        
                        <p style='color:#475569; font-size:14px; margin-top:20px;'>Voici votre code d'accès sécurisé unique :</p>
                        
                        <div style='font-size:28px; font-weight:bold; color:#ec4899; background:#fdf2f8; padding:15px; border-radius:8px; border:2px dashed #fbcfe8; margin:10px 0; letter-spacing: 3px;'>
                            $cabinet_code
                        </div>

                        <a href='https://www.psyspace.me/assistante.php' style='display:inline-block; background-color:#4f46e5; color:#ffffff; padding:12px 24px; text-decoration:none; border-radius:8px; font-weight:bold; margin-top:20px;'>Accéder au Portail Assistante</a>

                        <p style='color:#94a3b8; font-size:12px; margin-top:30px;'>Ce code est strictement confidentiel. Ne le partagez avec personne.</p>
                    </div>
                </div>";

                $mail->send();
                $email_status = "<div class='text-emerald-700 bg-emerald-50 border border-emerald-200 p-3 rounded-lg text-sm mb-4 font-semibold flex items-center gap-2'><svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path></svg> Invitation envoyée avec succès.</div>";
            } catch (Exception $e) {
                error_log("Erreur Mail Assistante : " . $e->getMessage());
                $email_status = "<div class='text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg text-sm mb-4 font-semibold'>L'envoi de l'email a échoué.</div>";
            }
        } else {
            $email_status = "<div class='text-amber-600 bg-amber-50 border border-amber-200 p-3 rounded-lg text-sm mb-4 font-semibold'>Adresse email invalide.</div>";
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liaison Secrétariat | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script nonce="<?= $nonce ?>">
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        };
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scroll::-webkit-scrollbar-thumb { background: #475569; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
        .dark .sidebar-link.active { background-color: rgba(79,70,229,0.2); color: #818cf8; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">

<div class="flex min-h-screen relative">

    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <aside id="sidebar" class="w-64 bg-slate-900 dark:bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
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
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
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
            <a href="chat_cabinet.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                Contact Assistant
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold border-2 border-indigo-200 overflow-hidden">
                    <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                        <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full rounded-full object-cover">
                    <?php else: ?>
                        <?= $doc_initial ?>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="profile.php" class="text-sm font-bold text-white truncate hover:text-indigo-300 transition-colors block">
                        Dr. <?= htmlspecialchars(ucwords(strtolower($nom_docteur))) ?>
                    </a>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-red-400 p-2" title="Déconnexion">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full flex flex-col lg:flex-row gap-6 h-screen overflow-hidden">
        
        <div class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center gap-4 bg-slate-50 dark:bg-slate-800/50">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center shadow-inner shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <h2 class="font-bold text-slate-900 dark:text-white">Accueil Secrétariat</h2>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Canal sécurisé actif
                    </p>
                </div>
            </div>

            <div id="chat-messages" class="flex-1 p-5 overflow-y-auto bg-slate-50/50 dark:bg-slate-900 flex flex-col gap-4 custom-scroll">
            </div>

            <div class="p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
                <form id="chat-form" class="flex gap-3">
                    <input type="text" id="chat-input" placeholder="Envoyer un message à l'assistante..." required autocomplete="off" class="flex-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-5 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900/30 dark:text-white transition-all">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-6 font-bold text-sm flex items-center gap-2 shadow-sm hover:shadow transition-all">
                        Envoyer
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="w-full lg:w-80 flex flex-col gap-6 overflow-y-auto custom-scroll pr-1">
            
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-pink-100 dark:bg-pink-900/30 text-pink-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm">Code Cabinet</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">L'assistante doit entrer ce code sur la page d'accueil sécurisée.</p>
                <div class="bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 text-center">
                    <span class="font-mono text-xl font-bold tracking-[0.2em] text-slate-800 dark:text-white"><?= $cabinet_code ?></span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-sm">Envoyer l'accès</h3>
                </div>
                
                <?= $email_status ?>

                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Entrez l'email de votre assistante. Elle recevra le lien de connexion et le code d'accès.</p>
                
                <form method="POST" action="chat_cabinet.php" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="send_invite_email" value="1">
                    <div>
                        <input type="email" name="assistant_email" required placeholder="email@exemple.com" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:text-white transition-all">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg text-sm shadow-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Envoyer l'invitation
                    </button>
                </form>
            </div>
            
            <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/50 rounded-2xl p-5">
                <h4 class="text-xs font-bold text-indigo-800 dark:text-indigo-300 uppercase tracking-wider mb-2">Comment ça marche ?</h4>
                <ul class="text-xs text-indigo-700 dark:text-indigo-400 space-y-2 list-disc list-inside pl-2">
                    <li>Le tchat est direct et sécurisé.</li>
                    <li>Vous recevrez ici les notifications automatiques d'ajout ou d'annulation de rendez-vous par l'assistante.</li>
                    <li>L'assistante n'a pas besoin de mot de passe, uniquement du <strong>Code Cabinet</strong>.</li>
                </ul>
            </div>

        </div>

    </main>
</div>

<script nonce="<?= $nonce ?>">
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const notifSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
    let lastMsgCount = 0;

    function loadMessages() {
        fetch('api_chat.php?action=fetch')
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.forEach(msg => {
                    if (msg.sender_type === 'system') {
                        html += `<div class="text-center my-3"><span class="bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold px-3 py-1.5 rounded-full inline-flex items-center gap-1.5"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> ${msg.message}</span><div class="text-[9px] text-slate-400 mt-1">${msg.time}</div></div>`;
                    } 
                    else if (msg.sender_type === 'doctor') {
                        html += `<div class="self-end max-w-[85%] flex flex-col items-end mb-2"><div class="bg-indigo-600 text-white text-sm py-2.5 px-4 rounded-2xl rounded-tr-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                    } 
                    else {
                        html += `<div class="self-start max-w-[85%] flex flex-col items-start mb-2"><span class="text-[10px] font-bold text-slate-500 mb-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Assistante</span><div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-sm py-2.5 px-4 rounded-2xl rounded-tl-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                    }
                });

                const isNew = data.length > lastMsgCount;
                chatMessages.innerHTML = html;

                if (isNew) {
                    if (lastMsgCount !== 0) notifSound.play().catch(e => {}); 
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    lastMsgCount = data.length;
                }
            });
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;

        const formData = new FormData(); 
        formData.append('message', text);

        fetch('api_chat.php?action=send', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    chatInput.value = '';
                    loadMessages();
                }
            });
    });

    loadMessages();
    setInterval(loadMessages, 3000);

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('open-sidebar');
    const closeBtn = document.getElementById('close-sidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    if(openBtn) openBtn.addEventListener('click', toggleSidebar);
    if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if(overlay) overlay.addEventListener('click', toggleSidebar);
</script>

</body>
</html>