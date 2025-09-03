<?php
class EmergencyAllocator {
    private $pdo;
    private $firebase;
    
    public function __construct($pdo, $firebase = null) {
        $this->pdo = $pdo;
        $this->firebase = $firebase;
    }
    
    public function analyzeIncident($incident) {
        try {
            $severity = $this->calculateSeverity($incident);
            $requiredUnits = $this->calculateRequiredUnits($incident, $severity);
            $recommendedUnits = $this->findRecommendedUnits($incident, $requiredUnits);
            $eta = $this->calculateETA($incident, $recommendedUnits);
            $recommendations = $this->generateRecommendations($incident, $severity);
            
            $analysis = [
                'severity' => $severity,
                'risk_level' => $this->getRiskLevel($severity),
                'required_units' => $requiredUnits,
                'recommended_units' => $recommendedUnits,
                'estimated_response_time' => $eta,
                'recommendations' => $recommendations,
                'analysis_timestamp' => date('Y-m-d H:i:s'),
                'confidence_score' => $this->calculateConfidenceScore($incident, $severity)
            ];
            
            if ($this->firebase && $this->firebase->isInitialized()) {
                $syncResult = $this->firebase->syncIncident(array_merge($incident, [
                    'analysis' => $analysis,
                    'updated_at' => date('Y-m-d H:i:s')
                ]));
                
                if (!$syncResult) {
                    error_log("Failed to sync incident analysis to Firebase for incident ID: " . $incident['id']);
                }
            }
            
            return $analysis;
        } catch (Exception $e) {
            error_log("Error analyzing incident: " . $e->getMessage());
            return [
                'error' => 'Analysis failed',
                'message' => 'Unable to complete incident analysis at this time',
                'severity' => 0.5,
                'risk_level' => 'medium'
            ];
        }
    }
    
    private function calculateSeverity($incident) {
        $severityWeights = [
            'structure-fire' => ['low' => 0.4, 'medium' => 0.6, 'high' => 0.8, 'critical' => 0.95],
            'vehicle-fire' => ['low' => 0.3, 'medium' => 0.5, 'high' => 0.7, 'critical' => 0.9],
            'wildfire' => ['low' => 0.5, 'medium' => 0.7, 'high' => 0.85, 'critical' => 0.98],
            'medical' => ['low' => 0.2, 'medium' => 0.4, 'high' => 0.6, 'critical' => 0.8],
            'rescue' => ['low' => 0.3, 'medium' => 0.5, 'high' => 0.7, 'critical' => 0.9],
            'hazmat' => ['low' => 0.4, 'medium' => 0.6, 'high' => 0.8, 'critical' => 0.95]
        ];
        
        $type = $incident['incident_type'];
        $priority = $incident['priority'];
        
        $baseSeverity = 0.5; // Default medium severity
        
        if (isset($severityWeights[$type][$priority])) {
            $baseSeverity = $severityWeights[$type][$priority];
        }
        
        $timeCreated = strtotime($incident['created_at']);
        $currentTime = time();
        $ageInMinutes = ($currentTime - $timeCreated) / 60;
        
        // Increase severity for incidents that have been pending too long
        if ($incident['status'] === 'pending' && $ageInMinutes > 10) {
            $baseSeverity += min(0.2, $ageInMinutes / 100); // Cap at 0.2 increase
        }
        
        $weather = $this->getCurrentWeather();
        if ($weather && $type === 'wildfire') {
            if ($weather['wind_speed'] > 20) {
                $baseSeverity += 0.1; // High wind increases wildfire severity
            }
            if ($weather['humidity'] < 30) {
                $baseSeverity += 0.1; // Low humidity increases fire risk
            }
        }
        
        return min(1.0, $baseSeverity); // Cap at 1.0
    }
    
    private function calculateConfidenceScore($incident, $severity) {
        $confidence = 0.8; // Base confidence
        
        // Reduce confidence if missing critical data
        if (empty($incident['latitude']) || empty($incident['longitude'])) {
            $confidence -= 0.2;
        }
        
        if (empty($incident['description'])) {
            $confidence -= 0.1;
        }
        
        // Increase confidence for well-documented incidents
        if (!empty($incident['description']) && strlen($incident['description']) > 50) {
            $confidence += 0.1;
        }
        
        return min(1.0, max(0.1, $confidence));
    }
    
    private function getRiskLevel($severity) {
        if ($severity >= 0.8) return 'high';
        if ($severity >= 0.6) return 'medium';
        return 'low';
    }
    
    private function calculateRequiredUnits($incident, $severity) {
        $requirements = [
            'structure-fire' => [
                'Fire Engine' => max(1, ceil($severity * 3)),
                'Ladder Truck' => max(1, ceil($severity * 2)),
                'Ambulance' => max(1, ceil($severity * 1.5))
            ],
            'vehicle-fire' => [
                'Fire Engine' => max(1, ceil($severity * 2)),
                'Ambulance' => ceil($severity * 1)
            ],
            'wildfire' => [
                'Brush Truck' => max(2, ceil($severity * 4)),
                'Fire Engine' => max(1, ceil($severity * 2)),
                'Water Tender' => ceil($severity * 1.5)
            ],
            'medical' => [
                'Ambulance' => max(1, ceil($severity * 2)),
                'Fire Engine' => $severity > 0.7 ? 1 : 0 // First responder support for serious medical
            ],
            'rescue' => [
                'Rescue Unit' => max(1, ceil($severity * 2)),
                'Ambulance' => max(1, ceil($severity * 1)),
                'Fire Engine' => ceil($severity * 1)
            ],
            'hazmat' => [
                'HazMat Unit' => max(1, ceil($severity * 2)),
                'Fire Engine' => max(1, ceil($severity * 1)),
                'Ambulance' => 1
            ]
        ];
        
        $type = $incident['incident_type'];
        $baseRequirements = isset($requirements[$type]) ? $requirements[$type] : ['Fire Engine' => 1];
        
        // Remove units with 0 requirement
        return array_filter($baseRequirements, function($count) {
            return $count > 0;
        });
    }
    
    private function findRecommendedUnits($incident, $requiredUnits) {
        $recommended = [];
        
        foreach ($requiredUnits as $unitType => $count) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT *, 
                    CASE 
                        WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN
                            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                            cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))
                        ELSE 999
                    END as distance
                    FROM units 
                    WHERE unit_type = ? AND status = 'available' 
                    ORDER BY distance ASC, last_maintenance DESC
                    LIMIT ?
                ");
                
                $stmt->execute([
                    $incident['latitude'] ?? 14.6760, 
                    $incident['longitude'] ?? 121.0437,
                    $incident['latitude'] ?? 14.6760,
                    $unitType,
                    $count
                ]);
                
                $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $recommended[$unitType] = $units;
            } catch (Exception $e) {
                error_log("Error finding recommended units: " . $e->getMessage());
                $recommended[$unitType] = [];
            }
        }
        
        return $recommended;
    }
    
    private function calculateETA($incident, $recommendedUnits) {
        $maxETA = 0;
        $currentHour = (int)date('H');
        
        // Traffic factor based on time of day
        $trafficFactor = 1.0;
        if (($currentHour >= 7 && $currentHour <= 9) || ($currentHour >= 17 && $currentHour <= 19)) {
            $trafficFactor = 1.5; // Rush hour
        } elseif ($currentHour >= 22 || $currentHour <= 5) {
            $trafficFactor = 0.8; // Late night/early morning
        }
        
        foreach ($recommendedUnits as $unitType => $units) {
            foreach ($units as $unit) {
                if (isset($unit['latitude']) && isset($unit['longitude'])) {
                    $distance = $this->calculateDistance(
                        $incident['latitude'] ?? 14.6760, 
                        $incident['longitude'] ?? 121.0437,
                        $unit['latitude'], 
                        $unit['longitude']
                    );
                    
                    // Base speed varies by unit type
                    $baseSpeed = 40; // km/h default
                    switch ($unitType) {
                        case 'Ladder Truck':
                            $baseSpeed = 35; // Slower due to size
                            break;
                        case 'Ambulance':
                            $baseSpeed = 45; // Faster response
                            break;
                        case 'Brush Truck':
                            $baseSpeed = 30; // Off-road capability but slower
                            break;
                    }
                    
                    $adjustedSpeed = $baseSpeed / $trafficFactor;
                    $eta = ($distance / $adjustedSpeed) * 60; // in minutes
                    $maxETA = max($maxETA, $eta);
                }
            }
        }
        
        return round(max(3, $maxETA)); // Minimum 3 minutes
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        // Haversine formula to calculate distance between two points
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    private function generateRecommendations($incident, $severity) {
        $recommendations = [];
        
        $type = $incident['incident_type'];
        $priority = $incident['priority'];
        
        // Base recommendations on incident type and severity
        switch ($type) {
            case 'structure-fire':
                $recommendations[] = "Establish incident command structure immediately";
                $recommendations[] = "Ensure water supply and hydrant connections";
                
                if ($severity > 0.7) {
                    $recommendations[] = "Request additional units from neighboring stations";
                    $recommendations[] = "Alert EMS for potential mass casualty incident";
                    $recommendations[] = "Consider establishing evacuation zones";
                }
                
                if ($severity > 0.9) {
                    $recommendations[] = "Notify utility companies for power/gas shutoff";
                    $recommendations[] = "Request aerial support if available";
                    $recommendations[] = "Establish media staging area";
                }
                break;
                
            case 'wildfire':
                $recommendations[] = "Assess wind direction and fire spread potential";
                $recommendations[] = "Establish firebreaks and containment lines";
                
                if ($severity > 0.6) {
                    $recommendations[] = "Request aerial firefighting support";
                    $recommendations[] = "Coordinate with forestry services";
                    $recommendations[] = "Prepare evacuation routes";
                }
                break;
                
            case 'medical':
                $recommendations[] = "Ensure ALS/BLS capabilities match patient needs";
                
                if ($priority === 'critical') {
                    $recommendations[] = "Consider helicopter transport if available";
                    $recommendations[] = "Alert receiving hospital of incoming critical patient";
                }
                break;
                
            case 'hazmat':
                $recommendations[] = "Establish hot, warm, and cold zones";
                $recommendations[] = "Ensure proper PPE for all personnel";
                $recommendations[] = "Contact hazmat specialists and poison control";
                
                if ($severity > 0.7) {
                    $recommendations[] = "Consider area evacuation";
                    $recommendations[] = "Alert environmental agencies";
                }
                break;
        }
        
        // Weather-based recommendations
        $weather = $this->getCurrentWeather();
        if ($weather) {
            if ($weather['wind_speed'] > 25 && in_array($type, ['structure-fire', 'wildfire'])) {
                $recommendations[] = "High wind conditions - exercise extreme caution with fire spread";
            }
            
            if ($weather['temperature'] > 35) {
                $recommendations[] = "High temperature - ensure adequate hydration for personnel";
            }
            
            if (strpos($weather['condition'], 'Rain') !== false) {
                $recommendations[] = "Wet conditions - adjust tactics for slippery surfaces";
            }
        }
        
        // Time-based recommendations
        $currentHour = (int)date('H');
        if ($currentHour >= 22 || $currentHour <= 6) {
            $recommendations[] = "Night operations - ensure adequate lighting and visibility";
        }
        
        // Ensure minimum recommendations
        if (empty($recommendations)) {
            $recommendations[] = "Follow standard operating procedures for " . str_replace('-', ' ', $type);
            $recommendations[] = "Maintain situational awareness and safety protocols";
        }
        
        return array_unique($recommendations);
    }
    
    private function getCurrentWeather() {
        try {
            $api_key = '7141f71887b2f22b9ae8de6b54b643bf';
            $city = 'Quezon City';
            $country = 'PH';
            
            $url = "http://api.openweathermap.org/data/2.5/weather?q={$city},{$country}&appid={$api_key}&units=metric";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if ($data && $data['cod'] == 200) {
                return [
                    'temperature' => round($data['main']['temp']),
                    'condition' => $data['weather'][0]['main'],
                    'humidity' => $data['main']['humidity'],
                    'wind_speed' => round($data['wind']['speed'] * 3.6),
                    'pressure' => $data['main']['pressure']
                ];
            }
        } catch (Exception $e) {
            error_log("Weather API error in emergency allocator: " . $e->getMessage());
        }
        
        return null;
    }
}
?>
