<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['incident_id'])) {
    echo json_encode(['error' => 'Incident ID required']);
    exit;
}

$incident_id = $_GET['incident_id'];

$stmt = $pdo->prepare("
    SELECT ia.*, u.first_name, u.last_name 
    FROM incident_actions ia 
    LEFT JOIN users u ON ia.performed_by = u.id 
    WHERE ia.incident_id = ? 
    ORDER BY ia.performed_at ASC
");
$stmt->execute([$incident_id]);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($timeline);
