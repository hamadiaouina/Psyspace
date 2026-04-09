<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.cookie_secure', '1');   
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();
require_once "connection.php";

// AJOUT : Inclusion de PHPMailer pour envoyer l'email de bienvenue
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// --- 2. RÉCUPÉRATION DE L'EMAIL ---
if (!isset($_SESSION['pending_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['pending_email'];
$user_otp = trim($_POST['otp'] ?? '');

// --- 3. ANTI BRUTE-FORCE SUR LE CODE OTP ---
if (!isset($_SESSION['register_otp_attempts'])) {
    $_SESSION['register_otp_attempts'] = 0;
}
$_SESSION['register_otp_attempts']++;

if ($_SESSION['register_otp_attempts'] > 5) {
    if (!isset($con)) { $con = $conn ?? null; }
    $stmt_del = $con->prepare("DELETE FROM doctor WHERE docemail = ? AND status = 'pending'");
    $stmt_del->bind_param("s", $email);
    $stmt_del->execute();
    
    unset($_SESSION['pending_email']);
    unset($_SESSION['register_otp_attempts']);
    header("Location: register.php?error=spam_protection");
    exit();
}

if (empty($user_otp)) {
    header("Location: verify.php?error=empty");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

// --- 4. VÉRIFICATION DU CODE OTP ---
$stmt = $con->prepare("SELECT docid, docname, docemail FROM doctor WHERE docemail = ? AND otp_code = ? AND status = 'pending' AND created_at >= NOW() - INTERVAL 5 MINUTE");
$stmt->bind_param("ss", $email, $user_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $doctor = $result->fetch_assoc();

    // --- 5. SUCCÈS : ACTIVATION DU COMPTE ---
    $update_stmt = $con->prepare("UPDATE doctor SET status = 'active', otp_code = NULL WHERE docemail = ?");
    $update_stmt->bind_param("s", $email);
    
    if ($update_stmt->execute()) {
        
        // =====================================================================
        // 💡 NOUVEAU : GÉNÉRATION DU CODE À 10 CARACTÈRES ET ENVOI DE L'EMAIL
        // =====================================================================
        $doc_id = $doctor['docid'];
        $fullName = $doctor['docname'];
        
        // 🔒 SÉCURITÉ MAXIMALE : Création du code Assistante unique et aléatoire
        $cabinet_code = strtoupper(bin2hex(random_bytes(5))); // Code unique et aléatoire à 10 caractères
        
        // Sauvegarde du code dans la nouvelle table assistant_access
        $stmt_assist = $con->prepare("INSERT INTO assistant_access (doctor_id, access_code) VALUES (?, ?)");
        $stmt_assist->bind_param("is", $doc_id, $cabinet_code);
        $stmt_assist->execute();
        $stmt_assist->close();

        try {
            $mail = new PHPMailer(true);
            $smtp_user = getenv('SMTP_USER') ?: 'psyspace.all@gmail.com';
            $smtp_pass = getenv('SMTP_PASS') ?: ''; 

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user; 
            $mail->Password   = $smtp_pass; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($smtp_user, 'PsySpace');
            $mail->addAddress($email, $fullName);
            $mail->isHTML(true);
            $mail->Subject = "🎉 Votre compte PsySpace est activé ! (+ Code Secrétariat)";
            
            $mail->Body = "
            <div style='font-family:sans-serif; max-width:500px; margin:0 auto; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;'>
                <div style='background:#10b981; padding:20px; text-align:center;'>
                    <h2 style='color:#ffffff; margin:0;'>Compte Activé !</h2>
                </div>
                <div style='padding:30px; background:#ffffff; text-align:center;'>
                    <p style='color:#475569; font-size:16px;'>Félicitations Dr. <b>" . htmlspecialchars($fullName) . "</b>,</p>
                    <p style='color:#475569; line-height:1.5;'>Votre compte PsySpace est désormais actif. Vous pouvez dès à présent configurer votre cabinet.</p>
                    
                    <hr style='border:none; border-top:1px solid #e2e8f0; margin:30px 0;'>
                    
                    <h3 style='color:#0f172a;'>👩‍💼 Code Accès Secrétariat</h3>
                    <p style='color:#475569; font-size:14px;'>Confiez ce code unique à 10 caractères à votre assistante pour qu'elle puisse gérer votre agenda sur la plateforme :</p>
                    
                    <div style='font-size:24px; font-weight:bold; color:#ec4899; background:#fdf2f8; padding:15px; border-radius:8px; border:2px dashed #fbcfe8; margin:20px 0; letter-spacing: 3px;'>
                        $cabinet_code
                    </div>

                    <p style='color:#94a3b8; font-size:12px; margin-top:20px;'>Vous retrouverez ce code à tout moment sur votre tableau de bord.</p>
                </div>
            </div>";

            $mail->send();
        } catch (Exception $e) {
            // Si l'email rate, ce n'est pas très grave, le compte est quand même activé
            error_log("Erreur Mail Bienvenue : " . $e->getMessage());
        }
        // =====================================================================

        // 6. INITIALISATION DE LA SESSION SÉCURISÉE 
        session_regenerate_id(true); 
        
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['last_login'] = time(); 
        
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        unset($_SESSION['pending_email']);
        unset($_SESSION['register_otp_attempts']);

        header("Location: welcome.php");
        exit();
        
    } else {
        header("Location: verify.php?error=dbfail");
        exit();
    }

} else {
    header("Location: verify.php?error=expired_or_wrong");
    exit();
}