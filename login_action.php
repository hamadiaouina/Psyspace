<?php
session_start();
include "connection.php";

if (!isset($con) && isset($conn)) { $con = $conn; }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email']   ?? ''));
$password = trim($_POST['password'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] == 1;

if (!$email || !$password) {
    header("Location: login.php?error=invalid");
    exit();
}

// ── 1. Cherche dans doctor ────────────────────────────────────────────────────
$result = $con->query("SELECT * FROM doctor WHERE docemail = '$email' LIMIT 1");

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if ($user['status'] !== 'active') {
        header("Location: login.php?error=notactive&email=" . urlencode($email));
        exit();
    }

    if (password_verify($password, $user['docpassword'])) {
        $_SESSION['id']    = $user['docid'];
        $_SESSION['nom']   = $user['docname'];
        $_SESSION['email'] = $user['docemail'];
        $_SESSION['role']  = 'doctor';

        // ── Rester connecté ───────────────────────────────────────────────────
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 jours

            // Sauvegarder le token en BDD
            $con->query("UPDATE doctor SET remember_token = '$token', token_expiry = '$expiry' WHERE docid = '{$user['docid']}'");

            // Poser le cookie
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
        }
        // ─────────────────────────────────────────────────────────────────────

        header("Location: welcome.php");
        exit();
    } else {
        header("Location: login.php?error=wrongpw&email=" . urlencode($email));
        exit();
    }
}

// ── 2. Cherche dans admin ─────────────────────────────────────────────────────
$result2 = $con->query("SELECT * FROM admin WHERE admemail = '$email' LIMIT 1");

if ($result2 && $result2->num_rows > 0) {
    $admin = $result2->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {
        $_SESSION['admin_id']    = $admin['admid'];
        $_SESSION['admin_name']  = $admin['admname'];
        $_SESSION['admin_email'] = $admin['admemail'];
        $_SESSION['role']        = 'admin';
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=wrongpw&email=" . urlencode($email));
        exit();
    }
}

// ── 3. Introuvable ────────────────────────────────────────────────────────────
header("Location: login.php?error=noaccount");
exit();
?>