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
$active_module = 'tcr';
$active_submodule = 'training_calendar_and_scheduling';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all training courses for dropdown
    $courses = $dbManager->fetchAll("tcr", "SELECT * FROM training_courses WHERE status = 'active' ORDER BY course_name");
    
    // Get all training sessions with course details
    $sessions = $dbManager->fetchAll("tcr", 
        "SELECT ts.*, tc.course_name, tc.course_code 
         FROM training_sessions ts 
         JOIN training_courses tc ON ts.course_id = tc.id 
         ORDER BY ts.start_date, ts.start_time"
    );
    
    // Get enrolled participants count for each session
    $enrollmentCounts = [];
    foreach ($sessions as $session) {
        $count = $dbManager->fetch("tcr", 
            "SELECT COUNT(*) as count FROM training_enrollments WHERE session_id = ?", 
            [$session['id']]
        );
        $enrollmentCounts[$session['id']] = $count['count'];
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_session'])) {
            $course_id = $_POST['course_id'];
            $session_code = $_POST['session_code'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $location = $_POST['location'];
            $instructor = $_POST['instructor'];
            $max_participants = $_POST['max_participants'];
            $status = $_POST['status'];
            
            try {
                // Check if session code already exists
                $existing = $dbManager->fetch("tcr", 
                    "SELECT * FROM training_sessions WHERE session_code = ?", 
                    [$session_code]
                );
                
                if ($existing) {
                    $error_message = "Session code already exists. Please use a unique session code.";
                } else {
                    // Add new session
                    $dbManager->query("tcr", 
                        "INSERT INTO training_sessions (course_id, session_code, title, description, start_date, end_date, start_time, end_time, location, instructor, max_participants, status, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$course_id, $session_code, $title, $description, $start_date, $end_date, $start_time, $end_time, $location, $instructor, $max_participants, $status, $_SESSION['user_id']]
                    );
                    
                    $success_message = "Training session successfully added.";
                }
            } catch (Exception $e) {
                $error_message = "Error adding session: " . $e->getMessage();
            }
        }
        
        if (isset($_POST['edit_session'])) {
            $session_id = $_POST['session_id'];
            $course_id = $_POST['course_id'];
            $session_code = $_POST['session_code'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $location = $_POST['location'];
            $instructor = $_POST['instructor'];
            $max_participants = $_POST['max_participants'];
            $status = $_POST['status'];
            
            try {
                // Check if session code already exists for another session
                $existing = $dbManager->fetch("tcr", 
                    "SELECT * FROM training_sessions WHERE session_code = ? AND id != ?", 
                    [$session_code, $session_id]
                );
                
                if ($existing) {
                    $error_message = "Session code already exists. Please use a unique session code.";
                } else {
                    // Update session
                    $dbManager->query("tcr", 
                        "UPDATE training_sessions SET course_id = ?, session_code = ?, title = ?, description = ?, start_date = ?, end_date = ?, 
                         start_time = ?, end_time = ?, location = ?, instructor = ?, max_participants = ?, status = ? WHERE id = ?",
                        [$course_id, $session_code, $title, $description, $start_date, $end_date, $start_time, $end_time, $location, $instructor, $max_participants, $status, $session_id]
                    );
                    
                    $success_message = "Training session successfully updated.";
                }
            } catch (Exception $e) {
                $error_message = "Error updating session: " . $e->getMessage();
            }
        }
        
        if (isset($_POST['delete_session'])) {
            $session_id = $_POST['session_id'];
            
            try {
                // Check if session has enrollments
                $enrollments = $dbManager->fetch("tcr", 
                    "SELECT COUNT(*) as count FROM training_enrollments WHERE session_id = ?", 
                    [$session_id]
                );
                
                if ($enrollments['count'] > 0) {
                    $error_message = "Cannot delete session. There are personnel enrolled in this session.";
                } else {
                    // Delete session
                    $dbManager->query("tcr", 
                        "DELETE FROM training_sessions WHERE id = ?",
                        [$session_id]
                    );
                    
                    $success_message = "Training session successfully deleted.";
                }
            } catch (Exception $e) {
                $error_message = "Error deleting session: " . $e->getMessage();
            }
        }
        
        // Refresh data after changes
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $courses = [];
    $sessions = [];
    $enrollmentCounts = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? $success_message ?? '';
$error_message = $_SESSION['error_message'] ?? $error_message ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get current date for calendar view
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate previous and next months
$prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
$prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
$nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
$nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;

// Get number of days in the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);

// Get the first day of the month (0=Sunday, 1=Monday, etc.)
$firstDay = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Group sessions by date for calendar view
$sessionsByDate = [];
foreach ($sessions as $session) {
    $startDate = new DateTime($session['start_date']);
    $endDate = new DateTime($session['end_date']);
    
    // Add all days in the session range
    $period = new DatePeriod(
        $startDate,
        new DateInterval('P1D'),
        $endDate->modify('+1 day')
    );
    
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        if (!isset($sessionsByDate[$dateStr])) {
            $sessionsByDate[$dateStr] = [];
        }
        $sessionsByDate[$dateStr][] = $session;
    }
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
        :root {
            --calendar-cell-height: 80px;
            --calendar-cell-height-mobile: 60px;
        }
        
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .calendar-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 5px;
            min-width: 700px;
        }
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            padding: 10px 5px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .calendar-day {
            min-height: var(--calendar-cell-height);
            border: 1px solid #dee2e6;
            padding: 8px;
            border-radius: 5px;
            background-color: white;
            position: relative;
            overflow: hidden;
        }
        .calendar-day.other-month {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .calendar-day.has-sessions {
            background-color: #e8f4fd;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .session-badge {
            display: block;
            font-size: 0.7rem;
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 3px;
            background-color: #0d6efd;
            color: white;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .session-badge:hover {
            opacity: 0.8;
        }
        .session-modal .badge {
            margin-right: 5px;
        }
        .session-status-scheduled {
            background-color: #0dcaf0;
        }
        .session-status-ongoing {
            background-color: #198754;
        }
        .session-status-completed {
            background-color: #6c757d;
        }
        .session-status-cancelled {
            background-color: #dc3545;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .sessions-table {
            min-width: 1000px;
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
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
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .calendar-view-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .calendar-view-options .btn {
            padding: 5px 15px;
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
            .calendar-grid {
                min-width: 600px;
            }
            .calendar-day {
                min-height: var(--calendar-cell-height-mobile);
                padding: 4px;
            }
            .day-number {
                font-size: 0.8rem;
            }
            .session-badge {
                font-size: 0.6rem;
                padding: 1px 3px;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                width: 100%;
            }
            .header-actions .btn-group {
                width: 100%;
                flex-direction: column;
            }
            .header-actions .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            .modal-dialog {
                margin: 10px;
            }
            .modal-content {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-container {
                padding: 10px;
            }
            .calendar-header h5 {
                font-size: 1.1rem;
            }
            .calendar-nav .btn {
                padding: 4px 8px;
                font-size: 0.8rem;
            }
            .table-responsive {
                border-radius: 5px;
            }
            .empty-state {
                padding: 20px 10px;
            }
            .empty-state i {
                font-size: 2rem;
            }
            .empty-state h5 {
                font-size: 1.1rem;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
        }
        
        /* Improved accessibility */
        .session-badge:focus,
        .btn:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .calendar-day {
                border: 2px solid #000;
            }
            .calendar-day.has-sessions {
                background-color: #b3d9ff;
            }
            .session-badge {
                border: 1px solid #000;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .animate-fade-in,
            .animate-slide-in {
                animation: none;
            }
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#tcrMenu" role="button">
                    <i class='bx bx-certification'></i>
                    <span class="text">Training and Certification <br>Records</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="tcrMenu">
                    <a href="../personnel_training_profile/ptr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-reader'></i>
                        <span>Personnel Training Profiles</span>
                    </a>
                    <a href="../training_course_management/tcm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
                    </a>
                    <a href="tcas.php" class="sidebar-dropdown-link active">
                       <i class='bx bx-calendar'></i>
        <span>Training Calendar and Scheduling</span>
                    </a>
                    <a href="../certification_tracking/ct.php" class="sidebar-dropdown-link">
                       <i class='bx bx-badge-check'></i>
        <span>Certification Tracking</span>
                    </a>
                      <a href="../training_compliance_monitoring/tcm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-shield'></i>
        <span>Training Compliance Monitoring</span>
                    </a>
                     <a href="../evaluation_and_assessment_recoreds/eaar.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
                    </a>
                    <a href="../reporting_and_auditlogs/ral.php" class="sidebar-dropdown-link">
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
                    <h1>Training Calendar and Scheduling</h1>
                    <p>View, create, and manage training sessions for fire and rescue personnel.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                            <i class='bx bx-plus'></i> Schedule New Session
                        </button>
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
            
            <!-- Training Calendar and Scheduling Content -->
            <div class="dashboard-content">
                <!-- Calendar View -->
                <div class="calendar-container animate-fade-in">
                    <div class="calendar-header">
                        <h5 class="mb-0">Training Calendar - <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?></h5>
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class='bx bx-chevron-left'></i>
                            </a>
                            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-outline-secondary btn-sm">
                                Today
                            </a>
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class='bx bx-chevron-right'></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                        
                        <!-- Empty days for the first week -->
                        <?php for ($i = 0; $i < $firstDay; $i++): ?>
                            <div class="calendar-day other-month"></div>
                        <?php endfor; ?>
                        
                        <!-- Days of the month -->
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                            <?php
                            $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                            $hasSessions = isset($sessionsByDate[$currentDate]);
                            $isToday = ($day == date('j') && $currentMonth == date('n') && $currentYear == date('Y'));
                            ?>
                            <div class="calendar-day <?php echo $hasSessions ? 'has-sessions' : ''; ?>">
                                <div class="day-number <?php echo $isToday ? 'text-primary fw-bold' : ''; ?>">
                                    <?php echo $day; ?>
                                </div>
                                <?php if ($hasSessions): ?>
                                    <?php foreach ($sessionsByDate[$currentDate] as $session): ?>
                                        <span class="session-badge" data-bs-toggle="modal" data-bs-target="#sessionDetailModal" 
                                            data-id="<?php echo $session['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($session['session_code']); ?>"
                                            data-title="<?php echo htmlspecialchars($session['title']); ?>"
                                            data-course="<?php echo htmlspecialchars($session['course_name']); ?>"
                                            data-start="<?php echo date('M j, Y', strtotime($session['start_date'])); ?>"
                                            data-end="<?php echo date('M j, Y', strtotime($session['end_date'])); ?>"
                                            data-time="<?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>"
                                            data-location="<?php echo htmlspecialchars($session['location']); ?>"
                                            data-instructor="<?php echo htmlspecialchars($session['instructor']); ?>"
                                            data-max="<?php echo $session['max_participants']; ?>"
                                            data-enrolled="<?php echo $enrollmentCounts[$session['id']] ?? 0; ?>"
                                            data-status="<?php echo $session['status']; ?>"
                                            data-description="<?php echo htmlspecialchars($session['description']); ?>">
                                            <?php echo htmlspecialchars($session['course_code'] . ': ' . $session['title']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                        
                        <!-- Empty days for the last week -->
                        <?php
                        $lastDay = date('w', mktime(0, 0, 0, $currentMonth, $daysInMonth, $currentYear));
                        for ($i = $lastDay + 1; $i < 7; $i++): ?>
                            <div class="calendar-day other-month"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Sessions List -->
                <div class="calendar-container animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">All Training Sessions</h5>
                    </div>
                    
                    <?php if (count($sessions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover sessions-table">
                                <thead>
                                    <tr>
                                        <th>Session Code</th>
                                        <th>Course</th>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Instructor</th>
                                        <th>Participants</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($session['session_code']); ?></td>
                                            <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($session['title']); ?></td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($session['start_date'])); ?>
                                                <?php if ($session['start_date'] != $session['end_date']): ?>
                                                    - <?php echo date('M j, Y', strtotime($session['end_date'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($session['location']); ?></td>
                                            <td><?php echo htmlspecialchars($session['instructor']); ?></td>
                                            <td>
                                                <?php echo $enrollmentCounts[$session['id']] ?? 0; ?> / <?php echo $session['max_participants']; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge 
                                                    <?php if ($session['status'] == 'scheduled') echo 'bg-info';
                                                    elseif ($session['status'] == 'ongoing') echo 'bg-success';
                                                    elseif ($session['status'] == 'completed') echo 'bg-secondary';
                                                    else echo 'bg-danger'; ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary view-session" 
                                                        data-bs-toggle="modal" data-bs-target="#sessionDetailModal"
                                                        data-id="<?php echo $session['id']; ?>"
                                                        data-code="<?php echo htmlspecialchars($session['session_code']); ?>"
                                                        data-title="<?php echo htmlspecialchars($session['title']); ?>"
                                                        data-course="<?php echo htmlspecialchars($session['course_name']); ?>"
                                                        data-start="<?php echo date('M j, Y', strtotime($session['start_date'])); ?>"
                                                        data-end="<?php echo date('M j, Y', strtotime($session['end_date'])); ?>"
                                                        data-time="<?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>"
                                                        data-location="<?php echo htmlspecialchars($session['location']); ?>"
                                                        data-instructor="<?php echo htmlspecialchars($session['instructor']); ?>"
                                                        data-max="<?php echo $session['max_participants']; ?>"
                                                        data-enrolled="<?php echo $enrollmentCounts[$session['id']] ?? 0; ?>"
                                                        data-status="<?php echo $session['status']; ?>"
                                                        data-description="<?php echo htmlspecialchars($session['description']); ?>">
                                                        <i class='bx bx-show'></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning edit-session" 
                                                        data-bs-toggle="modal" data-bs-target="#editSessionModal"
                                                        data-id="<?php echo $session['id']; ?>"
                                                        data-course-id="<?php echo $session['course_id']; ?>"
                                                        data-code="<?php echo htmlspecialchars($session['session_code']); ?>"
                                                        data-title="<?php echo htmlspecialchars($session['title']); ?>"
                                                        data-description="<?php echo htmlspecialchars($session['description']); ?>"
                                                        data-start-date="<?php echo $session['start_date']; ?>"
                                                        data-end-date="<?php echo $session['end_date']; ?>"
                                                        data-start-time="<?php echo $session['start_time']; ?>"
                                                        data-end-time="<?php echo $session['end_time']; ?>"
                                                        data-location="<?php echo htmlspecialchars($session['location']); ?>"
                                                        data-instructor="<?php echo htmlspecialchars($session['instructor']); ?>"
                                                        data-max-participants="<?php echo $session['max_participants']; ?>"
                                                        data-status="<?php echo $session['status']; ?>">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                        <button type="submit" name="delete_session" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this session?')">
                                                            <i class='bx bx-trash'></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-calendar-x'></i>
                            <h5>No Training Sessions Scheduled</h5>
                            <p>Get started by scheduling your first training session.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                                Schedule New Session
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Training Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="course_id" class="form-label">Course *</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select a course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="session_code" class="form-label">Session Code *</label>
                                <input type="text" class="form-control" id="session_code" name="session_code" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Session Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="instructor" class="form-label">Instructor *</label>
                                <input type="text" class="form-control" id="instructor" name="instructor" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="max_participants" class="form-label">Max Participants *</label>
                                <input type="number" class="form-control" id="max_participants" name="max_participants" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_session" class="btn btn-primary">Schedule Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Training Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" id="edit_session_id" name="session_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_course_id" class="form-label">Course *</label>
                                <select class="form-select" id="edit_course_id" name="course_id" required>
                                    <option value="">Select a course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_session_code" class="form-label">Session Code *</label>
                                <input type="text" class="form-control" id="edit_session_code" name="session_code" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_title" class="form-label">Session Title *</label>
                                <input type="text" class="form-control" id="edit_title" name="title" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_time" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_time" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="edit_location" name="location" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_instructor" class="form-label">Instructor *</label>
                                <input type="text" class="form-control" id="edit_instructor" name="instructor" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_participants" class="form-label">Max Participants *</label>
                                <input type="number" class="form-control" id="edit_max_participants" name="max_participants" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status *</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_session" class="btn btn-primary">Update Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Session Detail Modal -->
    <div class="modal fade" id="sessionDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sessionDetailTitle">Session Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body session-modal">
                    <div class="mb-3">
                        <strong>Session Code:</strong> <span id="sessionDetailCode"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Course:</strong> <span id="sessionDetailCourse"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Title:</strong> <span id="sessionDetailTitle"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> <span id="sessionDetailDate"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Time:</strong> <span id="sessionDetailTime"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Location:</strong> <span id="sessionDetailLocation"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Instructor:</strong> <span id="sessionDetailInstructor"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Participants:</strong> <span id="sessionDetailParticipants"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> <span class="badge" id="sessionDetailStatus"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Description:</strong> <p id="sessionDetailDescription" class="mt-2"></p>
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
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Session detail modal
        const sessionDetailModal = document.getElementById('sessionDetailModal');
        if (sessionDetailModal) {
            sessionDetailModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const code = button.getAttribute('data-code');
                const title = button.getAttribute('data-title');
                const course = button.getAttribute('data-course');
                const start = button.getAttribute('data-start');
                const end = button.getAttribute('data-end');
                const time = button.getAttribute('data-time');
                const location = button.getAttribute('data-location');
                const instructor = button.getAttribute('data-instructor');
                const max = button.getAttribute('data-max');
                const enrolled = button.getAttribute('data-enrolled');
                const status = button.getAttribute('data-status');
                const description = button.getAttribute('data-description');
                
                // Set date text (handle single day vs multi-day)
                let dateText = start;
                if (start !== end) {
                    dateText = `${start} to ${end}`;
                }
                
                // Set status badge class
                let statusClass = 'bg-info';
                if (status === 'ongoing') statusClass = 'bg-success';
                else if (status === 'completed') statusClass = 'bg-secondary';
                else if (status === 'cancelled') statusClass = 'bg-danger';
                
                // Update modal content
                document.getElementById('sessionDetailTitle').textContent = title;
                document.getElementById('sessionDetailCode').textContent = code;
                document.getElementById('sessionDetailCourse').textContent = course;
                document.getElementById('sessionDetailTitle').textContent = title;
                document.getElementById('sessionDetailDate').textContent = dateText;
                document.getElementById('sessionDetailTime').textContent = time;
                document.getElementById('sessionDetailLocation').textContent = location;
                document.getElementById('sessionDetailInstructor').textContent = instructor;
                document.getElementById('sessionDetailParticipants').textContent = `${enrolled} / ${max}`;
                document.getElementById('sessionDetailStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                document.getElementById('sessionDetailStatus').className = `badge ${statusClass}`;
                document.getElementById('sessionDetailDescription').textContent = description;
            });
        }
        
        // Edit session modal
        const editSessionModal = document.getElementById('editSessionModal');
        if (editSessionModal) {
            editSessionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('edit_session_id').value = button.getAttribute('data-id');
                document.getElementById('edit_course_id').value = button.getAttribute('data-course-id');
                document.getElementById('edit_session_code').value = button.getAttribute('data-code');
                document.getElementById('edit_title').value = button.getAttribute('data-title');
                document.getElementById('edit_description').value = button.getAttribute('data-description');
                document.getElementById('edit_start_date').value = button.getAttribute('data-start-date');
                document.getElementById('edit_end_date').value = button.getAttribute('data-end-date');
                document.getElementById('edit_start_time').value = button.getAttribute('data-start-time');
                document.getElementById('edit_end_time').value = button.getAttribute('data-end-time');
                document.getElementById('edit_location').value = button.getAttribute('data-location');
                document.getElementById('edit_instructor').value = button.getAttribute('data-instructor');
                document.getElementById('edit_max_participants').value = button.getAttribute('data-max-participants');
                document.getElementById('edit_status').value = button.getAttribute('data-status');
            });
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
        
        // Form validation for dates
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const editStartDateInput = document.getElementById('edit_start_date');
            const editEndDateInput = document.getElementById('edit_end_date');
            
            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                });
            }
            
            if (editStartDateInput && editEndDateInput) {
                editStartDateInput.addEventListener('change', function() {
                    editEndDateInput.min = this.value;
                });
            }
            
            // Set today's date as default for start date fields
            const today = new Date().toISOString().split('T')[0];
            if (startDateInput && !startDateInput.value) {
                startDateInput.value = today;
            }
            if (editStartDateInput && !editStartDateInput.value) {
                editStartDateInput.value = today;
            }
        });
    </script>
</body>
</html>