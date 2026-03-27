<?php
session_start();
// On utilise ton nouveau fichier centralisé
require_once 'config/db.php'; 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

/**
 * --- 1) Validation Cloudflare Turnstile ---
 */
$turnstileSecret = getenv('TURNSTILE_SECRET');
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (!$turnstileSecret || empty($turnstileToken)) {
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
 * --- 2) Inputs sécurisés ---
 */
$email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

try {
    /**
     * --- 3) Test DOCTOR (Requête Préparée PDO) ---
     * Note : J'utilise 'doctor' (minuscule), c'est la norme SQL. 
     * Si ta table s'appelle autrement, change juste le nom ici.
     */
    $stmtDoc = $pdo->prepare("SELECT docid, docname, docpassword FROM doctor WHERE docemail = ? LIMIT 1");
    $stmtDoc->execute([$email]);
    $doctor = $stmtDoc->fetch();

    if ($doctor && password_verify($password, $doctor['docpassword'])) {
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        header("Location: welcome.php");
        exit();
    }

    /**
     * --- 4) Test ADMIN (Requête Préparée PDO) ---
     */
    $stmtAdmin = $pdo->prepare("SELECT admid, admpassword FROM admin WHERE admemail = ? LIMIT 1");
    $stmtAdmin->execute([$email]);
    $admin = $stmtAdmin->fetch();

    if ($admin && password_verify($password, $admin['admpassword'])) {
        $_SESSION['admin_id'] = $admin['admid'];
        $_SESSION['role'] = 'admin';
        header("Location: admin/dashboard.php");
        exit();
    }

    // Si on arrive ici, c'est que rien n'a matché
    header("Location: login.php?error=wrongpw");
    exit();

} catch (PDOException $e) {
    // Erreur de base de données : on log pour nous, on cache pour l'utilisateur
    error_log("Erreur Auth : " . $e->getMessage());
    header("Location: login.php?error=server");
    exit();
}