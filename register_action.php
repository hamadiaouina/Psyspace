<?php
// 1. Inclusion des fichiers nécessaires
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2. Récupération et nettoyage des données
    $nom = mysqli_real_escape_string($con, $_POST['nom']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $otp = rand(100000, 999999);

    // 3. Vérification si l'email existe déjà
    $checkEmail = $con->query("SELECT docemail FROM doctor WHERE docemail = '$email'");
    
    if ($checkEmail->num_rows > 0) {
        // --- MODIFICATION ICI : ZERO ALERT ---
        header("Location: register.php?error=emailexist");
        exit(); 
    }

    // 4. Insertion dans la base de données
    $sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, status) 
            VALUES ('$email', '$nom', '$hashed_password', '$otp', 'pending')";

    if ($con->query($sql) === TRUE) {
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'psyspace.all@gmail.com';
            $mail->Password   = 'lszg gkpz ylbg ypdt'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('no-reply@psyspace.ai', 'PsySpace AI');
            $mail->addAddress($email, $nom);

            $mail->isHTML(true);
            $mail->Subject = "Votre code de validation PsySpace";
            $mail->Body = "
            <div style='background-color: #f8fafc; padding: 40px 0; font-family: sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0;'>
                    <div style='background-color: #2563eb; padding: 30px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;'>PsySpace AI</h1>
                    </div>
                    <div style='padding: 40px; text-align: center;'>
                        <h2 style='color: #1e293b; font-size: 20px;'>Vérifiez votre adresse email</h2>
                        <p style='color: #64748b;'>Bonjour <strong>$nom</strong>, utilisez le code ci-dessous :</p>
                        <div style='background-color: #f1f5f9; border-radius: 12px; padding: 25px; margin: 20px 0;'>
                            <span style='font-family: monospace; font-size: 36px; font-weight: bold; letter-spacing: 12px; color: #2563eb;'>$otp</span>
                        </div>
                    </div>
                </div>
            </div>";

            $mail->send();
            
            header("Location: verify.php?email=" . urlencode($email));
            exit();

        } catch (Exception $e) {
            // Optionnel : rediriger avec une erreur mail si ça échoue
            header("Location: register.php?error=mailfail");
            exit();
        }

    } else {
        header("Location: register.php?error=dbfail");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>