<?php
session_start();

// Include the Database Manager first
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'hwrm';
$active_submodule = 'water_source_status_monitoring';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all water sources from the database
    $water_sources = $dbManager->fetchAll("hwrm", "SELECT * FROM water_sources ORDER BY barangay, source_id");
    
    // Get unique barangays for filtering
    $barangays = $dbManager->fetchAll("hwrm", "SELECT DISTINCT barangay FROM water_sources ORDER BY barangay");
    
    // Get unique source types for filtering
    $source_types = $dbManager->fetchAll("hwrm", "SELECT DISTINCT source_type FROM water_sources ORDER BY source_type");
    
    // Get status counts for summary
    $status_counts = $dbManager->fetchAll("hwrm", 
        "SELECT status, COUNT(*) as count FROM water_sources GROUP BY status"
    );
    
    // Get recent inspections for status monitoring
    $recent_inspections = $dbManager->fetchAll("hwrm", 
        "SELECT ws.source_id, ws.name, ws.barangay, ws.status, 
                MAX(wsi.inspection_date) as last_inspection_date,
                wsi.condition, wsi.issues_found
         FROM water_sources ws
         LEFT JOIN water_source_inspections wsi ON ws.id = wsi.source_id
         GROUP BY ws.id
         ORDER BY last_inspection_date DESC
         LIMIT 10"
    );
    
    // Get maintenance records for status monitoring
    $recent_maintenance = $dbManager->fetchAll("hwrm", 
        "SELECT ws.source_id, ws.name, wsm.maintenance_type, wsm.maintenance_date, 
                wsm.description, wsm.parts_used
         FROM water_sources ws
         JOIN water_source_maintenance wsm ON ws.id = wsm.source_id
         ORDER BY wsm.maintenance_date DESC
         LIMIT 10"
    );
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $water_sources = [];
    $barangays = [];
    $source_types = [];
    $status_counts = [];
    $recent_inspections = [];
    $recent_maintenance = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process filter requests
$selected_barangay = $_GET['barangay'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';
$selected_type = $_GET['type'] ?? 'all';

// Filter water sources based on selection
$filtered_sources = $water_sources;
if ($selected_barangay !== 'all') {
    $filtered_sources = array_filter($filtered_sources, function($source) use ($selected_barangay) {
        return $source['barangay'] === $selected_barangay;
    });
}

if ($selected_status !== 'all') {
    $filtered_sources = array_filter($filtered_sources, function($source) use ($selected_status) {
        return $source['status'] === $selected_status;
    });
}

if ($selected_type !== 'all') {
    $filtered_sources = array_filter($filtered_sources, function($source) use ($selected_type) {
        return $source['source_type'] === $selected_type;
    });
}

// Prepare water source data for JavaScript
$sources_js = [];
foreach ($filtered_sources as $source) {
    $sources_js[] = [
        'id' => $source['id'],
        'source_id' => $source['source_id'],
        'name' => $source['name'],
        'source_type' => $source['source_type'],
        'location' => $source['location'],
        'latitude' => floatval($source['latitude']),
        'longitude' => floatval($source['longitude']),
        'capacity' => $source['capacity'],
        'pressure' => $source['pressure'],
        'flow_rate' => $source['flow_rate'],
        'status' => $source['status'],
        'barangay' => $source['barangay']
    ];
}

// Display error messages
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 500px;
        }
        #source-map {
            height: 100%;
            border-radius: 8px;
            z-index: 1;
        }
        .filters-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .stat-card.active {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .stat-card.maintenance {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        .stat-card.inactive {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .stat-card.low_flow {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        .source-info-window {
            padding: 10px;
            min-width: 250px;
        }
        .source-info-window h6 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .source-info-window p {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .status-active {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-maintenance {
            background-color: #fff3cd;
            color: #664d03;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-low_flow {
            background-color: #cfe2ff;
            color: #084298;
        }
        .legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.4);
        }
        .legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 0.7;
            border-radius: 50%;
        }
        .source-type-badge {
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }
        .badge-hydrant {
            background-color: #dc3545;
        }
        .badge-reservoir {
            background-color: #0d6efd;
        }
        .badge-lake {
            background-color: #198754;
        }
        .badge-river {
            background-color: #0dcaf0;
        }
        .badge-well {
            background-color: #6f42c1;
        }
        .badge-storage_tank {
            background-color: #fd7e14;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .indicator-active {
            background-color: #4caf50;
        }
        .indicator-maintenance {
            background-color: #ffc107;
        }
        .indicator-inactive {
            background-color: #f44336;
        }
        .indicator-low_flow {
            background-color: #2196f3;
        }
        .activity-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 100%;
        }
        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .condition-badge {
            font-size: 0.7rem;
        }
        .condition-excellent {
            background-color: #4caf50;
        }
        .condition-good {
            background-color: #8bc34a;
        }
        .condition-fair {
            background-color: #ffc107;
        }
        .condition-poor {
            background-color: #ff9800;
        }
        .condition-critical {
            background-color: #f44336;
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
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#irdMenu" role="button">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
            
                <div class="sidebar-dropdown collapse" id="irdMenu">
                   
                    <a href="../../IRD/incident_intake/ii.php" class="sidebar-dropdown-link">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="../../IRD/incident_location_mapping/ilm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-map'></i>
                        <span>Incident Location Mapping</span>
                    </a>
                    <a href="../../IRD/unit_assignment/ua.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="../../IRD/communication/comm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="../../IRD/status_monitoring/sm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i>
                        <span>Status Monitoring</span>
                    </a>
                    <a href="../../IRD/reporting/report.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
                <!-- Fire Station Inventory & Equipment Tracking -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse" id="fsietMenu">
                    <a href="../../FSIET/inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="../../FSIET/equipment_location_tracking/elt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="../../FSIET/maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../../FSIET/repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../../FSIET/inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Inventory Reports & Audit Logs</span>
                    </a>
                    
                </div>
                
                <!-- Hydrant and Water Resource Mapping -->
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="hwrmMenu">
                    <a href="../hydrant_resources_mapping/hrm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Hydrant resources mapping</span>
                    </a>
                      <a href="../water_source_database/wsd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Water Source Database</span>
                    </a>
                     <a href="../water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link active">
                       <i class='bx bx-droplet'></i>
                        <span>Water Source Status Monitoring</span>
                    </a>
                    <a href="../inspection_maintenance_records/imr.php" class="sidebar-dropdown-link">
                      <i class='bx bx-wrench'></i>
    <span> Inspection & Maintenance Records</span>
                    </a>
                    <a href="../reporting_analytics/ra.php" class="sidebar-dropdown-link">
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
                
                <a href="../settings/settings.php" class="sidebar-link">
                    <i class='bx bx-cog'></i>
                    <span class="text">Settings</span>
                </a>
                
                <a href="../help/help.php" class="sidebar-link">
                    <i class='bx bx-help-circle'></i>
                    <span class="text">Help & Support</span>
                </a>
                
                <a href="../logout.php" class="sidebar-link">
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
                    <h1>Water Source Status Monitoring</h1>
                    <p>Monitor real-time status of water sources across Quezon City.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class='bx bx-refresh'></i> Update Status
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Toast Notifications -->
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
            
            <!-- Water Source Status Monitoring Content -->
            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    $active_count = 0;
                    $maintenance_count = 0;
                    $inactive_count = 0;
                    $low_flow_count = 0;
                    
                    foreach ($status_counts as $status) {
                        switch ($status['status']) {
                            case 'active':
                                $active_count = $status['count'];
                                break;
                            case 'maintenance':
                                $maintenance_count = $status['count'];
                                break;
                            case 'inactive':
                                $inactive_count = $status['count'];
                                break;
                            case 'low_flow':
                                $low_flow_count = $status['count'];
                                break;
                        }
                    }
                    $total_count = $active_count + $maintenance_count + $inactive_count + $low_flow_count;
                    ?>
                    
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card active">
                                <div class="stat-number"><?php echo $active_count; ?></div>
                                <div class="stat-label">Active Sources</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card maintenance">
                                <div class="stat-number"><?php echo $maintenance_count; ?></div>
                                <div class="stat-label">Under Maintenance</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card inactive">
                                <div class="stat-number"><?php echo $inactive_count; ?></div>
                                <div class="stat-label">Inactive Sources</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card low_flow">
                                <div class="stat-number"><?php echo $low_flow_count; ?></div>
                                <div class="stat-label">Low Flow Sources</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="filters-container">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Filter by Barangay</label>
                                        <select class="form-select" name="barangay">
                                            <option value="all" <?php echo $selected_barangay === 'all' ? 'selected' : ''; ?>>All Barangays</option>
                                            <?php foreach ($barangays as $barangay): ?>
                                                <option value="<?php echo htmlspecialchars($barangay['barangay']); ?>" 
                                                    <?php echo $selected_barangay === $barangay['barangay'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($barangay['barangay']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Filter by Status</label>
                                        <select class="form-select" name="status">
                                            <option value="all" <?php echo $selected_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                            <option value="active" <?php echo $selected_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="maintenance" <?php echo $selected_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="inactive" <?php echo $selected_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="low_flow" <?php echo $selected_status === 'low_flow' ? 'selected' : ''; ?>>Low Flow</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Filter by Type</label>
                                        <select class="form-select" name="type">
                                            <option value="all" <?php echo $selected_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <?php foreach ($source_types as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type['source_type']); ?>" 
                                                    <?php echo $selected_type === $type['source_type'] ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(str_replace('_', ' ', $type['source_type'])); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class='bx bx-filter'></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Map -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="map-container animate-fade-in">
                            <div id="source-map"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="activity-card">
                            <h5 class="mb-3">Recent Inspections</h5>
                            <div class="activity-list">
                                <?php if (count($recent_inspections) > 0): ?>
                                    <?php foreach ($recent_inspections as $inspection): ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($inspection['source_id']); ?></strong>
                                                <span class="text-muted">- <?php echo htmlspecialchars($inspection['name']); ?></span>
                                            </div>
                                            <span class="badge condition-badge condition-<?php echo $inspection['condition'] ?? 'good'; ?>">
                                                <?php echo ucfirst($inspection['condition'] ?? 'Good'); ?>
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($inspection['barangay']); ?> • 
                                            <?php echo $inspection['last_inspection_date'] ? date('M j, Y', strtotime($inspection['last_inspection_date'])) : 'Never inspected'; ?>
                                        </div>
                                        <?php if (!empty($inspection['issues_found'])): ?>
                                        <div class="text-danger small mt-1">
                                            <i class='bx bx-error'></i> Issues: <?php echo htmlspecialchars(substr($inspection['issues_found'], 0, 50)); ?>...
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">No inspection records found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="activity-card">
                            <h5 class="mb-3">Recent Maintenance</h5>
                            <div class="activity-list">
                                <?php if (count($recent_maintenance) > 0): ?>
                                    <?php foreach ($recent_maintenance as $maintenance): ?>
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($maintenance['source_id']); ?></strong>
                                                <span class="text-muted">- <?php echo htmlspecialchars($maintenance['name']); ?></span>
                                            </div>
                                            <span class="badge bg-info"><?php echo ucfirst($maintenance['maintenance_type']); ?></span>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo $maintenance['maintenance_date'] ? date('M j, Y', strtotime($maintenance['maintenance_date'])) : 'No date'; ?>
                                        </div>
                                        <div class="small mt-1">
                                            <?php echo htmlspecialchars(substr($maintenance['description'], 0, 60)); ?>...
                                        </div>
                                        <?php if (!empty($maintenance['parts_used'])): ?>
                                        <div class="text-info small mt-1">
                                            <i class='bx bx-package'></i> Parts: <?php echo htmlspecialchars(substr($maintenance['parts_used'], 0, 40)); ?>...
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">No maintenance records found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Water Source Status List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Water Source Status</h5>
                                <p class="mb-0">Showing <?php echo count($filtered_sources); ?> of <?php echo count($water_sources); ?> water sources</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Source ID</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Last Inspection</th>
                                                <th>Condition</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($filtered_sources) > 0): ?>
                                                <?php foreach ($filtered_sources as $source): ?>
                                                <tr>
                                                    <td>
                                                        <span class="status-indicator indicator-<?php echo $source['status']; ?>"></span>
                                                        <span class="badge status-badge status-<?php echo $source['status']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $source['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($source['source_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($source['name']); ?></td>
                                                    <td>
                                                        <span class="badge source-type-badge badge-<?php echo $source['source_type']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $source['source_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($source['location']); ?></td>
                                                    <td><?php echo htmlspecialchars($source['barangay']); ?></td>
                                                    <td><?php echo $source['last_inspection'] ? date('M j, Y', strtotime($source['last_inspection'])) : 'Never'; ?></td>
                                                    <td>
                                                        <?php 
                                                        // Get condition from latest inspection if available
                                                        $condition = 'good'; // Default
                                                        if (!empty($source['last_inspection'])) {
                                                            // In a real implementation, you would fetch the condition from the inspection records
                                                            $condition = 'good'; // Placeholder
                                                        }
                                                        ?>
                                                        <span class="badge condition-badge condition-<?php echo $condition; ?>">
                                                            <?php echo ucfirst($condition); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary view-details-btn" data-source-id="<?php echo $source['id']; ?>">
                                                            <i class='bx bx-show'></i> Details
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <p class="text-muted">No water sources found matching the selected filters</p>
                                                    </td>
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

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Water Source Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm" action="update_status.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Water Source</label>
                            <select class="form-select" name="source_id" required>
                                <option value="">Select a water source</option>
                                <?php foreach ($water_sources as $source): ?>
                                    <option value="<?php echo $source['id']; ?>">
                                        <?php echo htmlspecialchars($source['source_id'] . ' - ' . $source['name'] . ' (' . $source['barangay'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="status" required>
                                <option value="">Select status</option>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                                <option value="low_flow">Low Flow</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="updateStatusForm" class="btn btn-primary">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDetailsModalLabel">Water Source Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="sourceDetailsContent">
                    <!-- Details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map with MapTiler satellite view
        const map = L.map('source-map').setView([14.6760, 121.0437], 12); // Quezon City coordinates
        
        // Add MapTiler satellite layer
        L.tileLayer('https://api.maptiler.com/maps/satellite/{z}/{x}/{y}.jpg?key=gZtMDh9pV46hFgly6xCT', {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
            tileSize: 512,
            zoomOffset: -1,
            minZoom: 1
        }).addTo(map);
        
        // Add water source markers
        const waterSources = <?php echo json_encode($sources_js); ?>;
        const markers = [];
        
        // Define custom icons for different statuses
        const iconConfigs = {
            active: { color: '#4caf50', icon: 'bx-droplet' },
            maintenance: { color: '#ffc107', icon: 'bx-wrench' },
            inactive: { color: '#f44336', icon: 'bx-x-circle' },
            low_flow: { color: '#2196f3', icon: 'bx-tachometer' }
        };
        
        // Create a custom icon for each status
        function createCustomIcon(status, sourceType) {
            const config = iconConfigs[status] || { color: '#666', icon: 'bx-question-mark' };
            
            // Create a custom HTML marker
            return L.divIcon({
                className: 'custom-marker',
                html: `<div style="background-color: ${config.color}; width: 30px; height: 30px; border-radius: 50%; 
                                display: flex; align-items: center; justify-content: center; border: 3px solid white;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                          <i class='bx ${config.icon}' style="color: white; font-size: 16px;"></i>
                       </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
        }
        
        // Add markers for each water source
        waterSources.forEach(source => {
            if (source.latitude && source.longitude) {
                const marker = L.marker([source.latitude, source.longitude], {
                    icon: createCustomIcon(source.status, source.source_type)
                }).addTo(map);
                
                // Create popup content
                const popupContent = `
                    <div class="source-info-window">
                        <h6>${source.name}</h6>
                        <p><strong>ID:</strong> ${source.source_id}</p>
                        <p><strong>Type:</strong> ${source.source_type.replace('_', ' ')}</p>
                        <p><strong>Status:</strong> <span class="badge status-badge status-${source.status}">${source.status.replace('_', ' ')}</span></p>
                        <p><strong>Location:</strong> ${source.location}</p>
                        <p><strong>Barangay:</strong> ${source.barangay}</p>
                        <button class="btn btn-sm btn-primary mt-2 view-details-btn" data-source-id="${source.id}">
                            View Details
                        </button>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markers.push(marker);
            }
        });
        
        // Add legend
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'legend');
            div.innerHTML = '<h6>Status Legend</h6>';
            
            for (const status in iconConfigs) {
                const config = iconConfigs[status];
                div.innerHTML += `
                    <div class="d-flex align-items-center mb-1">
                        <div style="background-color: ${config.color}; width: 18px; height: 18px; border-radius: 50%; 
                                    border: 2px solid white; margin-right: 8px;"></div>
                        <span>${status.replace('_', ' ')}</span>
                    </div>
                `;
            }
            
            return div;
        };
        legend.addTo(map);
        
        // Handle view details button clicks
        $(document).on('click', '.view-details-btn', function() {
            const sourceId = $(this).data('source-id');
            
            // Show loading state
            $('#sourceDetailsContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading details...</p>
                </div>
            `);
            
            // Load details via AJAX
            $.ajax({
                url: 'get_source_details.php',
                method: 'GET',
                data: { id: sourceId },
                success: function(response) {
                    $('#sourceDetailsContent').html(response);
                    $('#viewDetailsModal').modal('show');
                },
                error: function() {
                    $('#sourceDetailsContent').html(`
                        <div class="alert alert-danger">
                            Error loading details. Please try again.
                        </div>
                    `);
                }
            });
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            $('.toast-container').fadeOut();
        }, 5000);
    </script>
</body>
</html>