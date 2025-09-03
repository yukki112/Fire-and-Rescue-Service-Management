<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Source ID required']);
    exit;
}

$source_id = $_GET['id'];

try {
    // Get source details
    $stmt = $pdo->prepare("SELECT * FROM water_sources WHERE id = ?");
    $stmt->execute([$source_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        http_response_code(404);
        echo json_encode(['error' => 'Water source not found']);
        exit;
    }

    // Get inspection history
    $stmt = $pdo->prepare("
        SELECT i.*, u.first_name, u.last_name 
        FROM water_source_inspections i 
        LEFT JOIN users u ON i.inspected_by = u.id 
        WHERE i.source_id = ? 
        ORDER BY i.inspection_date DESC
    ");
    $stmt->execute([$source_id]);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get maintenance history
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name 
        FROM water_source_maintenance m 
        LEFT JOIN users u ON m.performed_by = u.id 
        WHERE m.source_id = ? 
        ORDER BY m.maintenance_date DESC
    ");
    $stmt->execute([$source_id]);
    $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'source' => $source,
        'inspections' => $inspections,
        'maintenance' => $maintenance
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>