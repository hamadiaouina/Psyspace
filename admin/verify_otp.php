<?php
session_start();
include "../connection.php"; // On remonte d'un dossier pour trouver la connexion

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // On récupère l'ID de l'admin qui est stocké temporairement dans la session
    if (!isset($_SESSION['temp_admin_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $admin_id = $_SESSION['temp_admin_id'];
    $user_otp = $_POST['otp']; // Le code tapé par l'admin

    // 1. On vérifie si le code correspond à cet admin dans la table 'admin'
    // Note : On utilise 'admid' car c'est le nom dans ton fichier SQL
    $stmt = $con->prepare("SELECT * FROM admin WHERE admid = ? AND otp_code = ?");
    $stmt->bind_param("is", $admin_id, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        // 2. Code correct ! On nettoie l'OTP et on active la VRAIE session
        $update_stmt = $con->prepare("UPDATE admin SET otp_code = NULL WHERE admid = ?");
        $update_stmt->bind_param("i", $admin_id);
        
        if ($update_stmt->execute()) {
            // 3. Initialisation des sessions pour dashboard.php
            $_SESSION['admin_id'] = $admin['admid']; 
            $_SESSION['admin_name'] = $admin['admname'];
            $_SESSION['role'] = 'admin';

            // On supprime la session temporaire
            unset($_SESSION['temp_admin_id']);

            // 4. Redirection vers TON dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: verify_otp.php?error=dbfail");
            exit();
        }

    } else {
        // Code erroné
        header("Location: verify_otp.php?error=wrongotp");
        exit();
    }
} else {
    // Si on essaie d'accéder au fichier sans POST, on affiche le formulaire
    // (Tu peux mettre ton code HTML ici ou faire un include)
}
?>