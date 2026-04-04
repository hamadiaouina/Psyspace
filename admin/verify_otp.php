<?php
session_start();
require_once __DIR__ . "/../connection.php"; 

if (!isset($con)) { $con = $conn ?? null; }

// --- 1. SÉCURITÉ : VÉRIFICATION DU BADGE INVISIBLE ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}

// --- 2. SÉCURITÉ : VÉRIFICATION DE LA SESSION TEMPORAIRE ---
if (!isset($_SESSION['temp_admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['temp_admin_id'];

// --- 3. GESTION DES TENTATIVES OTP ---
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Blocage immédiat si on recharge la page après 3 tentatives
if ($_SESSION['otp_attempts'] >= 3) {
    // On détruit le code dans la base de données pour empêcher le brute-force
    $stmt_lock = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
    $stmt_lock->bind_param("i", $admin_id);
    $stmt_lock->execute();

    session_destroy(); // Destruction TOTALE de la session
    header("Location: ../index.php?error=security_lock");
    exit();
}

$error = "";

// --- 4. TRAITEMENT DU FORMULAIRE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = trim($_POST['otp']); 

    $stmt = $con->prepare("SELECT * FROM admin WHERE admid = ? AND otp_code = ?");
    $stmt->bind_param("is", $admin_id, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        // ✅ SUCCÈS : On nettoie l'OTP pour qu'il ne soit plus réutilisable
        $update_stmt = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
        $update_stmt->bind_param("i", $admin_id);
        
        if ($update_stmt->execute()) {
            // ON ACTIVE LA SESSION FINALE
            $_SESSION['admin_id'] = $admin['admid']; 
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role'] = 'admin';
            
            // On nettoie les variables temporaires
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['otp_attempts']);

            header("Location: dashboard_admin.php");
            exit();
        }
    } else {
        // ❌ MAUVAIS CODE
        $_SESSION['otp_attempts']++;
        $restant = 3 - $_SESSION['otp_attempts'];
        
        if ($restant <= 0) {
            // BLOCAGE DÉFINITIF APRÈS 3 ÉCHECS
            $stmt_lock = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
            $stmt_lock->bind_param("i", $admin_id);
            $stmt_lock->execute();

            session_destroy(); // On rase tout
            header("Location: ../index.php?error=security_lock");
            exit();
        } else {
            $error = "Code incorrect. Il vous reste $restant tentative(s) avant le blocage de sécurité.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de sécurité | PsySpace</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 12px; width: 100%; max-width: 350px; border: 1px solid #334155; text-align: center; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3); }
        h2 { color: #4f46e5; margin-bottom: 10px; }
        p { color: #94a3b8; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; text-align: center; font-size: 24px; letter-spacing: 5px; outline: none; transition: border-color 0.3s; }
        input:focus { border-color: #4f46e5; }
        button { width: 100%; background: #4f46e5; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background 0.2s; text-transform: uppercase; letter-spacing: 1px; }
        button:hover { background: #4338ca; }
        .error { color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>
    <div class="box">
        <h2>Authentification à 2 facteurs</h2>
        <p>Un code de sécurité à 6 chiffres a été envoyé à votre adresse e-mail. Veuillez le saisir ci-dessous.</p>

        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- L'attribut oninput empêche de taper autre chose que des chiffres -->
            <input type="text" name="otp" placeholder="000000" maxlength="6" required autocomplete="off" autofocus 
                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            <button type="submit">Confirmer l'accès</button>
        </form>
    </div>
</body>
</html>