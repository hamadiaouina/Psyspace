<?php
session_start();
include "../connection.php";

// Utilise $con (comme dans ton login_action.php existant)
if (!isset($con)) { $con = $conn ?? null; }
if (!$con) { header("Location: login.php?error=invalid"); exit(); }

// Seul POST autorisé
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// 1. Nettoyage
$email    = mysqli_real_escape_string($con, trim($_POST['email']   ?? ''));
$password = trim($_POST['password'] ?? '');

if (!$email || !$password) {
    header("Location: login.php?error=invalid");
    exit();
}

// 2. Recherche dans la table admin
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if (!$result || $result->num_rows === 0) {
    header("Location: login.php?error=noaccount");
    exit();
}

$admin = $result->fetch_assoc();

// 3. Vérification mot de passe (bcrypt)
if (!password_verify($password, $admin['admpassword'])) {
    header("Location: login.php?error=wrongpw&email=" . urlencode($email));
    exit();
}

// 4. ✅ SUCCÈS — session admin isolée des sessions doctor
$_SESSION['admin_id']    = $admin['admid'];
$_SESSION['admin_name']  = $admin['admname'];
$_SESSION['admin_email'] = $admin['admemail'];
$_SESSION['role']        = 'admin';

header("Location: dashboard.php");
exit();
?>