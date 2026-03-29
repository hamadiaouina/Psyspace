<?php
session_start();
include "../connection.php";

// 1. On s'assure que la connexion à la base de données est là
if (!isset($con) && isset($conn)) { $con = $conn; }

// 2. SÉCURITÉ : Si l'admin n'est pas connecté, on le redirige proprement
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    // On force le chemin vers ton fichier login.php qui est dans le dossier admin
    header("Location: ./login.php"); 
    exit();
}

$admin_name    = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// --- ICI COMMENCE TON CODE HTML DU DASHBOARD ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PsySpace | Dashboard Admin</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; margin: 20px; }
        .welcome-card { background: #1e293b; padding: 30px; border-radius: 15px; border: 1px solid #334155; }
        h1 { color: #4f46e5; }
    </style>
</head>
<body>
    <div class="welcome-card">
        <h1>Bienvenue, <?php echo $admin_name; ?> !</h1>
        <p>Le dashboard de ton PFE est maintenant opérationnel et sécurisé.</p>
        <hr style="border: 0; border-top: 1px solid #334155; margin: 20px 0;">
        <p>Tu peux maintenant ajouter tes tableaux de docteurs et de statistiques ici.</p>
    </div>
</body>
</html>