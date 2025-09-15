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
$active_submodule = 'personnel_and_unit_involvement';

// Initialize variables
$error_message = '';
$success_message = '';
$personnel_involvements = [];
$unit_involvements = [];
$personnel_details = null;
$unit_details = null;
$edit_personnel_mode = false;
$edit_unit_mode = false;
$incidents = [];
$employees = [];

// Fetch all incidents for dropdown
try {
    $query = "SELECT id, report_title FROM piar.incident_analysis_reports WHERE status != 'archived' ORDER BY created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
}

// Fetch all employees for dropdown
try {
    $query = "SELECT id, first_name, last_name, employee_id FROM frsm.employees WHERE is_active = 1 ORDER BY last_name, first_name";
    $employees = $dbManager->fetchAll("frsm", $query);
} catch (Exception $e) {
    error_log("Fetch employees error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Personnel Involvement Form
        if (isset($_POST['create_personnel_involvement'])) {
            $query = "INSERT INTO piar.personnel_involvement 
                     (incident_id, personnel_id, role, arrival_time, departure_time, actions_performed, performance_notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['personnel_id'],
                $_POST['role'],
                $_POST['arrival_time'] ?: null,
                $_POST['departure_time'] ?: null,
                $_POST['actions_performed'] ?: null,
                $_POST['performance_notes'] ?: null
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Personnel involvement recorded successfully!";
            
        } elseif (isset($_POST['update_personnel_involvement'])) {
            $involvement_id = $_POST['involvement_id'];
            $query = "UPDATE piar.personnel_involvement 
                     SET incident_id = ?, personnel_id = ?, role = ?, arrival_time = ?, 
                         departure_time = ?, actions_performed = ?, performance_notes = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['incident_id'],
                $_POST['personnel_id'],
                $_POST['role'],
                $_POST['arrival_time'] ?: null,
                $_POST['departure_time'] ?: null,
                $_POST['actions_performed'] ?: null,
                $_POST['performance_notes'] ?: null,
                $involvement_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Personnel involvement updated successfully!";
        }
        
        // Unit Involvement Form
        if (isset($_POST['create_unit_involvement'])) {
            $query = "INSERT INTO piar.unit_involvement 
                     (incident_id, unit_id, dispatch_time, arrival_time, departure_time, equipment_used, actions_performed, performance_notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['unit_id'],
                $_POST['dispatch_time'],
                $_POST['arrival_time'] ?: null,
                $_POST['departure_time'] ?: null,
                $_POST['equipment_used'] ?: null,
                $_POST['actions_performed'] ?: null,
                $_POST['performance_notes'] ?: null
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Unit involvement recorded successfully!";
            
        } elseif (isset($_POST['update_unit_involvement'])) {
            $involvement_id = $_POST['involvement_id'];
            $query = "UPDATE piar.unit_involvement 
                     SET incident_id = ?, unit_id = ?, dispatch_time = ?, arrival_time = ?, 
                         departure_time = ?, equipment_used = ?, actions_performed = ?, performance_notes = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['incident_id'],
                $_POST['unit_id'],
                $_POST['dispatch_time'],
                $_POST['arrival_time'] ?: null,
                $_POST['departure_time'] ?: null,
                $_POST['equipment_used'] ?: null,
                $_POST['actions_performed'] ?: null,
                $_POST['performance_notes'] ?: null,
                $involvement_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Unit involvement updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Involvement error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit/delete)
if (isset($_GET['action'])) {
    try {
        $type = $_GET['type'] ?? '';
        $id = $_GET['id'] ?? 0;
        
        if ($_GET['action'] === 'view' && $id) {
            if ($type === 'personnel') {
                $query = "SELECT pi.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
                         FROM piar.personnel_involvement pi
                         LEFT JOIN piar.incident_analysis_reports iar ON pi.incident_id = iar.id
                         LEFT JOIN frsm.employees e ON pi.personnel_id = e.id
                         WHERE pi.id = ?";
                $personnel_details = $dbManager->fetch("piar", $query, [$id]);
            } elseif ($type === 'unit') {
                $query = "SELECT ui.*, iar.report_title 
                         FROM piar.unit_involvement ui
                         LEFT JOIN piar.incident_analysis_reports iar ON ui.incident_id = iar.id
                         WHERE ui.id = ?";
                $unit_details = $dbManager->fetch("piar", $query, [$id]);
            }
            
        } elseif ($_GET['action'] === 'edit' && $id) {
            if ($type === 'personnel') {
                $query = "SELECT * FROM piar.personnel_involvement WHERE id = ?";
                $personnel_details = $dbManager->fetch("piar", $query, [$id]);
                $edit_personnel_mode = true;
            } elseif ($type === 'unit') {
                $query = "SELECT * FROM piar.unit_involvement WHERE id = ?";
                $unit_details = $dbManager->fetch("piar", $query, [$id]);
                $edit_unit_mode = true;
            }
            
        } elseif ($_GET['action'] === 'delete' && $id) {
            if ($type === 'personnel') {
                $query = "DELETE FROM piar.personnel_involvement WHERE id = ?";
                $dbManager->query("piar", $query, [$id]);
                $success_message = "Personnel involvement deleted successfully!";
            } elseif ($type === 'unit') {
                $query = "DELETE FROM piar.unit_involvement WHERE id = ?";
                $dbManager->query("piar", $query, [$id]);
                $success_message = "Unit involvement deleted successfully!";
            }
        }
    } catch (Exception $e) {
        error_log("Involvement action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all personnel involvements
try {
    $query = "SELECT pi.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
             FROM piar.personnel_involvement pi
             LEFT JOIN piar.incident_analysis_reports iar ON pi.incident_id = iar.id
             LEFT JOIN frsm.employees e ON pi.personnel_id = e.id
             ORDER BY pi.created_at DESC";
    $personnel_involvements = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch personnel involvements error: " . $e->getMessage());
}

// Fetch all unit involvements
try {
    $query = "SELECT ui.*, iar.report_title 
             FROM piar.unit_involvement ui
             LEFT JOIN piar.incident_analysis_reports iar ON ui.incident_id = iar.id
             ORDER BY ui.created_at DESC";
    $unit_involvements = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch unit involvements error: " . $e->getMessage());
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
        
        .involvement-table {
            font-size: 0.9rem;
        }
        
        .involvement-table th {
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
        
        /* Tabs styling */
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1rem;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
            background: transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
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
        
        /* Involvement status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background-color: #198754; color: #fff; }
        .status-completed { background-color: #6c757d; color: #fff; }
        
        /* Tab content styling */
        .tab-pane {
            padding: 20px 0;
        }
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
                     <a href="paui.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-group'></i>
                        <span>Personnel and Unit Involvement</span>
                    </a>
                     <a href="../cause_and_origin_investigation/caoi.php" class="sidebar-dropdown-link">
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
                    <h1>Personnel and Unit Involvement</h1>
                    <p>Track personnel and unit involvement in incidents</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPersonnelModal">
                        <i class='bx bx-user-plus'></i> Add Personnel
                    </button>
                    <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#createUnitModal">
                        <i class='bx bx-car'></i> Add Unit
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
            
            <!-- Personnel/Unit Details View -->
            <?php if ($personnel_details): ?>
                <!-- Personnel Details View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Personnel Involvement Details</h4>
                        <div>
                            <?php if ($personnel_details['departure_time']): ?>
                                <span class="status-badge status-completed">Completed</span>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($personnel_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Personnel:</strong> <?php echo htmlspecialchars($personnel_details['first_name'] . ' ' . $personnel_details['last_name'] . ' (' . $personnel_details['employee_id'] . ')'); ?></p>
                            <p><strong>Role:</strong> <?php echo htmlspecialchars($personnel_details['role'] ?? 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Arrival Time:</strong> <?php echo $personnel_details['arrival_time'] ? date('M j, Y g:i A', strtotime($personnel_details['arrival_time'])) : 'Not recorded'; ?></p>
                            <p><strong>Departure Time:</strong> <?php echo $personnel_details['departure_time'] ? date('M j, Y g:i A', strtotime($personnel_details['departure_time'])) : 'Not recorded'; ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($personnel_details['actions_performed'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Actions Performed</h5>
                        <p><?php echo nl2br(htmlspecialchars($personnel_details['actions_performed'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($personnel_details['performance_notes'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Performance Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($personnel_details['performance_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="paui.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="paui.php?action=edit&type=personnel&id=<?php echo $personnel_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type" value="personnel">
                            <input type="hidden" name="id" value="<?php echo $personnel_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this personnel involvement record?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($unit_details): ?>
                <!-- Unit Details View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Unit Involvement Details</h4>
                        <div>
                            <?php if ($unit_details['departure_time']): ?>
                                <span class="status-badge status-completed">Completed</span>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($unit_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Unit ID:</strong> <?php echo htmlspecialchars($unit_details['unit_id'] ?? 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Dispatch Time:</strong> <?php echo date('M j, Y g:i A', strtotime($unit_details['dispatch_time'])); ?></p>
                            <p><strong>Arrival Time:</strong> <?php echo $unit_details['arrival_time'] ? date('M j, Y g:i A', strtotime($unit_details['arrival_time'])) : 'Not recorded'; ?></p>
                            <p><strong>Departure Time:</strong> <?php echo $unit_details['departure_time'] ? date('M j, Y g:i A', strtotime($unit_details['departure_time'])) : 'Not recorded'; ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($unit_details['equipment_used'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Equipment Used</h5>
                        <p><?php echo nl2br(htmlspecialchars($unit_details['equipment_used'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($unit_details['actions_performed'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Actions Performed</h5>
                        <p><?php echo nl2br(htmlspecialchars($unit_details['actions_performed'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($unit_details['performance_notes'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Performance Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($unit_details['performance_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="paui.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="paui.php?action=edit&type=unit&id=<?php echo $unit_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type" value="unit">
                            <input type="hidden" name="id" value="<?php echo $unit_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this unit involvement record?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="involvementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personnel-tab" data-bs-toggle="tab" data-bs-target="#personnel" type="button" role="tab" aria-controls="personnel" aria-selected="true">
                            <i class='bx bx-user'></i> Personnel Involvement
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="unit-tab" data-bs-toggle="tab" data-bs-target="#unit" type="button" role="tab" aria-controls="unit" aria-selected="false">
                            <i class='bx bx-car'></i> Unit Involvement
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="involvementTabsContent">
                    <!-- Personnel Involvement Tab -->
                    <div class="tab-pane fade show active" id="personnel" role="tabpanel" aria-labelledby="personnel-tab">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Personnel Involvement Records</h4>
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="Search personnel..." id="personnelSearch">
                                </div>
                            </div>
                            
                            <?php if (!empty($personnel_involvements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover involvement-table">
                                        <thead>
                                            <tr>
                                                <th>Incident</th>
                                                <th>Personnel</th>
                                                <th>Role</th>
                                                <th>Arrival Time</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($personnel_involvements as $personnel): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($personnel['report_title'] ?? 'Unknown Incident'); ?></td>
                                                    <td><?php echo htmlspecialchars($personnel['first_name'] . ' ' . $personnel['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($personnel['role'] ?? 'Not specified'); ?></td>
                                                    <td><?php echo $personnel['arrival_time'] ? date('M j, Y g:i A', strtotime($personnel['arrival_time'])) : 'Not recorded'; ?></td>
                                                    <td>
                                                        <?php if ($personnel['departure_time']): ?>
                                                            <span class="status-badge status-completed">Completed</span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-active">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="paui.php?action=view&type=personnel&id=<?php echo $personnel['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                            <i class='bx bx-show'></i>
                                                        </a>
                                                        <a href="paui.php?action=edit&type=personnel&id=<?php echo $personnel['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                            <i class='bx bx-edit'></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class='bx bx-user-x'></i>
                                    <h5>No Personnel Involvement Records</h5>
                                    <p>Get started by adding personnel involvement records.</p>
                                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createPersonnelModal">
                                        Add Personnel Involvement
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Unit Involvement Tab -->
                    <div class="tab-pane fade" id="unit" role="tabpanel" aria-labelledby="unit-tab">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Unit Involvement Records</h4>
                                <div class="form-group">
                                    <input type="text" class="form-control" placeholder="Search units..." id="unitSearch">
                                </div>
                            </div>
                            
                            <?php if (!empty($unit_involvements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover involvement-table">
                                        <thead>
                                            <tr>
                                                <th>Incident</th>
                                                <th>Unit ID</th>
                                                <th>Dispatch Time</th>
                                                <th>Arrival Time</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unit_involvements as $unit): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($unit['report_title'] ?? 'Unknown Incident'); ?></td>
                                                    <td><?php echo htmlspecialchars($unit['unit_id'] ?? 'Not specified'); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($unit['dispatch_time'])); ?></td>
                                                    <td><?php echo $unit['arrival_time'] ? date('M j, Y g:i A', strtotime($unit['arrival_time'])) : 'Not recorded'; ?></td>
                                                    <td>
                                                        <?php if ($unit['departure_time']): ?>
                                                            <span class="status-badge status-completed">Completed</span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-active">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="paui.php?action=view&type=unit&id=<?php echo $unit['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                            <i class='bx bx-show'></i>
                                                        </a>
                                                        <a href="paui.php?action=edit&type=unit&id=<?php echo $unit['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                            <i class='bx bx-edit'></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class='bx bx-car'></i>
                                    <h5>No Unit Involvement Records</h5>
                                    <p>Get started by adding unit involvement records.</p>
                                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createUnitModal">
                                        Add Unit Involvement
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Personnel Involvement Modal -->
    <div class="modal fade" id="createPersonnelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Personnel Involvement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="incident_id" class="form-label">Incident</label>
                                <select class="form-select" id="incident_id" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>"><?php echo htmlspecialchars($incident['report_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="personnel_id" class="form-label">Personnel</label>
                                <select class="form-select" id="personnel_id" name="personnel_id" required>
                                    <option value="">Select Personnel</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" name="role" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="arrival_time" name="arrival_time">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="departure_time" class="form-label">Departure Time</label>
                                <input type="datetime-local" class="form-control" id="departure_time" name="departure_time">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="actions_performed" class="form-label">Actions Performed</label>
                            <textarea class="form-control" id="actions_performed" name="actions_performed" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="performance_notes" class="form-label">Performance Notes</label>
                            <textarea class="form-control" id="performance_notes" name="performance_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="create_personnel_involvement">Add Involvement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Unit Involvement Modal -->
    <div class="modal fade" id="createUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Unit Involvement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_incident_id" class="form-label">Incident</label>
                                <select class="form-select" id="unit_incident_id" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>"><?php echo htmlspecialchars($incident['report_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="unit_id" class="form-label">Unit ID</label>
                                <input type="text" class="form-control" id="unit_id" name="unit_id" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dispatch_time" class="form-label">Dispatch Time</label>
                                <input type="datetime-local" class="form-control" id="dispatch_time" name="dispatch_time" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="unit_arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="unit_arrival_time" name="arrival_time">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="unit_departure_time" class="form-label">Departure Time</label>
                                <input type="datetime-local" class="form-control" id="unit_departure_time" name="departure_time">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipment_used" class="form-label">Equipment Used</label>
                            <textarea class="form-control" id="equipment_used" name="equipment_used" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="unit_actions_performed" class="form-label">Actions Performed</label>
                            <textarea class="form-control" id="unit_actions_performed" name="actions_performed" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="unit_performance_notes" class="form-label">Performance Notes</label>
                            <textarea class="form-control" id="unit_performance_notes" name="performance_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="create_unit_involvement">Add Involvement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Personnel Involvement Modal -->
    <?php if ($edit_personnel_mode && $personnel_details): ?>
    <div class="modal fade show" id="editPersonnelModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Personnel Involvement</h5>
                    <a href="paui.php" class="btn-close" aria-label="Close"></a>
                </div>
                <form method="POST">
                    <input type="hidden" name="involvement_id" value="<?php echo $personnel_details['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_incident_id" class="form-label">Incident</label>
                                <select class="form-select" id="edit_incident_id" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>" <?php echo $personnel_details['incident_id'] == $incident['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($incident['report_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_personnel_id" class="form-label">Personnel</label>
                                <select class="form-select" id="edit_personnel_id" name="personnel_id" required>
                                    <option value="">Select Personnel</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php echo $personnel_details['personnel_id'] == $employee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="edit_role" name="role" value="<?php echo htmlspecialchars($personnel_details['role'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="edit_arrival_time" name="arrival_time" 
                                       value="<?php echo $personnel_details['arrival_time'] ? date('Y-m-d\TH:i', strtotime($personnel_details['arrival_time'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_departure_time" class="form-label">Departure Time</label>
                                <input type="datetime-local" class="form-control" id="edit_departure_time" name="departure_time" 
                                       value="<?php echo $personnel_details['departure_time'] ? date('Y-m-d\TH:i', strtotime($personnel_details['departure_time'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_actions_performed" class="form-label">Actions Performed</label>
                            <textarea class="form-control" id="edit_actions_performed" name="actions_performed" rows="3"><?php echo htmlspecialchars($personnel_details['actions_performed'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_performance_notes" class="form-label">Performance Notes</label>
                            <textarea class="form-control" id="edit_performance_notes" name="performance_notes" rows="2"><?php echo htmlspecialchars($personnel_details['performance_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="paui.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" name="update_personnel_involvement">Update Involvement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Edit Unit Involvement Modal -->
    <?php if ($edit_unit_mode && $unit_details): ?>
    <div class="modal fade show" id="editUnitModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Unit Involvement</h5>
                    <a href="paui.php" class="btn-close" aria-label="Close"></a>
                </div>
                <form method="POST">
                    <input type="hidden" name="involvement_id" value="<?php echo $unit_details['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_unit_incident_id" class="form-label">Incident</label>
                                <select class="form-select" id="edit_unit_incident_id" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>" <?php echo $unit_details['incident_id'] == $incident['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($incident['report_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_unit_id" class="form-label">Unit ID</label>
                                <input type="text" class="form-control" id="edit_unit_id" name="unit_id" 
                                       value="<?php echo htmlspecialchars($unit_details['unit_id'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_dispatch_time" class="form-label">Dispatch Time</label>
                                <input type="datetime-local" class="form-control" id="edit_dispatch_time" name="dispatch_time" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($unit_details['dispatch_time'])); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_unit_arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="edit_unit_arrival_time" name="arrival_time" 
                                       value="<?php echo $unit_details['arrival_time'] ? date('Y-m-d\TH:i', strtotime($unit_details['arrival_time'])) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_unit_departure_time" class="form-label">Departure Time</label>
                                <input type="datetime-local" class="form-control" id="edit_unit_departure_time" name="departure_time" 
                                       value="<?php echo $unit_details['departure_time'] ? date('Y-m-d\TH:i', strtotime($unit_details['departure_time'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_equipment_used" class="form-label">Equipment Used</label>
                            <textarea class="form-control" id="edit_equipment_used" name="equipment_used" rows="2"><?php echo htmlspecialchars($unit_details['equipment_used'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_unit_actions_performed" class="form-label">Actions Performed</label>
                            <textarea class="form-control" id="edit_unit_actions_performed" name="actions_performed" rows="3"><?php echo htmlspecialchars($unit_details['actions_performed'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_unit_performance_notes" class="form-label">Performance Notes</label>
                            <textarea class="form-control" id="edit_unit_performance_notes" name="performance_notes" rows="2"><?php echo htmlspecialchars($unit_details['performance_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="paui.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" name="update_unit_involvement">Update Involvement</button>
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
        document.getElementById('personnelSearch')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#personnel .involvement-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
        
        document.getElementById('unitSearch')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#unit .involvement-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
        
        // Auto set current datetime for new records
        document.addEventListener('DOMContentLoaded', function() {
            const dispatchTimeInput = document.getElementById('dispatch_time');
            if (dispatchTimeInput && !dispatchTimeInput.value) {
                const now = new Date();
                const formatted = now.toISOString().slice(0, 16);
                dispatchTimeInput.value = formatted;
            }
            
            const arrivalTimeInput = document.getElementById('arrival_time');
            if (arrivalTimeInput && !arrivalTimeInput.value) {
                const now = new Date();
                const formatted = now.toISOString().slice(0, 16);
                arrivalTimeInput.value = formatted;
            }
        });
    </script>
</body>
</html>