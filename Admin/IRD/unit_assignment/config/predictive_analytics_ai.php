<?php
class PredictiveAnalyticsAI {
    private $dbManager;
    
    public function __construct($dbManager) {
        $this->dbManager = $dbManager;
    }
    
    public function analyzeIncident($incidentData, $historicalIncidents) {
        $similarIncidents = $this->findSimilarIncidents($incidentData, $historicalIncidents);
        $trendAnalysis = $this->analyzeTrends($incidentData, $historicalIncidents);
        $resourcePrediction = $this->predictResourceNeeds($incidentData, $similarIncidents);
        
        return [
            'similar_incidents_count' => count($similarIncidents),
            'similar_incidents' => array_slice($similarIncidents, 0, 5),
            'trend_direction' => $trendAnalysis['direction'],
            'trend_percentage' => $trendAnalysis['percentage'],
            'predicted_resource_need' => $resourcePrediction,
            'pattern_insights' => $this->generatePatternInsights($incidentData, $similarIncidents)
        ];
    }
    
    private function findSimilarIncidents($currentIncident, $historicalIncidents) {
        $similar = [];
        
        foreach ($historicalIncidents as $incident) {
            $similarityScore = 0;
            
            // Type similarity
            if (strtolower($incident['incident_type']) === strtolower($currentIncident['type'])) {
                $similarityScore += 40;
            }
            
            // Location similarity (same barangay)
            if (isset($incident['barangay']) && isset($currentIncident['barangay']) && 
                strtolower($incident['barangay']) === strtolower($currentIncident['barangay'])) {
                $similarityScore += 30;
            }
            
            // Priority similarity
            if (isset($incident['priority']) && 
                strtolower($incident['priority']) === strtolower($currentIncident['priority'])) {
                $similarityScore += 20;
            }
            
            // Severity similarity (based on injuries/fatalities)
            $currentSeverity = ($currentIncident['injuries'] * 2) + ($currentIncident['fatalities'] * 5);
            $incidentSeverity = (($incident['injuries'] ?? 0) * 2) + (($incident['fatalities'] ?? 0) * 5);
            
            if (abs($currentSeverity - $incidentSeverity) <= 3) {
                $similarityScore += 10;
            }
            
            if ($similarityScore >= 50) {
                $incident['similarity_score'] = $similarityScore;
                $similar[] = $incident;
            }
        }
        
        // Sort by similarity score (descending)
        usort($similar, function($a, $b) {
            return $b['similarity_score'] - $a['similarity_score'];
        });
        
        return $similar;
    }
    
    private function analyzeTrends($currentIncident, $historicalIncidents) {
        $currentType = strtolower($currentIncident['type']);
        $currentBarangay = strtolower($currentIncident['barangay']);
        
        $last30Days = array_filter($historicalIncidents, function($incident) {
            $incidentDate = strtotime($incident['created_at']);
            $thirtyDaysAgo = strtotime('-30 days');
            return $incidentDate >= $thirtyDaysAgo;
        });
        
        $previous30Days = array_filter($historicalIncidents, function($incident) {
            $incidentDate = strtotime($incident['created_at']);
            $sixtyDaysAgo = strtotime('-60 days');
            $thirtyDaysAgo = strtotime('-30 days');
            return $incidentDate >= $sixtyDaysAgo && $incidentDate < $thirtyDaysAgo;
        });
        
        // Count similar incidents in both periods
        $recentCount = count(array_filter($last30Days, function($incident) use ($currentType, $currentBarangay) {
            return strtolower($incident['incident_type']) === $currentType &&
                   strtolower($incident['barangay']) === $currentBarangay;
        }));
        
        $previousCount = count(array_filter($previous30Days, function($incident) use ($currentType, $currentBarangay) {
            return strtolower($incident['incident_type']) === $currentType &&
                   strtolower($incident['barangay']) === $currentBarangay;
        }));
        
        if ($previousCount === 0) {
            return ['direction' => 'stable', 'percentage' => 0];
        }
        
        $percentageChange = (($recentCount - $previousCount) / $previousCount) * 100;
        
        if ($percentageChange >= 20) {
            return ['direction' => 'increasing', 'percentage' => abs(round($percentageChange))];
        } elseif ($percentageChange <= -20) {
            return ['direction' => 'decreasing', 'percentage' => abs(round($percentageChange))];
        } else {
            return ['direction' => 'stable', 'percentage' => abs(round($percentageChange))];
        }
    }
    
    private function predictResourceNeeds($currentIncident, $similarIncidents) {
        if (empty($similarIncidents)) {
            return 'Standard response based on incident type';
        }
        
        // Analyze resource usage in similar incidents
        $unitCounts = [];
        $completionTimes = [];
        
        foreach ($similarIncidents as $incident) {
            // Get dispatch records for this incident
            try {
                $dispatches = $this->dbManager->fetchAll("ird", 
                    "SELECT unit_type FROM dispatches d JOIN units u ON d.unit_id = u.id WHERE incident_id = ?",
                    [$incident['id']]
                );
                
                foreach ($dispatches as $dispatch) {
                    $unitType = $dispatch['unit_type'];
                    $unitCounts[$unitType] = ($unitCounts[$unitType] ?? 0) + 1;
                }
                
            } catch (Exception $e) {
                // If we can't get dispatch data, continue with next incident
                continue;
            }
        }
        
        if (empty($unitCounts)) {
            return 'Standard response based on incident type';
        }
        
        // Get the most commonly used units
        arsort($unitCounts);
        $recommendedUnits = array_slice(array_keys($unitCounts), 0, 3);
        
        return implode(', ', $recommendedUnits) . ' (based on historical patterns)';
    }
    
    private function generatePatternInsights($currentIncident, $similarIncidents) {
        if (empty($similarIncidents)) {
            return ['No historical pattern data available for this type of incident'];
        }
        
        $insights = [];
        $topSimilar = array_slice($similarIncidents, 0, 3);
        
        // Time pattern analysis
        $times = array_map(function($incident) {
            return date('H', strtotime($incident['created_at']));
        }, $topSimilar);
        
        $timeCounts = array_count_values($times);
        arsort($timeCounts);
        $commonHour = key($timeCounts);
        
        if ($timeCounts[$commonHour] >= 2) {
            $insights[] = "Similar incidents frequently occur around " . $commonHour . ":00";
        }
        
        // Response time analysis
        $responseTimes = [];
        foreach ($topSimilar as $incident) {
            try {
                $dispatch = $this->dbManager->fetch("ird",
                    "SELECT dispatched_at FROM dispatches WHERE incident_id = ? ORDER BY dispatched_at LIMIT 1",
                    [$incident['id']]
                );
                
                if ($dispatch && isset($incident['created_at'])) {
                    $createTime = strtotime($incident['created_at']);
                    $dispatchTime = strtotime($dispatch['dispatched_at']);
                    $responseTimes[] = round(($dispatchTime - $createTime) / 60); // Minutes
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        if (!empty($responseTimes)) {
            $avgResponse = round(array_sum($responseTimes) / count($responseTimes));
            $insights[] = "Average historical response time: " . $avgResponse . " minutes";
        }
        
        // Outcome analysis
        $resolvedCount = 0;
        foreach ($topSimilar as $incident) {
            if (isset($incident['status']) && strtolower($incident['status']) === 'resolved') {
                $resolvedCount++;
            }
        }
        
        if ($resolvedCount > 0) {
            $successRate = round(($resolvedCount / count($topSimilar)) * 100);
            $insights[] = "Historical resolution rate: " . $successRate . "%";
        }
        
        return $insights;
    }
    
    // New method to get proximity-based recommendations
    public function getProximityBasedRecommendations($incidentData, $availableUnits) {
        // This would integrate with mapping/GIS data in a real implementation
        // For now, we'll use a simplified approach
        
        $recommendedUnits = [];
        
        foreach ($availableUnits as $unit) {
            // Calculate proximity score (simplified)
            $proximityScore = $this->calculateProximityScore($incidentData, $unit);
            
            if ($proximityScore > 0.6) { // Threshold for "close enough"
                $recommendedUnits[] = [
                    'unit' => $unit,
                    'proximity_score' => $proximityScore,
                    'estimated_arrival' => $this->estimateArrivalTime($proximityScore)
                ];
            }
        }
        
        // Sort by proximity score (descending)
        usort($recommendedUnits, function($a, $b) {
            return $b['proximity_score'] - $a['proximity_score'];
        });
        
        return $recommendedUnits;
    }
    
    private function calculateProximityScore($incidentData, $unit) {
        // Simplified proximity calculation based on barangay
        // In a real system, this would use actual coordinates and distance calculations
        
        $barangayProximity = [
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
        
        if (isset($barangayProximity[$incidentBarangay][$unitBarangay])) {
            return $barangayProximity[$incidentBarangay][$unitBarangay];
        }
        
        // Default score if not in matrix
        return 0.5;
    }
    
    private function estimateArrivalTime($proximityScore) {
        // Convert proximity score to estimated arrival time in minutes
        // Closer units have lower arrival times
        return round(15 * (1 - $proximityScore)) + 3; // 3-18 minutes range
    }
}
?>