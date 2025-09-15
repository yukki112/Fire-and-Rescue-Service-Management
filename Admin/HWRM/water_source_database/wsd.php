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
$active_submodule = 'water_source_database';

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
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $water_sources = [];
    $barangays = [];
    $source_types = [];
    $status_counts = [];
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
            height: 600px;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="img/frsm1.png" alt="QC Logo">
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
                      <a href="../water_source_database/wsd.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-water'></i>
                        <span>Water Source Database</span>
                    </a>
                     <a href="../water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link">
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
                    <h1>Water Source Database</h1>
                    <p>View and manage water source locations and status across Quezon City.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                            <i class='bx bx-plus'></i> Add Water Source
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
            
            <!-- Water Source Database Content -->
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
                <div class="row">
                    <div class="col-12">
                        <div class="map-container animate-fade-in">
                            <div id="source-map"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Water Source List -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Water Source List</h5>
                                <p class="mb-0">Showing <?php echo count($filtered_sources); ?> of <?php echo count($water_sources); ?> water sources</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Source ID</th>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Capacity (L)</th>
                                                <th>Pressure (PSI)</th>
                                                <th>Flow Rate (L/min)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($filtered_sources) > 0): ?>
                                                <?php foreach ($filtered_sources as $source): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($source['source_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($source['name']); ?></td>
                                                    <td>
                                                        <span class="badge source-type-badge badge-<?php echo $source['source_type']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $source['source_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($source['location']); ?></td>
                                                    <td><?php echo htmlspecialchars($source['barangay']); ?></td>
                                                    <td><?php echo $source['capacity'] ?? 'N/A'; ?></td>
                                                    <td><?php echo $source['pressure'] ?? 'N/A'; ?></td>
                                                    <td><?php echo $source['flow_rate'] ?? 'N/A'; ?></td>
                                                    <td>
                                                        <span class="badge status-badge status-<?php echo $source['status']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $source['status'])); ?>
                                                        </span>
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

    <!-- Add Water Source Modal -->
    <div class="modal fade" id="addSourceModal" tabindex="-1" aria-labelledby="addSourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSourceModalLabel">Add New Water Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addSourceForm" action="add_water_source.php" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Source Type</label>
                                    <select class="form-select" name="source_type" required>
                                        <option value="">Select Type</option>
                                        <option value="hydrant">Hydrant</option>
                                        <option value="reservoir">Reservoir</option>
                                        <option value="lake">Lake</option>
                                        <option value="river">River</option>
                                        <option value="well">Well</option>
                                        <option value="storage_tank">Storage Tank</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Source ID</label>
                                    <input type="text" class="form-control" name="source_id" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" class="form-control" name="barangay" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="low_flow">Low Flow</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="any" class="form-control" name="latitude" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="any" class="form-control" name="longitude" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Capacity (Liters)</label>
                                    <input type="number" class="form-control" name="capacity">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Pressure (PSI)</label>
                                    <input type="number" class="form-control" name="pressure">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Flow Rate (L/min)</label>
                                    <input type="number" class="form-control" name="flow_rate">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Last Inspection Date</label>
                                    <input type="date" class="form-control" name="last_inspection">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addSourceForm" class="btn btn-primary">Add Water Source</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map
        const map = L.map('source-map').setView([14.6965, 121.0824], 13);
        
        // Add MapTiler Satellite tiles
        L.tileLayer('https://api.maptiler.com/maps/satellite/{z}/{x}/{y}.jpg?key=gZtMDh9pV46hFgly6xCT', {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
            tileSize: 512,
            zoomOffset: -1,
            maxZoom: 20
        }).addTo(map);
        
        // Water source data from PHP
        const sources = <?php echo json_encode($sources_js); ?>;
        
        // Create custom icons based on source type
        const createIcon = (color) => {
            return L.divIcon({
                html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>`,
                className: 'source-icon',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
        };
        
        const iconColors = {
            'hydrant': '#dc3545',
            'reservoir': '#0d6efd',
            'lake': '#198754',
            'river': '#0dcaf0',
            'well': '#6f42c1',
            'storage_tank': '#fd7e14'
        };
        
        // Add water sources to the map
        sources.forEach(source => {
            const iconColor = iconColors[source.source_type] || '#6c757d';
            const icon = createIcon(iconColor);
            
            const marker = L.marker([source.latitude, source.longitude], { icon: icon }).addTo(map);
            
            // Create popup content
            const popupContent = `
                <div class="source-info-window">
                    <h6>${source.source_id} - ${source.name}</h6>
                    <p><strong>Type:</strong> ${source.source_type.charAt(0).toUpperCase() + source.source_type.slice(1).replace('_', ' ')}</p>
                    <p><strong>Location:</strong> ${source.location}</p>
                    <p><strong>Barangay:</strong> ${source.barangay}</p>
                    <p><strong>Capacity:</strong> ${source.capacity || 'N/A'} L</p>
                    <p><strong>Pressure:</strong> ${source.pressure || 'N/A'} PSI</p>
                    <p><strong>Flow Rate:</strong> ${source.flow_rate || 'N/A'} L/min</p>
                    <p><strong>Status:</strong> <span class="badge status-badge status-${source.status}">${source.status.charAt(0).toUpperCase() + source.status.slice(1).replace('_', ' ')}</span></p>
                </div>
            `;
            
            marker.bindPopup(popupContent);
        });
        
        // Add legend
        const legend = L.control({ position: 'bottomright' });
        
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'legend');
            div.innerHTML = '<h6>Water Source Types</h6>';
            
            for (const type in iconColors) {
                div.innerHTML += `
                    <div>
                        <i style="background: ${iconColors[type]}"></i>
                        ${type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' ')}
                    </div>
                `;
            }
            
            return div;
        };
        
        legend.addTo(map);
        
        // Auto-close toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>