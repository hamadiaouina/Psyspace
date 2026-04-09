<?php
session_start();
if (!isset($_SESSION['id'])) { 
    echo json_encode(['unread' => 0]); 
    exit; 
}
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$doc_id = (int)$_SESSION['id'];

$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM cabinet_chat WHERE doctor_id = ? AND sender_type = 'assistant' AND is_read = 0");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

echo json_encode(['unread' => (int)$res['unread']]);