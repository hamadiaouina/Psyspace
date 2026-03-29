<?php
session_start();
include "../connection.php"; 

if (!isset($con)) { $con = $conn ?? null; }

// 1. On vérifie si une tentative de connexion est en cours
if (!isset($_SESSION['temp_admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = "";

// 2. Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_SESSION['temp_admin_id'];
    $user_otp = trim($_POST['otp']); 

    $stmt = $con->prepare("SELECT * FROM admin WHERE admid = ? AND otp_code = ?");
    $stmt->bind_param("is", $admin_id, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        // On nettoie l'OTP
        $update_stmt = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
        $update_stmt->bind_param("i", $admin_id);
        
        if ($update_stmt->execute()) {
            // ON ACTIVE LA SESSION FINALE
            $_SESSION['admin_id'] = $admin['admid']; 
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role'] = 'admin';

            unset($_SESSION['temp_admin_id']);

            header("Location: dashboard_admin.php");
            exit();
        }
    } else {
        $error = "Code incorrect. Veuillez vérifier vos emails.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <title>Vérification de sécurité | PsySpace</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 12px; width: 100%; max-width: 350px; border: 1px solid #334155; text-align: center; }
        h2 { color: #4f46e5; margin-bottom: 10px; }
        p { color: #94a3b8; font-size: 14px; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; text-align: center; font-size: 24px; letter-spacing: 5px; }
        button { width: 100%; background: #4f46e5; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .error { color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Vérification</h2>
        <p>Entrez le code à 6 chiffres envoyé à votre adresse email.</p>

        <?php if($error) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <input type="text" name="otp" placeholder="000000" maxlength="6" required autocomplete="off">
            <button type="submit">Confirmer l'accès</button>
        </form>
    </div>
</body>
</html>