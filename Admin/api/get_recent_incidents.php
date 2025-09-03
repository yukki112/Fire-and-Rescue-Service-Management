<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT i.*, u.first_name, u.last_name 
        FROM incidents i
        LEFT JOIN users u ON i.reported_by = u.id
        ORDER BY i.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $incidents = $stmt->fetchAll();
    
    // Format incidents for display
    $formatted_incidents = array_map(function($incident) {
        return [
            'id' => $incident['id'],
            'incident_type' => $incident['incident_type'],
            'location' => $incident['location'],
            'priority' => $incident['priority'],
            'status' => $incident['status'],
            'created_at' => date('M j, g:i A', strtotime($incident['created_at'])),
            'reporter' => $incident['first_name'] . ' ' . $incident['last_name']
        ];
    }, $incidents);
    
    echo json_encode([
        'success' => true,
        'incidents' => $formatted_incidents
    ]);
    
} catch (Exception $e) {
    error_log("Recent incidents error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get recent incidents']);
}
?>
