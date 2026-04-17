<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SÉCURITÉ : Cacher les erreurs en production
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

include "connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

$message = "";

if (isset($_POST['reset-request'])) {
    
    // --- 2. ANTI-SPAM (Rate Limiting) ---
    if (isset($_SESSION['last_reset_request']) && (time() - $_SESSION['last_reset_request']) < 60) {
        $message = "<div class='p-4 mb-6 text-orange-800 bg-orange-50 rounded-2xl border border-orange-100 font-bold text-center'>Veuillez patienter 1 minute avant de refaire une demande.</div>";
    } else {
        $_SESSION['last_reset_request'] = time();
        
        $email = trim($_POST['email']);
        
        // --- 3. REQUÊTE PRÉPARÉE (Anti-Injection SQL) ---
        $stmt = $con->prepare("SELECT docname FROM doctor WHERE docemail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // --- 4. ANTI USER ENUMERATION ---
        // On affiche TOUJOURS un message de succès, même si l'email n'existe pas.
        // Ainsi, un hacker ne peut pas savoir qui est inscrit sur la plateforme.
        $message = "<div class='p-4 mb-6 text-emerald-800 bg-emerald-50 rounded-2xl border border-emerald-100 font-bold text-center'>✓ Si cette adresse est associée à un compte praticien, un email contenant un lien de réinitialisation vient de vous être envoyé.</div>";
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $fullName = trim($row['docname']); 
            $nameParts = explode(' ', $fullName);
            $lastName = end($nameParts); 
            $drName = "Dr " . ucfirst(strtolower($lastName)); 

            // Génération d'un token ultra-sécurisé de 64 caractères
            $token = bin2hex(random_bytes(32));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
            
            $update = $con->prepare("UPDATE doctor SET reset_token=?, token_expiry=? WHERE docemail=?");
            $update->bind_param("sss", $token, $expiry, $email);
            $update->execute();
            
            // --- 5. ENVOI DE L'EMAIL ---
            $mail = new PHPMailer(true);

            try {
                // Attention: Évite de laisser ton mot de passe Gmail en dur !
                // Utilise getenv() si possible sur ton serveur de production.
$smtp_pass = getenv('SMTP_PASS');
$smtp_user = getenv('SMTP_USER');

                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
$mail->Username   = $smtp_user;
$mail->Password   = $smtp_pass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

$mail->setFrom($smtp_user, 'Sécurité PsySpace');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = "🔑 Réinitialisation de votre mot de passe - PsySpace";
                
                $url = "https://psyspace.me/reset_password.php?token=$token";

                $mail->Body = "
                <div style='background-color: #f8fafc; padding: 40px 0; font-family: sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
                        <div style='background-color: #2563eb; padding: 30px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -1px; font-style: italic; text-transform: uppercase;'>PSYSPACE</h1>
                        </div>
                        <div style='padding: 40px; text-align: center;'>
                            <h2 style='color: #1e293b; margin-bottom: 20px;'>Récupération de compte</h2>
                            <p style='color: #64748b; font-size: 16px;'>Bonjour <strong>$drName</strong>,</p>
                            <p style='color: #64748b;'>Vous avez demandé à réinitialiser votre mot de passe. Veuillez cliquer sur le bouton ci-dessous :</p>
                            <div style='margin: 35px 0;'>
                                <a href='$url' style='background-color: #2563eb; color: #ffffff; padding: 18px 35px; border-radius: 12px; text-decoration: none; font-weight: bold; display: inline-block; text-transform: uppercase; letter-spacing: 1px;'>Changer mon mot de passe</a>
                            </div>
                            <p style='color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;'>Ce lien est strictement personnel et expire dans 1 heure.</p>
                        </div>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                // On log l'erreur côté serveur, mais on ne montre rien à l'utilisateur
                error_log("Erreur Mail Forgot Password: " . $mail->ErrorInfo);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="icon" type="image/png" href="{{ asset('assets/images/logo.png') }}">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-serif { font-family: 'Merriweather', serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-5xl flex rounded-2xl shadow-xl overflow-hidden border border-slate-200 bg-white">

        <!-- Bloc gauche -->
        <div class="hidden md:flex w-5/12 bg-blue-950 p-12 flex-col justify-between text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-16">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>

                <h1 class="font-serif text-3xl font-bold leading-snug mb-4">
                    Récupérez l'accès à<br>
                    <em class="text-blue-300">votre espace.</em>
                </h1>

                <p class="text-sm text-blue-200/70 leading-relaxed">
                    Saisissez votre adresse email professionnelle. Nous vous enverrons un lien sécurisé pour réinitialiser votre mot de passe.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-1">Sécurité</p>
                <p class="text-sm text-white/80">Lien temporaire à usage unique.</p>
            </div>
        </div>

        <!-- Bloc droit -->
        <div class="w-full md:w-7/12 p-10 md:p-14">
            <div class="mb-8">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Mot de passe oublié</h2>
                <p class="text-sm text-slate-400">Entrez votre email pour recevoir un lien de réinitialisation.</p>
            </div>

            <?php echo $message; ?>

            <form method="POST" class="space-y-6 mt-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                        Adresse email
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        required
                        placeholder="docteur@psyspace.fr"
                        class="w-full px-4 py-3.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-100 transition-all"
                    >
                </div>

                <button 
                    type="submit" 
                    name="reset-request"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm shadow-blue-100"
                >
                    Envoyer le lien
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100">
                <a href="login.php" class="text-sm text-slate-500 hover:text-blue-600 transition-colors inline-flex items-center gap-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Retour à la connexion
                </a>
            </div>
        </div>

    </div>

</body>
</html>