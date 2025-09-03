<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Count by type
    $stmt = $pdo->prepare("
        SELECT source_type, COUNT(*) as count 
        FROM water_sources 
        GROUP BY source_type
    ");
    $stmt->execute();
    $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM water_sources 
        GROUP BY status
    ");
    $stmt->execute();
    $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count by barangay
    $stmt = $pdo->prepare("
        SELECT barangay, COUNT(*) as count 
        FROM water_sources 
        GROUP BY barangay 
        ORDER BY count DESC
    ");
    $stmt->execute();
    $by_barangay = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count overdue inspections
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM water_sources 
        WHERE next_inspection < CURDATE() AND status != 'inactive'
    ");
    $stmt->execute();
    $overdue_inspections = $stmt->fetch()['count'];

    echo json_encode([
        'by_type' => $by_type,
        'by_status' => $by_status,
        'by_barangay' => $by_barangay,
        'overdue_inspections' => $overdue_inspections
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>