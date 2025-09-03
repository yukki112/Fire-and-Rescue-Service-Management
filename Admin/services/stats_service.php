<?php
class StatsService {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function getIncidentStats() {
        header('Content-Type: application/json');
        
        try {
            $stats = [];
            
            // Active incidents
            $stats['active_incidents'] = $this->dbManager->fetch("ird", 
                "SELECT COUNT(*) as count FROM incidents WHERE status IN ('pending', 'dispatched', 'responding')"
            )['count'];
            
            // Critical incidents
            $stats['critical_incidents'] = $this->dbManager->fetch("ird", 
                "SELECT COUNT(*) as count FROM incidents WHERE priority = 'critical' AND status IN ('pending', 'dispatched', 'responding')"
            )['count'];
            
            // Responding units
            $stats['responding_units'] = $this->dbManager->fetch("ird", 
                "SELECT COUNT(*) as count FROM units WHERE status IN ('dispatched', 'responding', 'onscene')"
            )['count'];
            
            // On-scene personnel
            $stats['on_scene_personnel'] = $this->dbManager->fetch("ird", 
                "SELECT SUM(personnel_count) as count FROM units WHERE status = 'onscene'"
            )['count'] ?? 0;
            
            // Today's resolved incidents
            $today = date('Y-m-d');
            $stats['resolved_today'] = $this->dbManager->fetch("ird", 
                "SELECT COUNT(*) as count FROM incidents WHERE status = 'resolved' AND DATE(created_at) = ?", 
                [$today]
            )['count'];
            
            // Available units
            $stats['available_units'] = $this->dbManager->fetch("ird", 
                "SELECT COUNT(*) as count FROM units WHERE status = 'available'"
            )['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>