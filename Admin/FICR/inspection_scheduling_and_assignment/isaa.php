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
$active_module = 'ficr';
$active_submodule = 'inspection_scheduling_and_assignment';

// Initialize variables
$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_schedule'])) {
            // Add new inspection schedule
            $establishment_id = $_POST['establishment_id'];
            $scheduled_date = $_POST['scheduled_date'];
            $scheduled_time = $_POST['scheduled_time'];
            $assigned_inspector = $_POST['assigned_inspector'];
            $checklist_id = $_POST['checklist_id'];
            $priority = $_POST['priority'];
            $notes = $_POST['notes'];
            
            $query = "INSERT INTO inspection_schedules 
                     (establishment_id, scheduled_date, scheduled_time, assigned_inspector, 
                      checklist_id, priority, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $establishment_id, $scheduled_date, $scheduled_time, $assigned_inspector,
                $checklist_id, $priority, $notes, $_SESSION['user_id']
            ];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Inspection scheduled successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'create', 'inspection_schedules', LAST_INSERT_ID(), ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], 
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_schedule'])) {
            // Update inspection schedule
            $id = $_POST['id'];
            $establishment_id = $_POST['establishment_id'];
            $scheduled_date = $_POST['scheduled_date'];
            $scheduled_time = $_POST['scheduled_time'];
            $assigned_inspector = $_POST['assigned_inspector'];
            $checklist_id = $_POST['checklist_id'];
            $priority = $_POST['priority'];
            $status = $_POST['status'];
            $notes = $_POST['notes'];
            
            $query = "UPDATE inspection_schedules SET 
                     establishment_id = ?, scheduled_date = ?, scheduled_time = ?, 
                     assigned_inspector = ?, checklist_id = ?, priority = ?, 
                     status = ?, notes = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [
                $establishment_id, $scheduled_date, $scheduled_time, $assigned_inspector,
                $checklist_id, $priority, $status, $notes, $id
            ];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Inspection schedule updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'inspection_schedules', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['delete_schedule'])) {
            // Delete inspection schedule
            $id = $_POST['id'];
            
            $query = "DELETE FROM inspection_schedules WHERE id = ?";
            $dbManager->query("ficr", $query, [$id]);
            
            $success_message = "Inspection schedule deleted successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'delete', 'inspection_schedules', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_status'])) {
            // Update schedule status only
            $id = $_POST['id'];
            $status = $_POST['status'];
            
            $query = "UPDATE inspection_schedules SET status = ?, updated_at = NOW() WHERE id = ?";
            $dbManager->query("ficr", $query, [$status, $id]);
            
            $success_message = "Schedule status updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'inspection_schedules', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "An error occurred. Please try again.";
    }
}

// Get filter parameters
$filter_status = $_GET['filter_status'] ?? '';
$filter_priority = $_GET['filter_priority'] ?? '';
$filter_inspector = $_GET['filter_inspector'] ?? '';
$search = $_GET['search'] ?? '';

// Calendar navigation
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : 9; // September is month 9
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : 2025;

// Validate month and year
if ($currentMonth < 1 || $currentMonth > 12) {
    $currentMonth = date('n');
}
if ($currentYear < 2020 || $currentYear > 2030) {
    $currentYear = date('Y');
}

// Calculate previous and next months
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

try {
    // Get current user
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Build query for inspection schedules with filters
    $query = "SELECT s.*, e.name as establishment_name, e.address, e.barangay, 
                     CONCAT(emp.first_name, ' ', emp.last_name) as inspector_name,
                     c.name as checklist_name
              FROM ficr.inspection_schedules s
              LEFT JOIN ficr.establishments e ON s.establishment_id = e.id
              LEFT JOIN frsm.employees emp ON s.assigned_inspector = emp.id
              LEFT JOIN ficr.inspection_checklists c ON s.checklist_id = c.id
              WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (e.name LIKE ? OR e.address LIKE ? OR e.owner_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND s.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_priority)) {
        $query .= " AND s.priority = ?";
        $params[] = $filter_priority;
    }
    
    if (!empty($filter_inspector) && $filter_inspector != 'all') {
        $query .= " AND s.assigned_inspector = ?";
        $params[] = $filter_inspector;
    }
    
    $query .= " ORDER BY s.scheduled_date ASC, s.scheduled_time ASC";
    
    // Get inspection schedules data
    $schedules = $dbManager->fetchAll("ficr", $query, $params);
    
    // Get establishments for dropdown
    $establishments = $dbManager->fetchAll("ficr", "SELECT id, name FROM establishments WHERE status = 'active' ORDER BY name");
    
    // Get inspectors (employees with appropriate role)
    $inspectors = $dbManager->fetchAll("frsm", "SELECT id, first_name, last_name FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get checklists
    $checklists = $dbManager->fetchAll("ficr", "SELECT id, name FROM inspection_checklists WHERE is_active = 1 ORDER BY name");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $schedules = [];
    $establishments = [];
    $inspectors = [];
    $checklists = [];
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

// Function to generate calendar
function generateCalendar($month, $year, $schedules) {
    // Create a new date object for the first day of the month
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $numberOfDays = date('t', $firstDayOfMonth);
    $firstDayOfWeek = date('w', $firstDayOfMonth); // 0 = Sunday, 1 = Monday, etc.
    
    // Adjust for Monday as the first day of the week
    $firstDayOfWeek = $firstDayOfWeek == 0 ? 6 : $firstDayOfWeek - 1;
    
    // Start the calendar HTML
    $calendar = '<div class="calendar-grid">';
    
    // Add day headers
    $dayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($dayHeaders as $header) {
        $calendar .= '<div class="calendar-day-header">' . $header . '</div>';
    }
    
    // Add empty cells for days before the first day of the month
    for ($i = 0; $i < $firstDayOfWeek; $i++) {
        $calendar .= '<div class="calendar-day empty"></div>';
    }
    
    // Add days of the month
    for ($day = 1; $day <= $numberOfDays; $day++) {
        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $daySchedules = array_filter($schedules, function($schedule) use ($currentDate) {
            return $schedule['scheduled_date'] == $currentDate;
        });
        
        $calendar .= '<div class="calendar-day" data-date="' . $currentDate . '">';
        $calendar .= '<div class="day-number">' . $day . '</div>';
        
        // Add events for this day
        foreach ($daySchedules as $schedule) {
            $calendar .= '<div class="calendar-event ' . $schedule['priority'] . '" data-bs-toggle="modal" data-bs-target="#scheduleDetailModal" 
                         data-id="' . $schedule['id'] . '"
                         data-establishment="' . htmlspecialchars($schedule['establishment_name']) . '"
                         data-date="' . $schedule['scheduled_date'] . '"
                         data-time="' . date('g:i A', strtotime($schedule['scheduled_time'])) . '"
                         data-inspector="' . htmlspecialchars($schedule['inspector_name']) . '"
                         data-checklist="' . htmlspecialchars($schedule['checklist_name']) . '"
                         data-priority="' . $schedule['priority'] . '"
                         data-status="' . $schedule['status'] . '"
                         data-notes="' . htmlspecialchars($schedule['notes']) . '">
                         ' . htmlspecialchars($schedule['establishment_name']) . '
                      </div>';
        }
        
        $calendar .= '</div>';
        
        // Check if we need to start a new row
        if (($firstDayOfWeek + $day) % 7 == 0 && $day != $numberOfDays) {
            $calendar .= '</div><div class="calendar-grid">';
        }
    }
    
    // Add empty cells for days after the last day of the month
    $lastDayOfWeek = ($firstDayOfWeek + $numberOfDays - 1) % 7;
    for ($i = $lastDayOfWeek + 1; $i < 7; $i++) {
        $calendar .= '<div class="calendar-day empty"></div>';
    }
    
    $calendar .= '</div>';
    
    return $calendar;
}

// Generate the calendar HTML
$calendarHTML = generateCalendar($currentMonth, $currentYear, $schedules);

// Get month name for display
$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
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
        .schedule-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .schedule-table {
            min-width: 1000px;
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
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
        
        /* Filter section styles */
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            cursor: pointer;
        }
        .filter-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
        .filter-toggle {
            transition: transform 0.3s ease;
        }
        .filter-toggle.collapsed {
            transform: rotate(180deg);
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }
        .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }
        .modal-footer {
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 10px 10px;
            padding: 15px 20px;
        }
        
        /* Action buttons */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges */
        .badge-scheduled {
            background-color: #17a2b8;
            color: white;
        }
        .badge-in_progress {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-completed {
            background-color: #28a745;
            color: white;
        }
        .badge-cancelled {
            background-color: #6c757d;
            color: white;
        }
        .badge-rescheduled {
            background-color: #6610f2;
            color: white;
        }
        
        /* Priority badges */
        .badge-low {
            background-color: #28a745;
            color: white;
        }
        .badge-medium {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-high {
            background-color: #dc3545;
            color: white;
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
            .filter-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
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
            .filter-actions {
                flex-direction: column;
            }
            .filter-actions .btn {
                width: 100%;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
        }
        
        /* Improved accessibility */
        .btn:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .animate-fade-in,
            .animate-slide-in {
                animation: none;
            }
            .filter-toggle {
                transition: none;
            }
        }
        
        /* Calendar view */
        .calendar-view {
            display: none;
            margin-top: 20px;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-day {
            border: 1px solid #dee2e6;
            padding: 10px;
            min-height: 100px;
            background: white;
            position: relative;
        }
        .calendar-day.empty {
            background: #f8f9fa;
        }
        .calendar-day-header {
            text-align: center;
            font-weight: bold;
            background: #f8f9fa;
            padding: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        .calendar-event {
            background: #e9ecef;
            padding: 3px 6px;
            border-radius: 3px;
            margin-bottom: 3px;
            font-size: 0.8rem;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .calendar-event.high {
            background: #f8d7da;
            border-left: 3px solid #dc3545;
        }
        .calendar-event.medium {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }
        .calendar-event.low {
            background: #d1ecf1;
            border-left: 3px solid #17a2b8;
        }
        .calendar-event:hover {
            opacity: 0.9;
        }
        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .today {
            background-color: #e7f5ff;
            border: 2px solid #0d6efd;
        }
        
        /* View toggle */
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .view-toggle-btn {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 5px;
            cursor: pointer;
        }
        .view-toggle-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#ficrMenu" role="button">
                    <i class='bx bx-clipboard'></i>
                    <span class="text">Inspection & Compliance</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="ficrMenu">
                    <a href="../establishment_registry/er.php" class="sidebar-dropdown-link">
                           <i class='bx bx-building-house'></i>
                        <span>Establishment/Property Registry</span>
                    </a>
                    <a href="../inspection_scheduling_and_assignment/isaa.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-calendar-event'></i>
                        <span>Inspection Scheduling and Assignment</span>
                    </a>
                    <a href="../inspection_checklist_management/icm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-list-check'></i>
                        <span>Inspection Checklist Management</span>
                    </a>
                    <a href="../violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link">
                           <i class='bx bx-shield-x'></i>
                        <span>Violation and Compliance Tracking</span>
                    </a>
                    <a href="../clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link">
                          <i class='bx bx-file'></i>
                        <span>Clearance and Certification Management</span>
                    </a>
                     <a href="../reporting_and_analytics/raa.php" class="sidebar-dropdown-link">
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
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Inspection Scheduling and Assignment</h1>
                    <p>Schedule and assign fire safety inspections to establishments</p>
                </div>
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <i class='bx bx-plus'></i> New Schedule
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="toggleFilters">
                            <i class='bx bx-filter-alt'></i> Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-toggle-btn active" id="tableViewBtn">Table View</button>
                <button class="view-toggle-btn" id="calendarViewBtn">Calendar View</button>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section" id="filterSection">
                <div class="filter-header" data-bs-toggle="collapse" data-bs-target="#filterContent" aria-expanded="true">
                    <h5><i class='bx bx-filter-alt'></i> Filter Schedules</h5>
                    <i class='bx bx-chevron-up filter-toggle'></i>
                </div>
                <div class="collapse show" id="filterContent">
                    <form method="GET" class="filter-content">
                        <div>
                            <label class="form-label">Status</label>
                            <select class="form-select" name="filter_status">
                                <option value="">All Statuses</option>
                                <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="rescheduled" <?= $filter_status === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="filter_priority">
                                <option value="">All Priorities</option>
                                <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Inspector</label>
                            <select class="form-select" name="filter_inspector">
                                <option value="all">All Inspectors</option>
                                <?php foreach ($inspectors as $inspector): ?>
                                <option value="<?= $inspector['id'] ?>" <?= $filter_inspector == $inspector['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inspector['first_name'] . ' ' . $inspector['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Search establishments..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="isaa.php" class="btn btn-outline-secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle'></i>
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class='bx bx-check-circle'></i>
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Table View -->
            <div id="tableView">
                <div class="table-responsive">
                    <?php if (!empty($schedules)): ?>
                    <table class="table table-hover schedule-table">
                        <thead>
                            <tr>
                                <th>Establishment</th>
                                <th>Scheduled Date</th>
                                <th>Time</th>
                                <th>Assigned Inspector</th>
                                <th>Checklist</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($schedule['establishment_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($schedule['address']) ?>, <?= htmlspecialchars($schedule['barangay']) ?></small>
                                </td>
                                <td><?= date('M j, Y', strtotime($schedule['scheduled_date'])) ?></td>
                                <td><?= date('g:i A', strtotime($schedule['scheduled_time'])) ?></td>
                                <td><?= htmlspecialchars($schedule['inspector_name']) ?></td>
                                <td><?= htmlspecialchars($schedule['checklist_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $schedule['priority'] == 'high' ? 'danger' : 
                                        ($schedule['priority'] == 'medium' ? 'warning' : 'success') 
                                    ?>">
                                        <?= ucfirst($schedule['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $schedule['status'] == 'scheduled' ? 'info' : 
                                        ($schedule['status'] == 'in_progress' ? 'warning' : 
                                        ($schedule['status'] == 'completed' ? 'success' : 
                                        ($schedule['status'] == 'cancelled' ? 'secondary' : 'primary'))) 
                                    ?>">
                                        <?= ucfirst(str_replace('_', ' ', $schedule['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#scheduleDetailModal"
                                                data-id="<?= $schedule['id'] ?>"
                                                data-establishment="<?= htmlspecialchars($schedule['establishment_name']) ?>"
                                                data-date="<?= $schedule['scheduled_date'] ?>"
                                                data-time="<?= date('g:i A', strtotime($schedule['scheduled_time'])) ?>"
                                                data-inspector="<?= htmlspecialchars($schedule['inspector_name']) ?>"
                                                data-checklist="<?= htmlspecialchars($schedule['checklist_name']) ?>"
                                                data-priority="<?= $schedule['priority'] ?>"
                                                data-status="<?= $schedule['status'] ?>"
                                                data-notes="<?= htmlspecialchars($schedule['notes']) ?>">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="modal" data-bs-target="#editScheduleModal"
                                                data-id="<?= $schedule['id'] ?>"
                                                data-establishment-id="<?= $schedule['establishment_id'] ?>"
                                                data-date="<?= $schedule['scheduled_date'] ?>"
                                                data-time="<?= $schedule['scheduled_time'] ?>"
                                                data-inspector="<?= $schedule['assigned_inspector'] ?>"
                                                data-checklist="<?= $schedule['checklist_id'] ?>"
                                                data-priority="<?= $schedule['priority'] ?>"
                                                data-status="<?= $schedule['status'] ?>"
                                                data-notes="<?= htmlspecialchars($schedule['notes']) ?>">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $schedule['id'] ?>">
                                            <button type="submit" name="delete_schedule" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-calendar-x'></i>
                        <h5>No inspection schedules found</h5>
                        <p>Create your first inspection schedule to get started</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <i class='bx bx-plus'></i> New Schedule
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Calendar View -->
            <div class="calendar-view" id="calendarView">
                <div class="calendar-header">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-outline-primary">
                        <i class='bx bx-chevron-left'></i> Previous
                    </a>
                    <h3><?= $monthName ?></h3>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-outline-primary">
                        Next <i class='bx bx-chevron-right'></i>
                    </a>
                </div>
                <?= $calendarHTML ?>
            </div>
        </div>
    </div>
    
    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Inspection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Establishment</label>
                                    <select class="form-select" name="establishment_id" required>
                                        <option value="">Select Establishment</option>
                                        <?php foreach ($establishments as $establishment): ?>
                                        <option value="<?= $establishment['id'] ?>"><?= htmlspecialchars($establishment['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assigned Inspector</label>
                                    <select class="form-select" name="assigned_inspector" required>
                                        <option value="">Select Inspector</option>
                                        <?php foreach ($inspectors as $inspector): ?>
                                        <option value="<?= $inspector['id'] ?>">
                                            <?= htmlspecialchars($inspector['first_name'] . ' ' . $inspector['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Scheduled Date</label>
                                    <input type="date" class="form-control" name="scheduled_date" required 
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Scheduled Time</label>
                                    <input type="time" class="form-control" name="scheduled_time" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Checklist</label>
                                    <select class="form-select" name="checklist_id" required>
                                        <option value="">Select Checklist</option>
                                        <?php foreach ($checklists as $checklist): ?>
                                        <option value="<?= $checklist['id'] ?>"><?= htmlspecialchars($checklist['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_schedule" class="btn btn-primary">Schedule Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Inspection Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Establishment</label>
                                    <select class="form-select" name="establishment_id" id="edit_establishment_id" required>
                                        <option value="">Select Establishment</option>
                                        <?php foreach ($establishments as $establishment): ?>
                                        <option value="<?= $establishment['id'] ?>"><?= htmlspecialchars($establishment['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assigned Inspector</label>
                                    <select class="form-select" name="assigned_inspector" id="edit_assigned_inspector" required>
                                        <option value="">Select Inspector</option>
                                        <?php foreach ($inspectors as $inspector): ?>
                                        <option value="<?= $inspector['id'] ?>">
                                            <?= htmlspecialchars($inspector['first_name'] . ' ' . $inspector['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Scheduled Date</label>
                                    <input type="date" class="form-control" name="scheduled_date" id="edit_scheduled_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Scheduled Time</label>
                                    <input type="time" class="form-control" name="scheduled_time" id="edit_scheduled_time" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Checklist</label>
                                    <select class="form-select" name="checklist_id" id="edit_checklist_id" required>
                                        <option value="">Select Checklist</option>
                                        <?php foreach ($checklists as $checklist): ?>
                                        <option value="<?= $checklist['id'] ?>"><?= htmlspecialchars($checklist['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority" id="edit_priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_status" required>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="rescheduled">Rescheduled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="3" placeholder="Add any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_schedule" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Schedule Detail Modal -->
    <div class="modal fade" id="scheduleDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inspection Schedule Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Establishment</label>
                        <p id="detail_establishment" class="form-control-static"></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Scheduled Date</label>
                                <p id="detail_date" class="form-control-static"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Scheduled Time</label>
                                <p id="detail_time" class="form-control-static"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Inspector</label>
                        <p id="detail_inspector" class="form-control-static"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Checklist</label>
                        <p id="detail_checklist" class="form-control-static"></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Priority</label>
                                <p id="detail_priority" class="form-control-static"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <p id="detail_status" class="form-control-static"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <p id="detail_notes" class="form-control-static"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Schedule Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="status_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="status_select" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="rescheduled">Rescheduled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Filter toggle
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const filterContent = document.getElementById('filterContent');
            const bsCollapse = new bootstrap.Collapse(filterContent, {
                toggle: true
            });
        });
        
        // Update filter toggle icon
        document.getElementById('filterContent').addEventListener('show.bs.collapse', function() {
            document.querySelector('.filter-toggle').classList.remove('collapsed');
        });
        
        document.getElementById('filterContent').addEventListener('hide.bs.collapse', function() {
            document.querySelector('.filter-toggle').classList.add('collapsed');
        });
        
        // Edit Schedule Modal
        const editScheduleModal = document.getElementById('editScheduleModal');
        if (editScheduleModal) {
            editScheduleModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const establishmentId = button.getAttribute('data-establishment-id');
                const date = button.getAttribute('data-date');
                const time = button.getAttribute('data-time');
                const inspector = button.getAttribute('data-inspector');
                const checklist = button.getAttribute('data-checklist');
                const priority = button.getAttribute('data-priority');
                const status = button.getAttribute('data-status');
                const notes = button.getAttribute('data-notes');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_establishment_id').value = establishmentId;
                document.getElementById('edit_scheduled_date').value = date;
                document.getElementById('edit_scheduled_time').value = time;
                document.getElementById('edit_assigned_inspector').value = inspector;
                document.getElementById('edit_checklist_id').value = checklist;
                document.getElementById('edit_priority').value = priority;
                document.getElementById('edit_status').value = status;
                document.getElementById('edit_notes').value = notes;
            });
        }
        
        // Schedule Detail Modal
        const scheduleDetailModal = document.getElementById('scheduleDetailModal');
        if (scheduleDetailModal) {
            scheduleDetailModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('detail_establishment').textContent = button.getAttribute('data-establishment');
                document.getElementById('detail_date').textContent = button.getAttribute('data-date');
                document.getElementById('detail_time').textContent = button.getAttribute('data-time');
                document.getElementById('detail_inspector').textContent = button.getAttribute('data-inspector');
                document.getElementById('detail_checklist').textContent = button.getAttribute('data-checklist');
                document.getElementById('detail_priority').textContent = button.getAttribute('data-priority');
                document.getElementById('detail_status').textContent = button.getAttribute('data-status');
                document.getElementById('detail_notes').textContent = button.getAttribute('data-notes') || 'No notes available';
            });
        }
        
        // Update Status Modal
        const updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            updateStatusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');
                
                document.getElementById('status_id').value = id;
                document.getElementById('status_select').value = status;
            });
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // View toggle functionality
        const tableViewBtn = document.getElementById('tableViewBtn');
        const calendarViewBtn = document.getElementById('calendarViewBtn');
        const tableView = document.getElementById('tableView');
        const calendarView = document.getElementById('calendarView');
        
        tableViewBtn.addEventListener('click', function() {
            tableView.style.display = 'block';
            calendarView.style.display = 'none';
            tableViewBtn.classList.add('active');
            calendarViewBtn.classList.remove('active');
        });
        
        calendarViewBtn.addEventListener('click', function() {
            tableView.style.display = 'none';
            calendarView.style.display = 'block';
            tableViewBtn.classList.remove('active');
            calendarViewBtn.classList.add('active');
        });
        
        // Highlight today in the calendar
        const today = new Date();
        const todayFormatted = today.toISOString().split('T')[0];
        document.querySelectorAll('.calendar-day').forEach(day => {
            const date = day.getAttribute('data-date');
            if (date === todayFormatted) {
                day.classList.add('today');
            }
        });
    </script>
</body>
</html>