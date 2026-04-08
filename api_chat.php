<?php
session_start();
include "connection.php";

// 1. On vérifie qui parle (Docteur ou Assistante ?)
$doc_id = 0;
$sender_type = '';

if (isset($_SESSION['sec_doc_id'])) {
    $doc_id = (int)$_SESSION['sec_doc_id'];
    $sender_type = 'assistant';
} elseif (isset($_SESSION['id'])) {
    $doc_id = (int)$_SESSION['id'];
    $sender_type = 'doctor';
} else {
    die(json_encode(['error' => 'Non autorisé']));
}

$action = $_GET['action'] ?? '';

// 2. LECTURE DES MESSAGES (Récupérer l'historique)
if ($action === 'fetch') {
    $stmt = $conn->prepare("SELECT sender_type, message, DATE_FORMAT(created_at, '%H:%i') as time FROM cabinet_chat WHERE doctor_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode($messages);
    exit;
}

// 3. ÉCRITURE D'UN MESSAGE
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    // Le type peut être forcé à 'system' si ça vient du code PHP (ex: annulation de RDV)
    $type = $_POST['type'] ?? $sender_type; 

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $doc_id, $type, $message);
        $stmt->execute();
        echo json_encode(['success' => true]);
    }
    exit;
}
?>