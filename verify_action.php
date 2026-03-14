<?php
session_start();
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Récupération sécurisée des données
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $user_otp = mysqli_real_escape_string($con, $_POST['otp']);

    // 2. Vérification du code OTP dans la base de données
    // On cherche un docteur avec cet email ET ce code OTP
    $sql = "SELECT * FROM doctor WHERE docemail = '$email' AND otp_code = '$user_otp'";
    $result = $con->query($sql);

    if ($result->num_rows > 0) {
        // --- CAS : CODE CORRECT ---
        $user = $result->fetch_assoc();

        // 3. Mise à jour du statut : On active le compte et on efface le code OTP utilisé
        $update_sql = "UPDATE doctor SET status = 'active', otp_code = NULL WHERE docemail = '$email'";
        
        if ($con->query($update_sql) === TRUE) {
            // 4. Initialisation de la session utilisateur
            $_SESSION['id'] = $user['docid']; // Assure-toi que le nom de la colonne est bien 'docid'
            $_SESSION['nom'] = $user['docname'];
            $_SESSION['email'] = $user['docemail'];

            // 5. Redirection vers la page de bienvenue archi-pro
            header("Location: welcome.php");
            exit();
        } else {
            // Erreur technique rare
            header("Location: verify.php?email=" . urlencode($email) . "&error=dbfail");
            exit();
        }

    } else {
        // --- CAS : CODE INCORRECT (OU EXPIRE) ---
        // On renvoie vers verify.php avec un paramètre error
        // On ne met PAS d'alert() ici pour rester professionnel
        header("Location: verify.php?email=" . urlencode($email) . "&error=wrongotp");
        exit();
    }
} else {
    // Si quelqu'un tente d'accéder au fichier directement sans POST
    header("Location: register.php");
    exit();
}
?>