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

if (!isset($data['unit_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$allowed_statuses = ['available', 'dispatched', 'responding', 'onscene', 'returning'];
if (!in_array($data['status'], $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

try {
    // Update unit status
    $stmt = $pdo->prepare("UPDATE units SET status = ? WHERE id = ?");
    $stmt->execute([$data['status'], $data['unit_id']]);
    
    // Update dispatch record if exists
    if (in_array($data['status'], ['responding', 'onscene'])) {
        $stmt = $pdo->prepare("
            UPDATE dispatches 
            SET status = ?, arrived_at = CASE WHEN ? = 'onscene' THEN NOW() ELSE arrived_at END 
            WHERE unit_id = ? AND status IN ('dispatched', 'responding')
        ");
        $stmt->execute([$data['status'], $data['status'], $data['unit_id']]);
    }
    
    // Log action if incident_id provided
    if (isset($data['incident_id'])) {
        $stmt = $pdo->prepare("
            INSERT INTO incident_actions (incident_id, action_type, description, performed_by, performed_at) 
            VALUES (?, 'status_update', ?, ?, NOW())
        ");
        $stmt->execute([
            $data['incident_id'],
            "Unit status updated to: " . $data['status'],
            $_SESSION['user_id']
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Unit status updated successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
