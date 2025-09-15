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
$active_submodule = 'inspection_maintenance_records';

// Initialize variables
$user = ['first_name' => 'User'];
$water_sources = [];
$inspections = [];
$maintenance_records = [];
$inspection_stats = [];
$maintenance_stats = [];
$error_message = '';

// Get user info and data
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all water sources for dropdown - FIXED: Check if table exists and has data
    $water_sources = $dbManager->fetchAll("hwrm", "SELECT id, source_id, name, location FROM water_sources ORDER BY source_id");
    
    // Debug: Check if water sources were retrieved
    if ($water_sources === false) {
        error_log("Water sources query failed or returned false");
        $water_sources = [];
    }
    
    // Get all inspections with source details
    $inspections = $dbManager->fetchAll("hwrm", 
        "SELECT i.*, s.source_id, s.name as source_name, s.location, s.barangay 
         FROM water_source_inspections i 
         JOIN water_sources s ON i.source_id = s.id 
         ORDER BY i.inspection_date DESC"
    ) ?: [];
    
    // Get all maintenance records with source details
    $maintenance_records = $dbManager->fetchAll("hwrm", 
        "SELECT m.*, s.source_id, s.name as source_name, s.location, s.barangay 
         FROM water_source_maintenance m 
         JOIN water_sources s ON m.source_id = s.id 
         ORDER BY m.maintenance_date DESC"
    ) ?: [];
    
    // Get status counts for summary
    $inspection_stats = $dbManager->fetchAll("hwrm", 
        "SELECT condition, COUNT(*) as count FROM water_source_inspections GROUP BY condition"
    ) ?: [];
    
    $maintenance_stats = $dbManager->fetchAll("hwrm", 
        "SELECT maintenance_type, COUNT(*) as count FROM water_source_maintenance GROUP BY maintenance_type"
    ) ?: [];
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_inspection'])) {
            // Validate required fields
            if (empty($_POST['source_id']) || empty($_POST['inspection_date']) || empty($_POST['condition']) || empty($_POST['next_inspection'])) {
                $_SESSION['error_message'] = "Please fill in all required fields.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            // Add new inspection
            $params = [
                $_POST['source_id'],
                $_SESSION['user_id'],
                $_POST['inspection_date'],
                $_POST['pressure'] ?? null,
                $_POST['flow_rate'] ?? null,
                $_POST['condition'],
                $_POST['issues_found'] ?? '',
                $_POST['actions_taken'] ?? '',
                $_POST['recommendations'] ?? '',
                $_POST['next_inspection']
            ];
            
            $sql = "INSERT INTO water_source_inspections 
                    (source_id, inspected_by, inspection_date, pressure, flow_rate, condition, 
                     issues_found, actions_taken, recommendations, next_inspection) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $dbManager->query("hwrm", $sql, $params);
            
            // Update the water source's last inspection date
            $update_sql = "UPDATE water_sources SET last_inspection = ? WHERE id = ?";
            $dbManager->query("hwrm", $update_sql, [$_POST['inspection_date'], $_POST['source_id']]);
            
            $_SESSION['success_message'] = "Inspection record added successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (isset($_POST['add_maintenance'])) {
            // Validate required fields
            if (empty($_POST['source_id']) || empty($_POST['maintenance_type']) || empty($_POST['maintenance_date']) || empty($_POST['description'])) {
                $_SESSION['error_message'] = "Please fill in all required fields.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            // Add new maintenance record
            $params = [
                $_POST['source_id'],
                $_POST['maintenance_type'],
                $_SESSION['user_id'],
                $_POST['maintenance_date'],
                $_POST['description'],
                $_POST['parts_used'] ?? '',
                $_POST['cost'] ?? 0,
                $_POST['hours_spent'] ?? 0
            ];
            
            $sql = "INSERT INTO water_source_maintenance 
                    (source_id, maintenance_type, performed_by, maintenance_date, 
                     description, parts_used, cost, hours_spent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $dbManager->query("hwrm", $sql, $params);
            
            $_SESSION['success_message'] = "Maintenance record added successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error saving record: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Display error/success messages
$error_message = $_SESSION['error_message'] ?? $error_message;
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']);
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
    <link rel="stylesheet" href="css/style.css">
    <style>
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
        .stat-card.excellent {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .stat-card.good {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        .stat-card.fair {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        .stat-card.poor {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .stat-card.critical {
            background-color: #f3e5f5;
            border-left: 4px solid #9c27b0;
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
        .condition-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .condition-excellent {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .condition-good {
            background-color: #cfe2ff;
            color: #084298;
        }
        .condition-fair {
            background-color: #fff3cd;
            color: #664d03;
        }
        .condition-poor {
            background-color: #f8d7da;
            color: #842029;
        }
        .condition-critical {
            background-color: #e0cffc;
            color: #472f92;
        }
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #0d6efd;
            font-weight: 600;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .source-display {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .source-id-display {
            font-weight: 600;
            color: #0d6efd;
        }
        .compact-cell {
            max-width: 100px;
        }
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        .water-source-alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar (unchanged as requested) -->
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
                      <a href="../water_source_database/wsd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Water Source Database</span>
                    </a>
                     <a href="../water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-droplet'></i>
                        <span>Water Source Status Monitoring</span>
                    </a>
                    <a href="../inspection_maintenance_records/imr.php" class="sidebar-dropdown-link active">
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
                    <h1>Inspection & Maintenance Records</h1>
                    <p>Manage water source inspections and maintenance records across Quezon City.</p>
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
            
            <!-- Inspection & Maintenance Records Content -->
            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    $excellent_count = 0;
                    $good_count = 0;
                    $fair_count = 0;
                    $poor_count = 0;
                    $critical_count = 0;
                    
                    foreach ($inspection_stats as $stat) {
                        switch ($stat['condition']) {
                            case 'excellent':
                                $excellent_count = $stat['count'];
                                break;
                            case 'good':
                                $good_count = $stat['count'];
                                break;
                            case 'fair':
                                $fair_count = $stat['count'];
                                break;
                            case 'poor':
                                $poor_count = $stat['count'];
                                break;
                            case 'critical':
                                $critical_count = $stat['count'];
                                break;
                        }
                    }
                    $total_inspections = $excellent_count + $good_count + $fair_count + $poor_count + $critical_count;
                    ?>
                    
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card excellent">
                                <div class="stat-number"><?php echo $excellent_count; ?></div>
                                <div class="stat-label">Excellent</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card good">
                                <div class="stat-number"><?php echo $good_count; ?></div>
                                <div class="stat-label">Good</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card fair">
                                <div class="stat-number"><?php echo $fair_count; ?></div>
                                <div class="stat-label">Fair</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card poor">
                                <div class="stat-number"><?php echo $poor_count; ?></div>
                                <div class="stat-label">Poor</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card critical">
                                <div class="stat-number"><?php echo $critical_count; ?></div>
                                <div class="stat-label">Critical</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-container">
                            <div class="stat-card" style="background-color: #f5f5f5; border-left: 4px solid #6c757d;">
                                <div class="stat-number"><?php echo $total_inspections; ?></div>
                                <div class="stat-label">Total Inspections</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alert if no water sources available -->
                <?php if (count($water_sources) === 0): ?>
                <div class="alert alert-warning water-source-alert">
                    <i class='bx bx-error'></i> No water sources found in the database. 
                    Please <a href="../water_source_database/wsd.php">add water sources</a> first before creating inspections or maintenance records.
                </div>
                <?php endif; ?>
                
                <!-- Tabs for Inspections and Maintenance -->
                <ul class="nav nav-tabs mb-4" id="recordsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="inspections-tab" data-bs-toggle="tab" data-bs-target="#inspections" type="button" role="tab" aria-controls="inspections" aria-selected="true">
                            Inspections
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">
                            Maintenance Records
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="recordsTabContent">
                    <!-- Inspections Tab -->
                    <div class="tab-pane fade show active" id="inspections" role="tabpanel" aria-labelledby="inspections-tab">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Inspection Records</h5>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInspectionModal" <?php echo count($water_sources) === 0 ? 'disabled' : ''; ?>>
                                            <i class='bx bx-plus'></i> Add Inspection
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($inspections) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Source ID</th>
                                                        <th>Source Name</th>
                                                        <th>Condition</th>
                                                        <th>Pressure</th>
                                                        <th>Flow Rate</th>
                                                        <th>Next Inspection</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($inspections as $inspection): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($inspection['inspection_date'])); ?></td>
                                                        <td class="source-id-display"><?php echo htmlspecialchars($inspection['source_id']); ?></td>
                                                        <td class="source-display" title="<?php echo htmlspecialchars($inspection['source_name']); ?>">
                                                            <?php echo htmlspecialchars($inspection['source_name']); ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge condition-badge condition-<?php echo $inspection['condition']; ?>">
                                                                <?php echo ucfirst($inspection['condition']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $inspection['pressure'] ?? 'N/A'; ?> PSI</td>
                                                        <td><?php echo $inspection['flow_rate'] ?? 'N/A'; ?> L/min</td>
                                                        <td><?php echo date('M d, Y', strtotime($inspection['next_inspection'])); ?></td>
                                                        <td class="action-buttons">
                                                            <button class="btn btn-sm btn-outline-primary view-inspection" 
                                                                    data-id="<?php echo $inspection['id']; ?>"
                                                                    data-source-id="<?php echo htmlspecialchars($inspection['source_id']); ?>"
                                                                    data-source-name="<?php echo htmlspecialchars($inspection['source_name']); ?>"
                                                                    data-location="<?php echo htmlspecialchars($inspection['location']); ?>"
                                                                    data-barangay="<?php echo htmlspecialchars($inspection['barangay']); ?>"
                                                                    data-date="<?php echo $inspection['inspection_date']; ?>"
                                                                    data-pressure="<?php echo $inspection['pressure']; ?>"
                                                                    data-flow-rate="<?php echo $inspection['flow_rate']; ?>"
                                                                    data-condition="<?php echo $inspection['condition']; ?>"
                                                                    data-issues="<?php echo htmlspecialchars($inspection['issues_found']); ?>"
                                                                    data-actions="<?php echo htmlspecialchars($inspection['actions_taken']); ?>"
                                                                    data-recommendations="<?php echo htmlspecialchars($inspection['recommendations']); ?>"
                                                                    data-next-inspection="<?php echo $inspection['next_inspection']; ?>">
                                                                <i class='bx bx-show'></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="no-data">
                                            <i class='bx bx-water' style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                            <h5>No Inspection Records Found</h5>
                                            <p>No water source inspections have been recorded yet.</p>
                                            <?php if (count($water_sources) > 0): ?>
                                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addInspectionModal">
                                                <i class='bx bx-plus'></i> Add Your First Inspection
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Maintenance Tab -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Maintenance Records</h5>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal" <?php echo count($water_sources) === 0 ? 'disabled' : ''; ?>>
                                            <i class='bx bx-plus'></i> Add Maintenance
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($maintenance_records) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Source ID</th>
                                                        <th>Source Name</th>
                                                        <th>Description</th>
                                                        <th>Cost</th>
                                                        <th>Hours</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($maintenance_records as $maintenance): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($maintenance['maintenance_date'])); ?></td>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $maintenance['maintenance_type'])); ?></td>
                                                        <td class="source-id-display"><?php echo htmlspecialchars($maintenance['source_id']); ?></td>
                                                        <td class="source-display" title="<?php echo htmlspecialchars($maintenance['source_name']); ?>">
                                                            <?php echo htmlspecialchars($maintenance['source_name']); ?>
                                                        </td>
                                                        <td><?php echo strlen($maintenance['description']) > 50 ? substr($maintenance['description'], 0, 50) . '...' : $maintenance['description']; ?></td>
                                                        <td>₱<?php echo number_format($maintenance['cost'], 2); ?></td>
                                                        <td><?php echo $maintenance['hours_spent']; ?></td>
                                                        <td class="action-buttons">
                                                            <button class="btn btn-sm btn-outline-primary view-maintenance" 
                                                                    data-id="<?php echo $maintenance['id']; ?>"
                                                                    data-source-id="<?php echo htmlspecialchars($maintenance['source_id']); ?>"
                                                                    data-source-name="<?php echo htmlspecialchars($maintenance['source_name']); ?>"
                                                                    data-location="<?php echo htmlspecialchars($maintenance['location']); ?>"
                                                                    data-barangay="<?php echo htmlspecialchars($maintenance['barangay']); ?>"
                                                                    data-type="<?php echo $maintenance['maintenance_type']; ?>"
                                                                    data-date="<?php echo $maintenance['maintenance_date']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($maintenance['description']); ?>"
                                                                    data-parts="<?php echo htmlspecialchars($maintenance['parts_used']); ?>"
                                                                    data-cost="<?php echo $maintenance['cost']; ?>"
                                                                    data-hours="<?php echo $maintenance['hours_spent']; ?>">
                                                                <i class='bx bx-show'></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="no-data">
                                            <i class='bx bx-wrench' style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                            <h5>No Maintenance Records Found</h5>
                                            <p>No water source maintenance has been recorded yet.</p>
                                            <?php if (count($water_sources) > 0): ?>
                                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                                <i class='bx bx-plus'></i> Add Your First Maintenance Record
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Inspection Modal -->
    <div class="modal fade" id="addInspectionModal" tabindex="-1" aria-labelledby="addInspectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addInspectionModalLabel">Add New Inspection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="source_id" class="form-label">Water Source <span class="text-danger">*</span></label>
                                <select class="form-select" id="source_id" name="source_id" required>
                                    <option value="">Select Water Source</option>
                                    <?php foreach ($water_sources as $source): ?>
                                    <option value="<?php echo $source['id']; ?>">
                                        <?php echo htmlspecialchars($source['source_id'] . ' - ' . $source['name'] . ' (' . $source['location'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="inspection_date" class="form-label">Inspection Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="inspection_date" name="inspection_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="pressure" class="form-label">Pressure (PSI)</label>
                                <input type="number" step="0.1" class="form-control" id="pressure" name="pressure" placeholder="Enter pressure">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="flow_rate" class="form-label">Flow Rate (L/min)</label>
                                <input type="number" step="0.1" class="form-control" id="flow_rate" name="flow_rate" placeholder="Enter flow rate">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                                <select class="form-select" id="condition" name="condition" required>
                                    <option value="">Select Condition</option>
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="issues_found" class="form-label">Issues Found</label>
                                <textarea class="form-control" id="issues_found" name="issues_found" rows="3" placeholder="Describe any issues found during inspection"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="actions_taken" class="form-label">Actions Taken</label>
                                <textarea class="form-control" id="actions_taken" name="actions_taken" rows="3" placeholder="Describe any actions taken during inspection"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="recommendations" class="form-label">Recommendations</label>
                                <textarea class="form-control" id="recommendations" name="recommendations" rows="3" placeholder="Provide recommendations for maintenance or follow-up"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="next_inspection" class="form-label">Next Inspection Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="next_inspection" name="next_inspection" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_inspection" class="btn btn-primary">Save Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMaintenanceModalLabel">Add Maintenance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_source_id" class="form-label">Water Source <span class="text-danger">*</span></label>
                                <select class="form-select" id="maintenance_source_id" name="source_id" required>
                                    <option value="">Select Water Source</option>
                                    <?php foreach ($water_sources as $source): ?>
                                    <option value="<?php echo $source['id']; ?>">
                                        <?php echo htmlspecialchars($source['source_id'] . ' - ' . $source['name'] . ' (' . $source['location'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_type" class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                                    <option value="">Select Type</option>
                                    <option value="routine">Routine Maintenance</option>
                                    <option value="corrective">Corrective Maintenance</option>
                                    <option value="preventive">Preventive Maintenance</option>
                                    <option value="emergency">Emergency Repair</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_date" class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hours_spent" class="form-label">Hours Spent</label>
                                <input type="number" step="0.1" class="form-control" id="hours_spent" name="hours_spent" placeholder="Enter hours spent">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Describe the maintenance performed"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="parts_used" class="form-label">Parts Used</label>
                                <textarea class="form-control" id="parts_used" name="parts_used" rows="2" placeholder="List any parts used during maintenance"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cost" class="form-label">Cost (₱)</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost" placeholder="Enter cost">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_maintenance" class="btn btn-primary">Save Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Inspection Modal -->
    <div class="modal fade" id="viewInspectionModal" tabindex="-1" aria-labelledby="viewInspectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewInspectionModalLabel">Inspection Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Water Source</label>
                            <p id="view-source" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <p id="view-location" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Inspection Date</label>
                            <p id="view-date" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Condition</label>
                            <p id="view-condition" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pressure</label>
                            <p id="view-pressure" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Flow Rate</label>
                            <p id="view-flow-rate" class="form-control-static"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Issues Found</label>
                            <p id="view-issues" class="form-control-static"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Actions Taken</label>
                            <p id="view-actions" class="form-control-static"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Recommendations</label>
                            <p id="view-recommendations" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Next Inspection</label>
                            <p id="view-next-inspection" class="form-control-static"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Maintenance Modal -->
    <div class="modal fade" id="viewMaintenanceModal" tabindex="-1" aria-labelledby="viewMaintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewMaintenanceModalLabel">Maintenance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Water Source</label>
                            <p id="view-m-source" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <p id="view-m-location" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Maintenance Type</label>
                            <p id="view-m-type" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Maintenance Date</label>
                            <p id="view-m-date" class="form-control-static"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <p id="view-m-description" class="form-control-static"></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Parts Used</label>
                            <p id="view-m-parts" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Cost</label>
                            <p id="view-m-cost" class="form-control-static"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hours Spent</label>
                            <p id="view-m-hours" class="form-control-static"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for next inspection to today
            const nextInspectionInput = document.getElementById('next_inspection');
            if (nextInspectionInput) {
                const today = new Date().toISOString().split('T')[0];
                nextInspectionInput.min = today;
            }
            
            // Handle view inspection buttons
            const viewInspectionButtons = document.querySelectorAll('.view-inspection');
            viewInspectionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-source-id');
                    const sourceName = this.getAttribute('data-source-name');
                    const location = this.getAttribute('data-location');
                    const barangay = this.getAttribute('data-barangay');
                    const date = this.getAttribute('data-date');
                    const pressure = this.getAttribute('data-pressure');
                    const flowRate = this.getAttribute('data-flow-rate');
                    const condition = this.getAttribute('data-condition');
                    const issues = this.getAttribute('data-issues');
                    const actions = this.getAttribute('data-actions');
                    const recommendations = this.getAttribute('data-recommendations');
                    const nextInspection = this.getAttribute('data-next-inspection');
                    
                    // Format the date
                    const formatDate = (dateString) => {
                        if (!dateString) return 'N/A';
                        const dateObj = new Date(dateString);
                        return dateObj.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                    };
                    
                    // Populate the modal
                    document.getElementById('view-source').textContent = `${sourceId} - ${sourceName}`;
                    document.getElementById('view-location').textContent = `${location}, ${barangay}`;
                    document.getElementById('view-date').textContent = formatDate(date);
                    document.getElementById('view-condition').innerHTML = `<span class="badge condition-badge condition-${condition}">${condition.charAt(0).toUpperCase() + condition.slice(1)}</span>`;
                    document.getElementById('view-pressure').textContent = pressure ? `${pressure} PSI` : 'N/A';
                    document.getElementById('view-flow-rate').textContent = flowRate ? `${flowRate} L/min` : 'N/A';
                    document.getElementById('view-issues').textContent = issues || 'None reported';
                    document.getElementById('view-actions').textContent = actions || 'None reported';
                    document.getElementById('view-recommendations').textContent = recommendations || 'None provided';
                    document.getElementById('view-next-inspection').textContent = formatDate(nextInspection);
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('viewInspectionModal'));
                    modal.show();
                });
            });
            
            // Handle view maintenance buttons
            const viewMaintenanceButtons = document.querySelectorAll('.view-maintenance');
            viewMaintenanceButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-source-id');
                    const sourceName = this.getAttribute('data-source-name');
                    const location = this.getAttribute('data-location');
                    const barangay = this.getAttribute('data-barangay');
                    const type = this.getAttribute('data-type');
                    const date = this.getAttribute('data-date');
                    const description = this.getAttribute('data-description');
                    const parts = this.getAttribute('data-parts');
                    const cost = this.getAttribute('data-cost');
                    const hours = this.getAttribute('data-hours');
                    
                    // Format the date
                    const formatDate = (dateString) => {
                        if (!dateString) return 'N/A';
                        const dateObj = new Date(dateString);
                        return dateObj.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                    };
                    
                    // Format maintenance type
                    const formatType = (type) => {
                        return type.split('_').map(word => 
                            word.charAt(0).toUpperCase() + word.slice(1)
                        ).join(' ');
                    };
                    
                    // Populate the modal
                    document.getElementById('view-m-source').textContent = `${sourceId} - ${sourceName}`;
                    document.getElementById('view-m-location').textContent = `${location}, ${barangay}`;
                    document.getElementById('view-m-type').textContent = formatType(type);
                    document.getElementById('view-m-date').textContent = formatDate(date);
                    document.getElementById('view-m-description').textContent = description || 'No description provided';
                    document.getElementById('view-m-parts').textContent = parts || 'None used';
                    document.getElementById('view-m-cost').textContent = cost ? `₱${parseFloat(cost).toFixed(2)}` : 'N/A';
                    document.getElementById('view-m-hours').textContent = hours ? `${hours} hours` : 'N/A';
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('viewMaintenanceModal'));
                    modal.show();
                });
            });
            
            // Auto-hide toasts after 5 seconds
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.classList.remove('animate-slide-in');
                    toast.classList.add('animate-slide-out');
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>