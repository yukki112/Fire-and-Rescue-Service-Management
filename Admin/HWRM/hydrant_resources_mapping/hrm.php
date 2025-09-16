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
$active_submodule = 'hydrant_resources_mapping';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all hydrants from the database
    $hydrants = $dbManager->fetchAll("hwrm", "SELECT * FROM hydrants ORDER BY barangay, hydrant_id");
    
    // Get unique barangays for filtering
    $barangays = $dbManager->fetchAll("hwrm", "SELECT DISTINCT barangay FROM hydrants ORDER BY barangay");
    
    // Get status counts for summary
    $status_counts = $dbManager->fetchAll("hwrm", 
        "SELECT status, COUNT(*) as count FROM hydrants GROUP BY status"
    );
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $hydrants = [];
    $barangays = [];
    $status_counts = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process filter requests
$selected_barangay = $_GET['barangay'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';

// Filter hydrants based on selection
$filtered_hydrants = $hydrants;
if ($selected_barangay !== 'all') {
    $filtered_hydrants = array_filter($filtered_hydrants, function($hydrant) use ($selected_barangay) {
        return $hydrant['barangay'] === $selected_barangay;
    });
}

if ($selected_status !== 'all') {
    $filtered_hydrants = array_filter($filtered_hydrants, function($hydrant) use ($selected_status) {
        return $hydrant['status'] === $selected_status;
    });
}

// Prepare hydrant data for JavaScript
$hydrants_js = [];
foreach ($filtered_hydrants as $hydrant) {
    $hydrants_js[] = [
        'id' => $hydrant['id'],
        'hydrant_id' => $hydrant['hydrant_id'],
        'location' => $hydrant['location'],
        'latitude' => floatval($hydrant['latitude']),
        'longitude' => floatval($hydrant['longitude']),
        'pressure' => $hydrant['pressure'],
        'flow_rate' => $hydrant['flow_rate'],
        'status' => $hydrant['status'],
        'barangay' => $hydrant['barangay'],
        'last_tested' => $hydrant['last_tested']
    ];
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $dbManager->query("hwrm", "DELETE FROM hydrants WHERE id = ?", [$delete_id]);
        $_SESSION['success_message'] = "Hydrant deleted successfully!";
        header("Location: hrm.php");
        exit;
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to delete hydrant. Please try again.";
        header("Location: hrm.php");
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new hydrant
    if (isset($_POST['add_hydrant'])) {
        try {
            $hydrant_id = $_POST['hydrant_id'];
            $location = $_POST['location'];
            $barangay = $_POST['barangay'];
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $pressure = $_POST['pressure'] ?: null;
            $flow_rate = $_POST['flow_rate'] ?: null;
            $status = $_POST['status'];
            $last_tested = $_POST['last_tested'] ?: null;
            
            $dbManager->query("hwrm", 
                "INSERT INTO hydrants (hydrant_id, location, barangay, latitude, longitude, pressure, flow_rate, status, last_tested) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$hydrant_id, $location, $barangay, $latitude, $longitude, $pressure, $flow_rate, $status, $last_tested]
            );
            
            $_SESSION['success_message'] = "Hydrant added successfully!";
            header("Location: hrm.php");
            exit;
        } catch (Exception $e) {
            error_log("Add hydrant error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to add hydrant. Please try again.";
            header("Location: hrm.php");
            exit;
        }
    }
    
    // Edit existing hydrant
    if (isset($_POST['edit_hydrant'])) {
        try {
            $id = $_POST['id'];
            $hydrant_id = $_POST['hydrant_id'];
            $location = $_POST['location'];
            $barangay = $_POST['barangay'];
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $pressure = $_POST['pressure'] ?: null;
            $flow_rate = $_POST['flow_rate'] ?: null;
            $status = $_POST['status'];
            $last_tested = $_POST['last_tested'] ?: null;
            
            $dbManager->query("hwrm", 
                "UPDATE hydrants SET hydrant_id = ?, location = ?, barangay = ?, latitude = ?, longitude = ?, 
                 pressure = ?, flow_rate = ?, status = ?, last_tested = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$hydrant_id, $location, $barangay, $latitude, $longitude, $pressure, $flow_rate, $status, $last_tested, $id]
            );
            
            $_SESSION['success_message'] = "Hydrant updated successfully!";
            header("Location: hrm.php");
            exit;
        } catch (Exception $e) {
            error_log("Edit hydrant error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to update hydrant. Please try again.";
            header("Location: hrm.php");
            exit;
        }
    }
}

// Display error messages
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// Display success messages
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
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
        #hydrant-map {
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
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        .hydrant-info-window {
            padding: 10px;
            min-width: 250px;
        }
        .hydrant-info-window h6 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .hydrant-info-window p {
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
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            margin-right: 0.25rem;
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
                    <a href="IRD/dashboard/index.php" class="sidebar-dropdown-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
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
                    <a href="hrm.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-water'></i>
                        <span>Hydrant resources mapping</span>
                    </a>
                      <a href="../water_source_database/wsd.php" class="sidebar-dropdown-link">
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
                    <h1>Hydrant Resources Mapping</h1>
                    <p>View and manage hydrant locations and status across Quezon City.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHydrantModal">
                            <i class='bx bx-plus'></i> Add Hydrant
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
            
            <!-- Hydrant Mapping Content -->
            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    $active_count = 0;
                    $maintenance_count = 0;
                    $inactive_count = 0;
                    
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
                        }
                    }
                    $total_count = $active_count + $maintenance_count + $inactive_count;
                    ?>
                    
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card active">
                                <div class="stat-number"><?php echo $active_count; ?></div>
                                <div class="stat-label">Active Hydrants</div>
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
                                <div class="stat-label">Inactive Hydrants</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $total_count; ?></div>
                                <div class="stat-label">Total Hydrants</div>
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
                                    <div class="col-md-5">
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
                                    <div class="col-md-5">
                                        <label class="form-label">Filter by Status</label>
                                        <select class="form-select" name="status">
                                            <option value="all" <?php echo $selected_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                            <option value="active" <?php echo $selected_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="maintenance" <?php echo $selected_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="inactive" <?php echo $selected_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
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
                            <div id="hydrant-map"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Hydrant List -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Hydrant List</h5>
                                <p class="mb-0">Showing <?php echo count($filtered_hydrants); ?> of <?php echo count($hydrants); ?> hydrants</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Hydrant ID</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Pressure (PSI)</th>
                                                <th>Flow Rate (L/min)</th>
                                                <th>Status</th>
                                                <th>Last Tested</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($filtered_hydrants) > 0): ?>
                                                <?php foreach ($filtered_hydrants as $hydrant): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($hydrant['hydrant_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($hydrant['location']); ?></td>
                                                    <td><?php echo htmlspecialchars($hydrant['barangay']); ?></td>
                                                    <td><?php echo $hydrant['pressure'] ?? 'N/A'; ?></td>
                                                    <td><?php echo $hydrant['flow_rate'] ?? 'N/A'; ?></td>
                                                    <td>
                                                        <span class="badge status-badge status-<?php echo $hydrant['status']; ?>">
                                                            <?php echo ucfirst($hydrant['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $hydrant['last_tested'] ?? 'Never'; ?></td>
                                                    <td class="action-buttons">
                                                        <button class="btn btn-sm btn-info view-hydrant" data-id="<?php echo $hydrant['id']; ?>" data-bs-toggle="modal" data-bs-target="#viewHydrantModal">
                                                            <i class='bx bx-show'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning edit-hydrant" data-id="<?php echo $hydrant['id']; ?>" data-bs-toggle="modal" data-bs-target="#editHydrantModal">
                                                            <i class='bx bx-edit'></i>
                                                        </button>
                                                        <a href="hrm.php?delete_id=<?php echo $hydrant['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this hydrant?')">
                                                            <i class='bx bx-trash'></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <p class="text-muted">No hydrants found matching the selected filters</p>
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

    <!-- Add Hydrant Modal -->
    <div class="modal fade" id="addHydrantModal" tabindex="-1" aria-labelledby="addHydrantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHydrantModalLabel">Add New Hydrant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="add_hydrant" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hydrant ID</label>
                                    <input type="text" class="form-control" name="hydrant_id" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
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
                                    <label class="form-label">Last Tested</label>
                                    <input type="date" class="form-control" name="last_tested">
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
                                    <label class="form-label">Pressure (PSI)</label>
                                    <input type="number" class="form-control" name="pressure">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Flow Rate (L/min)</label>
                                    <input type="number" class="form-control" name="flow_rate">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Hydrant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Hydrant Modal -->
    <div class="modal fade" id="viewHydrantModal" tabindex="-1" aria-labelledby="viewHydrantModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewHydrantModalLabel">Hydrant Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="hydrantDetails">
                    <!-- Details will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Hydrant Modal -->
    <div class="modal fade" id="editHydrantModal" tabindex="-1" aria-labelledby="editHydrantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHydrantModalLabel">Edit Hydrant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="edit_hydrant" value="1">
                    <input type="hidden" name="id" id="edit_hydrant_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hydrant ID</label>
                                    <input type="text" class="form-control" name="hydrant_id" id="edit_hydrant_hydrant_id" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_hydrant_status" required>
                                        <option value="active">Active</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="edit_hydrant_location" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" class="form-control" name="barangay" id="edit_hydrant_barangay" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Tested</label>
                                    <input type="date" class="form-control" name="last_tested" id="edit_hydrant_last_tested">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="any" class="form-control" name="latitude" id="edit_hydrant_latitude" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="any" class="form-control" name="longitude" id="edit_hydrant_longitude" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Pressure (PSI)</label>
                                    <input type="number" class="form-control" name="pressure" id="edit_hydrant_pressure">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Flow Rate (L/min)</label>
                                    <input type="number" class="form-control" name="flow_rate" id="edit_hydrant_flow_rate">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Hydrant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map with satellite view
        var map = L.map('hydrant-map').setView([14.6760, 121.0437], 12); // Quezon City coordinates
        
        // Add MapTiler satellite layer
        L.tileLayer('https://api.maptiler.com/maps/satellite/{z}/{x}/{y}.jpg?key=gZtMDh9pV46hFgly6xCT', {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
            maxZoom: 20
        }).addTo(map);
        
        // Add a scale control
        L.control.scale({metric: true, imperial: false}).addTo(map);
        
        // Create custom icons for different hydrant statuses
        function createHydrantIcon(status) {
            let iconColor;
            
            switch(status) {
                case 'active':
                    iconColor = '#28a745'; // Green
                    break;
                case 'maintenance':
                    iconColor = '#ffc107'; // Yellow
                    break;
                case 'inactive':
                    iconColor = '#dc3545'; // Red
                    break;
                default:
                    iconColor = '#6c757d'; // Gray
            }
            
            return L.divIcon({
                className: 'hydrant-marker',
                html: `<div style="background-color: ${iconColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
        }
        
        // Add hydrant markers to the map
        var hydrants = <?php echo json_encode($hydrants_js); ?>;
        var markers = [];
        
        hydrants.forEach(function(hydrant) {
            var marker = L.marker([hydrant.latitude, hydrant.longitude], {
                icon: createHydrantIcon(hydrant.status)
            }).addTo(map);
            
            // Create popup content
            var popupContent = `
                <div class="hydrant-info-window">
                    <h6>Hydrant ${hydrant.hydrant_id}</h6>
                    <p><strong>Location:</strong> ${hydrant.location}</p>
                    <p><strong>Barangay:</strong> ${hydrant.barangay}</p>
                    <p><strong>Status:</strong> <span class="badge status-${hydrant.status}">${hydrant.status.charAt(0).toUpperCase() + hydrant.status.slice(1)}</span></p>
                    <p><strong>Pressure:</strong> ${hydrant.pressure || 'N/A'} PSI</p>
                    <p><strong>Flow Rate:</strong> ${hydrant.flow_rate || 'N/A'} L/min</p>
                    <p><strong>Last Tested:</strong> ${hydrant.last_tested || 'Never'}</p>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-info view-hydrant" data-id="${hydrant.id}" data-bs-toggle="modal" data-bs-target="#viewHydrantModal">
                            <i class='bx bx-show'></i> View Details
                        </button>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            
            // Store reference to marker with hydrant data
            marker.hydrantData = hydrant;
            markers.push(marker);
        });
        
        // Add legend
        var legend = L.control({position: 'bottomright'});
        legend.onAdd = function(map) {
            var div = L.DomUtil.create('div', 'legend');
            div.innerHTML = `
                <h6>Hydrant Status</h6>
                <div><i style="background: #28a745"></i> Active</div>
                <div><i style="background: #ffc107"></i> Maintenance</div>
                <div><i style="background: #dc3545"></i> Inactive</div>
            `;
            return div;
        };
        legend.addTo(map);
        
        // Handle view hydrant button clicks
        document.querySelectorAll('.view-hydrant').forEach(button => {
            button.addEventListener('click', function() {
                const hydrantId = this.getAttribute('data-id');
                const hydrant = hydrants.find(h => h.id == hydrantId);
                
                if (hydrant) {
                    document.getElementById('hydrantDetails').innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Hydrant ID:</strong> ${hydrant.hydrant_id}</p>
                                <p><strong>Location:</strong> ${hydrant.location}</p>
                                <p><strong>Barangay:</strong> ${hydrant.barangay}</p>
                                <p><strong>Status:</strong> <span class="badge status-${hydrant.status}">${hydrant.status.charAt(0).toUpperCase() + hydrant.status.slice(1)}</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Latitude:</strong> ${hydrant.latitude}</p>
                                <p><strong>Longitude:</strong> ${hydrant.longitude}</p>
                                <p><strong>Pressure:</strong> ${hydrant.pressure || 'N/A'} PSI</p>
                                <p><strong>Flow Rate:</strong> ${hydrant.flow_rate || 'N/A'} L/min</p>
                                <p><strong>Last Tested:</strong> ${hydrant.last_tested || 'Never'}</p>
                            </div>
                        </div>
                    `;
                }
            });
        });
        
        // Handle edit hydrant button clicks
        document.querySelectorAll('.edit-hydrant').forEach(button => {
            button.addEventListener('click', function() {
                const hydrantId = this.getAttribute('data-id');
                const hydrant = hydrants.find(h => h.id == hydrantId);
                
                if (hydrant) {
                    document.getElementById('edit_hydrant_id').value = hydrant.id;
                    document.getElementById('edit_hydrant_hydrant_id').value = hydrant.hydrant_id;
                    document.getElementById('edit_hydrant_location').value = hydrant.location;
                    document.getElementById('edit_hydrant_barangay').value = hydrant.barangay;
                    document.getElementById('edit_hydrant_latitude').value = hydrant.latitude;
                    document.getElementById('edit_hydrant_longitude').value = hydrant.longitude;
                    document.getElementById('edit_hydrant_pressure').value = hydrant.pressure || '';
                    document.getElementById('edit_hydrant_flow_rate').value = hydrant.flow_rate || '';
                    document.getElementById('edit_hydrant_status').value = hydrant.status;
                    document.getElementById('edit_hydrant_last_tested').value = hydrant.last_tested || '';
                }
            });
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.toast').forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>