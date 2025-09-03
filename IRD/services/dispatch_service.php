<?php
class DispatchService {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function dispatchUnit() {
        header('Content-Type: application/json');
        
        try {
            if (empty($_POST['incident_id']) || empty($_POST['unit_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $incident_id = $_POST['incident_id'];
            $unit_id = $_POST['unit_id'];
            $notes = $_POST['notes'] ?? '';
            
            // Create dispatch record
            $query = "INSERT INTO dispatches (incident_id, unit_id, dispatched_at, status) 
                      VALUES (?, ?, NOW(), 'dispatched')";
            $this->dbManager->query("ird", $query, [$incident_id, $unit_id]);
            
            // Update unit status
            $query = "UPDATE units SET status = 'dispatched' WHERE id = ?";
            $this->dbManager->query("ird", $query, [$unit_id]);
            
            // Update incident status
            $query = "UPDATE incidents SET status = 'dispatched' WHERE id = ?";
            $this->dbManager->query("ird", $query, [$incident_id]);
            
            // Log the action
            $unit = $this->dbManager->fetch("ird", "SELECT unit_name FROM units WHERE id = ?", [$unit_id]);
            $this->logAction($incident_id, $_SESSION['user_id'], 'Unit Dispatched', 
                            "Unit {$unit['unit_name']} dispatched to incident. $notes");
            
            echo json_encode(['success' => true, 'message' => 'Unit dispatched successfully']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function logAction($incident_id, $user_id, $action, $details = null) {
        $query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
        $this->dbManager->query("ird", $query, [$incident_id, $user_id, $action, $details]);
    }
}
?>