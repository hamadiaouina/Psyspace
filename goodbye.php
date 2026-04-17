<?php
session_start();
session_destroy(); // On détruit la session PHP
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="icon" type="image/png" href="{{ asset('assets/images/logo.png') }}">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes expand {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        
        .animate-bar {
            animation: expand 2s ease-in-out forwards;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center overflow-hidden relative">
    
    <!-- Grille subtile en fond -->
    <div class="absolute top-0 left-0 w-full h-full opacity-30 pointer-events-none" style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px;"></div>

    <!-- Audio de fin -->
    <audio id="goodbyeSound" preload="auto">
        <source src="assets/sounds/goodbye.mp3" type="audio/mpeg">
    </audio>

    <div class="text-center max-w-md px-4 relative z-10 animate-fade-in">
        
        <!-- LOGO : Fond Indigo pour rester cohérent avec le style "PsySpace" -->
        <div class="mb-8 flex justify-center">
            <div class="w-20 h-20 bg-indigo-600 rounded-2xl shadow-lg flex items-center justify-center p-3 ring-4 ring-indigo-100 opacity-60">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="w-full h-full object-contain drop-shadow-md">
            </div>
        </div>

        <!-- Texte d'au revoir -->
        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight mb-2">
            Déconnexion réussie
        </h1>
        
        <p class="text-slate-500 text-sm font-medium mb-10">
            Votre session a été fermée en toute sécurité.
        </p>

        <!-- Barre de progression -->
        <div class="max-w-xs mx-auto">
            <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-full bg-slate-400 rounded-full animate-bar"></div>
            </div>
            <p class="mt-4 text-xs text-slate-400 uppercase tracking-widest font-bold">
                Redirection...
            </p>
        </div>
    </div>

    <script>
        window.onload = function() {
            var audio = document.getElementById("goodbyeSound");
            if (audio) {
                audio.volume = 0.5;
                audio.play().catch(function(error) {
                    console.log("Autoplay audio bloqué par le navigateur.");
                });
            }

            // Redirection vers l'accueil après 3 secondes
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 3000);
        };
    </script>
</body>
</html>