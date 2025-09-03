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

$required_fields = ['source_id', 'inspection_date', 'condition', 'next_inspection'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO water_source_inspections 
        (source_id, inspected_by, inspection_date, pressure, flow_rate, condition, issues_found, actions_taken, recommendations, next_inspection) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['source_id'],
        $_SESSION['user_id'],
        $data['inspection_date'],
        $data['pressure'] ?? null,
        $data['flow_rate'] ?? null,
        $data['condition'],
        $data['issues_found'] ?? null,
        $data['actions_taken'] ?? null,
        $data['recommendations'] ?? null,
        $data['next_inspection']
    ]);

    // Update the water source's last inspection date
    $stmt = $pdo->prepare("UPDATE water_sources SET last_inspection = ?, next_inspection = ? WHERE id = ?");
    $stmt->execute([$data['inspection_date'], $data['next_inspection'], $data['source_id']]);

    echo json_encode(['success' => true, 'inspection_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>