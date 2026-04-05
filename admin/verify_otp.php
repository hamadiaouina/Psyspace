<?php
// --- 1. SÉCURITÉ : CONFIGURATION DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . "/../connection.php"; 

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
        $user_otp = trim($_POST['otp']); 

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

            // 🛡️ SÉCURITÉ CRITIQUE : Anti-fixation de session
            session_regenerate_id(true);

            // ON ACTIVE LA SESSION FINALE
            $_SESSION['admin_id'] = $admin['admid']; 
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role'] = 'admin';
            
            // Renouvellement du jeton CSRF post-login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Nettoyage des variables temporaires
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['otp_attempts']);

            header("Location: dashboard_admin.php");
            exit();
            
        } else {
            // ❌ MAUVAIS CODE
            $_SESSION['otp_attempts']++;
            $restant = 3 - $_SESSION['otp_attempts'];
            
            if ($restant <= 0) {
                // BLOCAGE DÉFINITIF
                $stmt_lock = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
                $stmt_lock->bind_param("i", $admin_id);
                $stmt_lock->execute();

                session_destroy();
                header("Location: ../index.php?error=security_lock");
                exit();
            } else {
                $error = "Code incorrect. Il vous reste $restant tentative(s) avant le blocage de sécurité.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de sécurité | PsySpace</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } }
        };
    </script>
</head>
<body class="bg-slate-950 text-white flex justify-center items-center min-h-screen font-sans selection:bg-indigo-500 selection:text-white">

    <div class="w-full max-w-md p-8 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl relative overflow-hidden">
        
        <!-- Décoration de la carte -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 to-rose-500"></div>

        <div class="text-center mb-8">
            <div class="w-14 h-14 mx-auto bg-indigo-500/10 text-indigo-500 rounded-2xl flex items-center justify-center mb-5 border border-indigo-500/20 shadow-inner">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Authentification 2FA</h2>
            <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                Un code de sécurité à 6 chiffres a été envoyé à votre adresse e-mail. Veuillez le saisir ci-dessous.
            </p>
        </div>

        <?php if($error): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl text-sm font-medium flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- SÉCURITÉ : JETON CSRF -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            
            <div>
                <input type="text" name="otp" placeholder="••••••" maxlength="6" required autocomplete="off" autofocus 
                       class="w-full px-4 py-4 bg-slate-950 border border-slate-800 rounded-xl text-center text-3xl font-bold tracking-[0.5em] text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all placeholder:text-slate-700"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            
            <button type="submit" 
                    class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl transition-all shadow-lg hover:shadow-indigo-500/25 flex justify-center items-center gap-2">
                <span>Confirmer l'accès</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
        </form>
    </div>

</body>
</html>