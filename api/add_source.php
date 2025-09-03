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

$required_fields = ['source_type', 'source_id', 'name', 'barangay', 'location', 'latitude', 'longitude', 'status'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO water_sources 
        (source_type, source_id, name, location, barangay, latitude, longitude, capacity, pressure, flow_rate, status, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['source_type'],
        $data['source_id'],
        $data['name'],
        $data['location'],
        $data['barangay'],
        $data['latitude'],
        $data['longitude'],
        $data['capacity'] ?? null,
        $data['pressure'] ?? null,
        $data['flow_rate'] ?? null,
        $data['status'],
        $data['notes'] ?? null
    ]);

    echo json_encode(['success' => true, 'source_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>