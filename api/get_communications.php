<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$incident_id = $_GET['incident_id'] ?? null;

if ($incident_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM communications 
        WHERE incident_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$incident_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM communications 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
}

$communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($communications);
?>
