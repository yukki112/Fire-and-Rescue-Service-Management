<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;

class DialogflowIntegration {
    private $sessionsClient;
    private $session;
    private $projectId = 'frsm-470418';
    private $isInitialized = false;
    
    public function __construct() {
        try {
            $serviceAccountPath = __DIR__ . '/../config/frsm-470418-7671325dd08c.json';
            
            if (!file_exists($serviceAccountPath)) {
                throw new Exception("Dialogflow service account file not found at: " . $serviceAccountPath);
            }
            
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $serviceAccountPath);
            
            $this->sessionsClient = new SessionsClient();
            $this->session = $this->sessionsClient->sessionName($this->projectId, uniqid());
            $this->isInitialized = true;
            
        } catch (Exception $e) {
            error_log("Dialogflow initialization error: " . $e->getMessage());
            $this->isInitialized = false;
        }
    }
    
    public function isInitialized() {
        return $this->isInitialized;
    }
    
    public function processNaturalLanguageQuery($query) {
        if (!$this->isInitialized || !$this->sessionsClient) {
            error_log("Dialogflow not initialized, using fallback pattern matching");
            return $this->fallbackPatternMatching($query);
        }
        
        try {
            // Create text input
            $textInput = new TextInput();
            $textInput->setText($query);
            $textInput->setLanguageCode('en-US');
            
            // Create query input
            $queryInput = new QueryInput();
            $queryInput->setText($textInput);
            
            // Get response
            $response = $this->sessionsClient->detectIntent($this->session, $queryInput);
            $queryResult = $response->getQueryResult();
            $intent = $queryResult->getIntent();
            $parameters = $queryResult->getParameters();
            
            // Process based on intent
            $intentName = $intent->getDisplayName();
            
            switch ($intentName) {
                case 'Incident.Status':
                    return $this->handleStatusQuery($queryResult);
                case 'Unit.Dispatch':
                    return $this->handleDispatchQuery($queryResult);
                case 'Weather.Query':
                    return $this->handleWeatherQuery($queryResult);
                case 'Resource.Query':
                    return $this->handleResourceQuery($queryResult);
                default:
                    return [
                        'response' => $queryResult->getFulfillmentText() ?: "I understand you're asking about: " . $query . ". How can I help you with incident response?",
                        'action' => null,
                        'parameters' => []
                    ];
            }
        } catch (Exception $e) {
            error_log("Dialogflow query error: " . $e->getMessage());
            return $this->fallbackPatternMatching($query);
        }
    }
    
    private function fallbackPatternMatching($query) {
        $query = strtolower(trim($query));
        
        // Enhanced pattern matching for different types of queries
        if (preg_match('/\b(status|how many|count|active|current)\b.*\b(incident|fire|emergency)\b/i', $query)) {
            return $this->handleStatusQuery($query);
        } elseif (preg_match('/\b(dispatch|send|deploy|assign)\b.*\b(unit|engine|truck|ambulance)\b/i', $query)) {
            return $this->handleDispatchQuery($query);
        } elseif (preg_match('/\b(weather|temperature|condition|rain|wind)\b/i', $query)) {
            return $this->handleWeatherQuery($query);
        } elseif (preg_match('/\b(resource|unit|hydrant|equipment|available)\b/i', $query)) {
            return $this->handleResourceQuery($query);
        } elseif (preg_match('/\b(help|assist|what can you do|commands)\b/i', $query)) {
            return [
                'response' => "I can help you with:\n• Check incident status\n• Dispatch units\n• Get weather information\n• View available resources\n• Analyze emergency situations\n\nTry asking: 'How many active fires?' or 'Dispatch Engine 5 to Main Street'",
                'action' => 'show_help',
                'parameters' => []
            ];
        } else {
            return [
                'response' => "I'm here to help with emergency response. You can ask me about incident status, unit dispatches, weather conditions, or available resources. What would you like to know?",
                'action' => null,
                'parameters' => []
            ];
        }
    }
    
    private function handleStatusQuery($queryResult) {
        global $pdo;
        
        if (is_string($queryResult)) {
            // Enhanced fallback from pattern matching
            if (preg_match('/\b(fire|structure|vehicle|wildfire)\b/i', $queryResult)) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incidents WHERE incident_type LIKE '%fire%' AND status IN ('pending', 'dispatched', 'responding')");
                    $stmt->execute();
                    $count = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->prepare("SELECT incident_type, COUNT(*) as count FROM incidents WHERE incident_type LIKE '%fire%' AND status IN ('pending', 'dispatched', 'responding') GROUP BY incident_type");
                    $stmt->execute();
                    $breakdown = $stmt->fetchAll();
                    
                    $response = "There are currently {$count} active fire incidents.";
                    if (!empty($breakdown)) {
                        $response .= " Breakdown: ";
                        $details = [];
                        foreach ($breakdown as $item) {
                            $details[] = $item['count'] . " " . str_replace('-', ' ', $item['incident_type']);
                        }
                        $response .= implode(', ', $details);
                    }
                    
                    return [
                        'response' => $response,
                        'action' => 'show_incidents',
                        'parameters' => ['status' => 'active', 'type' => 'fire']
                    ];
                } catch (Exception $e) {
                    error_log("Database error in status query: " . $e->getMessage());
                    return [
                        'response' => "I'm having trouble accessing the incident database right now. Please try again in a moment.",
                        'action' => null,
                        'parameters' => []
                    ];
                }
            } elseif (preg_match('/\b(unit|truck|engine|ambulance)\b/i', $queryResult)) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM units WHERE status = 'available'");
                    $stmt->execute();
                    $available = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM units WHERE status IN ('dispatched', 'responding', 'onscene')");
                    $stmt->execute();
                    $busy = $stmt->fetch()['count'];
                    
                    return [
                        'response' => "Unit status: {$available} units available, {$busy} units currently deployed.",
                        'action' => 'show_units',
                        'parameters' => ['status' => 'all']
                    ];
                } catch (Exception $e) {
                    error_log("Database error in unit status query: " . $e->getMessage());
                    return [
                        'response' => "I'm having trouble accessing the unit database right now. Please try again in a moment.",
                        'action' => null,
                        'parameters' => []
                    ];
                }
            }
        }
        
        // Dialogflow response processing
        $parameters = $queryResult->getParameters()->getFields();
        
        if (isset($parameters['incident_type'])) {
            $incidentType = $parameters['incident_type']->getStringValue();
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incidents WHERE incident_type = ? AND status IN ('pending', 'dispatched', 'responding')");
            $stmt->execute([$incidentType]);
            $count = $stmt->fetch()['count'];
            
            return [
                'response' => "There are currently {$count} active {$incidentType} incidents.",
                'action' => 'show_incidents',
                'parameters' => ['status' => 'active', 'type' => $incidentType]
            ];
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incidents WHERE status IN ('pending', 'dispatched', 'responding')");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            
            return [
                'response' => "There are currently {$count} active incidents.",
                'action' => 'show_incidents',
                'parameters' => ['status' => 'active']
            ];
        }
    }
    
    private function handleDispatchQuery($queryResult) {
        if (is_string($queryResult)) {
            // Enhanced fallback from pattern matching
            preg_match('/(engine|ladder|medic|unit|truck|ambulance)\s*(\d+)/i', $queryResult, $unitMatches);
            preg_match('/to\s+([^.!?]+)/i', $queryResult, $locationMatches);
            
            if (count($unitMatches) > 1 && count($locationMatches) > 1) {
                $unitType = $unitMatches[1];
                $unitNumber = $unitMatches[2];
                $location = trim($locationMatches[1]);
                
                return [
                    'response' => "I can help you dispatch {$unitType} {$unitNumber} to {$location}. This would require confirmation from the dispatcher. Would you like me to prepare the dispatch order?",
                    'action' => 'prepare_dispatch',
                    'parameters' => [
                        'unit_type' => $unitType,
                        'unit_number' => $unitNumber, 
                        'location' => $location
                    ]
                ];
            } else {
                return [
                    'response' => "To dispatch a unit, I need to know which unit and the destination. Try something like 'Dispatch Engine 5 to 123 Main Street' or 'Send Ambulance 2 to City Hall'.",
                    'action' => null,
                    'parameters' => []
                ];
            }
        }
        
        // Dialogflow response processing
        $parameters = $queryResult->getParameters()->getFields();
        
        if (isset($parameters['unit_number']) && isset($parameters['location'])) {
            $unitId = $parameters['unit_number']->getStringValue();
            $location = $parameters['location']->getStringValue();
            
            return [
                'response' => "I can help you dispatch Unit {$unitId} to {$location}. Would you like me to proceed?",
                'action' => 'prepare_dispatch',
                'parameters' => ['unit_id' => $unitId, 'location' => $location]
            ];
        } else {
            return [
                'response' => "I need to know which unit and where to dispatch. Try something like 'Dispatch Engine 5 to Main Street'.",
                'action' => null,
                'parameters' => []
            ];
        }
    }
    
    private function handleWeatherQuery($queryResult) {
        $weatherData = $this->getQuezonCityWeather();
        
        return [
            'response' => "Current weather in Quezon City: {$weatherData['temperature']}°C, {$weatherData['condition']}, humidity {$weatherData['humidity']}%, wind speed {$weatherData['wind_speed']} km/h.",
            'action' => 'show_weather',
            'parameters' => $weatherData
        ];
    }
    
    private function handleResourceQuery($queryResult) {
        global $pdo;
        
        try {
            if (is_string($queryResult)) {
                // Enhanced fallback from pattern matching
                if (preg_match('/\b(hydrant|water)\b/i', $queryResult)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM hydrants WHERE status = 'active'");
                    $stmt->execute();
                    $count = $stmt->fetch()['count'];
                    
                    return [
                        'response' => "There are {$count} active hydrants in the system.",
                        'action' => 'show_hydrants',
                        'parameters' => ['status' => 'active']
                    ];
                } elseif (preg_match('/\b(unit|equipment)\b/i', $queryResult)) {
                    $stmt = $pdo->prepare("SELECT unit_type, COUNT(*) as count FROM units WHERE status = 'available' GROUP BY unit_type");
                    $stmt->execute();
                    $units = $stmt->fetchAll();
                    
                    $response = "Available units: ";
                    $details = [];
                    foreach ($units as $unit) {
                        $details[] = $unit['count'] . " " . $unit['unit_type'];
                    }
                    $response .= implode(', ', $details);
                    
                    return [
                        'response' => $response,
                        'action' => 'show_units',
                        'parameters' => ['status' => 'available']
                    ];
                }
            }
            
            return [
                'response' => "I can provide information about hydrants, units, and equipment. What specific resources would you like to know about?",
                'action' => null,
                'parameters' => []
            ];
        } catch (Exception $e) {
            error_log("Database error in resource query: " . $e->getMessage());
            return [
                'response' => "I'm having trouble accessing the resource database right now. Please try again in a moment.",
                'action' => null,
                'parameters' => []
            ];
        }
    }
    
    private function getQuezonCityWeather() {
        $api_key = '7141f71887b2f22b9ae8de6b54b643bf'; 
        $city = 'Quezon City';
        $country = 'PH';
        
        $url = "http://api.openweathermap.org/data/2.5/weather?q={$city},{$country}&appid={$api_key}&units=metric";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'FRSM Weather Client 1.0');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if ($data && $data['cod'] == 200) {
                    return [
                        'temperature' => round($data['main']['temp']),
                        'condition' => $data['weather'][0]['main'],
                        'humidity' => $data['main']['humidity'],
                        'wind_speed' => round($data['wind']['speed'] * 3.6), // Convert m/s to km/h
                        'pressure' => $data['main']['pressure'],
                        'icon' => $data['weather'][0]['icon']
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Weather API error: " . $e->getMessage());
        }
        
        // Fallback data if API fails
        return [
            'temperature' => 28,
            'condition' => 'Partly Cloudy',
            'humidity' => 65,
            'wind_speed' => 12,
            'pressure' => 1013,
            'icon' => '02d'
        ];
    }
    
    public function __destruct() {
        if ($this->sessionsClient) {
            $this->sessionsClient->close();
        }
    }
}
?>
