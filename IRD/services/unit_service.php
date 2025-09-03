<?php
class UnitService {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function updateUnitStatus() {
        header('Content-Type: application/json');
        
        try {
            if (empty($_POST['unit_id']) || empty($_POST['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $unit_id = $_POST['unit_id'];
            $status = $_POST['status'];
            $location = $_POST['location'] ?? null;
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            
            $query = "UPDATE units SET status = ?, current_location = ?, latitude = ?, longitude = ?, updated_at = NOW() 
                      WHERE id = ?";
            $this->dbManager->query("ird", $query, [$status, $location, $latitude, $longitude, $unit_id]);
            
            echo json_encode(['success' => true, 'message' => 'Unit status updated successfully']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    public function addUnit() {
        header('Content-Type: application/json');
        
        try {
            $required = ['unit_name', 'unit_type', 'station', 'barangay'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    return;
                }
            }
            
            $unit_name = $_POST['unit_name'];
            $unit_type = $_POST['unit_type'];
            $station = $_POST['station'];
            $barangay = $_POST['barangay'];
            $personnel_count = $_POST['personnel_count'] ?? 1;
            $equipment = $_POST['equipment'] ?? null;
            $specialization = $_POST['specialization'] ?? null;
            $status = $_POST['status'] ?? 'available';
            
            $query = "INSERT INTO units (unit_name, unit_type, station, barangay, personnel_count, 
                      equipment, specialization, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $unit_name, $unit_type, $station, $barangay, $personnel_count, 
                $equipment, $specialization, $status
            ];
            
            $this->dbManager->query("ird", $query, $params);
            $unit_id = $this->dbManager->getConnection("ird")->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Unit added successfully',
                'unit_id' => $unit_id
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>