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
$active_module = 'pss';
$active_submodule = 'shift_calendar_management';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all employees for assignment
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get all shift types
    $shift_types = $dbManager->fetchAll("pss", "SELECT * FROM shift_types ORDER BY name");
    
    // Get current month and year for calendar display
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    // Validate month and year
    if ($month < 1 || $month > 12) $month = (int)date('n');
    if ($year < 2020 || $year > 2030) $year = (int)date('Y');
    
    // Calculate previous and next month/year for navigation
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month < 1) {
        $prev_month = 12;
        $prev_year = $year - 1;
    }
    
    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month > 12) {
        $next_month = 1;
        $next_year = $year + 1;
    }
    
    // Get number of days in the month
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    // Get first day of the month (0=Sunday, 1=Monday, etc.)
    $first_day = date('w', mktime(0, 0, 0, $month, 1, $year));
    
    // Adjust for Monday as first day of week (convert Sunday from 0 to 6)
    if ($first_day == 0) {
        $first_day = 6;
    } else {
        $first_day = $first_day - 1;
    }
    
    // Get scheduled shifts for the month
    $start_date = "$year-$month-01";
    $end_date = "$year-$month-$days_in_month";
    
    $scheduled_shifts = $dbManager->fetchAll("pss", 
        "SELECT ss.*, e.first_name, e.last_name, e.employee_id, st.name as shift_name, 
                st.start_time, st.end_time, st.color
         FROM shift_schedules ss
         JOIN frsm.employees e ON ss.employee_id = e.id
         JOIN shift_types st ON ss.shift_type_id = st.id
         WHERE ss.date BETWEEN ? AND ?
         ORDER BY ss.date, st.start_time", 
        [$start_date, $end_date]
    );
    
    // Group shifts by date for easier display
    $shifts_by_date = [];
    foreach ($scheduled_shifts as $shift) {
        $date = $shift['date'];
        if (!isset($shifts_by_date[$date])) {
            $shifts_by_date[$date] = [];
        }
        $shifts_by_date[$date][] = $shift;
    }
    
    // Get upcoming shifts for the next 7 days
    $today = date('Y-m-d');
    $next_week = date('Y-m-d', strtotime('+7 days'));
    
    $upcoming_shifts = $dbManager->fetchAll("pss", 
        "SELECT ss.*, e.first_name, e.last_name, e.employee_id, st.name as shift_name, 
                st.start_time, st.end_time, st.color
         FROM shift_schedules ss
         JOIN frsm.employees e ON ss.employee_id = e.id
         JOIN shift_types st ON ss.shift_type_id = st.id
         WHERE ss.date BETWEEN ? AND ?
         ORDER BY ss.date, st.start_time", 
        [$today, $next_week]
    );
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $shift_types = [];
    $scheduled_shifts = [];
    $shifts_by_date = [];
    $upcoming_shifts = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_shift'])) {
        try {
            $employee_id = $_POST['employee_id'];
            $shift_type_id = $_POST['shift_type_id'];
            $date = $_POST['date'];
            $notes = $_POST['notes'] ?? '';
            
            // Check if employee already has a shift on this date
            $existing_shift = $dbManager->fetch("pss", 
                "SELECT id FROM shift_schedules WHERE employee_id = ? AND date = ?", 
                [$employee_id, $date]
            );
            
            if ($existing_shift) {
                $_SESSION['error_message'] = "This employee already has a shift scheduled on the selected date.";
            } else {
                // Insert new shift assignment
                $dbManager->query("pss", 
                    "INSERT INTO shift_schedules (employee_id, shift_type_id, date, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$employee_id, $shift_type_id, $date, $notes, $_SESSION['user_id']]
                );
                
                $_SESSION['success_message'] = "Shift assigned successfully!";
            }
            
            // Redirect to avoid form resubmission
            header("Location: scm.php?month=$month&year=$year");
            exit;
            
        } catch (Exception $e) {
            error_log("Error assigning shift: " . $e->getMessage());
            $_SESSION['error_message'] = "Error assigning shift. Please try again.";
        }
    }
    
    if (isset($_POST['update_shift'])) {
        try {
            $shift_id = $_POST['shift_id'];
            $shift_type_id = $_POST['shift_type_id'];
            $notes = $_POST['notes'] ?? '';
            
            // Update shift
            $dbManager->query("pss", 
                "UPDATE shift_schedules SET shift_type_id = ?, notes = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$shift_type_id, $notes, $shift_id]
            );
            
            $_SESSION['success_message'] = "Shift updated successfully!";
            
            // Redirect to avoid form resubmission
            header("Location: scm.php?month=$month&year=$year");
            exit;
            
        } catch (Exception $e) {
            error_log("Error updating shift: " . $e->getMessage());
            $_SESSION['error_message'] = "Error updating shift. Please try again.";
        }
    }
    
    if (isset($_POST['delete_shift'])) {
        try {
            $shift_id = $_POST['shift_id'];
            
            // Delete shift
            $dbManager->query("pss", 
                "DELETE FROM shift_schedules WHERE id = ?",
                [$shift_id]
            );
            
            $_SESSION['success_message'] = "Shift deleted successfully!";
            
            // Redirect to avoid form resubmission
            header("Location: scm.php?month=$month&year=$year");
            exit;
            
        } catch (Exception $e) {
            error_log("Error deleting shift: " . $e->getMessage());
            $_SESSION['error_message'] = "Error deleting shift. Please try again.";
        }
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
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .calendar-day {
            min-height: 120px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px;
            background-color: white;
            position: relative;
        }
        .calendar-day.other-month {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .shift-item {
            font-size: 0.75rem;
            padding: 3px 6px;
            margin-bottom: 4px;
            border-radius: 3px;
            color: white;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .shift-item:hover {
            opacity: 0.9;
        }
        .view-more {
            font-size: 0.7rem;
            color: #6c757d;
            cursor: pointer;
        }
        .shift-details-modal .shift-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
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
        .stat-card.primary {
            background-color: #e8f4ff;
            border-left: 4px solid #0d6efd;
        }
        .stat-card.success {
            background-color: #e8f5e9;
            border-left: 4px solid #198754;
        }
        .stat-card.warning {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        .stat-card.danger {
            background-color: #ffebee;
            border-left: 4px solid #dc3545;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        .filters-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .employee-shift {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .employee-shift-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 8px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
            margin-bottom: 5px;
        }
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 5px;
        }
        .upcoming-shifts-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .upcoming-shift-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .upcoming-shift-item:hover {
            background-color: #f8f9fa;
        }
        .upcoming-shift-item:last-child {
            border-bottom: none;
        }
        .upcoming-shift-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 15px;
        }
        .upcoming-shift-details {
            flex: 1;
        }
        .upcoming-shift-date {
            font-weight: 600;
            color: #495057;
        }
        .upcoming-shift-time {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .upcoming-shift-employee {
            font-weight: 500;
        }
        .upcoming-shift-actions {
            margin-left: 15px;
        }
        .upcoming-shifts-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .upcoming-shifts-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
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
                    
                </div>
                

                <!-- Personnel Shift Scheduling -->
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#pssMenu" role="button">
                    <i class='bx bx-calendar-event'></i>
                    <span class="text">Shift Scheduling</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="pssMenu">
                    <a href="scm.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-time'></i>
                        <span>Shift Calendar Management</span>
                    </a>
                    <a href="../personel_roster/pr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Personnel Roster</span>
                    </a>
                    <a href="../shift_assignment/sa.php" class="sidebar-dropdown-link">
                          <i class='bx bx-task'></i>
                        <span>Shift Assignment</span>
                    </a>
                      <a href="../leave_and_absence_management/laam.php" class="sidebar-dropdown-link">
                          <i class='bx bx-user-x'></i>
                        <span>Leave and Absence Management</span>
                    </a>
                      <a href="../notifications_and_alert/naa.php" class="sidebar-dropdown-link">
                           <i class='bx bx-bell'></i>
                        <span>Notifications and Alerts</span>
                    </a>
                     <a href="../reporting_and_logs/ral.php" class="sidebar-dropdown-link">
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
                    <a href="../../TCR/personnel_training_profile/ptr.php" class="sidebar-dropdown-link">
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
                    <h1>Shift Calendar Management</h1>
                    <p>Manage and view personnel shift schedules for Quezon City Fire and Rescue.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignShiftModal">
                            <i class='bx bx-plus'></i> Assign Shift
                        </button>
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
            
            <!-- Shift Calendar Content -->
            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    // Calculate shift statistics
                    $total_shifts = count($scheduled_shifts);
                    $confirmed_shifts = 0;
                    $scheduled_shifts_count = 0;
                    
                    foreach ($scheduled_shifts as $shift) {
                        if ($shift['status'] === 'confirmed') {
                            $confirmed_shifts++;
                        } else if ($shift['status'] === 'scheduled') {
                            $scheduled_shifts_count++;
                        }
                    }
                    ?>
                    
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card primary">
                                <div class="stat-number"><?php echo $total_shifts; ?></div>
                                <div class="stat-label">Total Shifts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card success">
                                <div class="stat-number"><?php echo $confirmed_shifts; ?></div>
                                <div class="stat-label">Confirmed Shifts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card warning">
                                <div class="stat-number"><?php echo $scheduled_shifts_count; ?></div>
                                <div class="stat-label">Scheduled Shifts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card danger">
                                <div class="stat-number"><?php echo count($employees); ?></div>
                                <div class 'stat-label'>Total Personnel</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Shifts Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="upcoming-shifts-container animate-fade-in">
                            <div class="upcoming-shifts-header">
                                <h3 class="upcoming-shifts-title">Upcoming Shifts (Next 7 Days)</h3>
                                <span class="badge bg-primary"><?php echo count($upcoming_shifts); ?> shifts</span>
                            </div>
                            
                            <?php if (count($upcoming_shifts) > 0): ?>
                                <div class="upcoming-shifts-list">
                                    <?php foreach ($upcoming_shifts as $shift): ?>
                                        <div class="upcoming-shift-item">
                                            <div class="upcoming-shift-color" style="background-color: <?php echo $shift['color']; ?>"></div>
                                            <div class="upcoming-shift-details">
                                                <div class="upcoming-shift-date">
                                                    <?php echo date('D, M j, Y', strtotime($shift['date'])); ?>
                                                </div>
                                                <div class="upcoming-shift-time">
                                                    <?php echo date('g:i A', strtotime($shift['start_time'])) . ' - ' . date('g:i A', strtotime($shift['end_time'])); ?>
                                                </div>
                                            </div>
                                            <div class="upcoming-shift-employee">
                                                <?php echo $shift['first_name'] . ' ' . $shift['last_name']; ?>
                                            </div>
                                            <div class="upcoming-shift-actions">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#shiftDetailsModal"
                                                        data-shift-id="<?php echo $shift['id']; ?>"
                                                        data-shift-type-id="<?php echo $shift['shift_type_id']; ?>"
                                                        data-employee-name="<?php echo htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']); ?>"
                                                        data-employee-id="<?php echo $shift['employee_id']; ?>"
                                                        data-shift-name="<?php echo htmlspecialchars($shift['shift_name']); ?>"
                                                        data-start-time="<?php echo date('g:i A', strtotime($shift['start_time'])); ?>"
                                                        data-end-time="<?php echo date('g:i A', strtotime($shift['end_time'])); ?>"
                                                        data-date="<?php echo date('M j, Y', strtotime($shift['date'])); ?>"
                                                        data-notes="<?php echo htmlspecialchars($shift['notes'] ?? ''); ?>"
                                                        data-status="<?php echo $shift['status']; ?>">
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class='bx bx-calendar-x' style="font-size: 3rem; color: #dee2e6;"></i>
                                    <p class="text-muted mt-2">No upcoming shifts in the next 7 days</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Calendar Navigation -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="calendar-container animate-fade-in">
                            <div class="calendar-header">
                                <div class="calendar-nav">
                                    <a href="scm.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-secondary">
                                        <i class='bx bx-chevron-left'></i> Prev
                                    </a>
                                    <h2 class="calendar-title"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
                                    <a href="scm.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-secondary">
                                        Next <i class='bx bx-chevron-right'></i>
                                    </a>
                                </div>
                                
                                <div class="legend d-flex flex-wrap">
                                    <?php foreach ($shift_types as $type): ?>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: <?php echo $type['color']; ?>"></div>
                                        <span><?php echo $type['name']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="calendar-grid">
                                <!-- Day headers -->
                                <div class="calendar-day-header">Monday</div>
                                <div class="calendar-day-header">Tuesday</div>
                                <div class="calendar-day-header">Wednesday</div>
                                <div class="calendar-day-header">Thursday</div>
                                <div class="calendar-day-header">Friday</div>
                                <div class="calendar-day-header">Saturday</div>
                                <div class="calendar-day-header">Sunday</div>
                                
                                <!-- Empty days for the first week -->
                                <?php for ($i = 0; $i < $first_day; $i++): ?>
                                    <div class="calendar-day other-month"></div>
                                <?php endfor; ?>
                                
                                <!-- Days of the month -->
                                <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                                    <?php
                                    $current_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    $day_shifts = $shifts_by_date[$current_date] ?? [];
                                    $display_date = $current_date;
                                    ?>
                                    
                                    <div class="calendar-day">
                                        <div class="day-number"><?php echo $day; ?></div>
                                        
                                        <?php if (count($day_shifts) > 0): ?>
                                            <?php 
                                            // Display up to 3 shifts, show "view more" if there are more
                                            $display_shifts = array_slice($day_shifts, 0, 3);
                                            $extra_shifts = count($day_shifts) - 3;
                                            ?>
                                            
                                            <?php foreach ($display_shifts as $shift): ?>
                                                <div class="shift-item" 
                                                     style="background-color: <?php echo $shift['color']; ?>"
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#shiftDetailsModal"
                                                     data-shift-id="<?php echo $shift['id']; ?>"
                                                     data-shift-type-id="<?php echo $shift['shift_type_id']; ?>"
                                                     data-employee-name="<?php echo htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']); ?>"
                                                     data-employee-id="<?php echo $shift['employee_id']; ?>"
                                                     data-shift-name="<?php echo htmlspecialchars($shift['shift_name']); ?>"
                                                     data-start-time="<?php echo date('g:i A', strtotime($shift['start_time'])); ?>"
                                                     data-end-time="<?php echo date('g:i A', strtotime($shift['end_time'])); ?>"
                                                     data-date="<?php echo date('M j, Y', strtotime($shift['date'])); ?>"
                                                     data-notes="<?php echo htmlspecialchars($shift['notes'] ?? ''); ?>"
                                                     data-status="<?php echo $shift['status']; ?>">
                                                    <?php echo $shift['first_name'] . ' ' . substr($shift['last_name'], 0, 1); ?>: 
                                                    <?php echo date('g:i', strtotime($shift['start_time'])) . '-' . date('g:i', strtotime($shift['end_time'])); ?>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($extra_shifts > 0): ?>
                                                <div class="view-more" data-bs-toggle="modal" data-bs-target="#dayShiftsModal" data-date="<?php echo $current_date; ?>">
                                                    +<?php echo $extra_shifts; ?> more shifts
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-muted" style="font-size: 0.8rem;">No shifts</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Start new row after Sunday -->
                                    <?php if (($first_day + $day) % 7 == 0 && $day != $days_in_month): ?>
                                        </div><div class="calendar-grid">
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <!-- Empty days for the last week -->
                                <?php
                                $last_day = date('w', mktime(0, 0, 0, $month, $days_in_month, $year));
                                // Adjust for Monday as first day of week
                                if ($last_day == 0) {
                                    $last_day = 6;
                                } else {
                                    $last_day = $last_day - 1;
                                }
                                $remaining_days = 6 - $last_day;
                                ?>
                                
                                <?php for ($i = 0; $i < $remaining_days; $i++): ?>
                                    <div class="calendar-day other-month"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Shift Modal -->
    <div class="modal fade" id="assignShiftModal" tabindex="-1" aria-labelledby="assignShiftModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignShiftModalLabel">Assign New Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo $employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shift_type_id" class="form-label">Shift Type</label>
                            <select class="form-select" id="shift_type_id" name="shift_type_id" required>
                                <option value="">Select Shift Type</option>
                                <?php foreach ($shift_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" data-color="<?php echo $type['color']; ?>">
                                    <?php echo $type['name'] . ' (' . date('g:i A', strtotime($type['start_time'])) . ' - ' . date('g:i A', strtotime($type['end_time'])) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_shift" class="btn btn-primary">Assign Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Shift Details Modal -->
    <div class="modal fade" id="shiftDetailsModal" tabindex="-1" aria-labelledby="shiftDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shiftDetailsModalLabel">Shift Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <span class="shift-color" id="detailShiftColor"></span>
                        <span class="fw-bold" id="detailShiftName"></span>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Employee</label>
                            <p class="fw-semibold" id="detailEmployee"></p>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Date</label>
                            <p class="fw-semibold" id="detailDate"></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Start Time</label>
                            <p class="fw-semibold" id="detailStartTime"></p>
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Time</label>
                            <p class="fw-semibold" id="detailEndTime"></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <p><span class="badge" id="detailStatus"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <p id="detailNotes" class="text-muted"></p>
                    </div>
                    
                    <form method="POST" action="" id="shiftUpdateForm">
                        <input type="hidden" name="shift_id" id="editShiftId">
                        
                        <div class="mb-3">
                            <label for="editShiftTypeId" class="form-label">Change Shift Type</label>
                            <select class="form-select" id="editShiftTypeId" name="shift_type_id">
                                <?php foreach ($shift_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo $type['name'] . ' (' . date('g:i A', strtotime($type['start_time'])) . ' - ' . date('g:i A', strtotime($type['end_time'])) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editNotes" class="form-label">Update Notes</label>
                            <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                    
                    <form method="POST" action="" id="shiftDeleteForm">
                        <input type="hidden" name="shift_id" id="deleteShiftId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="shiftDeleteForm" name="delete_shift" class="btn btn-danger me-auto" onclick="return confirm('Are you sure you want to delete this shift?')">Delete Shift</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="shiftUpdateForm" name="update_shift" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Day Shifts Modal -->
    <div class="modal fade" id="dayShiftsModal" tabindex="-1" aria-labelledby="dayShiftsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayShiftsModalLabel">Shifts for <span id="modalDate"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Shift</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="dayShiftsTableBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // Shift Details Modal
        const shiftDetailsModal = document.getElementById('shiftDetailsModal')
        if (shiftDetailsModal) {
            shiftDetailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget
                
                // Extract info from data-bs-* attributes
                const shiftId = button.getAttribute('data-shift-id')
                const shiftTypeId = button.getAttribute('data-shift-type-id')
                const employeeName = button.getAttribute('data-employee-name')
                const employeeId = button.getAttribute('data-employee-id')
                const shiftName = button.getAttribute('data-shift-name')
                const startTime = button.getAttribute('data-start-time')
                const endTime = button.getAttribute('data-end-time')
                const date = button.getAttribute('data-date')
                const notes = button.getAttribute('data-notes')
                const status = button.getAttribute('data-status')
                
                // Get the shift color from the button style
                const shiftColor = button.style.backgroundColor
                
                // Update the modal's content
                const modalTitle = shiftDetailsModal.querySelector('.modal-title')
                const detailShiftColor = shiftDetailsModal.querySelector('#detailShiftColor')
                const detailShiftName = shiftDetailsModal.querySelector('#detailShiftName')
                const detailEmployee = shiftDetailsModal.querySelector('#detailEmployee')
                const detailDate = shiftDetailsModal.querySelector('#detailDate')
                const detailStartTime = shiftDetailsModal.querySelector('#detailStartTime')
                const detailEndTime = shiftDetailsModal.querySelector('#detailEndTime')
                const detailStatus = shiftDetailsModal.querySelector('#detailStatus')
                const detailNotes = shiftDetailsModal.querySelector('#detailNotes')
                const editShiftId = shiftDetailsModal.querySelector('#editShiftId')
                const editShiftTypeId = shiftDetailsModal.querySelector('#editShiftTypeId')
                const editNotes = shiftDetailsModal.querySelector('#editNotes')
                const deleteShiftId = shiftDetailsModal.querySelector('#deleteShiftId')
                
                detailShiftColor.style.backgroundColor = shiftColor
                detailShiftName.textContent = shiftName
                detailEmployee.textContent = `${employeeName} (${employeeId})`
                detailDate.textContent = date
                detailStartTime.textContent = startTime
                detailEndTime.textContent = endTime
                detailStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1)
                detailStatus.className = 'badge ' + (status === 'confirmed' ? 'bg-success' : 'bg-warning')
                detailNotes.textContent = notes || 'No notes'
                
                editShiftId.value = shiftId
                deleteShiftId.value = shiftId
                editShiftTypeId.value = shiftTypeId
                editNotes.value = notes || ''
            })
        }
        
        // Day Shifts Modal
        const dayShiftsModal = document.getElementById('dayShiftsModal')
        if (dayShiftsModal) {
            dayShiftsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget
                const date = button.getAttribute('data-date')
                
                // Format the date for display
                const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })
                
                // Update modal title
                dayShiftsModal.querySelector('#modalDate').textContent = formattedDate
                
                // Fetch shifts for this date via AJAX
                fetch(`ajax/get_day_shifts.php?date=${date}`)
                    .then(response => response.json())
                    .then(shifts => {
                        const tableBody = dayShiftsModal.querySelector('#dayShiftsTableBody')
                        tableBody.innerHTML = ''
                        
                        if (shifts.length === 0) {
                            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No shifts scheduled for this day</td></tr>'
                            return
                        }
                        
                        shifts.forEach(shift => {
                            const row = document.createElement('tr')
                            
                            // Format the time
                            const startTime = new Date(`2000-01-01T${shift.start_time}`).toLocaleTimeString('en-US', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: true 
                            })
                            
                            const endTime = new Date(`2000-01-01T${shift.end_time}`).toLocaleTimeString('en-US', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: true 
                            })
                            
                            // Status badge
                            let statusClass = 'bg-warning'
                            let statusText = 'Scheduled'
                            if (shift.status === 'confirmed') {
                                statusClass = 'bg-success'
                                statusText = 'Confirmed'
                            }
                            
                            row.innerHTML = `
                                <td>${shift.first_name} ${shift.last_name} (${shift.employee_id})</td>
                                <td><span class="badge" style="background-color: ${shift.color}">${shift.shift_name}</span></td>
                                <td>${startTime} - ${endTime}</td>
                                <td><span class="badge ${statusClass}">${statusText}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#shiftDetailsModal"
                                            data-bs-dismiss="modal"
                                            data-shift-id="${shift.id}"
                                            data-shift-type-id="${shift.shift_type_id}"
                                            data-employee-name="${shift.first_name} ${shift.last_name}"
                                            data-employee-id="${shift.employee_id}"
                                            data-shift-name="${shift.shift_name}"
                                            data-start-time="${startTime}"
                                            data-end-time="${endTime}"
                                            data-date="${new Date(shift.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}"
                                            data-notes="${shift.notes || ''}"
                                            data-status="${shift.status}">
                                        View Details
                                    </button>
                                </td>
                            `
                            
                            tableBody.appendChild(row)
                        })
                    })
                    .catch(error => {
                        console.error('Error fetching day shifts:', error)
                        dayShiftsModal.querySelector('#dayShiftsTableBody').innerHTML = 
                            '<tr><td colspan="5" class="text-center text-danger">Error loading shifts</td></tr>'
                    })
            })
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast')
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in')
                toast.classList.add('animate-slide-out')
                
                setTimeout(() => {
                    toast.remove()
                }, 500)
            })
        }, 5000)
    </script>
</body>
</html>