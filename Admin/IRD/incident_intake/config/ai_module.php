<?php
/**
 * AI Module for Intelligent Emergency Allocation and Predictive Analytics
 * Mock implementation for the Quezon City Fire & Rescue Service Management System
 */

/**
 * Analyzes an incident and provides AI-powered recommendations
 * @param array $incidentData Incident data
 * @return array AI recommendations
 */
function analyzeIncidentWithAI($incidentData) {
    // Simulate AI processing delay
    usleep(500000); // 0.5 seconds
    
    // Determine risk level based on incident type and severity
    $riskLevel = calculateRiskLevel($incidentData);
    
    // Get recommended units based on incident type and location
    $recommendedUnits = getRecommendedUnits($incidentData, $riskLevel);
    
    // Estimate response time based on location and incident severity
    $responseTime = estimateResponseTime($incidentData, $riskLevel);
    
    // Generate notes based on incident details
    $notes = generateIncidentNotes($incidentData, $riskLevel);
    
    return [
        'recommended_units' => $recommendedUnits,
        'estimated_response_time' => $responseTime,
        'risk_level' => $riskLevel,
        'notes' => $notes,
        'analysis_timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Calculates risk level based on incident data
 * @param array $incidentData Incident data
 * @return string Risk level (low, medium, high, critical)
 */
function calculateRiskLevel($incidentData) {
    $riskScore = 0;
    
    // Base risk from incident type
    switch ($incidentData['type']) {
        case 'Fire':
            $riskScore += 8;
            break;
        case 'Hazardous Materials':
            $riskScore += 9;
            break;
        case 'Medical Emergency':
            $riskScore += 6;
            break;
        case 'Rescue':
            $riskScore += 7;
            break;
        case 'Traffic Accident':
            $riskScore += 5;
            break;
        default:
            $riskScore += 4;
    }
    
    // Add risk from casualties
    $riskScore += min(5, ($incidentData['injuries'] * 0.5));
    $riskScore += min(7, ($incidentData['fatalities'] * 2));
    $riskScore += min(4, ($incidentData['people_trapped'] * 0.8));
    
    // Add risk from hazardous materials
    if ($incidentData['hazardous_materials']) {
        $riskScore += 6;
    }
    
    // Adjust based on priority (if manually set)
    if (isset($incidentData['priority'])) {
        switch ($incidentData['priority']) {
            case 'critical':
                $riskScore += 5;
                break;
            case 'high':
                $riskScore += 3;
                break;
            case 'medium':
                $riskScore += 1;
                break;
        }
    }
    
    // Determine risk level based on score
    if ($riskScore >= 15) {
        return 'critical';
    } elseif ($riskScore >= 10) {
        return 'high';
    } elseif ($riskScore >= 6) {
        return 'medium';
    } else {
        return 'low';
    }
}

/**
 * Gets recommended units based on incident type and location
 * @param array $incidentData Incident data
 * @param string $riskLevel Risk level
 * @return array Recommended units
 */
function getRecommendedUnits($incidentData, $riskLevel) {
    $baseUnits = [];
    
    // Base units based on incident type
    switch ($incidentData['type']) {
        case 'Fire':
            $baseUnits = ['Fire Truck 1', 'Ambulance 1'];
            if ($riskLevel === 'high' || $riskLevel === 'critical') {
                $baseUnits[] = 'Fire Truck 2';
                $baseUnits[] = 'Rescue Unit 1';
            }
            break;
            
        case 'Hazardous Materials':
            $baseUnits = ['Hazmat Unit 1', 'Fire Truck 1', 'Ambulance 1'];
            if ($riskLevel === 'high' || $riskLevel === 'critical') {
                $baseUnits[] = 'Hazmat Unit 2';
            }
            break;
            
        case 'Medical Emergency':
            $baseUnits = ['Ambulance 1'];
            if ($incidentData['injuries'] > 3 || $incidentData['fatalities'] > 0) {
                $baseUnits[] = 'Ambulance 2';
            }
            break;
            
        case 'Rescue':
            $baseUnits = ['Rescue Unit 1', 'Ambulance 1'];
            if ($riskLevel === 'high' || $riskLevel === 'critical') {
                $baseUnits[] = 'Rescue Unit 2';
            }
            break;
            
        case 'Traffic Accident':
            $baseUnits = ['Ambulance 1', 'Rescue Unit 1'];
            if ($incidentData['injuries'] > 2 || $incidentData['people_trapped'] > 0) {
                $baseUnits[] = 'Fire Truck 1';
            }
            break;
            
        default:
            $baseUnits = ['Ambulance 1'];
    }
    
    // Add command unit for critical incidents
    if ($riskLevel === 'critical') {
        $baseUnits[] = 'Command Unit 1';
    }
    
    return $baseUnits;
}

/**
 * Estimates response time based on location and incident severity
 * @param array $incidentData Incident data
 * @param string $riskLevel Risk level
 * @return int Estimated response time in minutes
 */
function estimateResponseTime($incidentData, $riskLevel) {
    // Base time based on barangay (simulated)
    $barangayTimes = [
        'Commonwealth' => 12,
        'Batasan Hills' => 10,
        'Payatas' => 15,
        'Bagong Silangan' => 14,
        'Holy Spirit' => 8,
        'Alicia' => 7
    ];
    
    $baseTime = isset($barangayTimes[$incidentData['barangay']]) ? 
                $barangayTimes[$incidentData['barangay']] : 10;
    
    // Adjust based on risk level
    switch ($riskLevel) {
        case 'critical':
            return max(5, $baseTime - 7); // Fastest possible response
        case 'high':
            return max(7, $baseTime - 5);
        case 'medium':
            return $baseTime;
        case 'low':
            return $baseTime + 3;
        default:
            return $baseTime;
    }
}

/**
 * Generates incident notes based on incident details
 * @param array $incidentData Incident data
 * @param string $riskLevel Risk level
 * @return string Notes
 */
function generateIncidentNotes($incidentData, $riskLevel) {
    $notes = [];
    
    // Notes based on incident type
    switch ($incidentData['type']) {
        case 'Fire':
            $notes[] = "Structure fire reported. Evacuate surrounding areas if necessary.";
            if ($riskLevel === 'high' || $riskLevel === 'critical') {
                $notes[] = "Potential for rapid fire spread. Request additional units for containment.";
            }
            break;
            
        case 'Hazardous Materials':
            $notes[] = "Hazardous materials incident. Establish safety perimeter.";
            $notes[] = "Use appropriate PPE and follow hazmat protocols.";
            break;
            
        case 'Medical Emergency':
            if ($incidentData['injuries'] > 3) {
                $notes[] = "Multiple casualties reported. Consider activating mass casualty protocol.";
            }
            break;
            
        case 'Rescue':
            $notes[] = "Rescue operation required. Assess structural stability before entry.";
            break;
            
        case 'Traffic Accident':
            if ($incidentData['people_trapped'] > 0) {
                $notes[] = "Extrication equipment required for trapped victims.";
            }
            break;
    }
    
    // Notes based on risk level
    if ($riskLevel === 'critical') {
        array_unshift($notes, "CRITICAL INCIDENT: Maximum response required. Alert all available units.");
    } elseif ($riskLevel === 'high') {
        array_unshift($notes, "HIGH RISK INCIDENT: Deploy with caution and adequate resources.");
    }
    
    // Notes based on casualties
    if ($incidentData['fatalities'] > 0) {
        $notes[] = "Fatalities confirmed. Prepare for coroner notification and scene preservation.";
    }
    
    if ($incidentData['injuries'] > 5) {
        $notes[] = "Mass casualty incident. Activate emergency medical services coordination.";
    }
    
    return implode(" ", $notes);
}

/**
 * Gets AI predictions for risk assessment across different areas
 * @return array Predictions
 */
function getAIPredictions() {
    // Simulate AI processing for predictions
    usleep(300000); // 0.3 seconds
    
    $barangays = ['Commonwealth', 'Batasan Hills', 'Payatas', 'Bagong Silangan', 'Holy Spirit', 'Alicia'];
    $predictions = [];
    
    foreach ($barangays as $barangay) {
        // Simulate different risk levels and trends
        $riskLevels = ['low', 'medium', 'high', 'critical'];
        $trends = [
            'Stable risk level',
            'Slight increase in incident probability',
            'Decreasing risk trend',
            'Higher than usual activity detected'
        ];
        
        $predictions[] = [
            'area' => $barangay,
            'risk_level' => $riskLevels[array_rand($riskLevels)],
            'trend' => $trends[array_rand($trends)],
            'confidence' => rand(70, 95) . '%'
        ];
    }
    
    return $predictions;
}

/**
 * Predicts incident hotspots based on historical data and current conditions
 * @return array Hotspot predictions
 */
function predictHotspots() {
    // This would normally integrate with historical data and real-time feeds
    $hotspots = [
        [
            'barangay' => 'Commonwealth',
            'risk_factor' => 'High',
            'reasons' => ['Recent fire incidents', 'Population density', 'Building types'],
            'recommendations' => ['Pre-position units', 'Increase patrols']
        ],
        [
            'barangay' => 'Payatas',
            'risk_factor' => 'Medium-High',
            'reasons' => ['Informal settlements', 'Access challenges'],
            'recommendations' => ['Community fire safety education', 'Strategic equipment placement']
        ]
    ];
    
    return $hotspots;
}
?>