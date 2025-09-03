<?php
class CommunicationService {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function sendCommunication() {
        header('Content-Type: application/json');
        
        try {
            if (empty($_POST['channel']) || empty($_POST['receiver']) || empty($_POST['message'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $channel = $_POST['channel'];
            $receiver = $_POST['receiver'];
            $message = $_POST['message'];
            $incident_id = $_POST['incident_id'] ?? null;
            $sender = $_SESSION['user_name'] ?? 'System';
            
            $query = "INSERT INTO communications (channel, sender, receiver, message, incident_id) 
                      VALUES (?, ?, ?, ?, ?)";
            $this->dbManager->query("ird", $query, [$channel, $sender, $receiver, $message, $incident_id]);
            
            echo json_encode(['success' => true, 'message' => 'Communication sent successfully']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    public function logAction() {
        header('Content-Type: application/json');
        
        try {
            if (empty($_POST['incident_id']) || empty($_POST['action'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $incident_id = $_POST['incident_id'];
            $action = $_POST['action'];
            $details = $_POST['details'] ?? null;
            $user_id = $_SESSION['user_id'] ?? null;
            
            $query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $this->dbManager->query("ird", $query, [$incident_id, $user_id, $action, $details]);
            
            echo json_encode(['success' => true, 'message' => 'Action logged successfully']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>