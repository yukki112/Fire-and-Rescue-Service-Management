<?php
session_start();
require_once '../config/database.php';
require_once '../ai/dialogflow_integration.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    
    if (empty($query)) {
        http_response_code(400);
        echo json_encode(['error' => 'Query is required']);
        exit;
    }
    
    $dialogflow = new DialogflowIntegration();
    $analysis = $dialogflow->processNaturalLanguageQuery($query);
    
    // If confidence is high enough, create incident automatically
    if ($analysis['confidence'] > 0.7 && isset($_SESSION['user_id'])) {
        $incident_result = $dialogflow->createIncidentFromNL($query, $_SESSION['user_id']);
        
        if ($incident_result['success']) {
            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
                'incident_created' => true,
                'incident_data' => $incident_result['incident_data'],
                'ai_response' => $incident_result['ai_response']
            ]);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'incident_created' => false
    ]);
    
} catch (Exception $e) {
    error_log("Natural language processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed']);
}
?>
