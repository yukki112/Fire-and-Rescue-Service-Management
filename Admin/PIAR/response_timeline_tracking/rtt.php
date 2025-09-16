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
$active_submodule = 'response_timeline_tracking';

// Initialize variables
$error_message = '';
$success_message = '';
$timeline_events = [];
$event_details = null;
$edit_mode = false;
$incidents = [];

// Fetch all incidents for dropdown
try {
    $query = "SELECT id, report_title FROM piar.incident_analysis_reports WHERE status != 'archived' ORDER BY created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_event'])) {
            // Create new timeline event
            $query = "INSERT INTO piar.response_timeline 
                     (incident_id, event_type, event_description, event_time, unit_id, personnel_id, location, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['event_type'],
                $_POST['event_description'],
                $_POST['event_time'],
                $_POST['unit_id'] ?: null,
                $_POST['personnel_id'] ?: null,
                $_POST['location'] ?: null,
                $_POST['notes'] ?: null
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Timeline event created successfully!";
            
        } elseif (isset($_POST['update_event'])) {
            // Update existing event
            $event_id = $_POST['event_id'];
            $query = "UPDATE piar.response_timeline 
                     SET incident_id = ?, event_type = ?, event_description = ?, event_time = ?, 
                         unit_id = ?, personnel_id = ?, location = ?, notes = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['incident_id'],
                $_POST['event_type'],
                $_POST['event_description'],
                $_POST['event_time'],
                $_POST['unit_id'] ?: null,
                $_POST['personnel_id'] ?: null,
                $_POST['location'] ?: null,
                $_POST['notes'] ?: null,
                $event_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Timeline event updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Timeline event error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit/delete events)
if (isset($_GET['action'])) {
    try {
        if ($_GET['action'] === 'view' && isset($_GET['id'])) {
            // View event details
            $event_id = $_GET['id'];
            $query = "SELECT rt.*, iar.report_title 
                     FROM piar.response_timeline rt
                     LEFT JOIN piar.incident_analysis_reports iar ON rt.incident_id = iar.id
                     WHERE rt.id = ?";
            $event_details = $dbManager->fetch("piar", $query, [$event_id]);
            
        } elseif ($_GET['action'] === 'edit' && isset($_GET['id'])) {
            // Edit event
            $event_id = $_GET['id'];
            $query = "SELECT * FROM piar.response_timeline WHERE id = ?";
            $event_details = $dbManager->fetch("piar", $query, [$event_id]);
            $edit_mode = true;
            
        } elseif ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            // Delete event
            $event_id = $_GET['id'];
            $query = "DELETE FROM piar.response_timeline WHERE id = ?";
            $dbManager->query("piar", $query, [$event_id]);
            $success_message = "Timeline event deleted successfully!";
        }
    } catch (Exception $e) {
        error_log("Timeline action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all timeline events
try {
    $query = "SELECT rt.*, iar.report_title 
             FROM piar.response_timeline rt
             LEFT JOIN piar.incident_analysis_reports iar ON rt.incident_id = iar.id
             ORDER BY rt.event_time DESC";
    $timeline_events = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch timeline events error: " . $e->getMessage());
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
        
        .timeline-table {
            font-size: 0.9rem;
        }
        
        .timeline-table th {
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
        
        .event-type-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .event-type-dispatch { background-color: #ffc107; color: #000; }
        .event-type-response { background-color: #0d6efd; color: #fff; }
        .event-type-arrival { background-color: #198754; color: #fff; }
        .event-type-operation { background-color: #6c757d; color: #fff; }
        .event-type-completion { background-color: #6610f2; color: #fff; }
        
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
        
        /* Timeline visualization */
        .timeline-container {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 2px;
            height: 100%;
            background: #0d6efd;
            transform: translateX(-50%);
        }
        
        .timeline-event {
            position: relative;
            margin-bottom: 30px;
            width: 50%;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .timeline-event:nth-child(odd) {
            left: 0;
            padding-right: 30px;
        }
        
        .timeline-event:nth-child(even) {
            left: 50%;
            padding-left: 30px;
        }
        
        .timeline-event::before {
            content: '';
            position: absolute;
            top: 20px;
            width: 20px;
            height: 20px;
            background: #0d6efd;
            border-radius: 50%;
        }
        
        .timeline-event:nth-child(odd)::before {
            right: -10px;
        }
        
        .timeline-event:nth-child(even)::before {
            left: -10px;
        }
        
        .event-time {
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 5px;
        }
        
        .event-type {
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .event-description {
            margin-bottom: 10px;
        }
        
        .event-details {
            font-size: 0.85rem;
            color: #6c757d;
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
                    <a href="rtt.php" class="sidebar-dropdown-link active">
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
                    <h1>Response Timeline Tracking</h1>
                    <p>Track and manage incident response timelines</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">
                        <i class='bx bx-plus'></i> New Timeline Event
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
            
            <!-- Event List / Details View -->
            <?php if ($event_details): ?>
                <!-- Event Details View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Timeline Event Details</h4>
                        <div>
                            <span class="event-type-badge event-type-<?php echo strtolower($event_details['event_type']); ?>">
                                <?php echo ucfirst($event_details['event_type']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($event_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Event Time:</strong> <?php echo date('M j, Y g:i A', strtotime($event_details['event_time'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($event_details['location'] ?? 'Not specified'); ?></p>
                            <p><strong>Unit ID:</strong> <?php echo htmlspecialchars($event_details['unit_id'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="form-section-title">Event Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($event_details['event_description'])); ?></p>
                    </div>
                    
                    <?php if (!empty($event_details['notes'])): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Additional Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($event_details['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="rtt.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="rtt.php?action=edit&id=<?php echo $event_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $event_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this timeline event?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Event List View -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Response Timeline Events</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search events..." id="searchInput">
                        </div>
                    </div>
                    
                    <?php if (!empty($timeline_events)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover timeline-table">
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Event Type</th>
                                        <th>Event Time</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeline_events as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['report_title'] ?? 'Unknown Incident'); ?></td>
                                            <td>
                                                <span class="event-type-badge event-type-<?php echo strtolower($event['event_type']); ?>">
                                                    <?php echo ucfirst($event['event_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($event['event_time'])); ?></td>
                                            <td><?php echo htmlspecialchars(substr($event['event_description'], 0, 50) . (strlen($event['event_description']) > 50 ? '...' : '')); ?></td>
                                            <td>
                                                <a href="rtt.php?action=view&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <a href="rtt.php?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
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
                            <i class='bx bx-time'></i>
                            <h5>No Timeline Events</h5>
                            <p>Get started by creating your first timeline event.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createEventModal">
                                Create Timeline Event
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Timeline Event</h5>
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
                                <label for="event_type" class="form-label">Event Type</label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Select Event Type</option>
                                    <option value="dispatch">Dispatch</option>
                                    <option value="response">Response</option>
                                    <option value="arrival">Arrival</option>
                                    <option value="operation">Operation</option>
                                    <option value="completion">Completion</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_time" class="form-label">Event Time</label>
                                <input type="datetime-local" class="form-control" id="event_time" name="event_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_id" class="form-label">Unit ID</label>
                                <input type="text" class="form-control" id="unit_id" name="unit_id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="personnel_id" class="form-label">Personnel ID</label>
                                <input type="text" class="form-control" id="personnel_id" name="personnel_id">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_description" class="form-label">Event Description</label>
                            <textarea class="form-control" id="event_description" name="event_description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="create_event">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <?php if ($edit_mode && $event_details): ?>
    <div class="modal fade show" id="editEventModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Timeline Event</h5>
                    <a href="rtt.php" class="btn-close" aria-label="Close"></a>
                </div>
                <form method="POST">
                    <input type="hidden" name="event_id" value="<?php echo $event_details['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_incident_id" class="form-label">Incident</label>
                                <select class="form-select" id="edit_incident_id" name="incident_id" required>
                                    <option value="">Select Incident</option>
                                    <?php foreach ($incidents as $incident): ?>
                                        <option value="<?php echo $incident['id']; ?>" <?php echo $event_details['incident_id'] == $incident['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($incident['report_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_event_type" class="form-label">Event Type</label>
                                <select class="form-select" id="edit_event_type" name="event_type" required>
                                    <option value="">Select Event Type</option>
                                    <option value="dispatch" <?php echo $event_details['event_type'] == 'dispatch' ? 'selected' : ''; ?>>Dispatch</option>
                                    <option value="response" <?php echo $event_details['event_type'] == 'response' ? 'selected' : ''; ?>>Response</option>
                                    <option value="arrival" <?php echo $event_details['event_type'] == 'arrival' ? 'selected' : ''; ?>>Arrival</option>
                                    <option value="operation" <?php echo $event_details['event_type'] == 'operation' ? 'selected' : ''; ?>>Operation</option>
                                    <option value="completion" <?php echo $event_details['event_type'] == 'completion' ? 'selected' : ''; ?>>Completion</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_event_time" class="form-label">Event Time</label>
                                <input type="datetime-local" class="form-control" id="edit_event_time" name="event_time" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($event_details['event_time'])); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="edit_location" name="location" 
                                       value="<?php echo htmlspecialchars($event_details['location'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_unit_id" class="form-label">Unit ID</label>
                                <input type="text" class="form-control" id="edit_unit_id" name="unit_id" 
                                       value="<?php echo htmlspecialchars($event_details['unit_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_personnel_id" class="form-label">Personnel ID</label>
                                <input type="text" class="form-control" id="edit_personnel_id" name="personnel_id" 
                                       value="<?php echo htmlspecialchars($event_details['personnel_id'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_event_description" class="form-label">Event Description</label>
                            <textarea class="form-control" id="edit_event_description" name="event_description" rows="3" required><?php echo htmlspecialchars($event_details['event_description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="2"><?php echo htmlspecialchars($event_details['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="rtt.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" name="update_event">Update Event</button>
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
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.timeline-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
        
        // Auto set current datetime for new events
        document.addEventListener('DOMContentLoaded', function() {
            const eventTimeInput = document.getElementById('event_time');
            if (eventTimeInput && !eventTimeInput.value) {
                const now = new Date();
                // Format to YYYY-MM-DDTHH:MM
                const formatted = now.toISOString().slice(0, 16);
                eventTimeInput.value = formatted;
            }
        });
    </script>
</body>
</html>