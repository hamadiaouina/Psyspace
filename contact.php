<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
// Indispensable avant de charger PHPMailer et traiter le POST
ini_set('session.cookie_httponly', '1'); 
ini_set('session.cookie_samesite', 'Lax');
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Génération du Jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

$message_sent = false;
$error_message = '';

// --- 2. TRAITEMENT DU FORMULAIRE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Vérification du Pot de Miel (Honeypot) - Anti-Spam
    if (!empty($_POST['hp_contact'])) {
        $message_sent = true; // On ment au robot pour qu'il s'en aille
    } 
    // Vérification CSRF
    elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = "Erreur de sécurité (Jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } 
    // Anti-Spam temporel (1 message max par minute)
    elseif (isset($_SESSION['last_contact_time']) && (time() - $_SESSION['last_contact_time']) < 60) {
        $error_message = "Veuillez patienter 1 minute entre chaque message.";
    } 
    else {
        $mail = new PHPMailer(true);
        try {
            $smtp_user = getenv('SMTP_USER');
            $smtp_pass = getenv('SMTP_PASS');

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user; 
            $mail->Password   = $smtp_pass; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($smtp_user, 'PsySpace Support');
            $mail->addAddress($smtp_user); 
            
            // Nettoyage complet des données (Anti-XSS)
            $user_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $user_name  = htmlspecialchars(trim($_POST['name'] ?? 'Utilisateur Inconnu'), ENT_QUOTES, 'UTF-8');
            $subject    = htmlspecialchars(trim($_POST['subject_type'] ?? 'Demande Contact'), ENT_QUOTES, 'UTF-8');
            $user_msg   = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

            if (!$user_email || empty($user_msg)) {
                $error_message = "Veuillez remplir tous les champs correctement.";
            } else {
                $mail->addReplyTo($user_email, $user_name);
                $mail->isHTML(true);
                $mail->Subject = "Nouveau message : " . $subject . " de " . $user_name;
                
                $mail->Body = "
                    <div style='background:#f8fafc; padding:40px; font-family:sans-serif; color:#0f172a;'>
                        <div style='background:#ffffff; border:1px solid #e2e8f0; padding:32px; border-radius:16px; max-width:600px; margin:0 auto;'>
                            <h2 style='color:#4f46e5; margin-top:0;'>Nouveau message — PsySpace</h2>
                            <hr style='border:none; border-top:1px solid #e2e8f0; margin:20px 0;'>
                            <p><strong>Nom :</strong> " . $user_name . "</p>
                            <p><strong>Email :</strong> " . $user_email . "</p>
                            <p><strong>Sujet :</strong> " . $subject . "</p>
                            <div style='background:#f8fafc; padding:20px; margin-top:20px; border-radius:10px; color:#475569; line-height:1.6; border-left:3px solid #4f46e5;'>
                                " . nl2br($user_msg) . "
                            </div>
                        </div>
                    </div>";

                $mail->send();
                $_SESSION['last_contact_time'] = time(); // On valide l'anti-spam temporel
                $message_sent = true;
            }
        } catch (Exception $e) {
            $error_message = "Impossible d'envoyer le message pour le moment. Réessayez plus tard.";
            error_log("Erreur Contact: " . $mail->ErrorInfo);
        }
    }
}

// On inclut le header APRES les traitements pour permettre le bon fonctionnement des sessions
include "header.php";
?>

<style nonce="<?= $nonce ?? '' ?>">
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }
    
    .fade-up {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }
    .fade-up.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .input-clean {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        transition: all 0.2s;
        outline: none;
    }
    .input-clean:focus {
        background: white;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    .input-clean::placeholder {
        color: #94a3b8;
    }
</style>

<main class="text-slate-700 bg-white">

    <section class="bg-slate-900 pt-24 pb-20 md:pt-32 md:pb-28 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 24px 24px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 fade-up text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mb-6">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Contact</span>
            </div>
            
            <h1 class="font-serif text-4xl md:text-5xl font-bold text-white leading-tight tracking-tight mb-6">
                Une question ?<br>
                <span class="text-indigo-400">Nous sommes là.</span>
            </h1>
            
            <p class="text-lg text-slate-400 leading-relaxed max-w-xl mx-auto">
                Notre équipe assure la continuité de votre service. Réponse garantie sous <span class="text-white font-medium">24h ouvrées.</span>
            </p>
        </div>
    </section>

    <section class="py-20 md:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-5 gap-12 lg:gap-16 items-start">

                <div class="lg:col-span-3 fade-up">
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                            <svg class="shrink-0" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span><?= $error_message ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($message_sent && empty($error_message)): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-10 md:p-16 text-center">
                            <div class="w-16 h-16 bg-emerald-100 border border-emerald-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <h2 class="font-serif text-2xl font-bold text-slate-900 mb-3">Message envoyé !</h2>
                            <p class="text-slate-500 mb-8">Votre message a bien été transmis à notre équipe. Nous vous répondrons sous 24h.</p>
                            <a href="contact.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 underline underline-offset-4 transition-colors">
                                Envoyer un nouveau message
                            </a>
                        </div>
                    <?php else: ?>
                        <form action="contact.php" method="POST" class="bg-slate-50 border border-slate-200 rounded-xl p-6 md:p-10 space-y-6">
                            
                            <!-- SÉCURITÉ : Jeton CSRF et Honeypot -->
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <div style="display:none;" aria-hidden="true">
                                <label for="hp_contact">Ne pas remplir ce champ</label>
                                <input type="text" name="hp_contact" id="hp_contact" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="mb-8">
                                <h2 class="font-serif text-2xl font-bold text-slate-900 mb-1 tracking-tight">Envoyez-nous un message</h2>
                                <p class="text-sm text-slate-500">Tous les champs sont obligatoires.</p>
                            </div>

                            <!-- CHAMP NOM AJOUTÉ -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Nom complet</label>
                                    <input type="text" name="name" required placeholder="Dr. Dupont" class="input-clean" autocomplete="name">
                                </div>

                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email</label>
                                    <input type="email" name="email" required placeholder="votre@cabinet.fr" class="input-clean" autocomplete="email">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Sujet du message</label>
                                <select name="subject_type" class="input-clean cursor-pointer" required>
                                    <optgroup label="Assistance & Technique">
                                        <option value="Bug / Erreur">Signaler un bug ou une erreur</option>
                                        <option value="Accès Compte">Problème d'accès ou connexion</option>
                                        <option value="Installation">Aide à l'installation / Configuration</option>
                                        <option value="Performance">Lenteur de la plateforme</option>
                                    </optgroup>
                                    <optgroup label="Gestion & Cabinet">
                                        <option value="Facturation">Question sur la facturation / Abonnement</option>
                                        <option value="RGPD">Données patients & Confidentialité (RGPD)</option>
                                        <option value="Exports">Exportation des données patient</option>
                                        <option value="Agenda">Problème avec l'agenda / Prise de RDV</option>
                                    </optgroup>
                                    <optgroup label="Partenariat & Démo">
                                        <option value="Démo">Demander une démonstration complète</option>
                                        <option value="Feedback">Suggestion d'amélioration (Feedback)</option>
                                        <option value="Autre">Autre demande</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Message</label>
                                <textarea name="message" required rows="6" placeholder="Décrivez votre besoin..." class="input-clean resize-none"></textarea>
                            </div>

                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-lg transition-all hover:-translate-y-0.5 shadow-md shadow-indigo-200/50">
                                Envoyer le message
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="lg:col-span-2 space-y-6 fade-up">
                    <div class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
                        <h3 class="font-bold text-slate-900 mb-6 text-lg tracking-tight">Coordonnées</h3>
                        <div class="space-y-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-500 shrink-0">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider mb-0.5">Localisation</p>
                                    <p class="text-sm font-semibold text-slate-700">Rades, Tunisie</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-500 shrink-0">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider mb-0.5">Email</p>
                                    <p class="text-sm font-semibold text-slate-700">psyspace.me@gmail.com</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border border-indigo-100 bg-indigo-50/50 rounded-xl p-8">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse inline-block"></span>
                            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Équipe disponible</p>
                        </div>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Chaque message est traité sous <span class="font-semibold text-slate-800">24h ouvrées.</span> Vos échanges sont confidentiels et sécurisés.
                        </p>
                    </div>

                    <div class="border border-slate-200 rounded-xl p-6 flex items-center gap-3 flex-wrap bg-white">
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-md">
                            <svg class="text-emerald-500" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            RGPD
                        </span>
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-md">
                            <svg class="text-emerald-500" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            HDS
                        </span>
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-md">
                            <svg class="text-emerald-500" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            AES-256
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<script nonce="<?= $nonce ?? '' ?>">
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>

<?php include "footer.php"; ?>