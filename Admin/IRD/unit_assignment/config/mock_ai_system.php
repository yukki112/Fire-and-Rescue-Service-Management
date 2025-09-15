<?php
class MockAISystem {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function analyzeIncident($incidentData, $availableUnits = []) {
        // Mock AI analysis based on incident data
        $riskScore = $this->calculateRiskScore($incidentData);
        $recommendedUnits = $this->determineRecommendedUnits($incidentData, $availableUnits);
        $responseTime = $this->estimateResponseTime($incidentData);
        
        return [
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_score' => $riskScore,
            'recommended_units' => $recommendedUnits,
            'estimated_response_time' => $responseTime,
            'recommended_actions' => $this->getRecommendedActions($incidentData)
        ];
    }
    
    private function calculateRiskScore($incidentData) {
        $score = 0.3; // Base score
        
        // Adjust based on incident type
        $typeWeights = [
            'fire' => 0.8,
            'medical' => 0.6,
            'rescue' => 0.7,
            'hazardous' => 0.9,
            'other' => 0.5
        ];
        
        $type = strtolower($incidentData['type']);
        $score += $typeWeights[$type] ?? 0.5;
        
        // Adjust based on injuries/fatalities
        $score += min(0.3, ($incidentData['injuries'] * 0.05));
        $score += min(0.4, ($incidentData['fatalities'] * 0.1));
        
        // Adjust based on people trapped
        $score += min(0.2, ($incidentData['people_trapped'] * 0.05));
        
        // Adjust based on hazardous materials
        if ($incidentData['hazardous_materials'] > 0) {
            $score += 0.3;
        }
        
        // Adjust based on priority
        $priorityWeights = [
            'critical' => 0.4,
            'high' => 0.3,
            'medium' => 0.1,
            'low' => 0
        ];
        
        $score += $priorityWeights[strtolower($incidentData['priority'])] ?? 0;
        
        return min(1.0, max(0.1, $score));
    }
    
    private function getRiskLevel($score) {
        if ($score >= 0.8) return 'Critical';
        if ($score >= 0.6) return 'High';
        if ($score >= 0.4) return 'Medium';
        return 'Low';
    }
    
    private function determineRecommendedUnits($incidentData, $availableUnits = []) {
        $type = strtolower($incidentData['type']);
        $units = [];
        
        switch ($type) {
            case 'fire':
                $units[] = 'Fire Engine';
                $units[] = 'Rescue Unit';
                if ($incidentData['people_trapped'] > 0) {
                    $units[] = 'Ladder Truck';
                }
                break;
                
            case 'medical':
                $units[] = 'Ambulance';
                if ($incidentData['injuries'] > 3) {
                    $units[] = 'Additional Ambulance';
                }
                break;
                
            case 'rescue':
                $units[] = 'Rescue Unit';
                $units[] = 'Ambulance';
                break;
                
            case 'hazardous':
                $units[] = 'Hazmat Unit';
                $units[] = 'Fire Engine';
                break;
                
            default:
                $units[] = 'Response Unit';
                break;
        }
        
        return implode(', ', $units);
    }
    
    private function estimateResponseTime($incidentData) {
        $baseTime = 8; // Base response time in minutes
        
        // Adjust based on time of day (mock traffic conditions)
        $hour = date('H');
        if ($hour >= 7 && $hour <= 9) $baseTime += 5; // Morning rush
        if ($hour >= 16 && $hour <= 18) $baseTime += 6; // Evening rush
        
        // Adjust based on incident priority
        $priority = strtolower($incidentData['priority']);
        if ($priority === 'critical') $baseTime -= 2;
        if ($priority === 'high') $baseTime -= 1;
        
        return max(3, $baseTime); // Minimum 3 minutes
    }
    
    private function getRecommendedActions($incidentData) {
        $actions = [];
        $type = strtolower($incidentData['type']);
        
        switch ($type) {
            case 'fire':
                $actions[] = 'Establish water supply immediately';
                $actions[] = 'Perform 360-degree size-up';
                if ($incidentData['people_trapped'] > 0) {
                    $actions[] = 'Initiate rescue operations';
                }
                break;
                
            case 'medical':
                $actions[] = 'Bring trauma kit and AED';
                if ($incidentData['injuries'] > 2) {
                    $actions[] = 'Request additional medical resources';
                }
                break;
                
            case 'hazardous':
                $actions[] = 'Approach from upwind';
                $actions[] = 'Establish isolation perimeter';
                break;
        }
        
        // General actions based on risk
        if ($this->calculateRiskScore($incidentData) > 0.6) {
            $actions[] = 'Request additional units for backup';
        }
        
        return $actions;
    }
    
    // New method to get proximity-based recommendations
    public function getProximityBasedRecommendations($incidentData, $availableUnits) {
        $recommendedUnitTypes = $this->getRecommendedUnitTypes($incidentData);
        $proximityUnits = [];
        
        foreach ($availableUnits as $unit) {
            if (in_array($unit['unit_type'], $recommendedUnitTypes)) {
                // Calculate distance score (mock implementation)
                $distanceScore = $this->calculateDistanceScore($incidentData, $unit);
                $proximityUnits[] = [
                    'unit' => $unit,
                    'distance_score' => $distanceScore,
                    'suitability_score' => $this->calculateSuitabilityScore($incidentData, $unit, $distanceScore)
                ];
            }
        }
        
        // Sort by suitability score (descending)
        usort($proximityUnits, function($a, $b) {
            return $b['suitability_score'] - $a['suitability_score'];
        });
        
        return $proximityUnits;
    }
    
    private function getRecommendedUnitTypes($incidentData) {
        $type = strtolower($incidentData['type']);
        $unitTypes = [];
        
        switch ($type) {
            case 'fire':
            case 'structure-fire':
            case 'vehicle-fire':
                $unitTypes = ['Fire Engine', 'Ladder Truck', 'Rescue Unit', 'HazMat Unit', 'Ambulance'];
                break;
                
            case 'medical':
            case 'medical emergency':
                $unitTypes = ['Ambulance'];
                break;
                
            case 'rescue':
                $unitTypes = ['Rescue Unit', 'Ambulance'];
                break;
                
            case 'hazardous':
            case 'hazardous materials':
                $unitTypes = ['HazMat Unit', 'Fire Engine'];
                break;
                
            default:
                $unitTypes = ['Response Unit'];
                break;
        }
        
        return $unitTypes;
    }
    
    private function calculateDistanceScore($incidentData, $unit) {
        // Mock implementation - in a real system, you would use actual coordinates
        // and calculate real distance using Haversine formula
        
        $barangayDistanceMatrix = [
            'commonwealth' => [
                'commonwealth' => 1.0,
                'batasan hills' => 0.8,
                'payatas' => 0.7,
                'bagong silangan' => 0.6,
                'holy spirit' => 0.9
            ],
            'batasan hills' => [
                'commonwealth' => 0.8,
                'batasan hills' => 1.0,
                'payatas' => 0.9,
                'bagong silangan' => 0.7,
                'holy spirit' => 0.8
            ],
            // Add more barangays as needed
        ];
        
        $incidentBarangay = strtolower($incidentData['barangay']);
        $unitBarangay = strtolower($unit['barangay']);
        
        if (isset($barangayDistanceMatrix[$incidentBarangay][$unitBarangay])) {
            return $barangayDistanceMatrix[$incidentBarangay][$unitBarangay];
        }
        
        // Default distance score if not in matrix
        return 0.5;
    }
    
    private function calculateSuitabilityScore($incidentData, $unit, $distanceScore) {
        $score = $distanceScore * 0.6; // 60% weight to distance
        
        // Add weight based on unit type match
        $recommendedTypes = $this->getRecommendedUnitTypes($incidentData);
        if (in_array($unit['unit_type'], $recommendedTypes)) {
            $score += 0.4; // 40% weight to type match
        }
        
        return $score;
    }
}
?>