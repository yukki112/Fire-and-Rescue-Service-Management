<?php
require_once '../config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['incident_id']) || !isset($data['action_type']) || !isset($data['description'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO incident_actions (incident_id, action_type, description, performed_by, performed_at) 
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([
    $data['incident_id'],
    $data['action_type'],
    $data['description'],
    $_SESSION['user_id']
]);

echo json_encode(['success' => true, 'action_id' => $pdo->lastInsertId()]);
