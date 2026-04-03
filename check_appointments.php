<?php
include "connection.php"; // Ton fichier avec la connexion $con
// On utilise PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; 

// 1. On cherche les RDV dans 30 min (entre 29 et 31 min pour être sûr de ne pas les rater)
$query = "SELECT a.*, d.docemail, d.docname 
          FROM appointments a 
          JOIN doctor d ON a.doctor_id = d.docid 
          WHERE a.app_date BETWEEN NOW() + INTERVAL 29 MINUTE AND NOW() + INTERVAL 31 MINUTE";

$result = $con->query($query);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            // Configuration SMTP (à remplir avec tes infos)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Ou ton serveur
            $mail->SMTPAuth = true;
            $mail->Username = 'ton-email@gmail.com';
            $mail->Password = 'ton-mot-de-pass-app';
            $mail->Port = 465;
            $mail->SMTPSecure = "ssl";

            $mail->setFrom('noreply@psyspace.me', 'PsySpace Alert');
            $mail->addAddress($row['docemail']); 

            $mail->isHTML(true);
            $mail->Subject = "Rappel : Consultation dans 30 minutes";
            $mail->Body = "Bonjour Dr. {$row['docname']}, vous avez un RDV avec <b>{$row['patient_name']}</b> à " . date('H:i', strtotime($row['app_date']));

            $mail->send();
        } catch (Exception $e) {
            error_log("Erreur Mail : " . $mail->ErrorInfo);
        }
    }
}