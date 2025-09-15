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
$active_submodule = 'reporting_and_logs';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all active employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get available modules for filtering
    $modules = ['pss', 'ird', 'fsiet', 'hwrm', 'tcr', 'ficr', 'piar'];
    
    // Initialize filter variables
    $module_filter = $_GET['module'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $action_filter = $_GET['action'] ?? '';
    
    // Build where conditions for queries
    $where_conditions = [];
    $params = [];
    
    if (!empty($module_filter)) {
        $where_conditions[] = "table_name LIKE ?";
        $params[] = $module_filter . '%';
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    if (!empty($action_filter)) {
        $where_conditions[] = "action LIKE ?";
        $params[] = '%' . $action_filter . '%';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get audit logs
    $audit_logs = $dbManager->fetchAll("frsm", 
        "SELECT al.*, u.first_name, u.last_name, u.username 
         FROM audit_logs al 
         JOIN users u ON al.user_id = u.id 
         $where_clause 
         ORDER BY al.created_at DESC 
         LIMIT 100",
        $params
    );
    
    // Get attendance logs
    $attendance_logs = $dbManager->fetchAll("pss", 
        "SELECT al.*, e.first_name, e.last_name, e.employee_id 
         FROM attendance_logs al 
         JOIN frsm.employees e ON al.employee_id = e.id 
         ORDER BY al.created_at DESC 
         LIMIT 50"
    );
    
    // Get leave requests
    $leave_requests = $dbManager->fetchAll("pss", 
        "SELECT lr.*, e.first_name, e.last_name, e.employee_id, lt.name as leave_type 
         FROM leave_requests lr 
         JOIN frsm.employees e ON lr.employee_id = e.id 
         JOIN leave_types lt ON lr.leave_type_id = lt.id 
         ORDER BY lr.created_at DESC 
         LIMIT 50"
    );
    
    // Get shift schedules
    $shift_schedules = $dbManager->fetchAll("pss", 
        "SELECT ss.*, e.first_name, e.last_name, e.employee_id, st.name as shift_type 
         FROM shift_schedules ss 
         JOIN frsm.employees e ON ss.employee_id = e.id 
         JOIN shift_types st ON ss.shift_type_id = st.id 
         ORDER BY ss.created_at DESC 
         LIMIT 50"
    );
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $audit_logs = [];
    $attendance_logs = [];
    $leave_requests = [];
    $shift_schedules = [];
    $error_message = "System temporarily unavailable. Please try again later.";
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
        .logs-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .filter-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .log-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
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
        .export-btn {
            margin-left: 10px;
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#pssMenu" role="button">
                    <i class='bx bx-calendar-event'></i>
                    <span class="text">Shift Scheduling</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="pssMenu">
                    <a href="../shift_calendar_management/scm.php" class="sidebar-dropdown-link">
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
                     <a href="../reporting_and_logs/ral.php" class="sidebar-dropdown-link active">
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
                    <h1>Reporting & Logs</h1>
                    <p>View and analyze system logs, attendance records, and activity reports.</p>
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
            
            <!-- Reporting & Logs Content -->
            <div class="dashboard-content">
                <!-- Filter Form -->
                <div class="logs-container animate-fade-in">
                    <h5 class="mb-3">Filter Logs</h5>
                    <form method="GET" action="" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="module" class="form-label">Module</label>
                                    <select class="form-select" id="module" name="module">
                                        <option value="">All Modules</option>
                                        <?php foreach ($modules as $module): ?>
                                        <option value="<?php echo $module; ?>" <?php echo $module_filter === $module ? 'selected' : ''; ?>>
                                            <?php echo strtoupper($module); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="action" class="form-label">Action Type</label>
                                    <input type="text" class="form-control" id="action" name="action" value="<?php echo htmlspecialchars($action_filter); ?>" placeholder="Search action...">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-filter'></i> Apply Filters
                                </button>
                                <a href="ral.php" class="btn btn-outline-secondary">
                                    <i class='bx bx-reset'></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Logs Tabs -->
                <ul class="nav nav-tabs mb-3" id="logsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab">
                            <i class='bx bx-history'></i> Audit Logs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class='bx bx-calendar-check'></i> Attendance Logs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="leave-tab" data-bs-toggle="tab" data-bs-target="#leave" type="button" role="tab">
                            <i class='bx bx-user-x'></i> Leave Requests
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shift-tab" data-bs-toggle="tab" data-bs-target="#shift" type="button" role="tab">
                            <i class='bx bx-time'></i> Shift Schedules
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="logsTabContent">
                    <!-- Audit Logs Tab -->
                    <div class="tab-pane fade show active" id="audit" role="tabpanel">
                        <div class="logs-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">System Audit Logs</h5>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportTableToCSV('audit-logs-table', 'audit-logs.csv')">
                                        <i class='bx bx-download'></i> Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (count($audit_logs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="audit-logs-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Record ID</th>
                                                <th>Timestamp</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($audit_logs as $log): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo $log['username']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($log['table_name']); ?></span>
                                                    </td>
                                                    <td><?php echo $log['record_id'] ?? 'N/A'; ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                                    <td><?php echo $log['ip_address'] ?? 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-search-alt'></i>
                                    <h5>No audit logs found</h5>
                                    <p>There are no audit logs matching your criteria.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Attendance Logs Tab -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="logs-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Attendance Logs</h5>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportTableToCSV('attendance-logs-table', 'attendance-logs.csv')">
                                        <i class='bx bx-download'></i> Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (count($attendance_logs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendance-logs-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Hours Worked</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_logs as $log): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo $log['employee_id']; ?></small>
                                                    </td>
                                                    <td><?php echo $log['check_in'] ? date('M j, Y g:i A', strtotime($log['check_in'])) : 'N/A'; ?></td>
                                                    <td><?php echo $log['check_out'] ? date('M j, Y g:i A', strtotime($log['check_out'])) : 'N/A'; ?></td>
                                                    <td><?php echo $log['hours_worked'] ?? 'N/A'; ?></td>
                                                    <td>
                                                        <span class="log-badge bg-<?php 
                                                            switch($log['status']) {
                                                                case 'present': echo 'success'; break;
                                                                case 'absent': echo 'danger'; break;
                                                                case 'late': echo 'warning'; break;
                                                                case 'early_departure': echo 'info'; break;
                                                                case 'on_leave': echo 'secondary'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $log['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($log['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-search-alt'></i>
                                    <h5>No attendance logs found</h5>
                                    <p>There are no attendance records in the system.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Leave Requests Tab -->
                    <div class="tab-pane fade" id="leave" role="tabpanel">
                        <div class="logs-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Leave Requests</h5>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportTableToCSV('leave-requests-table', 'leave-requests.csv')">
                                        <i class='bx bx-download'></i> Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (count($leave_requests) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="leave-requests-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Leave Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Requested On</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo $request['employee_id']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                                    <td>
                                                        <span class="log-badge bg-<?php 
                                                            switch($request['status']) {
                                                                case 'approved': echo 'success'; break;
                                                                case 'pending': echo 'warning'; break;
                                                                case 'rejected': echo 'danger'; break;
                                                                case 'cancelled': echo 'secondary'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-search-alt'></i>
                                    <h5>No leave requests found</h5>
                                    <p>There are no leave requests in the system.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Shift Schedules Tab -->
                    <div class="tab-pane fade" id="shift" role="tabpanel">
                        <div class="logs-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Shift Schedules</h5>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportTableToCSV('shift-schedules-table', 'shift-schedules.csv')">
                                        <i class='bx bx-download'></i> Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (count($shift_schedules) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="shift-schedules-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Shift Type</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Scheduled On</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($shift_schedules as $schedule): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo $schedule['employee_id']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($schedule['shift_type']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($schedule['date'])); ?></td>
                                                    <td>
                                                        <span class="log-badge bg-<?php 
                                                            switch($schedule['status']) {
                                                                case 'scheduled': echo 'info'; break;
                                                                case 'confirmed': echo 'success'; break;
                                                                case 'cancelled': echo 'danger'; break;
                                                                case 'completed': echo 'secondary'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($schedule['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($schedule['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-search-alt'></i>
                                    <h5>No shift schedules found</h5>
                                    <p>There are no shift schedules in the system.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 1000);
            });
        }, 5000);
        
        // Function to export table to CSV
        function exportTableToCSV(tableId, filename) {
            var csv = [];
            var rows = document.getElementById(tableId).querySelectorAll('tr');
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (var j = 0; j < cols.length; j++) {
                    // Remove any HTML tags and get clean text
                    let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));        
            }
            
            // Download CSV file
            var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
            var downloadLink = document.createElement('a');
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>