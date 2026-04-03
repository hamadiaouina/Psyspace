<?php
include "connection.php";
header('Content-Type: application/json');

// On regarde s'il y a un RDV dans les 30 prochaines minutes pour le docteur connecté
$doc_id = $_SESSION['user_id']; // Adapte selon ta variable de session
$query = "SELECT patient_name, app_date FROM appointments 
          WHERE doctor_id = $doc_id 
          AND app_date BETWEEN NOW() AND NOW() + INTERVAL 30 MINUTE 
          LIMIT 1";

$res = $con->query($query);
if($row = $res->fetch_assoc()) {
    echo json_encode([
        'alert' => true, 
        'patient' => $row['patient_name'], 
        'time' => date('H:i', strtotime($row['app_date']))
    ]);
} else {
    echo json_encode(['alert' => false]);
}