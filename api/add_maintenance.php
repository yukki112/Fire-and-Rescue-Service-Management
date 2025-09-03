<?php
require_once '../config/database.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$required_fields = ['source_id', 'maintenance_type', 'maintenance_date', 'description'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO water_source_maintenance 
        (source_id, maintenance_type, performed_by, maintenance_date, description, parts_used, cost, hours_spent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['source_id'],
        $data['maintenance_type'],
        $_SESSION['user_id'],
        $data['maintenance_date'],
        $data['description'],
        $data['parts_used'] ?? null,
        $data['cost'] ?? null,
        $data['hours_spent'] ?? null
    ]);

    echo json_encode(['success' => true, 'maintenance_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>