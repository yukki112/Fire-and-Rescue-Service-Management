<?php
session_start();

// Include the Database Manager
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'piar';
$active_submodule = 'cause_and_origin_investigation';

// Initialize variables
$error_message = '';
$success_message = '';
$investigations = [];
$investigation_details = null;
$edit_mode = false;
$incidents = [];
$investigators = [];

// Fetch all incidents for dropdown
try {
    $query = "SELECT id, report_title FROM piar.incident_analysis_reports WHERE status != 'archived' ORDER BY created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
}

// Fetch all investigators (employees) for dropdown
try {
    $query = "SELECT id, first_name, last_name, employee_id FROM frsm.employees WHERE is_active = 1 ORDER BY last_name, first_name";
    $investigators = $dbManager->fetchAll("frsm", $query);
} catch (Exception $e) {
    error_log("Fetch investigators error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create Investigation
        if (isset($_POST['create_investigation'])) {
            $query = "INSERT INTO piar.cause_origin_investigation 
                     (incident_id, investigator_id, investigation_date, cause_classification, origin_location, 
                      ignition_source, contributing_factors, evidence_collected, witness_statements, 
                      investigation_status, final_report) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['investigator_id'],
                $_POST['investigation_date'],
                $_POST['cause_classification'] ?: null,
                $_POST['origin_location'] ?: null,
                $_POST['ignition_source'] ?: null,
                $_POST['contributing_factors'] ?: null,
                $_POST['evidence_collected'] ?: null,
                $_POST['witness_statements'] ?: null,
                $_POST['investigation_status'],
                $_POST['final_report'] ?: null
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Investigation created successfully!";
            
        } elseif (isset($_POST['update_investigation'])) {
            // Update Investigation
            $investigation_id = $_POST['investigation_id'];
            $query = "UPDATE piar.cause_origin_investigation 
                     SET incident_id = ?, investigator_id = ?, investigation_date = ?, cause_classification = ?, 
                         origin_location = ?, ignition_source = ?, contributing_factors = ?, evidence_collected = ?, 
                         witness_statements = ?, investigation_status = ?, final_report = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['incident_id'],
                $_POST['investigator_id'],
                $_POST['investigation_date'],
                $_POST['cause_classification'] ?: null,
                $_POST['origin_location'] ?: null,
                $_POST['ignition_source'] ?: null,
                $_POST['contributing_factors'] ?: null,
                $_POST['evidence_collected'] ?: null,
                $_POST['witness_statements'] ?: null,
                $_POST['investigation_status'],
                $_POST['final_report'] ?: null,
                $investigation_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Investigation updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Investigation error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit/delete)
if (isset($_GET['action'])) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if ($_GET['action'] === 'view' && $id) {
            $query = "SELECT coi.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
                     FROM piar.cause_origin_investigation coi
                     LEFT JOIN piar.incident_analysis_reports iar ON coi.incident_id = iar.id
                     LEFT JOIN frsm.employees e ON coi.investigator_id = e.id
                     WHERE coi.id = ?";
            $investigation_details = $dbManager->fetch("piar", $query, [$id]);
            
        } elseif ($_GET['action'] === 'edit' && $id) {
            $query = "SELECT * FROM piar.cause_origin_investigation WHERE id = ?";
            $investigation_details = $dbManager->fetch("piar", $query, [$id]);
            $edit_mode = true;
            
        } elseif ($_GET['action'] === 'delete' && $id) {
            $query = "DELETE FROM piar.cause_origin_investigation WHERE id = ?";
            $dbManager->query("piar", $query, [$id]);
            $success_message = "Investigation deleted successfully!";
        }
    } catch (Exception $e) {
        error_log("Investigation action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all investigations
try {
    $query = "SELECT coi.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
             FROM piar.cause_origin_investigation coi
             LEFT JOIN piar.incident_analysis_reports iar ON coi.incident_id = iar.id
             LEFT JOIN frsm.employees e ON coi.investigator_id = e.id
             ORDER BY coi.created_at DESC";
    $investigations = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch investigations error: " . $e->getMessage());
}

// Display success/error messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
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
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 100%;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .stat-card .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .investigation-table {
            font-size: 0.9rem;
        }
        
        .investigation-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .action-btn {
            margin-right: 5px;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                height: 100vh;
                overflow-y: auto;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: #0d6efd;
                color: white;
                border: none;
                border-radius: 5px;
                padding: 8px 12px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                width: 100%;
            }
            .stat-card {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .filter-section {
                padding: 15px;
            }
            .empty-state {
                padding: 20px 10px;
            }
            .empty-state i {
                font-size: 2rem;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
        }
        
        /* Card hover effects */
        .dashboard-card:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        /* Form styling */
        .form-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #0d6efd;
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-ongoing { background-color: #fd7e14; color: #fff; }
        .status-completed { background-color: #198754; color: #fff; }
        .status-closed { background-color: #6c757d; color: #fff; }
        
        /* Stats cards colors */
        .stat-ongoing { background-color: #fd7e14; }
        .stat-completed { background-color: #198754; }
        .stat-closed { background-color: #6c757d; }
        .stat-total { background-color: #0d6efd; }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuButton">
        <i class='bx bx-menu'></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
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
                        <span>Inspection Location Mapping</span>
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
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse" id="hwrmMenu">
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
                    <a href="../../HWRM/access_and_permissions/ap.php" class="sidebar-dropdown-link">
                    <i class='bx bx-lock-alt'></i>
    <span> Access and Permissions</span>
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
                           <i class='bx bx-file'></i>
        <span>Reporting & Logs</span>
                    </a>
                    
                </div>
                
               <!-- Training and Certification Records -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#tcrMenu" role="button">
                    <i class='bx bx-certification'></i>
                    <span class="text">Training and Certification <br>Records</span>
                </a>
                <div class="sidebar-dropdown collapse" id="tcrMenu">
                    <a href="../personnel_training_profile/ptr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-reader'></i>
                        <span>Personnel Training Profiles</span>
                    </a>
                    <a href="../../TCR/training_course_management/tcm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
                    </a>
                    <a href="../../TCR/training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link">
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
                     <a href="../../TCR/evaluation_and_assessment_records/eaar.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
                    </a>
                    <a href="../../TCR/reporting_and_auditlogs/ral.php" class="sidebar-dropdown-link">
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
                     <a href="../../FICR/ reporting_and_analytics/raa.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <span>Reporting and Analytics</span>
                    </a>
                </div>
                
                <!-- Post-Incident Analysis and Reporting -->
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#piarMenu" role="button">
                    <i class='bx bx-analyse'></i>
                    <span class="text">Post-Incident Analysis</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="piarMenu">
                    <a href="../incident_summary_documentation/isd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Incident Summary Documentation</span>
                    </a>
                    <a href="../response_timeline_tracking/rtt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i>
                        <span>Response Timeline Tracking</span>
                    </a>
                     <a href="../personnel_and_unit_involvement/paui.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Personnel and Unit Involvement</span>
                    </a>
                     <a href="caoi.php" class="sidebar-dropdown-link active">
                       <i class='bx bx-search-alt'></i>
                        <span>Cause and Origin Investigation</span>
                    </a>
                       <a href="../damage_assessment/da.php" class="sidebar-dropdown-link">
                      <i class='bx bx-building-house'></i>
                        <span>Damage Assessment</span>
                    </a>
                       <a href="../action_review_and_lessons_learned/arall.php" class="sidebar-dropdown-link">
                     <i class='bx bx-refresh'></i>
                        <span>Action Review and Lessons Learned</span>
                    </a>
                     <a href="../report_generation_and_archiving/rgaa.php" class="sidebar-dropdown-link">
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
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Cause and Origin Investigation</h1>
                    <p>Investigate and document the cause and origin of incidents</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvestigationModal">
                        <i class='bx bx-plus'></i> New Investigation
                    </button>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle'></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class='bx bx-check-circle'></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card stat-total">
                        <i class='bx bx-file'></i>
                        <div class="number"><?php echo count($investigations); ?></div>
                        <div class="label">Total Investigations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-ongoing">
                        <i class='bx bx-time'></i>
                        <div class="number"><?php echo count(array_filter($investigations, function($inv) { return $inv['investigation_status'] === 'ongoing'; })); ?></div>
                        <div class="label">Ongoing</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-completed">
                        <i class='bx bx-check-circle'></i>
                        <div class="number"><?php echo count(array_filter($investigations, function($inv) { return $inv['investigation_status'] === 'completed'; })); ?></div>
                        <div class="label">Completed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-closed">
                        <i class='bx bx-archive'></i>
                        <div class="number"><?php echo count(array_filter($investigations, function($inv) { return $inv['investigation_status'] === 'closed'; })); ?></div>
                        <div class="label">Closed</div>
                    </div>
                </div>
            </div>
            
            <!-- Investigation Details View -->
            <?php if ($investigation_details && !$edit_mode): ?>
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Investigation Details</h4>
                        <div>
                            <?php 
                            $status_class = '';
                            switch ($investigation_details['investigation_status']) {
                                case 'ongoing': $status_class = 'status-ongoing'; break;
                                case 'completed': $status_class = 'status-completed'; break;
                                case 'closed': $status_class = 'status-closed'; break;
                            }
                            ?>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo ucfirst($investigation_details['investigation_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($investigation_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Investigator:</strong> <?php echo htmlspecialchars($investigation_details['first_name'] . ' ' . $investigation_details['last_name'] . ' (' . $investigation_details['employee_id'] . ')'); ?></p>
                            <p><strong>Investigation Date:</strong> <?php echo date('M j, Y', strtotime($investigation_details['investigation_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Cause Classification:</strong> <?php echo htmlspecialchars($investigation_details['cause_classification'] ?? 'Not specified'); ?></p>
                            <p><strong>Origin Location:</strong> <?php echo htmlspecialchars($investigation_details['origin_location'] ?? 'Not specified'); ?></p>
                            <p><strong>Ignition Source:</strong> <?php echo htmlspecialchars($investigation_details['ignition_source'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($investigation_details['contributing_factors'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Contributing Factors</h5>
                        <p><?php echo nl2br(htmlspecialchars($investigation_details['contributing_factors'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($investigation_details['evidence_collected'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Evidence Collected</h5>
                        <p><?php echo nl2br(htmlspecialchars($investigation_details['evidence_collected'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($investigation_details['witness_statements'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Witness Statements</h5>
                        <p><?php echo nl2br(htmlspecialchars($investigation_details['witness_statements'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($investigation_details['final_report'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Final Report</h5>
                        <p><?php echo nl2br(htmlspecialchars($investigation_details['final_report'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="caoi.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="caoi.php?action=edit&id=<?php echo $investigation_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $investigation_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this investigation record?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Investigations List -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Investigation Records</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search investigations..." id="investigationSearch">
                        </div>
                    </div>
                    
                    <?php if (!empty($investigations)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover investigation-table">
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Investigator</th>
                                        <th>Investigation Date</th>
                                        <th>Cause Classification</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($investigations as $investigation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($investigation['report_title'] ?? 'Unknown Incident'); ?></td>
                                            <td><?php echo htmlspecialchars($investigation['first_name'] . ' ' . $investigation['last_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($investigation['investigation_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($investigation['cause_classification'] ?? 'Not specified'); ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($investigation['investigation_status']) {
                                                    case 'ongoing': $status_class = 'status-ongoing'; break;
                                                    case 'completed': $status_class = 'status-completed'; break;
                                                    case 'closed': $status_class = 'status-closed'; break;
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($investigation['investigation_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="caoi.php?action=view&id=<?php echo $investigation['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <a href="caoi.php?action=edit&id=<?php echo $investigation['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="caoi.php?action=delete&id=<?php echo $investigation['id']; ?>" class="btn btn-sm btn-danger action-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this investigation record?')">
                                                    <i class='bx bx-trash'></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-search-alt'></i>
                            <h5>No Investigation Records Found</h5>
                            <p>Start by creating a new investigation record.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createInvestigationModal">
                                <i class='bx bx-plus'></i> Create Investigation
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Investigation Modal -->
    <div class="modal fade" id="createInvestigationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Investigation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Incident *</label>
                                <select class="form-select" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>"><?php echo htmlspecialchars($incident['report_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Investigator *</label>
                                <select class="form-select" name="investigator_id" required>
                                    <option value="">Select Investigator</option>
                                    <?php foreach ($investigators as $investigator): ?>
                                        <option value="<?php echo $investigator['id']; ?>"><?php echo htmlspecialchars($investigator['first_name'] . ' ' . $investigator['last_name'] . ' (' . $investigator['employee_id'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Investigation Date *</label>
                                <input type="date" class="form-control" name="investigation_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="investigation_status" required>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Cause Classification</label>
                                <input type="text" class="form-control" name="cause_classification" placeholder="e.g., Electrical, Cooking, Arson">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Origin Location</label>
                                <input type="text" class="form-control" name="origin_location" placeholder="Specific location where fire originated">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ignition Source</label>
                            <input type="text" class="form-control" name="ignition_source" placeholder="e.g., Faulty wiring, Cigarette, Lightning">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contributing Factors</label>
                            <textarea class="form-control" name="contributing_factors" rows="3" placeholder="Factors that contributed to the incident"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Evidence Collected</label>
                            <textarea class="form-control" name="evidence_collected" rows="3" placeholder="List of evidence collected during investigation"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Witness Statements</label>
                            <textarea class="form-control" name="witness_statements" rows="3" placeholder="Summary of witness statements"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Final Report</label>
                            <textarea class="form-control" name="final_report" rows="4" placeholder="Final investigation report and conclusions"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_investigation" class="btn btn-primary">Create Investigation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Investigation Modal -->
    <?php if ($edit_mode && $investigation_details): ?>
    <div class="modal fade show" id="editInvestigationModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Investigation</h5>
                    <a href="caoi.php" class="btn-close" aria-label="Close"></a>
                </div>
                <form method="POST">
                    <input type="hidden" name="investigation_id" value="<?php echo $investigation_details['id']; ?>">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Incident *</label>
                                <select class="form-select" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>" <?php echo $incident['id'] == $investigation_details['incident_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($incident['report_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Investigator *</label>
                                <select class="form-select" name="investigator_id" required>
                                    <option value="">Select Investigator</option>
                                    <?php foreach ($investigators as $investigator): ?>
                                        <option value="<?php echo $investigator['id']; ?>" <?php echo $investigator['id'] == $investigation_details['investigator_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($investigator['first_name'] . ' ' . $investigator['last_name'] . ' (' . $investigator['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Investigation Date *</label>
                                <input type="date" class="form-control" name="investigation_date" required value="<?php echo $investigation_details['investigation_date']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="investigation_status" required>
                                    <option value="ongoing" <?php echo $investigation_details['investigation_status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo $investigation_details['investigation_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="closed" <?php echo $investigation_details['investigation_status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Cause Classification</label>
                                <input type="text" class="form-control" name="cause_classification" value="<?php echo htmlspecialchars($investigation_details['cause_classification'] ?? ''); ?>" placeholder="e.g., Electrical, Cooking, Arson">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Origin Location</label>
                                <input type="text" class="form-control" name="origin_location" value="<?php echo htmlspecialchars($investigation_details['origin_location'] ?? ''); ?>" placeholder="Specific location where fire originated">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ignition Source</label>
                            <input type="text" class="form-control" name="ignition_source" value="<?php echo htmlspecialchars($investigation_details['ignition_source'] ?? ''); ?>" placeholder="e.g., Faulty wiring, Cigarette, Lightning">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contributing Factors</label>
                            <textarea class="form-control" name="contributing_factors" rows="3" placeholder="Factors that contributed to the incident"><?php echo htmlspecialchars($investigation_details['contributing_factors'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Evidence Collected</label>
                            <textarea class="form-control" name="evidence_collected" rows="3" placeholder="List of evidence collected during investigation"><?php echo htmlspecialchars($investigation_details['evidence_collected'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Witness Statements</label>
                            <textarea class="form-control" name="witness_statements" rows="3" placeholder="Summary of witness statements"><?php echo htmlspecialchars($investigation_details['witness_statements'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Final Report</label>
                            <textarea class="form-control" name="final_report" rows="4" placeholder="Final investigation report and conclusions"><?php echo htmlspecialchars($investigation_details['final_report'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="caoi.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_investigation" class="btn btn-primary">Update Investigation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Search functionality
        document.getElementById('investigationSearch').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.investigation-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>