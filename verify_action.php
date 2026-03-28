<?php
session_start();
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $user_otp = $_POST['otp'];

    // 1. Requête préparée pour la vérification (Sécurité maximale)
    $stmt = $con->prepare("SELECT * FROM doctor WHERE docemail = ? AND otp_code = ? AND status = 'pending'");
    $stmt->bind_param("ss", $email, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // 2. Mise à jour du statut
        $update_stmt = $con->prepare("UPDATE doctor SET status = 'active', otp_code = NULL WHERE docemail = ?");
        $update_stmt->bind_param("s", $email);
        
        if ($update_stmt->execute()) {
            // 3. Initialisation de la session
            // VÉRIFIE BIEN SI C'EST 'docid' OU 'id' DANS TA BASE
            $_SESSION['id'] = $user['docid']; 
            $_SESSION['nom'] = $user['docname'];
            $_SESSION['email'] = $user['docemail'];

            header("Location: welcome.php");
            exit();
        } else {
            header("Location: verify.php?email=" . urlencode($email) . "&error=dbfail");
            exit();
        }

    } else {
        header("Location: verify.php?email=" . urlencode($email) . "&error=wrongotp");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>