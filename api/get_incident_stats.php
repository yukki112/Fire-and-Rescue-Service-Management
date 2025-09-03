<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Count active incidents by type
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM incidents 
    WHERE incident_type IN ('structure-fire', 'vehicle-fire', 'wildfire') 
    AND status IN ('pending', 'dispatched', 'responding')
");
$stmt->execute();
$active_fires = $stmt->fetch()['count'];

// Count critical incidents
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM incidents 
    WHERE incident_type IN ('structure-fire', 'vehicle-fire', 'wildfire') 
    AND status IN ('pending', 'dispatched', 'responding')
    AND priority = 'critical'
");
$stmt->execute();
$critical_fires = $stmt->fetch()['count'];

// Count responding units
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM units WHERE status IN ('dispatched', 'responding', 'onscene')");
$stmt->execute();
$responding_units = $stmt->fetch()['count'];

// Count on-scene personnel
$stmt = $pdo->prepare("
    SELECT SUM(u.personnel_count) as total 
    FROM units u 
    WHERE u.status = 'onscene'
");
$stmt->execute();
$on_scene_personnel = $stmt->fetch()['total'] ?: 0;

// Count contained fires
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM incidents 
    WHERE incident_type IN ('structure-fire', 'vehicle-fire', 'wildfire') 
    AND status = 'resolved'
    AND DATE(created_at) = CURDATE()
");
$stmt->execute();
$contained_fires = $stmt->fetch()['count'];

echo json_encode([
    'activeFires' => $active_fires,
    'criticalFires' => $critical_fires,
    'respondingUnits' => $responding_units,
    'onScenePersonnel' => $on_scene_personnel,
    'containedFires' => $contained_fires
]);
