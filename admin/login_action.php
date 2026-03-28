<?php
session_start();
include "../connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Chemins blindés pour Azure (on remonte d'un dossier pour trouver vendor)
require __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

if (!isset($con)) { $con = $conn ?? null; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

// 2. Recherche de l'admin
$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // 3. Vérification du mot de passe
    if (password_verify($password, $admin['admpassword'])) {
        
        // --- BLOC TEST MAIL (On force l'affichage pour comprendre) ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // On utilise le compte psyspace.all pour l'envoi (celui qui marche)
            $mail->Username   = 'psyspace.all@gmail.com'; 
            $mail->Password   = 'lszg gkpz ylbg ypdt'; 
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Expéditeur et Destinataire
            $mail->setFrom('psyspace.all@gmail.com', 'PsySpace Shield');
            $mail->addAddress('admin.psyspace@gmail.com'); 

            $mail->isHTML(true);
            $mail->Subject = "⚠️ ALERTE CONNEXION ADMIN : " . $admin['admname'];
            $mail->Body    = "Tentative de connexion reussie pour l'admin : <b>" . $admin['admname'] . "</b>";

            // ON TENTE L'ENVOI ET ON COMMUNIQUE LE RÉSULTAT
            if($mail->send()) {
                echo "<h2 style='color:green;'>✅ LE MAIL EST PARTI !</h2>";
                echo "<p>Verifie tes SPAMS sur <b>admin.psyspace@gmail.com</b>.</p>";
            }

        } catch (Exception $e) {
            echo "<h2 style='color:red;'>❌ ERREUR PHPMailer :</h2>";
            echo "<p>" . $mail->ErrorInfo . "</p>";
        }

        // --- ON ARRÊTE TOUT ICI POUR QUE TU PUISSES LIRE LE MESSAGE ---
        echo "<hr><p>Clique ici pour continuer vers le <a href='dashboard.php'>DASHBOARD</a></p>";
        die(); 

    } else {
        header("Location: login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: login.php?error=noaccount");
    exit();
}