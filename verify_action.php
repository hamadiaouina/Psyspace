<?php
session_start();
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $user_otp = trim($_POST['otp']);

    // 1. On vérifie l'OTP ET si le compte n'a pas expiré (moins de 5 min)
    // On joint la condition de temps directement ici pour être super pro
    $stmt = $con->prepare("SELECT * FROM doctor WHERE docemail = ? AND otp_code = ? AND status = 'pending' AND created_at >= NOW() - INTERVAL 5 MINUTE");
    $stmt->bind_param("ss", $email, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // 2. Mise à jour du statut (On active le compte et on vide l'OTP)
        $update_stmt = $con->prepare("UPDATE doctor SET status = 'active', otp_code = NULL WHERE docemail = ?");
        $update_stmt->bind_param("s", $email);
        
        if ($update_stmt->execute()) {
            // 3. Initialisation de la session
            // Dans ton SQL, c'est bien 'docid', donc on garde ça
            $_SESSION['user'] = $user['docemail']; 
            $_SESSION['typetuser'] = 'doctor'; // Utile si tu as des patients plus tard
            $_SESSION['docname'] = $user['docname'];
            $_SESSION['docid'] = $user['docid'];

            header("Location: dashboard.php"); // Ou welcome.php selon ton flux
            exit();
        } else {
            header("Location: verify.php?email=" . urlencode($email) . "&error=dbfail");
            exit();
        }

    } else {
        // Si on ne trouve rien, c'est soit le mauvais code, soit les 5 min sont passées
        header("Location: verify.php?email=" . urlencode($email) . "&error=expired_or_wrong");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}