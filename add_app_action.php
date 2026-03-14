<?php
session_start();
include "connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doc_id = $_SESSION['id'];
    $name = mb_strtoupper(mysqli_real_escape_string($con, $_POST['patient_name']), 'UTF-8');
    $start_time = $_POST['app_date']; // Heure choisie

    // 1. On calcule l'heure de FIN (Début + 90 minutes)
    // On utilise strtotime pour manipuler les dates facilement
    $start_timestamp = strtotime($start_time);
    $end_timestamp = $start_timestamp + (90 * 60); // 90 min en secondes
    
    $start_fmt = date('Y-m-d H:i:s', $start_timestamp);
    $end_fmt = date('Y-m-d H:i:s', $end_timestamp);

    // 2. LA VÉRIFICATION INTELLIGENTE
    // On cherche si un RDV existe déjà qui CHEVAUCHE cette période
    // Un RDV existe si (son début est avant ma fin) ET (sa fin est après mon début)
    $query = "SELECT id FROM appointments 
              WHERE doctor_id = '$doc_id' 
              AND (
                  ('$start_fmt' BETWEEN app_date AND DATE_ADD(app_date, INTERVAL 90 MINUTE))
                  OR 
                  ('$end_fmt' BETWEEN app_date AND DATE_ADD(app_date, INTERVAL 90 MINUTE))
              )";
    
    $check = $con->query($query);

    if ($check->num_rows > 0) {
        // Conflit détecté !
        header("Location: agenda.php?error=conflict");
        exit();
    }

    // 3. INSERTION SI TOUT EST OK
    $sql = "INSERT INTO appointments (doctor_id, patient_name, app_date) VALUES ('$doc_id', '$name', '$start_time')";

    if ($con->query($sql) === TRUE) {
        header("Location: agenda.php?success=1");
    } else {
        echo "Erreur : " . $con->error;
    }
}
?>