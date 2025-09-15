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
$active_submodule = 'incident_summary_documentation';

// Initialize variables
$error_message = '';
$success_message = '';
$incidents = [];
$incident_details = null;
$edit_mode = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_incident'])) {
            // Create new incident
            $query = "INSERT INTO piar.incident_analysis_reports 
                     (report_title, incident_summary, response_timeline, personnel_involved, 
                      units_involved, cause_investigation, origin_investigation, 
                      damage_assessment, lessons_learned, recommendations, created_by, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
            
            $params = [
                $_POST['report_title'],
                $_POST['incident_summary'],
                $_POST['response_timeline'],
                $_POST['personnel_involved'],
                $_POST['units_involved'],
                $_POST['cause_investigation'],
                $_POST['origin_investigation'],
                $_POST['damage_assessment'],
                $_POST['lessons_learned'],
                $_POST['recommendations'],
                $_SESSION['user_id']
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Incident report created successfully!";
            
        } elseif (isset($_POST['update_incident'])) {
            // Update existing incident
            $incident_id = $_POST['incident_id'];
            $query = "UPDATE piar.incident_analysis_reports 
                     SET report_title = ?, incident_summary = ?, response_timeline = ?, 
                         personnel_involved = ?, units_involved = ?, cause_investigation = ?, 
                         origin_investigation = ?, damage_assessment = ?, lessons_learned = ?, 
                         recommendations = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?";
            
            $params = [
                $_POST['report_title'],
                $_POST['incident_summary'],
                $_POST['response_timeline'],
                $_POST['personnel_involved'],
                $_POST['units_involved'],
                $_POST['cause_investigation'],
                $_POST['origin_investigation'],
                $_POST['damage_assessment'],
                $_POST['lessons_learned'],
                $_POST['recommendations'],
                $incident_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Incident report updated successfully!";
            
        } elseif (isset($_POST['change_status'])) {
            // Change incident status
            $incident_id = $_POST['incident_id'];
            $new_status = $_POST['new_status'];
            
            $query = "UPDATE piar.incident_analysis_reports 
                     SET status = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?";
            
            $dbManager->query("piar", $query, [$new_status, $incident_id]);
            $success_message = "Incident status updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Incident management error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit incidents)
if (isset($_GET['action'])) {
    try {
        if ($_GET['action'] === 'view' && isset($_GET['id'])) {
            // View incident details
            $incident_id = $_GET['id'];
            $query = "SELECT iar.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
                     FROM piar.incident_analysis_reports iar
                     LEFT JOIN frsm.users u ON iar.created_by = u.id
                     WHERE iar.id = ?";
            $incident_details = $dbManager->fetch("piar", $query, [$incident_id]);
            
        } elseif ($_GET['action'] === 'edit' && isset($_GET['id'])) {
            // Edit incident
            $incident_id = $_GET['id'];
            $query = "SELECT * FROM piar.incident_analysis_reports WHERE id = ?";
            $incident_details = $dbManager->fetch("piar", $query, [$incident_id]);
            $edit_mode = true;
            
        } elseif ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            // Delete incident (soft delete by changing status to archived)
            $incident_id = $_GET['id'];
            $query = "UPDATE piar.incident_analysis_reports 
                     SET status = 'archived', updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?";
            $dbManager->query("piar", $query, [$incident_id]);
            $success_message = "Incident report archived successfully!";
        }
    } catch (Exception $e) {
        error_log("Incident action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all incidents
try {
    $query = "SELECT piar.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
             FROM piar.incident_analysis_reports iar
             LEFT JOIN frsm.users u ON iar.created_by = u.id
             WHERE iar.status != 'archived'
             ORDER BY iar.created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
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
        
        .incident-table {
            font-size: 0.9rem;
        }
        
        .incident-table th {
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-draft { background-color: #ffc107; color: #000; }
        .status-submitted { background-color: #0d6efd; color: #fff; }
        .status-approved { background-color: #198754; color: #fff; }
        .status-archived { background-color: #6c757d; color: #fff; }
        
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
                    <a href="isd.php" class="sidebar-dropdown-link active">
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
                    <h1>Incident Summary Documentation</h1>
                    <p>Create, view, and manage incident analysis reports</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIncidentModal">
                        <i class='bx bx-plus'></i> New Incident Report
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
            
            <!-- Incident List / Details View -->
            <?php if ($incident_details): ?>
                <!-- Incident Details View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4><?php echo htmlspecialchars($incident_details['report_title']); ?></h4>
                        <div>
                            <span class="status-badge status-<?php echo $incident_details['status']; ?>">
                                <?php echo ucfirst($incident_details['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Created by:</strong> <?php echo htmlspecialchars($incident_details['creator_name'] ?? 'Unknown'); ?></p>
                            <p><strong>Created at:</strong> <?php echo date('M j, Y g:i A', strtotime($incident_details['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last updated:</strong> <?php echo date('M j, Y g:i A', strtotime($incident_details['updated_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Incident Summary</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['incident_summary'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Response Timeline</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['response_timeline'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Personnel Involved</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['personnel_involved'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Units Involved</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['units_involved'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Cause Investigation</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['cause_investigation'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Origin Investigation</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['origin_investigation'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Damage Assessment</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['damage_assessment'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Lessons Learned</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['lessons_learned'])); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Recommendations</h5>
                        <p><?php echo nl2br(htmlspecialchars($incident_details['recommendations'])); ?></p>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="isd.php" class="btn btn-secondary me-2">Back to List</a>
                        <?php if ($incident_details['status'] !== 'archived'): ?>
                            <a href="isd.php?action=edit&id=<?php echo $incident_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                            
                            <!-- Status Change Form -->
                            <form method="POST" class="d-inline me-2">
                                <input type="hidden" name="incident_id" value="<?php echo $incident_details['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $incident_details['status'] === 'draft' ? 'submitted' : 'approved'; ?>">
                                <button type="submit" name="change_status" class="btn btn-success">
                                    <?php echo $incident_details['status'] === 'draft' ? 'Submit' : 'Approve'; ?>
                                </button>
                            </form>
                            
                            <!-- Archive Form -->
                            <form method="GET" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $incident_details['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to archive this incident report?')">Archive</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Incident List View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Incident Reports</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search reports..." id="searchInput">
                        </div>
                    </div>
                    
                    <?php if (!empty($incidents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover incident-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidents as $incident): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($incident['report_title']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $incident['status']; ?>">
                                                    <?php echo ucfirst($incident['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($incident['creator_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($incident['created_at'])); ?></td>
                                            <td>
                                                <a href="isd.php?action=view&id=<?php echo $incident['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <?php if ($incident['status'] !== 'archived'): ?>
                                                    <a href="isd.php?action=edit&id=<?php echo $incident['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                        <i class='bx bx-edit'></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-file'></i>
                            <h5>No Incident Reports</h5>
                            <p>Get started by creating your first incident report.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createIncidentModal">
                                Create Incident Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Incident Modal -->
    <div class="modal fade" id="createIncidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Incident Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="report_title" class="form-label">Report Title</label>
                            <input type="text" class="form-control" id="report_title" name="report_title" required>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Incident Summary</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="incident_summary" name="incident_summary" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Response Timeline</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="response_timeline" name="response_timeline" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Personnel Involved</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="personnel_involved" name="personnel_involved" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Units Involved</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="units_involved" name="units_involved" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Cause Investigation</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="cause_investigation" name="cause_investigation" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Origin Investigation</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="origin_investigation" name="origin_investigation" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Damage Assessment</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="damage_assessment" name="damage_assessment" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Lessons Learned</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="lessons_learned" name="lessons_learned" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title">Recommendations</h5>
                            <div class="mb-3">
                                <textarea class="form-control" id="recommendations" name="recommendations" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_incident" class="btn btn-primary">Create Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Incident Modal (shown when in edit mode) -->
    <?php if ($edit_mode && $incident_details): ?>
        <div class="modal fade show" id="editIncidentModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Incident Report</h5>
                        <a href="isd.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="incident_id" value="<?php echo $incident_details['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_report_title" class="form-label">Report Title</label>
                                <input type="text" class="form-control" id="edit_report_title" name="report_title" 
                                       value="<?php echo htmlspecialchars($incident_details['report_title']); ?>" required>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Incident Summary</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_incident_summary" name="incident_summary" rows="4" required><?php echo htmlspecialchars($incident_details['incident_summary']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Response Timeline</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_response_timeline" name="response_timeline" rows="3"><?php echo htmlspecialchars($incident_details['response_timeline']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Personnel Involved</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_personnel_involved" name="personnel_involved" rows="3"><?php echo htmlspecialchars($incident_details['personnel_involved']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Units Involved</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_units_involved" name="units_involved" rows="3"><?php echo htmlspecialchars($incident_details['units_involved']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Cause Investigation</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_cause_investigation" name="cause_investigation" rows="3"><?php echo htmlspecialchars($incident_details['cause_investigation']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Origin Investigation</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_origin_investigation" name="origin_investigation" rows="3"><?php echo htmlspecialchars($incident_details['origin_investigation']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Damage Assessment</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_damage_assessment" name="damage_assessment" rows="3"><?php echo htmlspecialchars($incident_details['damage_assessment']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Lessons Learned</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_lessons_learned" name="lessons_learned" rows="3"><?php echo htmlspecialchars($incident_details['lessons_learned']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5 class="form-section-title">Recommendations</h5>
                                <div class="mb-3">
                                    <textarea class="form-control" id="edit_recommendations" name="recommendations" rows="3"><?php echo htmlspecialchars($incident_details['recommendations']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="isd.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="update_incident" class="btn btn-primary">Update Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.incident-table tbody tr');
            
            tableRows.forEach(row => {
                const title = row.cells[0].textContent.toLowerCase();
                const status = row.cells[1].textContent.toLowerCase();
                const creator = row.cells[2].textContent.toLowerCase();
                
                if (title.includes(searchValue) || status.includes(searchValue) || creator.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Auto-show edit modal if in edit mode
        <?php if ($edit_mode): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const editModal = new bootstrap.Modal(document.getElementById('editIncidentModal'));
                editModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>