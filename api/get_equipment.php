<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$filters = [];
$params = [];

if (isset($_GET['status'])) {
    $filters[] = "status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['type'])) {
    $filters[] = "type = ?";
    $params[] = $_GET['type'];
}

if (isset($_GET['unit_id'])) {
    $filters[] = "assigned_unit = ?";
    $params[] = $_GET['unit_id'];
}

$whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

$stmt = $pdo->prepare("
    SELECT e.*, u.unit_name 
    FROM equipment e 
    LEFT JOIN units u ON e.assigned_unit = u.id 
    $whereClause 
    ORDER BY e.name
");
$stmt->execute($params);
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($equipment);
