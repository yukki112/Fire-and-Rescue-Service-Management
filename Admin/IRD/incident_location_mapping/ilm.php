<?php
session_start();

// Include the Database Manager first
require_once 'config/database_manager.php';

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

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'ird';
$active_submodule = 'ilm';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all incidents for mapping across all of Quezon City
    $incidents = $dbManager->fetchAll("ird", "
        SELECT i.*, COUNT(d.id) as dispatch_count 
        FROM incidents i 
        LEFT JOIN dispatches d ON i.id = d.incident_id 
        WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ");
    
    // Get all units for mapping across all of Quezon City
    $units = $dbManager->fetchAll("ird", "
        SELECT * FROM units 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY unit_name
    ");
    
    // Get all hospitals for mapping across all of Quezon City
    $hospitals = $dbManager->fetchAll("ird", "
        SELECT * FROM hospitals 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY name
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $incidents = [];
    $units = [];
    $hospitals = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process incident selection
$selected_incident = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['incident_id'])) {
        try {
            $incident_id = intval($_POST['incident_id']);
            $selected_incident = $dbManager->fetch("ird", "
                SELECT i.*, u.first_name, u.last_name 
                FROM incidents i 
                LEFT JOIN users u ON i.reported_by = u.id 
                WHERE i.id = ?
            ", [$incident_id]);
        } catch (Exception $e) {
            error_log("Error fetching incident details: " . $e->getMessage());
            $_SESSION['error_message'] = "Error loading incident details.";
        }
    } elseif (isset($_POST['clear_selection'])) {
        // Clear the selection
        $selected_incident = null;
    }
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// MapTiler API Key
$maptiler_api_key = 'gZtMDh9pV46hFgly6xCT';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        .map-container {
            height: 600px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .sidebar-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 600px;
            overflow-y: auto;
        }
        .incident-card {
            border-left: 4px solid #dc3545;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .incident-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .incident-card.medium { border-left-color: #fd7e14; }
        .incident-card.high { border-left-color: #ffc107; }
        .incident-card.critical { border-left-color: #dc3545; }
        .incident-card.resolved { border-left-color: #198754; }
        .filter-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .legend {
            position: absolute;
            bottom: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 5px;
            border-radius: 50%;
        }
        .incident-details {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        .detail-label {
            font-weight: 600;
            min-width: 120px;
        }
        .leaflet-popup-content {
            margin: 15px;
        }
        .popup-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .popup-details {
            font-size: 0.9rem;
        }
        .district-boundary {
            stroke: #ff6b35;
            stroke-width: 3;
            stroke-dasharray: 10, 10;
            fill: none;
        }
        
        /* Custom marker styles */
        .custom-div-icon {
            background: transparent !important;
            border: none !important;
        }
        
        .pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -20px 0 0 -15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .pin::after {
            content: '';
            width: 12px;
            height: 12px;
            margin: 9px 0 0 9px;
            background: #fff;
            position: absolute;
            border-radius: 50%;
        }
        
        .critical-pin {
            background: #dc3545;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.8);
        }
        
        .high-pin {
            background: #ffc107;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.8);
        }
        
        .medium-pin {
            background: #fd7e14;
            box-shadow: 0 0 10px rgba(253, 126, 20, 0.8);
        }
        
        .low-pin {
            background: #6c757d;
            box-shadow: 0 0 10px rgba(108, 117, 125, 0.8);
        }
        
        .resolved-pin {
            background: #198754;
            box-shadow: 0 0 10px rgba(25, 135, 84, 0.8);
        }
        
        .unit-pin {
            background: #0d6efd;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.8);
        }
        
        .hospital-pin {
            background: #6610f2;
            box-shadow: 0 0 10px rgba(102, 16, 242, 0.8);
        }
        
        .pin-icon {
            transform: rotate(45deg);
            color: white;
            font-size: 12px;
            position: relative;
            z-index: 1;
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
                   
                    <a href="../incident_intake/ii.php" class="sidebar-dropdown-link">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="ilm.php" class="sidebar-dropdown-link active">
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
                    <h1>Incident Location Mapping - Quezon City</h1>
                    <p>Track and visualize incidents, response units, and resources across all of Quezon City.</p>
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
            
            <!-- Incident Location Mapping Content -->
            <div class="dashboard-content">
                <div class="row">
                    <!-- Map Column -->
                    <div class="col-md-8">
                        <div class="map-container animate-fade-in" id="map">
                            <!-- Map will be rendered here -->
                        </div>
                    </div>
                    
                    <!-- Sidebar Column -->
                    <div class="col-md-4">
                        <div class="sidebar-panel animate-fade-in">
                            <div class="filter-section">
                                <h5>Filters</h5>
                                <div class="mb-3">
                                    <label class="form-label">Incident Type</label>
                                    <select class="form-select" id="typeFilter">
                                        <option value="all">All Types</option>
                                        <option value="Fire">Fire</option>
                                        <option value="Medical Emergency">Medical Emergency</option>
                                        <option value="Rescue">Rescue</option>
                                        <option value="Traffic Accident">Traffic Accident</option>
                                        <option value="Hazardous Materials">Hazardous Materials</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" id="priorityFilter">
                                        <option value="all">All Priorities</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="statusFilter">
                                        <option value="all">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="dispatched">Dispatched</option>
                                        <option value="responding">Responding</option>
                                        <option value="resolved">Resolved</option>
                                    </select>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="showUnits" checked>
                                    <label class="form-check-label" for="showUnits">Show Response Units</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="showHospitals" checked>
                                    <label class="form-check-label" for="showHospitals">Show Hospitals</label>
                                </div>
                            </div>
                            
                            <div>
                                <h5>Recent Incidents</h5>
                                <?php if (count($incidents) > 0): ?>
                                    <?php foreach ($incidents as $incident): ?>
                                    <div class="incident-card <?php echo $incident['priority'] . ' ' . $incident['status']; ?>" 
                                         data-id="<?php echo $incident['id']; ?>"
                                         data-lat="<?php echo $incident['latitude']; ?>"
                                         data-lng="<?php echo $incident['longitude']; ?>"
                                         data-type="<?php echo $incident['incident_type']; ?>"
                                         data-priority="<?php echo $incident['priority']; ?>"
                                         data-status="<?php echo $incident['status']; ?>">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($incident['incident_type']); ?></h6>
                                                <span class="badge bg-<?php 
                                                    echo $incident['priority'] == 'critical' ? 'danger' : 
                                                        ($incident['priority'] == 'high' ? 'warning' : 
                                                        ($incident['priority'] == 'medium' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($incident['priority']); ?>
                                                </span>
                                            </div>
                                            <p class="card-text mb-1 small"><?php echo htmlspecialchars($incident['barangay']); ?></p>
                                            <p class="card-text small text-muted">
                                                <?php echo date('M j, H:i', strtotime($incident['created_at'])); ?>
                                                <?php if ($incident['dispatch_count'] > 0): ?>
                                                    • <?php echo $incident['dispatch_count']; ?> unit(s) dispatched
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class='bx bx-map-alt' style="font-size: 3rem; color: #6c757d;"></i>
                                        <p class="mt-2">No incidents with location data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Incident Details -->
                <?php if ($selected_incident): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Incident Details: #<?php echo $selected_incident['id']; ?></h5>
                                <form method="POST" action="" class="d-inline">
                                    <button type="submit" name="clear_selection" class="btn btn-sm btn-outline-secondary">Clear Selection</button>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-row">
                                            <span class="detail-label">Type:</span>
                                            <span><?php echo htmlspecialchars($selected_incident['incident_type']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Priority:</span>
                                            <span class="badge bg-<?php 
                                                echo $selected_incident['priority'] == 'critical' ? 'danger' : 
                                                    ($selected_incident['priority'] == 'high' ? 'warning' : 
                                                    ($selected_incident['priority'] == 'medium' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($selected_incident['priority']); ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Status:</span>
                                            <span class="badge bg-<?php 
                                                echo $selected_incident['status'] == 'resolved' ? 'success' : 
                                                    ($selected_incident['status'] == 'responding' ? 'info' : 
                                                    ($selected_incident['status'] == 'dispatched' ? 'primary' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($selected_incident['status']); ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Barangay:</span>
                                            <span><?php echo htmlspecialchars($selected_incident['barangay']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Location:</span>
                                            <span><?php echo htmlspecialchars($selected_incident['location']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-row">
                                            <span class="detail-label">Reported By:</span>
                                            <span>
                                                <?php 
                                                if ($selected_incident['first_name']) {
                                                    echo htmlspecialchars($selected_incident['first_name'] . ' ' . $selected_incident['last_name']);
                                                } else {
                                                    echo 'Unknown';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Date & Time:</span>
                                            <span><?php echo date('M j, Y H:i', strtotime($selected_incident['incident_date'] . ' ' . $selected_incident['incident_time'])); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Injuries:</span>
                                            <span><?php echo $selected_incident['injuries']; ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Fatalities:</span>
                                            <span><?php echo $selected_incident['fatalities']; ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">People Trapped:</span>
                                            <span><?php echo $selected_incident['people_trapped']; ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Hazardous Materials:</span>
                                            <span><?php echo $selected_incident['hazardous_materials'] ? 'Yes' : 'No'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="detail-label">Description:</div>
                                    <p><?php echo htmlspecialchars($selected_incident['description']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <footer class="dashboard-footer">
                <div class="footer-content">
                    <div class="footer-logo">
                        <img src="img/frsmse.png" alt="Quezon City Logo">
                        <span>Quezon City Fire & Rescue Service Management 2025</span>
                    </div>
                    <div class="footer-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Contact Us</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map with a view focused on all of Quezon City
        const map = L.map('map').setView([14.6760, 121.0437], 12);
        
        // Add MapTiler satellite tiles with your API key
        L.tileLayer('https://api.maptiler.com/maps/satellite/{z}/{x}/{y}.jpg?key=<?php echo $maptiler_api_key; ?>', {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
            maxZoom: 20
        }).addTo(map);
        
        // Define the approximate boundaries of Quezon City
        const quezonCityBounds = [
            [14.5700, 120.9500], // Southwest coordinates
            [14.7800, 121.1500]  // Northeast coordinates
        ];
        
        // Create a rectangle to show the city boundaries
        const cityArea = L.rectangle(quezonCityBounds, {
            color: '#ff6b35',
            weight: 3,
            dashArray: '10, 10',
            fillOpacity: 0.1,
            className: 'district-boundary'
        }).addTo(map);
        
        // Create layer groups
        const incidentMarkers = L.layerGroup().addTo(map);
        const unitMarkers = L.layerGroup().addTo(map);
        const hospitalMarkers = L.layerGroup().addTo(map);
        
        // Define custom pin icons
        function createPinIcon(priority, status, type) {
            let pinClass = '';
            
            if (type === 'unit') {
                pinClass = 'unit-pin';
            } else if (type === 'hospital') {
                pinClass = 'hospital-pin';
            } else {
                // For incidents, use priority-based colors
                if (status === 'resolved') {
                    pinClass = 'resolved-pin';
                } else {
                    switch(priority) {
                        case 'critical':
                            pinClass = 'critical-pin';
                            break;
                        case 'high':
                            pinClass = 'high-pin';
                            break;
                        case 'medium':
                            pinClass = 'medium-pin';
                            break;
                        case 'low':
                            pinClass = 'low-pin';
                            break;
                        default:
                            pinClass = 'low-pin';
                    }
                }
            }
            
            return L.divIcon({
                html: `<div class="pin ${pinClass}"><i class="bx ${type === 'unit' ? 'bx-group' : (type === 'hospital' ? 'bx-plus-medical' : 'bx-alarm')} pin-icon"></i></div>`,
                className: 'custom-div-icon',
                iconSize: [30, 42],
                iconAnchor: [15, 42]
            });
        }
        
        // Add incidents to map
        <?php foreach ($incidents as $incident): ?>
        <?php if ($incident['latitude'] && $incident['longitude']): ?>
        const marker<?php echo $incident['id']; ?> = L.marker([<?php echo $incident['latitude']; ?>, <?php echo $incident['longitude']; ?>], {
            icon: createPinIcon('<?php echo $incident['priority']; ?>', '<?php echo $incident['status']; ?>', 'incident')
        }).addTo(incidentMarkers);
        
        marker<?php echo $incident['id']; ?>.bindPopup(`
            <div class="popup-title"><?php echo addslashes($incident['incident_type']); ?></div>
            <div class="popup-details">
                <strong>Priority:</strong> <?php echo ucfirst($incident['priority']); ?><br>
                <strong>Status:</strong> <?php echo ucfirst($incident['status']); ?><br>
                <strong>Location:</strong> <?php echo addslashes($incident['location']); ?>, <?php echo addslashes($incident['barangay']); ?><br>
                <strong>Reported:</strong> <?php echo date('M j, H:i', strtotime($incident['created_at'])); ?>
            </div>
            <form method="POST" action="" class="mt-2">
                <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                <button type="submit" class="btn btn-sm btn-primary">View Details</button>
            </form>
        `);
        <?php endif; ?>
        <?php endforeach; ?>
        
        // Add units to map
        <?php foreach ($units as $unit): ?>
        <?php if ($unit['latitude'] && $unit['longitude']): ?>
        const unitMarker<?php echo $unit['id']; ?> = L.marker([<?php echo $unit['latitude']; ?>, <?php echo $unit['longitude']; ?>], {
            icon: createPinIcon('', '', 'unit')
        }).addTo(unitMarkers);
        
        unitMarker<?php echo $unit['id']; ?>.bindPopup(`
            <div class="popup-title"><?php echo addslashes($unit['unit_name']); ?></div>
            <div class="popup-details">
                <strong>Type:</strong> <?php echo addslashes($unit['unit_type']); ?><br>
                <strong>Station:</strong> <?php echo addslashes($unit['station']); ?><br>
                <strong>Barangay:</strong> <?php echo addslashes($unit['barangay']); ?><br>
                <strong>Status:</strong> <?php echo ucfirst($unit['status']); ?><br>
                <strong>Personnel:</strong> <?php echo $unit['personnel_count']; ?>
            </div>
        `);
        <?php endif; ?>
        <?php endforeach; ?>
        
        // Add hospitals to map
        <?php foreach ($hospitals as $hospital): ?>
        <?php if ($hospital['latitude'] && $hospital['longitude']): ?>
        const hospitalMarker<?php echo $hospital['id']; ?> = L.marker([<?php echo $hospital['latitude']; ?>, <?php echo $hospital['longitude']; ?>], {
            icon: createPinIcon('', '', 'hospital')
        }).addTo(hospitalMarkers);
        
        hospitalMarker<?php echo $hospital['id']; ?>.bindPopup(`
            <div class="popup-title"><?php echo addslashes($hospital['name']); ?></div>
            <div class="popup-details">
                <strong>Address:</strong> <?php echo addslashes($hospital['address']); ?><br>
                <strong>Barangay:</strong> <?php echo addslashes($hospital['barangay']); ?><br>
                <strong>Phone:</strong> <?php echo $hospital['phone']; ?><br>
                <strong>Emergency Contact:</strong> <?php echo $hospital['emergency_contact']; ?><br>
                <strong>Capacity:</strong> <?php echo $hospital['capacity']; ?><br>
                <strong>Specialties:</strong> <?php echo addslashes($hospital['specialties']); ?>
            </div>
        `);
        <?php endif; ?>
        <?php endforeach; ?>
        
        // Add map controls
        const mapControls = L.control({position: 'topright'});
        mapControls.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'map-controls');
            div.innerHTML = `
                <button class="btn btn-sm btn-light mb-1" onclick="map.locate({setView: true, maxZoom: 16})">
                    <i class='bx bx-current-location'></i> My Location
                </button>
                <br>
                <button class="btn btn-sm btn-light" onclick="fitToMarkers()">
                    <i class='bx bx-fullscreen'></i> View All
                </button>
                <br>
                <button class="btn btn-sm btn-light mt-1" onclick="focusOnCity()">
                    <i class='bx bx-map'></i> Focus on Quezon City
                </button>
            `;
            return div;
        };
        mapControls.addTo(map);
        
        // Add legend
        const legend = L.control({position: 'bottomright'});
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'legend');
            div.innerHTML = `
                <h6>Map Legend</h6>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #dc3545;"></div>
                    <span>Critical Incidents</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ffc107;"></div>
                    <span>High Priority</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #fd7e14;"></div>
                    <span>Medium Priority</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #6c757d;"></div>
                    <span>Low Priority</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #198754;"></div>
                    <span>Resolved</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #0d6efd;"></div>
                    <span>Response Units</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #6610f2;"></div>
                    <span>Hospitals</span>
                </div>
            `;
            return div;
        };
        legend.addTo(map);
        
        // Filter functionality
        document.getElementById('typeFilter').addEventListener('change', filterMarkers);
        document.getElementById('priorityFilter').addEventListener('change', filterMarkers);
        document.getElementById('statusFilter').addEventListener('change', filterMarkers);
        document.getElementById('showUnits').addEventListener('change', toggleLayer);
        document.getElementById('showHospitals').addEventListener('change', toggleLayer);
        
        function filterMarkers() {
            const type = document.getElementById('typeFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            // Filter incident cards
            document.querySelectorAll('.incident-card').forEach(card => {
                const cardType = card.getAttribute('data-type');
                const cardPriority = card.getAttribute('data-priority');
                const cardStatus = card.getAttribute('data-status');
                
                const typeMatch = type === 'all' || cardType === type;
                const priorityMatch = priority === 'all' || cardPriority === priority;
                const statusMatch = status === 'all' || cardStatus === status;
                
                if (typeMatch && priorityMatch && statusMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function toggleLayer() {
            const showUnits = document.getElementById('showUnits').checked;
            const showHospitals = document.getElementById('showHospitals').checked;
            
            if (showUnits) {
                map.addLayer(unitMarkers);
            } else {
                map.removeLayer(unitMarkers);
            }
            
            if (showHospitals) {
                map.addLayer(hospitalMarkers);
            } else {
                map.removeLayer(hospitalMarkers);
            }
        }
        
        // Fit map to show all markers
        function fitToMarkers() {
            const bounds = L.latLngBounds();
            
            // Add incident markers to bounds
            incidentMarkers.eachLayer(function(layer) {
                bounds.extend(layer.getLatLng());
            });
            
            // Add unit markers to bounds if visible
            if (map.hasLayer(unitMarkers)) {
                unitMarkers.eachLayer(function(layer) {
                    bounds.extend(layer.getLatLng());
                });
            }
            
            // Add hospital markers to bounds if visible
            if (map.hasLayer(hospitalMarkers)) {
                hospitalMarkers.eachLayer(function(layer) {
                    bounds.extend(layer.getLatLng());
                });
            }
            
            // Fit the map to the bounds with some padding
            map.fitBounds(bounds, {padding: [50, 50]});
        }
        
        // Focus on Quezon City
        function focusOnCity() {
            map.fitBounds(quezonCityBounds, {padding: [50, 50]});
        }
        
        // Handle incident card clicks
        document.querySelectorAll('.incident-card').forEach(card => {
            card.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const lat = parseFloat(this.getAttribute('data-lat'));
                const lng = parseFloat(this.getAttribute('data-lng'));
                
                // Submit form to select incident
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'incident_id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
                
                // Also center map on the incident
                map.setView([lat, lng], 16);
            });
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
        
        // Initialize the map view to show all of Quezon City
        focusOnCity();
    </script>
</body>
</html>