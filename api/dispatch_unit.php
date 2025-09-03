<?php
require_once '../config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['incident_id']) || !isset($data['unit_ids']) || !is_array($data['unit_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $dispatched_units = [];
    
    foreach ($data['unit_ids'] as $unit_id) {
        // Check if unit is available
        $stmt = $pdo->prepare("SELECT status FROM units WHERE id = ?");
        $stmt->execute([$unit_id]);
        $unit = $stmt->fetch();
        
        if (!$unit || $unit['status'] !== 'available') {
            throw new Exception("Unit ID $unit_id is not available");
        }
        
        // Create dispatch record
        $stmt = $pdo->prepare("
            INSERT INTO dispatches (incident_id, unit_id, dispatched_at, status) 
            VALUES (?, ?, NOW(), 'dispatched')
        ");
        $stmt->execute([$data['incident_id'], $unit_id]);
        
        // Update unit status
        $stmt = $pdo->prepare("UPDATE units SET status = 'dispatched' WHERE id = ?");
        $stmt->execute([$unit_id]);
        
        // Log action
        $stmt = $pdo->prepare("
            INSERT INTO incident_actions (incident_id, action_type, description, performed_by, performed_at) 
            VALUES (?, 'dispatch', ?, ?, NOW())
        ");
        $stmt->execute([
            $data['incident_id'],
            "Unit dispatched to incident",
            $_SESSION['user_id']
        ]);
        
        $dispatched_units[] = $unit_id;
    }
    
    // Update incident status
    $stmt = $pdo->prepare("UPDATE incidents SET status = 'dispatched' WHERE id = ?");
    $stmt->execute([$data['incident_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'dispatched_units' => $dispatched_units,
        'message' => count($dispatched_units) . ' unit(s) dispatched successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
