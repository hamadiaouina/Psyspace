<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Utilisation de chemins absolus pour être sûr à 100%
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Récupération des données (On ne nettoie plus ici, on utilisera bind_param)
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $dob = $_POST['dob'];
    $password = $_POST['password'];
    
    // On combine Nom et Prénom pour la base si nécessaire, ou on sépare les colonnes
    $fullName = $prenom . " " . $nom;
    $hashed_password = password_hash($password, PASSWORD_ARGON2ID); // Plus sécurisé que DEFAULT
    $otp = rand(100000, 999999);

    // 2. Vérification si l'email existe déjà (Requête Préparée)
    $stmt = $con->prepare("SELECT docemail FROM doctor WHERE docemail = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        header("Location: register.php?error=emailexist");
        exit(); 
    }

    // 3. Insertion dans la base de données (Requête Préparée)
    // Note : Assure-toi que ta table a bien les colonnes : docemail, docname, docpassword, otp_code, status, dob
    $sql = "INSERT INTO doctor (docemail, docname, docpassword, otp_code, status, dob) VALUES (?, ?, ?, ?, 'pending', ?)";
    $insertStmt = $con->prepare($sql);
    $insertStmt->bind_param("sssis", $email, $fullName, $hashed_password, $otp, $dob);

    if ($insertStmt->execute()) {
        $mail = new PHPMailer(true);

        try {
            // Utilisation des variables d'environnement pour la sécurité
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('MAIL_USER') ?: 'psyspace.all@gmail.com'; 
            $mail->Password   = getenv('MAIL_PASS') ?: 'lszg gkpz ylbg ypdt'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('no-reply@psyspace.ai', 'PsySpace AI');
            $mail->addAddress($email, $fullName);

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
                        <p style='color: #64748b;'>Bonjour <strong>$fullName</strong>, utilisez le code ci-dessous pour finaliser votre inscription :</p>
                        <div style='background-color: #f1f5f9; border-radius: 12px; padding: 25px; margin: 20px 0;'>
                            <span style='font-family: monospace; font-size: 36px; font-weight: bold; letter-spacing: 12px; color: #2563eb;'>$otp</span>
                        </div>
                        <p style='color: #94a3b8; font-size: 12px;'>Ce code expirera dans 10 minutes.</p>
                    </div>
                </div>
            </div>";

            $mail->send();
            
            header("Location: verify.php?email=" . urlencode($email));
            exit();

        } catch (Exception $e) {
            header("Location: register.php?error=mailfail");
            exit();
        }

    } else {
        header("Location: register.php?error=dbfail");
        exit();
    }
}