<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Get active incidents
    $active_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM incidents 
        WHERE status IN ('pending', 'dispatched', 'responding')
    ");
    $active_stmt->execute();
    $active_incidents = $active_stmt->fetch()['count'];
    
    // Get responding units
    $responding_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM units 
        WHERE status IN ('dispatched', 'responding', 'onscene')
    ");
    $responding_stmt->execute();
    $responding_units = $responding_stmt->fetch()['count'];
    
    // Get available units
    $available_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM units 
        WHERE status = 'available'
    ");
    $available_stmt->execute();
    $available_units = $available_stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'active_incidents' => $active_incidents,
            'responding_units' => $responding_units,
            'available_units' => $available_units,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get stats']);
}
?>
