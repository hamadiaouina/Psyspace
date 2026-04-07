<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Introuvable - PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">

    <div class="text-center px-6">
        <!-- Grosse icône rassurante -->
        <div class="flex justify-center mb-8">
            <svg class="w-32 h-32 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        
        <!-- Le texte 404 -->
        <h1 class="text-9xl font-extrabold text-indigo-600 tracking-tight">404</h1>
        <p class="text-2xl font-bold text-slate-800 mt-4">Oups ! Vous vous êtes égaré.</p>
        <p class="text-slate-500 mt-2 mb-8 max-w-md mx-auto">
            La page que vous recherchez semble avoir été déplacée, supprimée, ou n'a jamais existé. Ne vous inquiétez pas, on vous ramène à la maison.
        </p>
        
        <!-- Bouton retour -->
        <a href="/index.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-200">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Retour à l'accueil
        </a>
    </div>

</body>
</html>