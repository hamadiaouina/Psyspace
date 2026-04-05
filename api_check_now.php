<?php
// --- SÉCURITÉ ---
session_start();
header('Content-Type: application/json; charset=utf-8');
header("X-Content-Type-Options: nosniff");

// Vérifier si le médecin est bien connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['alert' => false, 'error' => 'unauthorized']);
    exit();
}

require_once "connection.php";
if (!isset($con)) { $con = $conn ?? null; }

$doc_id = (int)$_SESSION['id']; // Sécurisation en entier

// --- REQUÊTE PRÉPARÉE (Anti-Injection SQL) ---
// On cherche un RDV dans les 30 prochaines minutes
$stmt = $con->prepare("
    SELECT patient_name, app_date 
    FROM appointments 
    WHERE doctor_id = ? 
    AND app_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)
    ORDER BY app_date ASC
    LIMIT 1
");

$stmt->bind_param("i", $doc_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'alert' => true, 
        'patient' => htmlspecialchars($row['patient_name']), 
        'time' => date('H:i', strtotime($row['app_date']))
    ]);
} else {
    echo json_encode(['alert' => false]);
}

$stmt->close();
?>