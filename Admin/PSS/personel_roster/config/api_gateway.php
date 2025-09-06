<?php
// Only start a session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use require_once to prevent multiple inclusions
require_once 'database_manager.php';

// Handle API requests
if (isset($_GET['api']) || isset($_POST['api']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)) {
    header('Content-Type: application/json');
    
    // Extract API endpoint from URL or parameters
    $api_endpoint = '';
    if (isset($_GET['api'])) {
        $api_endpoint = $_GET['api'];
    } elseif (isset($_POST['api'])) {
        $api_endpoint = $_POST['api'];
    } elseif (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $api_endpoint = basename($path);
        $api_endpoint = str_replace('.php', '', $api_endpoint);
    }
    
    // Route to appropriate service
    switch ($api_endpoint) {
        case 'get_incident_stats':
            require_once 'services/stats_service.php';
            $service = new StatsService($dbManager);
            $service->getIncidentStats();
            break;
            
        case 'create_incident':
            require_once 'services/incident_service.php';
            $service = new IncidentService($dbManager);
            $service->createIncident();
            break;
            
        case 'update_incident_status':
            require_once 'services/incident_service.php';
            $service = new IncidentService($dbManager);
            $service->updateIncidentStatus();
            break;
            
        case 'dispatch_unit':
            require_once 'services/dispatch_service.php';
            $service = new DispatchService($dbManager);
            $service->dispatchUnit();
            break;
            
        case 'send_communication':
            require_once 'services/communication_service.php';
            $service = new CommunicationService($dbManager);
            $service->sendCommunication();
            break;
            
        case 'log_action':
            require_once 'services/communication_service.php';
            $service = new CommunicationService($dbManager);
            $service->logAction();
            break;
            
        case 'generate_report':
            require_once 'services/report_service.php';
            $service = new ReportService($dbManager);
            $service->generateReport();
            break;
            
        case 'update_unit_status':
            require_once 'services/unit_service.php';
            $service = new UnitService($dbManager);
            $service->updateUnitStatus();
            break;
            
        case 'add_unit':
            require_once 'services/unit_service.php';
            $service = new UnitService($dbManager);
            $service->addUnit();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found: ' . $api_endpoint]);
            exit;
    }
    exit;
}
?>