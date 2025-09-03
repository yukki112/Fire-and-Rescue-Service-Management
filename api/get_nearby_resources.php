<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    echo json_encode(['error' => 'Latitude and longitude required']);
    exit;
}

$lat = $_GET['lat'];
$lng = $_GET['lng'];
$radius = isset($_GET['radius']) ? $_GET['radius'] : 5; // Default 5km radius

$stmt = $pdo->prepare("
    SELECT *, 
    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance 
    FROM units 
    WHERE status = 'available' 
    HAVING distance < ? 
    ORDER BY distance 
    LIMIT 10
");
$stmt->execute([$lat, $lng, $lat, $radius]);
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get nearby hydrants (assuming we have a hydrants table)
$stmt = $pdo->prepare("
    SELECT *, 
    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance 
    FROM hydrants 
    HAVING distance < ? 
    ORDER BY distance 
    LIMIT 10
");
$stmt->execute([$lat, $lng, $lat, $radius]);
$hydrants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get nearby hospitals
$stmt = $pdo->prepare("
    SELECT *, 
    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance 
    FROM hospitals 
    HAVING distance < ? 
    ORDER BY distance 
    LIMIT 5
");
$stmt->execute([$lat, $lng, $lat, $radius]);
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'units' => $units,
    'hydrants' => $hydrants,
    'hospitals' => $hospitals
]);
