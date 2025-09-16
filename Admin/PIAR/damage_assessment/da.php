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
$active_submodule = 'damage_assessment';

// Initialize variables
$error_message = '';
$success_message = '';
$assessments = [];
$assessment_details = null;
$edit_mode = false;
$incidents = [];
$assessors = [];

// Fetch all incidents for dropdown
try {
    $query = "SELECT id, report_title FROM piar.incident_analysis_reports WHERE status != 'archived' ORDER BY created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
}

// Fetch all assessors (employees) for dropdown
try {
    $query = "SELECT id, first_name, last_name, employee_id FROM frsm.employees WHERE is_active = 1 ORDER BY last_name, first_name";
    $assessors = $dbManager->fetchAll("frsm", $query);
} catch (Exception $e) {
    error_log("Fetch assessors error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create Assessment
        if (isset($_POST['create_assessment'])) {
            $query = "INSERT INTO piar.damage_assessment 
                     (incident_id, assessor_id, assessment_date, property_damage, content_damage, 
                      business_interruption, total_estimated_loss, affected_structures, displaced_persons, 
                      casualties, fatalities, environmental_impact, assessment_notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['assessor_id'],
                $_POST['assessment_date'],
                $_POST['property_damage'] ?: null,
                $_POST['content_damage'] ?: null,
                $_POST['business_interruption'] ?: null,
                $_POST['total_estimated_loss'] ?: null,
                $_POST['affected_structures'] ?: null,
                $_POST['displaced_persons'] ?: null,
                $_POST['casualties'] ?: null,
                $_POST['fatalities'] ?: null,
                $_POST['environmental_impact'] ?: null,
                $_POST['assessment_notes'] ?: null
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Damage assessment created successfully!";
            
        } elseif (isset($_POST['update_assessment'])) {
            // Update Assessment
            $assessment_id = $_POST['assessment_id'];
            $query = "UPDATE piar.damage_assessment 
                     SET incident_id = ?, assessor_id = ?, assessment_date = ?, property_damage = ?, 
                         content_damage = ?, business_interruption = ?, total_estimated_loss = ?, 
                         affected_structures = ?, displaced_persons = ?, casualties = ?, fatalities = ?, 
                         environmental_impact = ?, assessment_notes = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['incident_id'],
                $_POST['assessor_id'],
                $_POST['assessment_date'],
                $_POST['property_damage'] ?: null,
                $_POST['content_damage'] ?: null,
                $_POST['business_interruption'] ?: null,
                $_POST['total_estimated_loss'] ?: null,
                $_POST['affected_structures'] ?: null,
                $_POST['displaced_persons'] ?: null,
                $_POST['casualties'] ?: null,
                $_POST['fatalities'] ?: null,
                $_POST['environmental_impact'] ?: null,
                $_POST['assessment_notes'] ?: null,
                $assessment_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Damage assessment updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Assessment error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit/delete)
if (isset($_GET['action'])) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if ($_GET['action'] === 'view' && $id) {
            $query = "SELECT da.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
                     FROM piar.damage_assessment da
                     LEFT JOIN piar.incident_analysis_reports iar ON da.incident_id = iar.id
                     LEFT JOIN frsm.employees e ON da.assessor_id = e.id
                     WHERE da.id = ?";
            $assessment_details = $dbManager->fetch("piar", $query, [$id]);
            
        } elseif ($_GET['action'] === 'edit' && $id) {
            $query = "SELECT * FROM piar.damage_assessment WHERE id = ?";
            $assessment_details = $dbManager->fetch("piar", $query, [$id]);
            $edit_mode = true;
            
        } elseif ($_GET['action'] === 'delete' && $id) {
            $query = "DELETE FROM piar.damage_assessment WHERE id = ?";
            $dbManager->query("piar", $query, [$id]);
            $success_message = "Damage assessment deleted successfully!";
        }
    } catch (Exception $e) {
        error_log("Assessment action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all assessments
try {
    $query = "SELECT da.*, iar.report_title, e.first_name, e.last_name, e.employee_id 
             FROM piar.damage_assessment da
             LEFT JOIN piar.incident_analysis_reports iar ON da.incident_id = iar.id
             LEFT JOIN frsm.employees e ON da.assessor_id = e.id
             ORDER BY da.created_at DESC";
    $assessments = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch assessments error: " . $e->getMessage());
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
        
        .assessment-table {
            font-size: 0.9rem;
        }
        
        .assessment-table th {
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
        
        /* Stats cards colors */
        .stat-total { background-color: #0d6efd; }
        .stat-property { background-color: #6610f2; }
        .stat-content { background-color: #6f42c1; }
        .stat-business { background-color: #d63384; }
        
        .currency-input {
            position: relative;
        }
        
        .currency-input::before {
            content: "₱";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: bold;
            color: #6c757d;
        }
        
        .currency-input input {
            padding-left: 25px;
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
                <img src="img/frsmse1.png" alt="QC Logo">
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
                     <a href="../cause_and_origin_investigation/caoi.php" class="sidebar-dropdown-link">
                       <i class='bx bx-search-alt'></i>
                        <span>Cause and Origin Investigation</span>
                    </a>
                       <a href="da.php" class="sidebar-dropdown-link active">
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
                    <h1>Damage Assessment</h1>
                    <p>Assess and document damages from incidents</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                        <i class='bx bx-plus'></i> New Assessment
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
                        <div class="number"><?php echo count($assessments); ?></div>
                        <div class="label">Total Assessments</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-property">
                        <i class='bx bx-building-house'></i>
                        <div class="number">₱<?php 
                            $totalProperty = array_sum(array_column($assessments, 'property_damage'));
                            echo number_format($totalProperty, 2);
                        ?></div>
                        <div class="label">Property Damage</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-content">
                        <i class='bx bx-package'></i>
                        <div class="number">₱<?php 
                            $totalContent = array_sum(array_column($assessments, 'content_damage'));
                            echo number_format($totalContent, 2);
                        ?></div>
                        <div class="label">Content Damage</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-business">
                        <i class='bx bx-store'></i>
                        <div class="number">₱<?php 
                            $totalBusiness = array_sum(array_column($assessments, 'business_interruption'));
                            echo number_format($totalBusiness, 2);
                        ?></div>
                        <div class="label">Business Loss</div>
                    </div>
                </div>
            </div>
            
            <!-- Assessment Details View -->
            <?php if ($assessment_details && !$edit_mode): ?>
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Damage Assessment Details</h4>
                        <div>
                            <span class="badge bg-primary">
                                <?php echo date('M j, Y', strtotime($assessment_details['assessment_date'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($assessment_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Assessor:</strong> <?php echo htmlspecialchars($assessment_details['first_name'] . ' ' . $assessment_details['last_name'] . ' (' . $assessment_details['employee_id'] . ')'); ?></p>
                            <p><strong>Assessment Date:</strong> <?php echo date('M j, Y', strtotime($assessment_details['assessment_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Affected Structures:</strong> <?php echo htmlspecialchars($assessment_details['affected_structures'] ?? 'Not specified'); ?></p>
                            <p><strong>Displaced Persons:</strong> <?php echo htmlspecialchars($assessment_details['displaced_persons'] ?? 'Not specified'); ?></p>
                            <p><strong>Casualties:</strong> <?php echo htmlspecialchars($assessment_details['casualties'] ?? '0'); ?></p>
                            <p><strong>Fatalities:</strong> <?php echo htmlspecialchars($assessment_details['fatalities'] ?? '0'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">Property Damage</h5>
                                    <h3 class="text-primary">₱<?php echo number_format($assessment_details['property_damage'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">Content Damage</h5>
                                    <h3 class="text-info">₱<?php echo number_format($assessment_details['content_damage'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h5 class="card-title">Business Loss</h5>
                                    <h3 class="text-warning">₱<?php echo number_format($assessment_details['business_interruption'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Estimated Loss</h5>
                                    <h2 class="text-danger">₱<?php echo number_format($assessment_details['total_estimated_loss'] ?? 0, 2); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($assessment_details['environmental_impact'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Environmental Impact</h5>
                        <p><?php echo nl2br(htmlspecialchars($assessment_details['environmental_impact'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($assessment_details['assessment_notes'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Assessment Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($assessment_details['assessment_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="da.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="da.php?action=edit&id=<?php echo $assessment_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $assessment_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this damage assessment record?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Assessments List -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Damage Assessment Records</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search assessments..." id="assessmentSearch">
                        </div>
                    </div>
                    
                    <?php if (!empty($assessments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover assessment-table">
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Assessor</th>
                                        <th>Assessment Date</th>
                                        <th>Property Damage</th>
                                        <th>Total Loss</th>
                                        <th>Affected Structures</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $assessment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assessment['report_title'] ?? 'Unknown Incident'); ?></td>
                                            <td><?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($assessment['assessment_date'])); ?></td>
                                            <td>₱<?php echo number_format($assessment['property_damage'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($assessment['total_estimated_loss'] ?? 0, 2); ?></td>
                                            <td><?php echo htmlspecialchars($assessment['affected_structures'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="da.php?action=view&id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <a href="da.php?action=edit&id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="da.php?action=delete&id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-danger action-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this assessment?')">
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
                            <i class='bx bx-file-blank'></i>
                            <h5>No Damage Assessments Found</h5>
                            <p>Get started by creating your first damage assessment record.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                                <i class='bx bx-plus'></i> Create Assessment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Assessment Modal -->
    <div class="modal fade" id="createAssessmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Damage Assessment</h5>
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
                                        <option value="<?php echo $incident['id']; ?>" <?php echo ($edit_mode && $assessment_details['incident_id'] == $incident['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($incident['report_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Assessor *</label>
                                <select class="form-select" name="assessor_id" required>
                                    <option value="">Select Assessor</option>
                                    <?php foreach ($assessors as $assessor): ?>
                                        <option value="<?php echo $assessor['id']; ?>" <?php echo ($edit_mode && $assessment_details['assessor_id'] == $assessor['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($assessor['first_name'] . ' ' . $assessor['last_name'] . ' (' . $assessor['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Assessment Date *</label>
                                <input type="date" class="form-control" name="assessment_date" value="<?php echo $edit_mode ? $assessment_details['assessment_date'] : date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Damage Assessment</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Property Damage (₱)</label>
                                    <div class="currency-input">
                                        <input type="number" class="form-control" name="property_damage" step="0.01" min="0" value="<?php echo $edit_mode ? $assessment_details['property_damage'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Content Damage (₱)</label>
                                    <div class="currency-input">
                                        <input type="number" class="form-control" name="content_damage" step="0.01" min="0" value="<?php echo $edit_mode ? $assessment_details['content_damage'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Business Interruption (₱)</label>
                                    <div class="currency-input">
                                        <input type="number" class="form-control" name="business_interruption" step="0.01" min="0" value="<?php echo $edit_mode ? $assessment_details['business_interruption'] : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Total Estimated Loss (₱)</label>
                                    <div class="currency-input">
                                        <input type="number" class="form-control" name="total_estimated_loss" step="0.01" min="0" value="<?php echo $edit_mode ? $assessment_details['total_estimated_loss'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Affected Structures</label>
                                    <input type="number" class="form-control" name="affected_structures" min="0" value="<?php echo $edit_mode ? $assessment_details['affected_structures'] : ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Displaced Persons</label>
                                    <input type="number" class="form-control" name="displaced_persons" min="0" value="<?php echo $edit_mode ? $assessment_details['displaced_persons'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Casualties</label>
                                    <input type="number" class="form-control" name="casualties" min="0" value="<?php echo $edit_mode ? $assessment_details['casualties'] : ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fatalities</label>
                                    <input type="number" class="form-control" name="fatalities" min="0" value="<?php echo $edit_mode ? $assessment_details['fatalities'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Additional Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Environmental Impact</label>
                                <textarea class="form-control" name="environmental_impact" rows="3"><?php echo $edit_mode ? $assessment_details['environmental_impact'] : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Assessment Notes</label>
                                <textarea class="form-control" name="assessment_notes" rows="3"><?php echo $edit_mode ? $assessment_details['assessment_notes'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="assessment_id" value="<?php echo $assessment_details['id']; ?>">
                            <button type="submit" name="update_assessment" class="btn btn-primary">Update Assessment</button>
                        <?php else: ?>
                            <button type="submit" name="create_assessment" class="btn btn-primary">Create Assessment</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Search functionality
        $(document).ready(function() {
            $('#assessmentSearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.assessment-table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
            
            // Auto-open edit modal if in edit mode
            <?php if ($edit_mode): ?>
                var editModal = new bootstrap.Modal(document.getElementById('createAssessmentModal'));
                editModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>