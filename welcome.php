<?php
// 1. Sécurité et Session
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; audio-src 'self';");

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// 2. Préparation du nom (On protège contre les failles XSS avec htmlspecialchars)
$nom_brut = $_SESSION['nom'] ?? 'Docteur';
$nom_complet = htmlspecialchars($nom_brut, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes expand { from { width: 0%; } to { width: 100%; } }
        .animate-fade-in { animation: fadeIn 0.8s ease-out forwards; }
        .animate-bar { animation: expand 2.8s ease-in-out forwards; }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex items-center justify-center overflow-hidden relative">
    
    <div class="absolute inset-0 opacity-20 pointer-events-none" 
         style="background-image: radial-gradient(#6366f1 1px, transparent 1px); background-size: 30px 30px;">
    </div>

    <audio id="welcomeSound" preload="auto">
        <source src="assets/sounds/welcome.mp3" type="audio/mpeg">
    </audio>

    <div class="text-center max-w-md px-6 relative z-10 animate-fade-in">
        
        <div class="mb-10 flex justify-center">
            <div class="w-24 h-24 bg-indigo-600 rounded-3xl shadow-2xl flex items-center justify-center p-4 transform hover:rotate-3 transition-transform duration-500 ring-8 ring-indigo-50">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="w-full h-full object-contain">
            </div>
        </div>

        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">
            Bonjour, Dr. <?= $nom_complet ?>
        </h1>
        
        <p class="text-slate-500 text-base mb-12">
            Initialisation de vos dossiers cliniques...
        </p>

        <div class="max-w-xs mx-auto">
            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden shadow-inner">
                <div class="h-full bg-indigo-600 rounded-full animate-bar shadow-[0_0_15px_rgba(79,70,229,0.5)]"></div>
            </div>
            <div class="flex items-center justify-center gap-2 mt-6">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                <p class="text-xs text-slate-400 uppercase tracking-[0.2em] font-bold">
                    Session Sécurisée
                </p>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const audio = document.getElementById("welcomeSound");
            if (audio) {
                audio.volume = 0.4;
                audio.play().catch(() => console.log("Audio en attente d'interaction"));
            }

            // Redirection après l'animation
            setTimeout(() => {
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 500);
            }, 3000);
        });
    </script>
</body>
</html>