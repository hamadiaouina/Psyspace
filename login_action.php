<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.cookie_secure', '1');   
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once 'config/db.php'; 

// Sécurité : Uniquement du POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// --- 2. ANTI BRUTE-FORCE (Verrouillage après 5 échecs) ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if ($_SESSION['login_attempts'] >= 5) {
    $time_locked = time() - $_SESSION['last_attempt_time'];
    if ($time_locked < 300) { // Bloqué pendant 5 minutes (300 secondes)
        header("Location: login.php?error=bruteforce");
        exit();
    } else {
        // Le temps est écoulé, on remet le compteur à zéro
        $_SESSION['login_attempts'] = 0; 
    }
}

/**
 * --- 3. VALIDATION CLOUDFLARE TURNSTILE ---
 */
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: 'TA_CLE_SECRETE_DE_TEST';
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (empty($turnstileToken)) {
    header("Location: login.php?error=captcha");
    exit();
}

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret'   => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR']
]));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response ?: '', true);
if (!($data['success'] ?? false)) {
    header("Location: login.php?error=captcha");
    exit();
}

/**
 * --- 4. NETTOYAGE ET RÉCUPÉRATION ---
 */
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

// On sauvegarde l'email en session pour pré-remplir le formulaire en cas d'erreur
// C'est beaucoup plus sécurisé que de le passer dans l'URL (?email=...)
$_SESSION['login_email_attempt'] = $email;

try {
    /**
     * --- 5. TEST DOCTOR UNIQUEMENT ---
     */
    $stmtDoc = $pdo->prepare("SELECT docid, docname, docpassword, status FROM doctor WHERE docemail = ? LIMIT 1");
    $stmtDoc->execute([$email]);
    $doctor = $stmtDoc->fetch();

    if ($doctor && password_verify($password, $doctor['docpassword'])) {
        
        // Vérification du statut
        if ($doctor['status'] === 'suspended') {
            header("Location: login.php?error=suspended");
            exit();
        }
        if ($doctor['status'] === 'pending') {
            header("Location: login.php?error=pending");
            exit();
        }

        // ==========================================
        // ✅ SUCCÈS : CONNEXION SÉCURISÉE
        // ==========================================
        
        $_SESSION['login_attempts'] = 0; // Reset du Brute-Force
        unset($_SESSION['login_email_attempt']); // On nettoie l'email mémorisé
        
        // Change l'ID de session (Anti Session-Fixation)
        session_regenerate_id(true);
        
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['last_login'] = time(); 
        
        // EMPREINTE DIGITALE (Anti Session-Hijacking)
        // On lie la session à l'adresse IP et au navigateur
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        header("Location: welcome.php");
        exit();

    } else {
        // ==========================================
        // ❌ ÉCHEC : MAUVAIS EMAIL OU MOT DE PASSE
        // ==========================================
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();

        // On renvoie juste 'wrongpw' sans l'email dans l'URL
        header("Location: login.php?error=wrongpw");
        exit();
    }

} catch (PDOException $e) {
    error_log("Erreur PsySpace Auth : " . $e->getMessage());
    header("Location: login.php?error=server");
    exit();
}