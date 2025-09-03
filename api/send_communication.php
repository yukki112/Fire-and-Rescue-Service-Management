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

if (!isset($data['channel']) || !isset($data['receiver']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    // Get sender info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $sender = $user['first_name'] . ' ' . $user['last_name'];
    
    // Insert communication record
    $stmt = $pdo->prepare("
        INSERT INTO communications (channel, sender, receiver, message, incident_id, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['channel'],
        $sender,
        $data['receiver'],
        $data['message'],
        $data['incident_id'] ?? null
    ]);
    
    // Log action if incident_id provided
    if (isset($data['incident_id'])) {
        $stmt = $pdo->prepare("
            INSERT INTO incident_actions (incident_id, action_type, description, performed_by, performed_at) 
            VALUES (?, 'communication', ?, ?, NOW())
        ");
        $stmt->execute([
            $data['incident_id'],
            "Message sent via " . $data['channel'] . " to " . $data['receiver'],
            $_SESSION['user_id']
        ]);
    }
    
    echo json_encode([
        'success' => true, 
        'communication_id' => $pdo->lastInsertId(),
        'message' => 'Communication sent successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
