<!DOCTYPE html>
<html lang="fr" class="bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PsySpace | Mode Hors-Ligne</title>
    <!-- On essaie de charger Tailwind depuis le cache local s'il existe -->
    <script src="assets/js/tailwind.min.js"></script>
    <style>
        /* Styles de secours natifs si Tailwind n'est pas en cache */
        body { font-family: system-ui, -apple-system, sans-serif; text-align: center; color: #1e293b; background: #f8fafc; margin: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; }
        @media (prefers-color-scheme: dark) { body { color: #f8fafc; background: #0f172a; } }
        
        /* Animation de respiration (Cohérence cardiaque) */
        .breathing-circle {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #818cf8, #4f46e5);
            border-radius: 50%;
            margin: 40px auto;
            box-shadow: 0 0 40px rgba(79, 70, 229, 0.4);
            animation: breathe 10s infinite ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }

        @keyframes breathe {
            0% { transform: scale(1); box-shadow: 0 0 20px rgba(79, 70, 229, 0.2); }
            40% { transform: scale(1.5); box-shadow: 0 0 60px rgba(79, 70, 229, 0.6); } /* Inspiration complète */
            50% { transform: scale(1.5); box-shadow: 0 0 60px rgba(79, 70, 229, 0.6); } /* Rétention */
            90% { transform: scale(1); box-shadow: 0 0 20px rgba(79, 70, 229, 0.2); } /* Expiration complète */
            100% { transform: scale(1); box-shadow: 0 0 20px rgba(79, 70, 229, 0.2); } /* Pause */
        }

        .text-breathe::before {
            content: "Inspirez...";
            animation: text-change 10s infinite;
        }

        @keyframes text-change {
            0%, 40% { content: "Inspirez..."; }
            41%, 50% { content: "Bloquez"; }
            51%, 90% { content: "Expirez..."; }
            91%, 100% { content: "Pause"; }
        }

        .btn { margin-top: 20px; padding: 12px 24px; background: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: opacity 0.3s; display: inline-block; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

    <div style="max-width: 500px; padding: 20px;">
        <img src="assets/images/logo.png" alt="PsySpace Logo" style="height: 60px; margin-bottom: 20px; border-radius: 12px;">
        
        <h1 style="font-size: 24px; margin-bottom: 10px;">Connexion perdue</h1>
        <p style="color: #64748b; font-size: 16px; line-height: 1.5; margin-bottom: 30px;">
            Il semble que vous soyez hors ligne. Prenez ce moment pour vous recentrer en suivant le cercle ci-dessous.
        </p>

        <!-- Le cercle qui grossit et rétrécit -->
        <div class="breathing-circle">
            <span class="text-breathe"></span>
        </div>

        <p style="color: #64748b; font-size: 14px; margin-top: 30px;">
            Nous reconnecterons PsySpace automatiquement dès que votre réseau sera de retour.
        </p>

        <a href="javascript:window.location.reload();" class="btn">Réessayer la connexion</a>
    </div>

</body>
</html>