<?php
// ════════════════════════════════════════════════════════════════
//  PsySpace · chat_cabinet_dashboard.php — Chatbot IA dans le dashboard
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

$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

$doc_id      = (int)$_SESSION['id'];
$nom_docteur = $_SESSION['nom'] ?? 'Docteur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant IA | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script nonce="<?= $nonce ?>">
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } };
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; overflow: hidden; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
        .dark .sidebar-link.active { background-color: rgba(79,70,229,0.2); color: #818cf8; }

        /* Layout principal avec sidebar */
        .app-shell { display: flex; height: 100vh; overflow: hidden; }
        .content-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #0f172a; }

        /* Chat layout */
        .chat-shell {
            flex: 1; display: flex; flex-direction: column;
            height: 100%; overflow: hidden;
        }
        .chat-topbar {
            padding: 16px 24px;
            background: rgba(15,23,42,0.95);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0;
        }
        .chat-body {
            flex: 1; display: flex; gap: 0;
            overflow: hidden;
            padding: 20px 24px;
            gap: 20px;
        }

        /* Avatar */
        .avatar-panel {
            width: 320px; flex-shrink: 0;
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border-radius: 1.5rem;
            border: 1px solid rgba(255,255,255,0.05);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        @media (max-width: 1024px) { .avatar-panel { display: none; } }
        #avatar-canvas { width: 100%; height: 100%; display: block; cursor: grab; }
        #avatar-canvas:active { cursor: grabbing; }
        .status-pill {
            position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
            display: flex; align-items: center; gap: 7px;
            padding: 7px 16px;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 99px;
            font-size: 11px; font-weight: 600; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
        }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #64748b; transition: all 0.3s; }
        .status-pill.listening .status-dot { background: #ef4444; box-shadow: 0 0 8px #ef4444; animation: pulse 1.5s infinite; }
        .status-pill.thinking  .status-dot { background: #6366f1; box-shadow: 0 0 8px #6366f1; animation: pulse 1.5s infinite; }
        .status-pill.speaking  .status-dot { background: #10b981; box-shadow: 0 0 8px #10b981; }

        /* Chat panel */
        .chat-panel {
            flex: 1; display: flex; flex-direction: column;
            background: #1e293b; border-radius: 1.5rem;
            border: 1px solid rgba(255,255,255,0.05);
            overflow: hidden;
        }
        .chat-area {
            flex: 1; padding: 24px; overflow-y: auto;
            display: flex; flex-direction: column; gap: 16px;
        }
        .chat-area::-webkit-scrollbar { width: 3px; }
        .chat-area::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        .message-bubble {
            max-width: 80%; padding: 14px 18px; border-radius: 18px;
            font-size: 14px; line-height: 1.65; animation: fadeIn 0.25s ease-out;
        }
        .message-bubble.bot {
            align-self: flex-start; background: #0f172a;
            border: 1px solid rgba(255,255,255,0.05);
            color: #e2e8f0; border-bottom-left-radius: 4px;
        }
        .message-bubble.user {
            align-self: flex-end; background: #4f46e5; color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        .controls-area {
            padding: 16px 20px 20px;
            background: #172033;
            border-top: 1px solid rgba(255,255,255,0.03);
            flex-shrink: 0;
        }
        .input-group {
            display: flex; gap: 10px; background: #0f172a;
            padding: 6px; border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .chat-input {
            flex: 1; background: transparent; border: none;
            padding: 10px 14px; color: white; font-size: 14px; outline: none;
            font-family: 'Inter', sans-serif;
        }
        .chat-input::placeholder { color: #475569; }
        .btn-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; border: none; flex-shrink: 0;
        }
        .btn-mic { background: transparent; color: #64748b; }
        .btn-mic:hover { color: white; background: rgba(255,255,255,0.05); }
        .btn-mic.active { color: #ef4444; background: rgba(239,68,68,0.1); }
        .btn-send { background: #6366f1; color: white; box-shadow: 0 3px 10px rgba(99,102,241,0.3); }
        .btn-send:hover { background: #4f46e5; transform: translateY(-1px); }
        .typing-dots { display: flex; gap: 4px; }
        .typing-dots span { width: 6px; height: 6px; background: #6366f1; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
        .suggestion-chip {
            cursor: pointer; padding: 5px 11px;
            background: rgba(255,255,255,0.05); border-radius: 99px;
            font-size: 11.5px; color: #94a3b8; transition: all .2s;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .suggestion-chip:hover { background: rgba(99,102,241,0.15); color: #a5b4fc; border-color: rgba(99,102,241,0.3); }

        @keyframes fadeIn  { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        @keyframes bounce  { 0%,80%,100% { transform:scale(0); } 40% { transform:scale(1); } }
        @keyframes pulse   { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
        @keyframes spin    { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-slate-900">

<div class="app-shell">

    <!-- OVERLAY MOBILE -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0 flex-shrink-0">
        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-8 rounded-lg object-cover">
                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                Archives
            </a>
            <a href="chat_cabinet_dashboard.php" class="sidebar-link active flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Assistant IA
                </div>
            </a>
            <a href="contact_dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Contact
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-2 text-slate-500 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <!-- ZONE PRINCIPALE (avec marge pour la sidebar) -->
    <div class="content-area lg:ml-64">

        <!-- TOPBAR -->
        <div class="chat-topbar">
            <div class="flex items-center gap-3">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0">
                    <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">Assistant PsySpace</p>
                    <p class="text-slate-400 text-xs">Bonjour, Dr. <?= htmlspecialchars($nom_docteur) ?> · Questions sur la plateforme</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5 bg-slate-800 px-3 py-1.5 rounded-lg">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                    <span class="text-xs text-slate-400 font-medium">En ligne</span>
                </div>
            </div>
        </div>

        <!-- CORPS -->
        <div class="chat-body">

            <!-- AVATAR 3D (masqué sur mobile) -->
            <div class="avatar-panel">
                <canvas id="avatar-canvas"></canvas>
                <div id="loader" style="position:absolute;color:white;text-align:center;">
                    <div style="width:36px;height:36px;border:3px solid rgba(255,255,255,0.1);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 10px;"></div>
                    <span style="font-size:12px;color:#94a3b8;">Chargement…</span>
                </div>
                <div class="status-pill" id="statusPill">
                    <div class="status-dot"></div>
                    <span id="statusText">En attente</span>
                </div>
            </div>

            <!-- CHAT -->
            <div class="chat-panel">
                <div class="chat-area" id="chatHistory">
                    <div class="message-bubble bot">
                        Bonjour Dr. <?= htmlspecialchars($nom_docteur) ?> ! Je suis l'assistant PsySpace. Posez-moi vos questions sur la plateforme.
                    </div>
                </div>
                <div class="controls-area">
                    <div class="input-group">
                        <button id="micBtn" class="btn-icon btn-mic" title="Microphone">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                        </button>
                        <input type="text" id="chatInput" class="chat-input" placeholder="Posez votre question sur PsySpace…">
                        <button id="sendBtn" class="btn-icon btn-send">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div style="margin-top:10px;display:flex;gap:7px;flex-wrap:wrap;">
                        <span class="suggestion-chip" data-ask="Comment exporter un PDF ?">Export PDF</span>
                        <span class="suggestion-chip" data-ask="Comment partager le code cabinet ?">Code cabinet</span>
                        <span class="suggestion-chip" data-ask="Comment installer l'app mobile ?">App mobile</span>
                        <span class="suggestion-chip" data-ask="Comment contacter le support ?">Support</span>
                        <span class="suggestion-chip" data-ask="Mes données sont-elles sécurisées ?">Sécurité</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Three.js -->
<script src="/assets/js/three.min.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/js/GLTFLoader.js" nonce="<?= $nonce ?>"></script>

<script nonce="<?= $nonce ?>">
/* ── SIDEBAR ── */
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebar-overlay');
const openBtn  = document.getElementById('open-sidebar');
const closeBtn = document.getElementById('close-sidebar');
function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); }
if (openBtn)  openBtn.addEventListener('click', toggleSidebar);
if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
if (overlay)  overlay.addEventListener('click', toggleSidebar);

/* ── STATE ── */
var isThinking=false, isListening=false, isSpeaking=false;
var recognition=null, currentAudio=null;
var scene, camera, renderer, mixer, clock, avatarRoot;
var headMesh=null, teethMesh=null, tongueMesh=null;
var lipIv=null, blinkIv=null, vIdx=0;

var M = {
    mouthOpen:0, viseme_sil:1,
    viseme_PP:2, viseme_FF:3, viseme_TH:4,
    viseme_DD:56, viseme_kk:57, viseme_CH:58,
    viseme_SS:59, viseme_nn:60, viseme_RR:61,
    viseme_aa:62, viseme_E:63, viseme_I:64,
    viseme_O:65, viseme_U:66,
    jawOpen:49, eyeBlinkL:18, eyeBlinkR:19, mouthSmile:47
};

var VISEMES = [
    {k:'viseme_aa',w:.85},{k:'viseme_O',w:.75},{k:'viseme_E',w:.65},
    {k:'viseme_PP',w:.55},{k:'viseme_SS',w:.65},{k:'viseme_DD',w:.55},
    {k:'viseme_I', w:.65},{k:'viseme_kk',w:.55},{k:'viseme_CH',w:.65},
    {k:'viseme_U', w:.75},{k:'mouthOpen',w:.65},{k:'viseme_nn',w:.45}
];

/* ── UI ── */
var chatArea   = document.getElementById('chatHistory');
var chatInput  = document.getElementById('chatInput');
var statusPill = document.getElementById('statusPill');
var statusText = document.getElementById('statusText');
var micBtn     = document.getElementById('micBtn');
var sendBtn    = document.getElementById('sendBtn');
var loaderDiv  = document.getElementById('loader');

function setStatus(s,t){ statusPill.className='status-pill '+(s||''); statusText.textContent=t||'En attente'; }

function addMessage(text,type){
    var d=document.createElement('div');
    d.className='message-bubble '+type;
    d.textContent=text;
    chatArea.appendChild(d);
    chatArea.scrollTop=chatArea.scrollHeight;
}
function showTyping(){
    var d=document.createElement('div');
    d.className='message-bubble bot'; d.id='typingBubble';
    d.innerHTML='<div class="typing-dots"><span></span><span></span><span></span></div>';
    chatArea.appendChild(d);
    chatArea.scrollTop=chatArea.scrollHeight;
}
function hideTyping(){ var t=document.getElementById('typingBubble'); if(t)t.remove(); }

/* ── MORPH ── */
function setM(mesh,idx,val){
    if(!mesh||!mesh.morphTargetInfluences) return;
    if(idx>=0&&idx<mesh.morphTargetInfluences.length)
        mesh.morphTargetInfluences[idx]=Math.max(0,Math.min(1,val));
}
function resetMorphs(){
    [headMesh,teethMesh,tongueMesh].forEach(function(m){
        if(m&&m.morphTargetInfluences)
            for(var i=0;i<m.morphTargetInfluences.length;i++) m.morphTargetInfluences[i]=0;
    });
    if(headMesh) setM(headMesh,M.mouthSmile,0.12);
}
function startLipSync(){
    clearInterval(lipIv); vIdx=0;
    lipIv=setInterval(function(){
        if(!isSpeaking){ clearInterval(lipIv); resetMorphs(); return; }
        resetMorphs();
        var v=VISEMES[vIdx%VISEMES.length];
        var w=v.w*(0.7+Math.random()*0.3);
        setM(headMesh,M[v.k],w);
        setM(headMesh,M.jawOpen,w*0.55);
        if(teethMesh) setM(teethMesh,M[v.k]||0,w*0.8);
        if(tongueMesh) setM(tongueMesh,0,w*0.3);
        vIdx++;
    },110);
}
function stopLipSync(){ clearInterval(lipIv); resetMorphs(); }
function startBlink(){
    function doBlink(){
        if(!headMesh){ blinkIv=setTimeout(doBlink,4000); return; }
        setM(headMesh,M.eyeBlinkL,1); setM(headMesh,M.eyeBlinkR,1);
        setTimeout(function(){
            setM(headMesh,M.eyeBlinkL,0); setM(headMesh,M.eyeBlinkR,0);
            blinkIv=setTimeout(doBlink,3000+Math.random()*3000);
        },130);
    }
    blinkIv=setTimeout(doBlink,2000);
}

/* ── THREE.JS ── */
function initThree(){
    if(typeof THREE==='undefined'||typeof THREE.GLTFLoader==='undefined'){
        if(loaderDiv) loaderDiv.style.display='none'; return;
    }
    var canvas=document.getElementById('avatar-canvas');
    if(!canvas) return;
    var W=canvas.clientWidth||300, H=canvas.clientHeight||400;
    scene=new THREE.Scene(); scene.background=new THREE.Color(0x1e293b);
    camera=new THREE.PerspectiveCamera(35,W/H,0.1,100);
    camera.position.set(0,1.6,2.0); camera.lookAt(0,1.55,0);
    renderer=new THREE.WebGLRenderer({canvas:canvas,antialias:true});
    renderer.setSize(W,H); renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    renderer.outputEncoding=THREE.sRGBEncoding;
    renderer.toneMapping=THREE.ACESFilmicToneMapping; renderer.toneMappingExposure=1.2;
    scene.add(new THREE.AmbientLight(0xffffff,0.8));
    var key=new THREE.DirectionalLight(0xffffff,1.2); key.position.set(1.5,3,2); scene.add(key);
    var fill=new THREE.DirectionalLight(0xffffff,0.6); fill.position.set(-2,1,1); scene.add(fill);
    clock=new THREE.Clock();
    var loader=new THREE.GLTFLoader();
    loader.load('model.glb',
        function(gltf){
            avatarRoot=gltf.scene;
            var box=new THREE.Box3().setFromObject(avatarRoot);
            var size=box.getSize(new THREE.Vector3());
            avatarRoot.position.sub(box.getCenter(new THREE.Vector3()));
            avatarRoot.position.y+=size.y*0.5;
            scene.add(avatarRoot);
            avatarRoot.traverse(function(o){
                if(o.isMesh&&o.morphTargetInfluences){
                    if(o.name==='Head_Mesh')   headMesh=o;
                    if(o.name==='Teeth_Mesh')  teethMesh=o;
                    if(o.name==='Tongue_Mesh') tongueMesh=o;
                }
            });
            if(gltf.animations.length>0){
                mixer=new THREE.AnimationMixer(avatarRoot);
                mixer.clipAction(gltf.animations[0]).play();
            }
            if(loaderDiv) loaderDiv.style.display='none';
            setTimeout(function(){ if(headMesh) setM(headMesh,M.mouthSmile,0.12); },600);
            startBlink();
        },
        function(xhr){
            var pct=xhr.total?Math.round(xhr.loaded/xhr.total*100):0;
            if(loaderDiv) loaderDiv.innerHTML='<div style="width:36px;height:36px;border:3px solid rgba(255,255,255,0.1);border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 10px;"></div><span style="font-size:11px;color:#94a3b8;">'+pct+'%</span>';
        },
        function(){ if(loaderDiv) loaderDiv.style.display='none'; }
    );
}

function animate(){
    requestAnimationFrame(animate);
    var d=clock?clock.getDelta():0.016;
    if(mixer) mixer.update(d);
    if(renderer&&scene&&camera) renderer.render(scene,camera);
}

/* ── AUDIO ── */
var audioCtx=null, audioUnlocked=false;
function unlockAudio(){
    if(audioUnlocked) return;
    try{
        audioCtx=new(window.AudioContext||window.webkitAudioContext)();
        var buf=audioCtx.createBuffer(1,1,22050);
        var src=audioCtx.createBufferSource();
        src.buffer=buf; src.connect(audioCtx.destination); src.start(0);
        audioUnlocked=true;
    }catch(e){}
}

function playAudio(b64){
    if(currentAudio){ currentAudio.pause(); currentAudio=null; }
    stopLipSync(); isSpeaking=false;
    try{
        var bin=atob(b64);
        var buf=new Uint8Array(bin.length);
        for(var i=0;i<bin.length;i++) buf[i]=bin.charCodeAt(i);
        var url=URL.createObjectURL(new Blob([buf],{type:'audio/mpeg'}));
        var audio=new Audio(url); audio.volume=1.0; currentAudio=audio;
        audio.onended=function(){ isSpeaking=false; stopLipSync(); setStatus('','En attente'); URL.revokeObjectURL(url); };
        function onPlay(){ isSpeaking=true; setStatus('speaking','En train de répondre'); startLipSync(); }
        function showPlayBtn(){
            var old=document.getElementById('play-btn'); if(old) old.remove();
            var btn=document.createElement('button');
            btn.id='play-btn'; btn.textContent='▶ Écouter la réponse';
            btn.style.cssText='display:block;margin-top:8px;padding:9px 18px;background:#6366f1;color:white;border:none;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;width:100%;';
            btn.addEventListener('click',function(){ btn.remove(); unlockAudio(); audio.play().then(onPlay).catch(function(e){console.error(e);}); });
            chatArea.appendChild(btn); chatArea.scrollTop=chatArea.scrollHeight;
        }
        if(audioCtx&&audioCtx.state==='suspended'){
            audioCtx.resume().then(function(){ audio.play().then(onPlay).catch(function(e){ showPlayBtn(); }); });
        } else {
            audio.play().then(onPlay).catch(function(e){ showPlayBtn(); });
        }
    } catch(e){ console.error(e); }
}

/* ── CHAT ── */
async function sendMessage(txt){
    var msg=txt||chatInput.value.trim();
    if(!msg||isThinking) return;
    addMessage(msg,'user');
    chatInput.value='';
    isThinking=true;
    setStatus('thinking','Analyse en cours');
    showTyping();
    try{
        var res=await fetch('chat_handler.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({message:msg})
        });
        var data=await res.json();
        hideTyping();
        addMessage(data.text,'bot');
        if(data.audio_base64&&data.audio_base64.length>50){
            playAudio(data.audio_base64);
        } else {
            setStatus('','En attente');
        }
    } catch(e){
        hideTyping();
        addMessage('Erreur de connexion. Réessayez.','bot');
        setStatus('','Erreur');
    } finally { isThinking=false; }
}

/* ── MIC ── */
var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
function toggleMic(){
    if(!SR){ alert('Reconnaissance vocale non supportée. Utilisez Chrome ou Safari.'); return; }
    if(isListening){ recognition.stop(); return; }
    recognition=new SR(); recognition.lang='fr-FR';
    recognition.onstart=function(){ isListening=true; micBtn.classList.add('active'); setStatus('listening','Je vous écoute'); };
    recognition.onresult=function(e){ sendMessage(e.results[0][0].transcript); };
    recognition.onend=function(){ isListening=false; micBtn.classList.remove('active'); if(!isThinking&&!isSpeaking) setStatus('','En attente'); };
    recognition.start();
}

/* ── INIT ── */
window.addEventListener('load',function(){
    initThree();
    animate();
    sendBtn.addEventListener('click',function(){ sendMessage(); });
    chatInput.addEventListener('keydown',function(e){ if(e.key==='Enter') sendMessage(); });
    micBtn.addEventListener('click',function(){ toggleMic(); });
    document.querySelectorAll('.suggestion-chip').forEach(function(chip){
        chip.addEventListener('click',function(){ sendMessage(chip.getAttribute('data-ask')); });
    });
    document.addEventListener('click',    unlockAudio,{once:true});
    document.addEventListener('keydown',  unlockAudio,{once:true});
    document.addEventListener('touchend', unlockAudio,{once:true});

    /* Drag avatar */
    var canvas=document.getElementById('avatar-canvas');
    if(canvas){
        var drag=false, prevX=0;
        canvas.addEventListener('mousedown', function(e){ drag=true; prevX=e.clientX; });
        window.addEventListener('mouseup',   function(){ drag=false; });
        window.addEventListener('mousemove', function(e){ if(drag&&avatarRoot){ avatarRoot.rotation.y+=(e.clientX-prevX)*0.01; } prevX=e.clientX; });
        canvas.addEventListener('touchstart',function(e){ drag=true; prevX=e.touches[0].clientX; },{passive:true});
        window.addEventListener('touchend',  function(){ drag=false; });
        window.addEventListener('touchmove', function(e){ if(drag&&avatarRoot){ avatarRoot.rotation.y+=(e.touches[0].clientX-prevX)*0.01; } prevX=e.touches[0].clientX; },{passive:true});
    }
});
</script>
</body>
</html>