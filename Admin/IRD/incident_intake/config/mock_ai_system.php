<?php
/**
 * Mock AI System for Intelligent Emergency Allocation and Predictive Analytics
 * Simulates AI functionality for the fire and rescue management system
 */

class MockAISystem {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    /**
     * Analyze an incident and provide AI recommendations
     */
    public function analyzeIncident($incident_data) {
        // Simulate AI processing delay
        usleep(500000); // 0.5 seconds
        
        // Calculate risk score based on incident parameters
        $risk_score = $this->calculateRiskScore($incident_data);
        
        // Determine risk level based on score
        $risk_level = $this->determineRiskLevel($risk_score);
        
        // Predict response time based on incident type and location
        $predicted_response_time = $this->predictResponseTime($incident_data);
        
        // Recommend units based on incident type and severity
        $recommended_units = $this->recommendUnits($incident_data, $risk_score);
        
        // Generate additional notes
        $notes = $this->generateNotes($incident_data, $risk_score);
        
        return [
            'risk_score' => $risk_score,
            'risk_level' => $risk_level,
            'predicted_response_time' => $predicted_response_time,
            'recommended_units' => $recommended_units,
            'recommended_units_count' => count($recommended_units),
            'notes' => $notes,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Calculate a risk score based on incident parameters
     */
    private function calculateRiskScore($incident_data) {
        $score = 0.5; // Base score
        
        // Adjust based on priority
        switch ($incident_data['priority']) {
            case 'low': $score -= 0.2; break;
            case 'medium': $score += 0.0; break;
            case 'high': $score += 0.2; break;
            case 'critical': $score += 0.4; break;
        }
        
        // Adjust based on incident type
        switch ($incident_data['type']) {
            case 'Fire': $score += 0.3; break;
            case 'Medical Emergency': $score += 0.1; break;
            case 'Rescue': $score += 0.2; break;
            case 'Hazardous Materials': $score += 0.4; break;
            case 'Traffic Accident': $score += 0.1; break;
        }
        
        // Adjust based on casualties
        $score += min(0.3, ($incident_data['injuries'] * 0.05));
        $score += min(0.4, ($incident_data['fatalities'] * 0.1));
        $score += min(0.3, ($incident_data['people_trapped'] * 0.07));
        
        // Adjust for hazardous materials
        if ($incident_data['hazardous_materials']) {
            $score += 0.2;
        }
        
        // Ensure score is between 0 and 1
        return max(0, min(1, $score));
    }
    
    /**
     * Determine risk level based on score
     */
    private function determineRiskLevel($score) {
        if ($score < 0.3) return 'low';
        if ($score < 0.6) return 'medium';
        if ($score < 0.8) return 'high';
        return 'critical';
    }
    
    /**
     * Predict response time based on incident data
     */
    private function predictResponseTime($incident_data) {
        $base_time = 15; // Base response time in minutes
        
        // Adjust based on incident type
        switch ($incident_data['type']) {
            case 'Fire': $base_time = 12; break;
            case 'Medical Emergency': $base_time = 10; break;
            case 'Rescue': $base_time = 14; break;
            case 'Hazardous Materials': $base_time = 18; break;
            case 'Traffic Accident': $base_time = 13; break;
        }
        
        // Adjust based on priority
        switch ($incident_data['priority']) {
            case 'low': $base_time += 5; break;
            case 'medium': $base_time += 0; break;
            case 'high': $base_time -= 3; break;
            case 'critical': $base_time -= 5; break;
        }
        
        // Add some randomness to simulate real-world conditions
        $random_factor = rand(-3, 3);
        $predicted_time = max(3, $base_time + $random_factor);
        
        return $predicted_time;
    }
    
    /**
     * Recommend units based on incident type and severity
     */
    private function recommendUnits($incident_data, $risk_score) {
        $units = [];
        
        switch ($incident_data['type']) {
            case 'Fire':
                $units[] = 'Fire Truck';
                if ($risk_score > 0.5) $units[] = 'Rescue Vehicle';
                if ($risk_score > 0.7) $units[] = 'Additional Fire Truck';
                if ($incident_data['hazardous_materials']) $units[] = 'Hazmat Unit';
                break;
                
            case 'Medical Emergency':
                $units[] = 'Ambulance';
                if ($risk_score > 0.6) $units[] = 'Additional Ambulance';
                if ($incident_data['injuries'] > 3) $units[] = 'Medical Support Unit';
                break;
                
            case 'Rescue':
                $units[] = 'Rescue Vehicle';
                if ($risk_score > 0.5) $units[] = 'Additional Rescue Vehicle';
                if ($incident_data['people_trapped'] > 2) $units[] = 'Technical Rescue Unit';
                break;
                
            case 'Hazardous Materials':
                $units[] = 'Hazmat Unit';
                $units[] = 'Fire Truck';
                if ($risk_score > 0.5) $units[] = 'Additional Hazmat Unit';
                break;
                
            case 'Traffic Accident':
                $units[] = 'Rescue Vehicle';
                if ($incident_data['injuries'] > 0) $units[] = 'Ambulance';
                if ($risk_score > 0.6) $units[] = 'Fire Truck';
                break;
                
            default:
                $units[] = 'General Response Unit';
                break;
        }
        
        // Add command unit for high-risk incidents
        if ($risk_score > 0.7) {
            array_unshift($units, 'Command Unit');
        }
        
        return $units;
    }
    
    /**
     * Generate notes based on incident analysis
     */
    private function generateNotes($incident_data, $risk_score) {
        $notes = [];
        
        // Notes based on incident type
        switch ($incident_data['type']) {
            case 'Fire':
                $notes[] = "Consider potential for fire spread based on local building materials.";
                if ($risk_score > 0.7) $notes[] = "High probability of structural collapse. Evacuate surrounding areas.";
                break;
                
            case 'Medical Emergency':
                $notes[] = "Prepare for triage based on reported injuries.";
                if ($incident_data['injuries'] > 5) $notes[] = "Mass casualty incident protocol recommended.";
                break;
                
            case 'Rescue':
                $notes[] = "Assess structural stability before entry.";
                if ($incident_data['people_trapped'] > 3) $notes[] = "Multiple extraction points may be required.";
                break;
                
            case 'Hazardous Materials':
                $notes[] = "Establish safety perimeter. Identify wind direction for evacuation planning.";
                break;
        }
        
        // Notes based on risk level
        if ($risk_score > 0.7) {
            $notes[] = "Consider requesting mutual aid from neighboring jurisdictions.";
        }
        
        if ($incident_data['hazardous_materials']) {
            $notes[] = "Hazardous materials present. Ensure proper protective equipment is used.";
        }
        
        // If no specific notes, provide a general one
        if (empty($notes)) {
            $notes[] = "Standard operating procedures apply. Monitor situation for changes.";
        }
        
        return implode(" ", $notes);
    }
    
    /**
     * Predict incident trends based on historical data (mock implementation)
     */
    public function predictTrends($timeframe = '7d') {
        // Simulate data retrieval and processing delay
        usleep(800000); // 0.8 seconds
        
        $trend_categories = ['Fire', 'Medical Emergency', 'Rescue', 'Traffic Accident', 'Hazardous Materials'];
        $predictions = [];
        
        foreach ($trend_categories as $category) {
            $predictions[$category] = [
                'predicted_incidents' => rand(5, 20),
                'change_percentage' => rand(-20, 30) / 10,
                'risk_level' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
                'hotspots' => [
                    ['barangay' => 'Commonwealth', 'probability' => rand(30, 90) / 100],
                    ['barangay' => 'Batasan Hills', 'probability' => rand(30, 90) / 100],
                    ['barangay' => 'Payatas', 'probability' => rand(30, 90) / 100]
                ]
            ];
        }
        
        return [
            'timeframe' => $timeframe,
            'predictions' => $predictions,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Optimize resource allocation (mock implementation)
     */
    public function optimizeResourceAllocation() {
        // Simulate optimization processing
        usleep(1000000); // 1 second
        
        $stations = ['Main Station', 'North Station', 'South Station', 'East Station', 'West Station'];
        $recommendations = [];
        
        foreach ($stations as $station) {
            $recommendations[$station] = [
                'recommended_changes' => rand(0, 3),
                'efficiency_gain' => rand(5, 25) / 10,
                'suggested_units' => [
                    ['type' => 'Fire Truck', 'action' => ['relocate', 'maintain', 'add'][rand(0, 2)]],
                    ['type' => 'Ambulance', 'action' => ['relocate', 'maintain', 'add'][rand(0, 2)]],
                    ['type' => 'Rescue Vehicle', 'action' => ['relocate', 'maintain', 'add'][rand(0, 2)]]
                ]
            ];
        }
        
        return [
            'optimization_date' => date('Y-m-d'),
            'estimated_impact' => 'Response times improved by ' . rand(5, 15) . '%',
            'station_recommendations' => $recommendations
        ];
    }
}
?>