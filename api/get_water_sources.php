<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$filters = [];
$params = [];

if (isset($_GET['barangay'])) {
    $filters[] = "barangay = ?";
    $params[] = $_GET['barangay'];
}

if (isset($_GET['status'])) {
    $filters[] = "status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['type'])) {
    $filters[] = "source_type = ?";
    $params[] = $_GET['type'];
}

$whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

try {
    $stmt = $pdo->prepare("SELECT * FROM water_sources $whereClause ORDER BY name");
    $stmt->execute($params);
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($sources);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>