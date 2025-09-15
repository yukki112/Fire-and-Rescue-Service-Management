<?php
session_start();

// Include the Database Manager first
require_once 'config/database_manager.php';

// Include the Mock AI System
require_once 'config/mock_ai_system.php';

// Include the new Predictive Analytics AI
require_once 'config/predictive_analytics_ai.php';

// Check if this is an API request
if (isset($_GET['api']) || isset($_POST['api']) || 
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)) {
    
    // Include and process API Gateway
    require_once 'config/api_gateway.php';
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize the AI Systems
$aiSystem = new MockAISystem($dbManager);
$predictiveAI = new PredictiveAnalyticsAI($dbManager);

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'ird';
$active_submodule = 'ii';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get barangays for dropdown
    $barangays = $dbManager->fetchAll("ird", "SELECT DISTINCT barangay FROM incidents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
    
    // Get incident types for dropdown
    $incident_types = $dbManager->fetchAll("ird", "SELECT DISTINCT incident_type FROM incidents WHERE incident_type IS NOT NULL AND incident_type != '' ORDER BY incident_type");
    
    // Get available units for AI recommendations
    $available_units = $dbManager->fetchAll("ird", "SELECT * FROM units WHERE status = 'available'");
    
    // Get historical data for predictive analytics
    $historical_incidents = $dbManager->fetchAll("ird", "SELECT * FROM incidents ORDER BY created_at DESC LIMIT 1000");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $barangays = [];
    $incident_types = [];
    $available_units = [];
    $historical_incidents = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_incident'])) {
    try {
        // Create new incident
        $query = "INSERT INTO incidents (incident_type, barangay, location, description, injuries, fatalities, people_trapped, hazardous_materials, priority, reported_by, incident_date, incident_time) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())";
        $params = [
            $_POST['incident_type'],
            $_POST['barangay'],
            $_POST['location'],
            $_POST['description'],
            $_POST['injuries'] ?? 0,
            $_POST['fatalities'] ?? 0,
            $_POST['people_trapped'] ?? 0,
            isset($_POST['hazardous_materials']) ? 1 : 0,
            $_POST['priority'],
            $_SESSION['user_id']
        ];
        $dbManager->query("ird", $query, $params);
        
        // Log the action
        $incident_id = $dbManager->getConnection("ird")->lastInsertId();
        $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
        $dbManager->query("ird", $log_query, [$incident_id, $_SESSION['user_id'], 'Incident Created', $_POST['description']]);
        
        // Use AI to recommend units and predict response time
        $incident_data = [
            'type' => $_POST['incident_type'],
            'barangay' => $_POST['barangay'],
            'injuries' => $_POST['injuries'] ?? 0,
            'fatalities' => $_POST['fatalities'] ?? 0,
            'people_trapped' => $_POST['people_trapped'] ?? 0,
            'hazardous_materials' => isset($_POST['hazardous_materials']) ? 1 : 0,
            'priority' => $_POST['priority']
        ];
        
        $ai_recommendations = $aiSystem->analyzeIncident($incident_data);
        
        // Use Predictive AI for advanced analytics
        $predictive_insights = $predictiveAI->analyzeIncident($incident_data, $historical_incidents);
        
        // Store AI recommendations in session for display
        $_SESSION['ai_recommendations'] = $ai_recommendations;
        $_SESSION['predictive_insights'] = $predictive_insights;
        $_SESSION['new_incident_id'] = $incident_id;
        
        $_SESSION['success_message'] = "Incident created successfully! AI recommendations generated.";
        header("Location: ii.php");
        exit;
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error creating incident: " . $e->getMessage();
    }
}

// Process AJAX request for real-time predictions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_predictions'])) {
    header('Content-Type: application/json');
    
    $incident_data = [
        'type' => $_POST['incident_type'] ?? '',
        'barangay' => $_POST['barangay'] ?? '',
        'injuries' => $_POST['injuries'] ?? 0,
        'fatalities' => $_POST['fatalities'] ?? 0,
        'people_trapped' => $_POST['people_trapped'] ?? 0,
        'hazardous_materials' => $_POST['hazardous_materials'] ?? 0,
        'priority' => $_POST['priority'] ?? 'medium'
    ];
    
    try {
        // Get real-time predictions
        $predictions = $predictiveAI->getRealTimePredictions($incident_data, $historical_incidents);
        echo json_encode(['success' => true, 'predictions' => $predictions]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
$ai_recommendations = $_SESSION['ai_recommendations'] ?? null;
$predictive_insights = $_SESSION['predictive_insights'] ?? null;
$new_incident_id = $_SESSION['new_incident_id'] ?? null;

// Clear the session variables after use
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
unset($_SESSION['ai_recommendations']);
unset($_SESSION['predictive_insights']);
unset($_SESSION['new_incident_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Fire and Rescue Service Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .incident-form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.4rem;
        }
        .priority-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.6em;
        }
        .map-preview {
            height: 300px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }
        .character-count {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
        }
        .ai-recommendation {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .predictive-insights {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .ai-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .ai-icon {
            font-size: 2rem;
            margin-right: 15px;
            animation: pulse 2s infinite;
        }
        .ai-prediction {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .prediction-value {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .unit-recommendation {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .risk-indicator {
            height: 10px;
            border-radius: 5px;
            margin-top: 5px;
            background: linear-gradient(to right, #4caf50, #ffeb3b, #f44336);
        }
        .risk-marker {
            height: 15px;
            width: 15px;
            background: white;
            border-radius: 50%;
            position: relative;
            top: -12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .prediction-chart {
            height: 100px;
            width: 100%;
            margin: 15px 0;
        }
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        .trend-up {
            background: rgba(255,255,255,0.2);
            color: #ff6b6b;
        }
        .trend-down {
            background: rgba(255,255,255,0.2);
            color: #51cf66;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .similar-incident {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="img/frsmse.png" alt="QC Logo">
                <div class="text">
                    Quezon City<br>
                    <small>Fire & Rescue Service Management</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                
                <a href="../../dashboard.php" class="sidebar-link">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
                
                <div class="sidebar-section">Modules</div>
                
                <!-- Incident Response Dispatch -->
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#irdMenu" role="button">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
            
                <div class="sidebar-dropdown collapse show" id="irdMenu">
                
                    <a href="ii.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="../incident_location_mapping/ilm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-map'></i>
                        <span>Incident Location Mapping</span>
                    </a>
                    <a href="../unit_assignment/ua.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="../communication/comm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="../status_monitoring/sm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i>
                        <span>Status Monitoring</span>
                    </a>
                    <a href="../reporting/report.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
                <!-- Fire Station Inventory & Equipment Tracking -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="fsietMenu">
                    <a href="../../FSIET/inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="../../equipment_tracking/et.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="../../FSIET/maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../../FSIET/repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../../FSIET/inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Inventory Reports & Audit Logs</span>
                    </a>
                     
                </div>
                
                
                 <!-- Hydrant and Water Resource Mapping -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="hwrmMenu">
                    <a href="../../HWRM/hydrant_resources_mapping/hrm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Hydrant resources mapping</span>
                    </a>
                      <a href="../../HWRM/water_source_database/wsd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Water Source Database</span>
                    </a>
                     <a href="../../HWRM/water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-droplet'></i>
                        <span>Water Source Status Monitoring</span>
                    </a>
                    <a href="../../HWRM/inspection_maintenance_records/imr.php" class="sidebar-dropdown-link">
                      <i class='bx bx-wrench'></i>
    <span> Inspection & Maintenance Records</span>
                    </a>
                    <a href="../../HWRM/reporting_analytics/ra.php" class="sidebar-dropdown-link">
                     <i class='bx bx-bar-chart-alt-2'></i>
    <span> Reporting & Analytics</span>
                    </a>
                  
                </div>
                
                    <!-- Personnel Shift Scheduling -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#pssMenu" role="button">
                    <i class='bx bx-calendar-event'></i>
                    <span class="text">Shift Scheduling</span>
                </a>
                <div class="sidebar-dropdown collapse" id="pssMenu">
                    <a href="../../PSS/shift_calendar_management/scm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i>
                        <span>Shift Calendar Management</span>
                    </a>
                    <a href="../../PSS/personel_roster/pr.php" class="sidebar-dropdown-link">
                         <i class='bx bx-group'></i>
                        <span>Personnel Roster</span>
                    </a>
                    <a href="../../PSS/shift_assignment/sa.php" class="sidebar-dropdown-link">
                           <i class='bx bx-task'></i>
                        <span>Shift Assignment</span>
                    </a>
                     <a href="../../PSS/leave_and_absence_management/laam.php" class="sidebar-dropdown-link">
                            <i class='bx bx-user-x'></i>
                        <span>Leave and Absence Management</span>
                    </a>
                    <a href="../../PSS/notifications_and_alert/naa.php" class="sidebar-dropdown-link">
                            <i class='bx bx-bell'></i>
                        <span>Notifications and Alerts</span>
                    </a>
                     <a href="../../PSS/reporting_and_logs/ral.php" class="sidebar-dropdown-link">
                            <i class='bx bx-bell'></i>
                        <span>Reporting & Logs</span>
                    </a>
                </div>
                
                
               <!-- Training and Certification Records -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#tcrMenu" role="button">
                    <i class='bx bx-certification'></i>
                    <span class="text">Training and Certification <br>Records</span>
                </a>
                <div class="sidebar-dropdown collapse" id="tcrMenu">
                    <a href="../../TCR/personnel_training_profile/ptr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-reader'></i>
                        <span>Personnel Training Profiles</span>
                    </a>
                    <a href="../../TCR/training_course_management/tcm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
                    </a>
                    <a href="../../TCT/training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link">
                       <i class='bx bx-calendar'></i>
        <span>Training Calendar and Scheduling</span>
                    </a>
                    <a href="../../TCR/certification_tracking/ct.php" class="sidebar-dropdown-link">
                       <i class='bx bx-badge-check'></i>
        <span>Certification Tracking</span>
                    </a>
                      <a href="../../TCR/training_compliance_monitoring/tcm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-shield'></i>
        <span>Training Compliance Monitoring</span>
                    </a>
                     <a href="../..TCR/evaluation_and_assessment_recoreds/eaar.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
                    </a>
                    <a href="../..TCR/reporting_and_auditlogs/ral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
        <span>Reporting and Audit Logs</span>
                </div>
                
            <!-- Fire Inspection and Compliance Records -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#ficrMenu" role="button">
                    <i class='bx bx-clipboard'></i>
                    <span class="text">Inspection & Compliance</span>
                </a>
                <div class="sidebar-dropdown collapse" id="ficrMenu">
                    <a href="../../FICR/establishment_registry/er.php" class="sidebar-dropdown-link">
                           <i class='bx bx-building-house'></i>
                        <span>Establishment/Property Registry</span>
                    </a>
                    <a href="../../FICR/inspection_scheduling_and_assignment/isaa.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar-event'></i>
                        <span>Inspection Scheduling and Assignment</span>
                    </a>
                    <a href="../../FICR/inspection_checklist_management/icm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-list-check'></i>
                        <span>Inspection Checklist Management</span>
                    </a>
                    <a href="../../FICR/violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link">
                           <i class='bx bx-shield-x'></i>
                        <span>Violation and Compliance Tracking</span>
                    </a>
                    <a href="../../FICR/clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link">
                          <i class='bx bx-file'></i>
                        <span>Clearance and Certification Management</span>
                    </a>
                     <a href="../../FICR/reporting_and_analytics/raa.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <span>Reporting and Analytics</span>
                    </a>
                </div>
                
                 <!-- Post-Incident Analysis and Reporting -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#piarMenu" role="button">
                    <i class='bx bx-analyse'></i>
                    <span class="text">Post-Incident Analysis</span>
                </a>
                <div class="sidebar-dropdown collapse" id="piarMenu">
                    <a href="../../PIAR/incident_summary_documentation/isd.php" class="sidebar-dropdown-link">
<i class='bx bx-file'></i>
    <span>Incident Summary Documentation</span>
                    </a>
                    <a href="../../PIAR/response_timeline_tracking/rtt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i>
    <span>Response Timeline Tracking</span>
                    </a>
                     <a href="../../PIAR/personnel_and_unit_involvement/paui.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
    <span>Personnel and Unit Involvement</span>
                    </a>
                     <a href="../../PIAR/cause_and_origin_investigation/caoi.php" class="sidebar-dropdown-link">
                       <i class='bx bx-search-alt'></i>
    <span>Cause and Origin Investigation</span>
                    </a>
                       <a href="../../PIAR/damage_assessment/da.php" class="sidebar-dropdown-link">
                      <i class='bx bx-building-house'></i>
    <span>Damage Assessment</span>
                    </a>
                       <a href="../../PIAR/action_review_and_lessons_learned/arall.php" class="sidebar-dropdown-link">
                     <i class='bx bx-refresh'></i>
    <span>Action Review and Lessons Learned</span>
                    </a>
                     <a href="../../PIAR/report_generation_and_archiving/rgaa.php" class="sidebar-dropdown-link">
                     <i class='bx bx-archive'></i>
    <span>Report Generation and Archiving</span>
                    </a>
                </div>
                
                <div class="sidebar-section">System</div>
                
                <a href="settings.php" class="sidebar-link">
                    <i class='bx bx-cog'></i>
                    <span class="text">Settings</span>
                </a>
                
                <a href="help.php" class="sidebar-link">
                    <i class='bx bx-help-circle'></i>
                    <span class="text">Help & Support</span>
                </a>
                
                <a href="logout.php" class="sidebar-link">
                    <i class='bx bx-log-out'></i>
                    <span class="text">Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header animate-fade-in">
                <div class="page-title">
                    <h1>Incident Intake</h1>
                    <p>Report new incidents and emergencies in Quezon City.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Toast Notifications -->
            <?php if ($success_message): ?>
            <div class="toast-container">
                <div class="toast toast-success animate-slide-in">
                    <i class='bx bx-check-circle'></i>
                    <div class="toast-content">
                        <div class="toast-title">Success</div>
                        <div class="toast-message"><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="toast-container">
                <div class="toast toast-error animate-slide-in">
                    <i class='bx bx-error-circle'></i>
                    <div class="toast-content">
                        <div class="toast-title">Error</div>
                        <div class="toast-message"><?php echo htmlspecialchars($error_message); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- AI Recommendations -->
            <?php if ($ai_recommendations): ?>
            <div class="ai-recommendation animate-fade-in">
                <div class="ai-header">
                    <i class='bx bx-brain ai-icon'></i>
                    <h3 class="mb-0">AI Recommendations</h3>
                </div>
                <p class="mb-3">Intelligent Emergency Allocation System has analyzed this incident and provided the following recommendations:</p>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Predicted Response Time</h6>
                            <div class="prediction-value"><?php echo $ai_recommendations['predicted_response_time']; ?> min</div>
                            <small>Based on traffic patterns and unit locations</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Risk Assessment</h6>
                            <div class="prediction-value"><?php echo ucfirst($ai_recommendations['risk_level']); ?></div>
                            <div class="risk-indicator">
                                <div class="risk-marker" style="left: <?php echo $ai_recommendations['risk_score'] * 100; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Recommended Units</h6>
                            <div class="prediction-value"><?php echo $ai_recommendations['recommended_units_count']; ?></div>
                            <small>Optimal resource allocation</small>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($ai_recommendations['recommended_units'])): ?>
                <div class="unit-recommendation">
                    <h6>Recommended Unit Types</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($ai_recommendations['recommended_units'] as $unit): ?>
                            <span class="badge bg-light text-dark"><?php echo $unit; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($ai_recommendations['notes'])): ?>
                <div class="unit-recommendation">
                    <h6>Additional Notes</h6>
                    <p class="mb-0"><?php echo $ai_recommendations['notes']; ?></p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="../unit_assignment/ua.php?incident_id=<?php echo $new_incident_id; ?>" class="btn btn-light">
                        <i class='bx bx-group'></i> Assign Units Now
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Predictive Analytics Insights -->
            <?php if ($predictive_insights): ?>
            <div class="predictive-insights animate-fade-in">
                <div class="ai-header">
                    <i class='bx bx-trending-up ai-icon'></i>
                    <h3 class="mb-0">Predictive Analytics Insights</h3>
                </div>
                <p class="mb-3">Advanced AI analysis based on historical data and patterns:</p>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Historical Similarity</h6>
                            <div class="prediction-value"><?php echo $predictive_insights['similarity_score']; ?>%</div>
                            <small>Match with past incidents</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Pattern Recognition</h6>
                            <div class="prediction-value"><?php echo ucfirst($predictive_insights['pattern_type']); ?></div>
                            <small>Identified incident pattern</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-prediction">
                            <h6>Trend Analysis</h6>
                            <div class="prediction-value">
                                <?php echo $predictive_insights['trend_direction']; ?>
                                <span class="trend-indicator <?php echo $predictive_insights['trend_direction'] === 'Increasing' ? 'trend-up' : 'trend-down'; ?>">
                                    <i class='bx bx-<?php echo $predictive_insights['trend_direction'] === 'Increasing' ? 'up-arrow' : 'down-arrow'; ?>'></i>
                                    <?php echo $predictive_insights['trend_percentage']; ?>%
                                </span>
                            </div>
                            <small>Compared to last month</small>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($predictive_insights['similar_incidents'])): ?>
                <div class="unit-recommendation">
                    <h6>Similar Historical Incidents</h6>
                    <?php foreach ($predictive_insights['similar_incidents'] as $incident): ?>
                        <div class="similar-incident">
                            <strong>#<?php echo $incident['id']; ?></strong>: 
                            <?php echo $incident['type']; ?> in <?php echo $incident['barangay']; ?> 
                            (<?php echo $incident['date']; ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($predictive_insights['recommendations'])): ?>
                <div class="unit-recommendation">
                    <h6>Strategic Recommendations</h6>
                    <ul class="mb-0">
                        <?php foreach ($predictive_insights['recommendations'] as $recommendation): ?>
                            <li><?php echo $recommendation; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Incident Intake Content -->
            <div class="dashboard-content">
                <div class="row">
                    <div class="col-12">
                        <div class="incident-form-container animate-fade-in">
                            <form method="POST" action="" id="incidentForm">
                                <!-- Incident Details Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-info-circle'></i>
                                        Incident Details
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Incident Type <span class="text-danger">*</span></label>
                                                <select class="form-select" name="incident_type" required id="incident_type">
                                                    <option value="">Select incident type</option>
                                                    <?php foreach ($incident_types as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type['incident_type']); ?>">
                                                            <?php echo htmlspecialchars($type['incident_type']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="Fire">Fire</option>
                                                    <option value="Medical Emergency">Medical Emergency</option>
                                                    <option value="Rescue">Rescue</option>
                                                    <option value="Hazardous Materials">Hazardous Materials</option>
                                                    <option value="Traffic Accident">Traffic Accident</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Priority <span class="text-danger">*</span></label>
                                                <select class="form-select" name="priority" required id="priority">
                                                    <option value="low">Low</option>
                                                    <option value="medium" selected>Medium</option>
                                                    <option value="high">High</option>
                                                    <option value="critical">Critical</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                                <select class="form-select" name="barangay" required id="barangay">
                                                    <option value="">Select barangay</option>
                                                    <?php foreach ($barangays as $barangay): ?>
                                                        <option value="<?php echo htmlspecialchars($barangay['barangay']); ?>">
                                                            <?php echo htmlspecialchars($barangay['barangay']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="Commonwealth">Commonwealth</option>
                                                    <option value="Batasan Hills">Batasan Hills</option>
                                                    <option value="Payatas">Payatas</option>
                                                    <option value="Bagong Silangan">Bagong Silangan</option>
                                                    <option value="Holy Spirit">Holy Spirit</option>
                                                    <option value="Alicia">Alicia</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="location" placeholder="Enter exact location" required id="location">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="description" rows="3" placeholder="Provide detailed description of the incident" required onkeyup="updateCharacterCount(this, 'desc-count')" id="description"></textarea>
                                        <div class="character-count" id="desc-count">0 characters</div>
                                    </div>
                                </div>
                                
                                <!-- Casualty Information Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-plus-medical'></i>
                                        Casualty Information
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Injuries</label>
                                                <input type="number" class="form-control" name="injuries" value="0" min="0" id="injuries">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Fatalities</label>
                                                <input type="number" class="form-control" name="fatalities" value="0" min="0" id="fatalities">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">People Trapped</label>
                                                <input type="number" class="form-control" name="people_trapped" value="0" min="0" id="people_trapped">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hazardous_materials" id="hazardousCheck">
                                            <label class="form-check-label" for="hazardousCheck">
                                                Hazardous Materials Involved
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Location Mapping Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-map'></i>
                                        Location Mapping
                                    </div>
                                    <div class="map-preview">
                                        <div class="text-center">
                                            <i class='bx bx-map-alt' style="font-size: 3rem;"></i>
                                            <p class="mt-2">Map integration will be available soon</p>
                                            <small class="text-muted">Coordinates will be automatically captured</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- AI Prediction Preview -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-brain'></i>
                                        AI Prediction Preview
                                        <span id="predictionLoading" style="display: none;" class="loading-spinner"></span>
                                    </div>
                                    <div class="alert alert-info">
                                        <div class="d-flex align-items-center">
                                            <i class='bx bx-info-circle me-2'></i>
                                            <span>Complete the form to see AI-powered predictions for resource allocation and response time</span>
                                        </div>
                                    </div>
                                    <div id="ai-preview" class="p-3 border rounded" style="display: none;">
                                        <h6>Estimated Impact:</h6>
                                        <div id="risk-indicator-preview" class="risk-indicator mb-2">
                                            <div id="risk-marker-preview" class="risk-marker"></div>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Low</span>
                                            <span id="risk-level-preview">Medium</span>
                                            <span>High</span>
                                        </div>
                                        <div class="mt-3">
                                            <span id="units-preview">0 units</span> recommended | 
                                            <span id="time-preview">0 min</span> estimated response
                                        </div>
                                        
                                        <!-- Advanced Predictive Analytics -->
                                        <div id="advanced-predictions" class="mt-3 p-2 border-top" style="display: none;">
                                            <h6 class="mt-2">Predictive Analytics:</h6>
                                            <div id="similarity-score" class="small"></div>
                                            <div id="pattern-type" class="small"></div>
                                            <div id="trend-analysis" class="small"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Section -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-text">
                                        Fields marked with <span class="text-danger">*</span> are required
                                    </div>
                                    <button type="submit" name="create_incident" class="btn btn-primary btn-lg">
                                        <i class='bx bx-plus'></i> Create Incident Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Incidents -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Recent Incidents</h5>
                                <a href="reporting.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Reported</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $recent_incidents = $dbManager->fetchAll("ird", "
                                                    SELECT * FROM incidents 
                                                    ORDER BY created_at DESC 
                                                    LIMIT 5
                                                ");
                                            } catch (Exception $e) {
                                                $recent_incidents = [];
                                            }
                                            ?>
                                            
                                            <?php if (count($recent_incidents) > 0): ?>
                                                <?php foreach ($recent_incidents as $incident): ?>
                                                    <tr>
                                                        <td>#<?php echo $incident['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                        <td><?php echo htmlspecialchars($incident['barangay']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $priority_class = '';
                                                            switch ($incident['priority']) {
                                                                case 'low': $priority_class = 'bg-secondary'; break;
                                                                case 'medium': $priority_class = 'bg-info'; break;
                                                                case 'high': $priority_class = 'bg-warning'; break;
                                                                case 'critical': $priority_class = 'bg-danger'; break;
                                                                default: $priority_class = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $priority_class; ?>"><?php echo ucfirst($incident['priority']); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch ($incident['status']) {
                                                                case 'pending': $status_class = 'bg-secondary'; break;
                                                                case 'dispatched': $status_class = 'bg-primary'; break;
                                                                case 'in_progress': $status_class = 'bg-warning'; break;
                                                                case 'resolved': $status_class = 'bg-success'; break;
                                                                default: $status_class = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?></span>
                                                        </td>
                                                        <td><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">No incidents found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update character count
        function updateCharacterCount(textarea, countElementId) {
            const count = textarea.value.length;
            document.getElementById(countElementId).textContent = count + ' characters';
        }
        
        // Function to simulate AI prediction preview
        function updateAIPreview() {
            const incidentType = document.getElementById('incident_type').value;
            const priority = document.getElementById('priority').value;
            const barangay = document.getElementById('barangay').value;
            const injuries = parseInt(document.getElementById('injuries').value) || 0;
            const fatalities = parseInt(document.getElementById('fatalities').value) || 0;
            const peopleTrapped = parseInt(document.getElementById('people_trapped').value) || 0;
            const hazardous = document.getElementById('hazardousCheck').checked;
            
            // Show AI preview if basic info is provided
            if (incidentType && barangay) {
                document.getElementById('ai-preview').style.display = 'block';
                
                // Calculate risk score (simplified)
                let riskScore = 0.3; // Base score
                
                if (priority === 'high') riskScore += 0.3;
                if (priority === 'critical') riskScore += 0.4;
                
                if (injuries > 0) riskScore += Math.min(0.2, injuries * 0.05);
                if (fatalities > 0) riskScore += Math.min(0.3, fatalities * 0.1);
                if (peopleTrapped > 0) riskScore += Math.min(0.2, peopleTrapped * 0.07);
                if (hazardous) riskScore += 0.2;
                
                riskScore = Math.min(1, riskScore);
                
                // Update risk indicator
                document.getElementById('risk-marker-preview').style.left = (riskScore * 100) + '%';
                
                // Update risk level text
                let riskLevel = 'Low';
                if (riskScore > 0.7) riskLevel = 'High';
                else if (riskScore > 0.4) riskLevel = 'Medium';
                
                document.getElementById('risk-level-preview').textContent = riskLevel;
                
                // Calculate recommended units and response time
                let recommendedUnits = Math.ceil(riskScore * 5);
                let responseTime = Math.max(5, 30 - (riskScore * 25));
                
                document.getElementById('units-preview').textContent = recommendedUnits + ' units';
                document.getElementById('time-preview').textContent = responseTime.toFixed(0);
                
                // Show advanced predictions if we have more data
                if (injuries > 0 || fatalities > 0 || peopleTrapped > 0) {
                    document.getElementById('advanced-predictions').style.display = 'block';
                    
                    // Simulate advanced analytics
                    let similarityScore = Math.floor(70 + (Math.random() * 25));
                    let patternType = ['Cluster', 'Sporadic', 'Recurring', 'Isolated'][Math.floor(Math.random() * 4)];
                    let trendDirection = ['Increasing', 'Decreasing', 'Stable'][Math.floor(Math.random() * 3)];
                    let trendPercentage = Math.floor(Math.random() * 30);
                    
                    document.getElementById('similarity-score').textContent = 
                        'Similarity Score: ' + similarityScore + '% match with historical incidents';
                    document.getElementById('pattern-type').textContent = 
                        'Pattern: ' + patternType;
                    document.getElementById('trend-analysis').textContent = 
                        'Trend: ' + trendDirection + ' by ' + trendPercentage + '% compared to last month';
                } else {
                    document.getElementById('advanced-predictions').style.display = 'none';
                }
                
                // Make AJAX call for real predictions
                fetchPredictions();
            } else {
                document.getElementById('ai-preview').style.display = 'none';
            }
        }
        
        // Function to fetch real predictions from the server
        function fetchPredictions() {
            const incidentType = document.getElementById('incident_type').value;
            const priority = document.getElementById('priority').value;
            const barangay = document.getElementById('barangay').value;
            const injuries = parseInt(document.getElementById('injuries').value) || 0;
            const fatalities = parseInt(document.getElementById('fatalities').value) || 0;
            const peopleTrapped = parseInt(document.getElementById('people_trapped').value) || 0;
            const hazardous = document.getElementById('hazardousCheck').checked ? 1 : 0;
            
            if (!incidentType || !barangay) return;
            
            // Show loading indicator
            document.getElementById('predictionLoading').style.display = 'inline-block';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('get_predictions', 'true');
            formData.append('incident_type', incidentType);
            formData.append('priority', priority);
            formData.append('barangay', barangay);
            formData.append('injuries', injuries);
            formData.append('fatalities', fatalities);
            formData.append('people_trapped', peopleTrapped);
            formData.append('hazardous_materials', hazardous);
            
            // Make AJAX request
            fetch('ii.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update with real predictions
                    const predictions = data.predictions;
                    
                    // Update risk indicator
                    document.getElementById('risk-marker-preview').style.left = (predictions.risk_score * 100) + '%';
                    document.getElementById('risk-level-preview').textContent = predictions.risk_level;
                    
                    // Update units and time
                    document.getElementById('units-preview').textContent = 
                        predictions.recommended_units_count + ' units';
                    document.getElementById('time-preview').textContent = 
                        predictions.predicted_response_time + ' min';
                    
                    // Show advanced predictions
                    document.getElementById('advanced-predictions').style.display = 'block';
                    document.getElementById('similarity-score').textContent = 
                        'Similarity Score: ' + predictions.similarity_score + '% match with historical incidents';
                    document.getElementById('pattern-type').textContent = 
                        'Pattern: ' + predictions.pattern_type;
                    document.getElementById('trend-analysis').textContent = 
                        'Trend: ' + predictions.trend_direction + ' by ' + predictions.trend_percentage + '% compared to last month';
                }
            })
            .catch(error => {
                console.error('Error fetching predictions:', error);
            })
            .finally(() => {
                // Hide loading indicator
                document.getElementById('predictionLoading').style.display = 'none';
            });
        }
        
        // Add event listeners to form fields
        document.getElementById('incident_type').addEventListener('change', updateAIPreview);
        document.getElementById('priority').addEventListener('change', updateAIPreview);
        document.getElementById('barangay').addEventListener('change', updateAIPreview);
        document.getElementById('injuries').addEventListener('input', updateAIPreview);
        document.getElementById('fatalities').addEventListener('input', updateAIPreview);
        document.getElementById('people_trapped').addEventListener('input', updateAIPreview);
        document.getElementById('hazardousCheck').addEventListener('change', updateAIPreview);
        document.getElementById('description').addEventListener('input', function() {
            updateCharacterCount(this, 'desc-count');
        });
        
        // Initialize character count
        document.querySelectorAll('textarea').forEach(textarea => {
            if (textarea.id === 'description') {
                updateCharacterCount(textarea, 'desc-count');
            }
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 1000);
            });
        }, 5000);
    </script>
</body>
</html>