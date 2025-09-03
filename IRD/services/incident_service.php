<?php
class IncidentService {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function createIncident() {
        header('Content-Type: application/json');
        
        try {
            // Validate required fields
            $required = ['incident_type', 'barangay', 'location', 'description'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    return;
                }
            }
            
            // Prepare data
            $incident_type = $_POST['incident_type'];
            $barangay = $_POST['barangay'];
            $location = $_POST['location'];
            $description = $_POST['description'];
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            $priority = $_POST['priority'] ?? 'medium';
            $injuries = $_POST['injuries'] ?? 0;
            $fatalities = $_POST['fatalities'] ?? 0;
            $people_trapped = $_POST['people_trapped'] ?? 0;
            $hazardous_materials = isset($_POST['hazardous_materials']) ? 1 : 0;
            $reported_by = $_SESSION['user_id'] ?? null;
            
            // Insert into database
            $query = "INSERT INTO incidents (incident_type, barangay, location, latitude, longitude, 
                      incident_date, incident_time, description, injuries, fatalities, people_trapped, 
                      hazardous_materials, priority, reported_by) 
                      VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $incident_type, $barangay, $location, $latitude, $longitude, $description, 
                $injuries, $fatalities, $people_trapped, $hazardous_materials, $priority, $reported_by
            ];
            
            $this->dbManager->query("ird", $query, $params);
            $incident_id = $this->dbManager->getConnection("ird")->lastInsertId();
            
            // Log the action
            $this->logAction($incident_id, $_SESSION['user_id'], 'Incident Created', $description);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Incident created successfully',
                'incident_id' => $incident_id
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    public function updateIncidentStatus() {
        header('Content-Type: application/json');
        
        try {
            if (empty($_POST['incident_id']) || empty($_POST['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $incident_id = $_POST['incident_id'];
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            $query = "UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?";
            $this->dbManager->query("ird", $query, [$status, $incident_id]);
            
            // Log the action
            $this->logAction($incident_id, $_SESSION['user_id'], 'Status Updated', "Status changed to $status. $notes");
            
            echo json_encode(['success' => true, 'message' => 'Incident status updated successfully']);
            
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