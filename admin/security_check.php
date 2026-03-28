<?php
session_start();
if (!isset($_SESSION['temp_admin_id'])) { header("Location: login.php"); exit(); }

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    
    // TON CODE PIN DE SÉCURITÉ (Change-le !)
    if ($pin === "1234") { 
        $_SESSION['admin_id'] = $_SESSION['temp_admin_id'];
        unset($_SESSION['temp_admin_id']);
        $_SESSION['role'] = 'admin';
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Code PIN incorrect !";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sécurité Admin</title>
    <link rel="stylesheet" href="../css/style.css"> </head>
<body style="background:#f1f5f9; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif;">
    <form method="POST" style="background:white; padding:30px; border-radius:10px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); text-align:center;">
        <h2 style="color:#1e293b;">🔒 Vérification de sécurité</h2>
        <p style="color:#64748b;">Veuillez entrer votre code PIN administrateur</p>
        
        <?php if($error): ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <input type="password" name="pin" maxlength="4" placeholder="0000" required 
               style="font-size:24px; text-align:center; width:150px; padding:10px; border:2px solid #cbd5e1; border-radius:5px; margin-bottom:20px;">
        <br>
        <button type="submit" style="background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">Valider</button>
    </form>
</body>
</html>