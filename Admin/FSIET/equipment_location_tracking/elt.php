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
$active_module = 'fsiet';
$active_submodule = 'equipment_tracking';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get equipment data with assignment information
    $equipment = $dbManager->fetchAll("fsiet", "
        SELECT e.*, 
               ea.assigned_to as assigned_user_id,
               ea.assigned_date,
               ea.expected_return,
               ea.status as assignment_status,
               CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
        FROM equipment e 
        LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.return_date IS NULL
        LEFT JOIN users u ON ea.assigned_to = u.id
        ORDER BY e.name
    ");
    
    // Get all users for assignment dropdown
    $users = $dbManager->fetchAll("frsm", "SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
    
    // Get assignment history
    $assignment_history = $dbManager->fetchAll("fsiet", "
        SELECT ea.*, 
               e.name as equipment_name,
               e.serial_no,
               CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
               CONCAT(ub.first_name, ' ', ub.last_name) as assigned_by_name
        FROM equipment_assignments ea
        JOIN equipment e ON ea.equipment_id = e.id
        LEFT JOIN users u ON ea.assigned_to = u.id
        LEFT JOIN users ub ON ea.assigned_by = ub.id
        ORDER BY ea.assigned_date DESC
        LIMIT 50
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $equipment = [];
    $users = [];
    $assignment_history = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Assign equipment to user
        if (isset($_POST['assign_equipment'])) {
            $query = "INSERT INTO equipment_assignments (equipment_id, assigned_to, assigned_by, assigned_date, expected_return, notes) 
                     VALUES (?, ?, ?, CURDATE(), ?, ?)";
            $params = [
                $_POST['equipment_id'],
                $_POST['assigned_to'],
                $_SESSION['user_id'],
                $_POST['expected_return'],
                $_POST['assignment_notes']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            // Update equipment status
            $update_query = "UPDATE equipment SET status = 'in-use', assigned_unit = ? WHERE id = ?";
            $dbManager->query("fsiet", $update_query, [$_POST['assigned_to'], $_POST['equipment_id']]);
            
            $_SESSION['success_message'] = "Equipment assigned successfully!";
            header("Location: et.php");
            exit;
        }
        
        // Return equipment
        if (isset($_POST['return_equipment'])) {
            $assignment_id = $_POST['assignment_id'];
            
            // Update assignment record
            $query = "UPDATE equipment_assignments SET return_date = CURDATE(), status = 'returned' WHERE id = ?";
            $dbManager->query("fsiet", $query, [$assignment_id]);
            
            // Get equipment ID from assignment
            $assignment = $dbManager->fetch("fsiet", "SELECT equipment_id FROM equipment_assignments WHERE id = ?", [$assignment_id]);
            
            if ($assignment) {
                // Update equipment status
                $update_query = "UPDATE equipment SET status = 'available', assigned_unit = NULL WHERE id = ?";
                $dbManager->query("fsiet", $update_query, [$assignment['equipment_id']]);
            }
            
            $_SESSION['success_message'] = "Equipment returned successfully!";
            header("Location: et.php");
            exit;
        }
        
        // Update equipment location/status
        if (isset($_POST['update_equipment'])) {
            $query = "UPDATE equipment SET status = ?, assigned_unit = ?, last_maintenance = ?, next_maintenance = ? WHERE id = ?";
            $params = [
                $_POST['status'],
                !empty($_POST['assigned_unit']) ? $_POST['assigned_unit'] : null,
                !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null,
                !empty($_POST['next_maintenance']) ? $_POST['next_maintenance'] : null,
                $_POST['equipment_id']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Equipment updated successfully!";
            header("Location: et.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Equipment operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .equipment-container {
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
        .card-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .equipment-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .equipment-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .equipment-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        .status-in-use {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-maintenance {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-retired {
            background-color: #f8d7da;
            color: #721c24;
        }
        .assignment-card {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .map-container {
            height: 400px;
            background-color: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="fsietMenu">
                    <a href="../inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="et.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="../maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                  <a href="../repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
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
                    <h1>Equipment Location Tracking</h1>
                    <p>Track equipment locations, assignments, and status.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
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
            
            <!-- Equipment Tracking Content -->
            <div class="dashboard-content">
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card bg-light">
                            <div class="stats-number text-primary">
                                <?php echo count($equipment); ?>
                            </div>
                            <div class="stats-label">Total Equipment</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-light">
                            <div class="stats-number text-success">
                                <?php 
                                    $available = array_filter($equipment, function($eq) {
                                        return $eq['status'] === 'available';
                                    });
                                    echo count($available);
                                ?>
                            </div>
                            <div class="stats-label">Available</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-light">
                            <div class="stats-number text-info">
                                <?php 
                                    $in_use = array_filter($equipment, function($eq) {
                                        return $eq['status'] === 'in-use';
                                    });
                                    echo count($in_use);
                                ?>
                            </div>
                            <div class="stats-label">In Use</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-light">
                            <div class="stats-number text-warning">
                                <?php 
                                    $maintenance = array_filter($equipment, function($eq) {
                                        return $eq['status'] === 'maintenance';
                                    });
                                    echo count($maintenance);
                                ?>
                            </div>
                            <div class="stats-label">Maintenance</div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="equipmentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tracking-tab" data-bs-toggle="tab" data-bs-target="#tracking" type="button" role="tab">
                            <i class='bx bx-map-pin'></i> Equipment Tracking
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">
                            <i class='bx bx-transfer-alt'></i> Assignments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                            <i class='bx bx-history'></i> Assignment History
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="equipmentTabsContent">
                    <!-- Equipment Tracking Tab -->
                    <div class="tab-pane fade show active" id="tracking" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="equipment-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-transfer-alt'></i>
                                                Assign Equipment
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                                <select class="form-select" name="equipment_id" required>
                                                    <option value="">Select Equipment</option>
                                                    <?php foreach ($equipment as $eq): 
                                                        if ($eq['status'] === 'available'): ?>
                                                        <option value="<?php echo $eq['id']; ?>">
                                                            <?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)
                                                        </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assign To <span class="text-danger">*</span></label>
                                                <select class="form-select" name="assigned_to" required>
                                                    <option value="">Select User</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Expected Return Date</label>
                                                <input type="date" class="form-control" name="expected_return">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="assignment_notes" rows="2"></textarea>
                                            </div>
                                            <button type="submit" name="assign_equipment" class="btn btn-primary w-100">
                                                <i class='bx bx-check'></i> Assign Equipment
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <div class="form-section mt-4">
                                        <div class="form-section-title">
                                            <i class='bx bx-cog'></i>
                                            Update Equipment Status
                                        </div>
                                        <form method="POST" action="">
                                            <div class="mb-3">
                                                <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                                <select class="form-select" name="equipment_id" id="update_equipment_select" required>
                                                    <option value="">Select Equipment</option>
                                                    <?php foreach ($equipment as $eq): ?>
                                                        <option value="<?php echo $eq['id']; ?>" data-status="<?php echo $eq['status']; ?>"
                                                            data-assigned="<?php echo $eq['assigned_unit']; ?>"
                                                            data-last="<?php echo $eq['last_maintenance']; ?>"
                                                            data-next="<?php echo $eq['next_maintenance']; ?>">
                                                            <?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                                <select class="form-select" name="status" id="status_select" required>
                                                    <option value="available">Available</option>
                                                    <option value="in-use">In Use</option>
                                                    <option value="maintenance">Maintenance</option>
                                                    <option value="retired">Retired</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assigned To</label>
                                                <select class="form-select" name="assigned_unit" id="assigned_unit_select">
                                                    <option value="">Not Assigned</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Last Maintenance</label>
                                                        <input type="date" class="form-control" name="last_maintenance" id="last_maintenance">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Next Maintenance</label>
                                                        <input type="date" class="form-control" name="next_maintenance" id="next_maintenance">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="update_equipment" class="btn btn-primary w-100">
                                                <i class='bx bx-save'></i> Update Equipment
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Equipment List</h5>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="filter-available">
                                                Available
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="filter-in-use">
                                                In Use
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="filter-all">
                                                All
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="equipment-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Type</th>
                                                        <th>Serial No</th>
                                                        <th>Status</th>
                                                        <th>Assigned To</th>
                                                        <th>Last Maintenance</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($equipment) > 0): ?>
                                                        <?php foreach ($equipment as $eq): 
                                                            $status_class = '';
                                                            switch ($eq['status']) {
                                                                case 'available':
                                                                    $status_class = 'status-available';
                                                                    break;
                                                                case 'in-use':
                                                                    $status_class = 'status-in-use';
                                                                    break;
                                                                case 'maintenance':
                                                                    $status_class = 'status-maintenance';
                                                                    break;
                                                                case 'retired':
                                                                    $status_class = 'status-retired';
                                                                    break;
                                                            }
                                                        ?>
                                                        <tr class="equipment-row" data-status="<?php echo $eq['status']; ?>">
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($eq['name']); ?></strong>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($eq['type']); ?></td>
                                                            <td><?php echo htmlspecialchars($eq['serial_no']); ?></td>
                                                            <td>
                                                                <span class="equipment-status <?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($eq['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($eq['assigned_user_name']): ?>
                                                                    <?php echo htmlspecialchars($eq['assigned_user_name']); ?>
                                                                    <?php if ($eq['expected_return']): ?>
                                                                        <br><small class="text-muted">Until: <?php echo date('M j, Y', strtotime($eq['expected_return'])); ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not assigned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo $eq['last_maintenance'] ? date('M j, Y', strtotime($eq['last_maintenance'])) : 'Never'; ?></td>
                                                            <td class="action-buttons">
                                                                <?php if ($eq['assignment_status'] === 'assigned' || $eq['assignment_status'] === 'in_use'): ?>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="assignment_id" value="<?php echo $eq['id']; ?>">
                                                                    <button type="submit" name="return_equipment" class="btn btn-sm btn-outline-success" 
                                                                            onclick="return confirm('Mark this equipment as returned?')">
                                                                        <i class='bx bx-check-circle'></i> Return
                                                                    </button>
                                                                </form>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center py-4">
                                                                <p class="text-muted">No equipment found</p>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Simple Map Visualization -->
                                <div class="card mt-4 animate-fade-in">
                                    <div class="card-header">
                                        <h5>Equipment Location Overview</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="map-container d-flex align-items-center justify-content-center">
                                            <div class="text-center text-muted">
                                                <i class='bx bx-map-alt' style="font-size: 3rem;"></i>
                                                <p class="mt-2">Map visualization would be integrated here</p>
                                                <small>Showing equipment locations and status</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge bg-success">Available</span>
                                                <span class="badge bg-primary">In Use</span>
                                                <span class="badge bg-warning">Maintenance</span>
                                                <span class="badge bg-secondary">Retired</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Current Equipment Assignments</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Equipment</th>
                                                        <th>Serial No</th>
                                                        <th>Assigned To</th>
                                                        <th>Assigned Date</th>
                                                        <th>Expected Return</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $current_assignments = array_filter($equipment, function($eq) {
                                                        return $eq['assignment_status'] && ($eq['assignment_status'] === 'assigned' || $eq['assignment_status'] === 'in_use');
                                                    });
                                                    ?>
                                                    <?php if (count($current_assignments) > 0): ?>
                                                        <?php foreach ($current_assignments as $assignment): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($assignment['name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($assignment['serial_no']); ?></td>
                                                            <td><?php echo htmlspecialchars($assignment['assigned_user_name']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?></td>
                                                            <td>
                                                                <?php if ($assignment['expected_return']): ?>
                                                                    <?php echo date('M j, Y', strtotime($assignment['expected_return'])); ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not specified</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary"><?php echo ucfirst($assignment['assignment_status']); ?></span>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <form method="POST" action="">
                                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                                    <button type="submit" name="return_equipment" class="btn btn-sm btn-outline-success" 
                                                                            onclick="return confirm('Mark this equipment as returned?')">
                                                                        <i class='bx bx-check-circle'></i> Return
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center py-4">
                                                                <p class="text-muted">No current assignments</p>
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
                    
                    <!-- History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Assignment History</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Equipment</th>
                                                        <th>Serial No</th>
                                                        <th>Assigned To</th>
                                                        <th>Assigned Date</th>
                                                        <th>Return Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($assignment_history) > 0): ?>
                                                        <?php foreach ($assignment_history as $history): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($history['equipment_name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($history['serial_no']); ?></td>
                                                            <td><?php echo htmlspecialchars($history['assigned_user_name']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($history['assigned_date'])); ?></td>
                                                            <td>
                                                                <?php if ($history['return_date']): ?>
                                                                    <?php echo date('M j, Y', strtotime($history['return_date'])); ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not returned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($history['status'] === 'returned'): ?>
                                                                    <span class="badge bg-success">Returned</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-primary"><?php echo ucfirst($history['status']); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No assignment history</p>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Equipment filtering
            const filterAvailable = document.getElementById('filter-available');
            const filterInUse = document.getElementById('filter-in-use');
            const filterAll = document.getElementById('filter-all');
            const equipmentRows = document.querySelectorAll('.equipment-row');
            
            filterAvailable.addEventListener('click', function() {
                equipmentRows.forEach(row => {
                    row.style.display = row.dataset.status === 'available' ? '' : 'none';
                });
                updateActiveFilterButton(this);
            });
            
            filterInUse.addEventListener('click', function() {
                equipmentRows.forEach(row => {
                    row.style.display = row.dataset.status === 'in-use' ? '' : 'none';
                });
                updateActiveFilterButton(this);
            });
            
            filterAll.addEventListener('click', function() {
                equipmentRows.forEach(row => {
                    row.style.display = '';
                });
                updateActiveFilterButton(this);
            });
            
            function updateActiveFilterButton(button) {
                document.querySelectorAll('#equipment-table + .btn-group .btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
            }
            
            // Update form with selected equipment data
            const equipmentSelect = document.getElementById('update_equipment_select');
            const statusSelect = document.getElementById('status_select');
            const assignedUnitSelect = document.getElementById('assigned_unit_select');
            const lastMaintenance = document.getElementById('last_maintenance');
            const nextMaintenance = document.getElementById('next_maintenance');
            
            if (equipmentSelect) {
                equipmentSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        statusSelect.value = selectedOption.dataset.status || 'available';
                        assignedUnitSelect.value = selectedOption.dataset.assigned || '';
                        lastMaintenance.value = selectedOption.dataset.last || '';
                        nextMaintenance.value = selectedOption.dataset.next || '';
                    } else {
                        statusSelect.value = 'available';
                        assignedUnitSelect.value = '';
                        lastMaintenance.value = '';
                        nextMaintenance.value = '';
                    }
                });
            }
            
            // Auto-hide toasts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.toast').forEach(toast => {
                    toast.classList.remove('animate-slide-in');
                    toast.classList.add('animate-slide-out');
                    setTimeout(() => toast.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>