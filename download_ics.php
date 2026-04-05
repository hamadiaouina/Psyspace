<?php
session_start();

// Vérification de sécurité (seul un utilisateur connecté peut télécharger)
if (!isset($_SESSION['id']) && !isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
    die("Accès non autorisé.");
}

require_once "connection.php";
if (!isset($con)) { $con = $conn ?? null; }

// Récupérer l'ID du rendez-vous
$app_id = (int)($_GET['id'] ?? 0);
if ($app_id === 0) {
    die("ID de rendez-vous invalide.");
}

// Chercher les infos du rendez-vous dans la base
$stmt = $con->prepare("
    SELECT a.app_date, a.patient_name, a.app_type, d.docname 
    FROM appointments a 
    LEFT JOIN doctor d ON a.doctor_id = d.docid 
    WHERE a.id = ?
");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Rendez-vous introuvable.");
}

$rdv = $res->fetch_assoc();

// --- PRÉPARATION DES DATES POUR LE CALENDRIER ---
// Le format standard iCal (RFC 5545) demande le format Ymd\THis\Z (en temps universel UTC)
$timestamp_debut = strtotime($rdv['app_date']);
// On suppose qu'une consultation dure 1 heure (3600 secondes) par défaut
$timestamp_fin = $timestamp_debut + 3600; 

$dtstart = gmdate('Ymd\THis\Z', $timestamp_debut);
$dtend   = gmdate('Ymd\THis\Z', $timestamp_fin);
$dtstamp = gmdate('Ymd\THis\Z');

// Nettoyage des textes pour éviter de casser le format du fichier
$summary = "Consultation PsySpace : " . htmlspecialchars($rdv['patient_name']);
$description = "Rendez-vous médical sur PsySpace.\\nMédecin : Dr. " . htmlspecialchars($rdv['docname']) . "\\nType : " . htmlspecialchars($rdv['app_type'] ?? 'Consultation standard');

// Identifiant unique pour l'événement
$uid = md5(uniqid(mt_rand(), true)) . "@psyspace.com";

// --- CONSTRUCTION DU FICHIER .ICS ---
$ics_content = "BEGIN:VCALENDAR\r\n";
$ics_content .= "VERSION:2.0\r\n";
$ics_content .= "PRODID:-//PsySpace//NONSGML v1.0//FR\r\n";
$ics_content .= "CALSCALE:GREGORIAN\r\n";
$ics_content .= "BEGIN:VEVENT\r\n";
$ics_content .= "DTEND:" . $dtend . "\r\n";
$ics_content .= "UID:" . $uid . "\r\n";
$ics_content .= "DTSTAMP:" . $dtstamp . "\r\n";
$ics_content .= "LOCATION:Cabinet Médical PsySpace (En ligne)\r\n";
$ics_content .= "DESCRIPTION:" . $description . "\r\n";
$ics_content .= "SUMMARY:" . $summary . "\r\n";
$ics_content .= "DTSTART:" . $dtstart . "\r\n";
$ics_content .= "END:VEVENT\r\n";
$ics_content .= "END:VCALENDAR\r\n";

// --- ENVOI DES HEADERS POUR FORCER LE TÉLÉCHARGEMENT ---
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="PsySpace_RDV_' . $app_id . '.ics"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $ics_content;
exit();
?>