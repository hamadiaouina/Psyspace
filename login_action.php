<?php
session_start();
include "connection.php";

// Synchronisation des variables de connexion
if (isset($conn) && !isset($con)) { $con = $conn; }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

/**
 * --- 0) Validation Cloudflare Turnstile ---
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
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response ?: '', true);
if (!($data['success'] ?? false)) {
    header("Location: login.php?error=captcha");
    exit();
}

/**
 * --- 1) Inputs & Anti-Injection ---
 */
$email    = mysqli_real_escape_string($con, trim($_POST['email']   ?? ''));
$password = trim($_POST['password'] ?? '');

/**
 * --- 2) Authentification ---
 */

// --- TEST DOCTOR (On essaie toutes les combinaisons de casse) ---
$tables_to_test = ['doctor', 'Doctor', 'DOCTORS', 'doctor_table'];
$user = null;

foreach ($tables_to_test as $table) {
    try {
        $res = $con->query("SELECT * FROM `$table` WHERE docemail = '$email' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            break; // Table trouvée et utilisateur existant !
        }
    } catch (Exception $e) {
        continue; // Cette table n'existe pas, on tente la suivante
    }
}

if ($user) {
    if (password_verify($password, $user['docpassword'])) {
        $_SESSION['id'] = $user['docid'];
        $_SESSION['nom'] = $user['docname'];
        $_SESSION['role'] = 'doctor';
        header("Location: welcome.php");
        exit();
    }
}

// --- TEST ADMIN ---
$admin_tables = ['admin', 'Admin', 'ADMIN'];
$admin = null;

foreach ($admin_tables as $t_admin) {
    try {
        $res2 = $con->query("SELECT * FROM `$t_admin` WHERE admemail = '$email' LIMIT 1");
        if ($res2 && $res2->num_rows > 0) {
            $admin = $res2->fetch_assoc();
            break;
        }
    } catch (Exception $e) {
        continue;
    }
}

if ($admin) {
    if (password_verify($password, $admin['admpassword'])) {
        $_SESSION['admin_id'] = $admin['admid'];
        $_SESSION['role'] = 'admin';
        header("Location: admin/dashboard.php");
        exit();
    }
}

header("Location: login.php?error=wrongpw");
exit();