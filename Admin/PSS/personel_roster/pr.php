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
$active_submodule = 'personnel_roster';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all employees for the roster
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get departments for filtering
    $departments = $dbManager->fetchAll("frsm", "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    
    // Get positions for filtering
    $positions = $dbManager->fetchAll("frsm", "SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $departments = [];
    $positions = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process any search/filter requests
$search_term = '';
$department_filter = '';
$position_filter = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search_term = $_GET['search'] ?? '';
    $department_filter = $_GET['department'] ?? '';
    $position_filter = $_GET['position'] ?? '';
    
    // Apply filters if provided
    if (!empty($search_term) || !empty($department_filter) || !empty($position_filter)) {
        $query = "SELECT * FROM employees WHERE is_active = 1";
        $params = [];
        
        if (!empty($search_term)) {
            $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_id LIKE ? OR email LIKE ?)";
            $search_param = "%$search_term%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if (!empty($department_filter)) {
            $query .= " AND department = ?";
            $params[] = $department_filter;
        }
        
        if (!empty($position_filter)) {
            $query .= " AND position = ?";
            $params[] = $position_filter;
        }
        
        $query .= " ORDER BY first_name, last_name";
        
        try {
            $employees = $dbManager->fetchAll("frsm", $query, $params);
        } catch (Exception $e) {
            error_log("Filter error: " . $e->getMessage());
            $employees = [];
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
        .roster-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .filters-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .employee-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .employee-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 15px;
        }
        .employee-body {
            padding: 15px;
        }
        .employee-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .employee-details {
            flex: 1;
        }
        .employee-id {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .employee-contact {
            border-top: 1px solid #e9ecef;
            padding-top: 10px;
            margin-top: 10px;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .department-badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .toggle-btn {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .toggle-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
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
        .action-btn {
            padding: 5px 10px;
            margin: 0 2px;
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
                    <a href="../shift_calendar_management/scm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i>
                        <span>Shift Calendar Management</span>
                    </a>
                    <a href="pr.php" class="sidebar-dropdown-link active">
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
                    <h1>Personnel Roster</h1>
                    <p>View and manage all active personnel in the Quezon City Fire and Rescue Department.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class='bx bx-download'></i> Export Roster
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
            
            <!-- Personnel Roster Content -->
            <div class="dashboard-content">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php
                    // Calculate statistics
                    $total_personnel = count($employees);
                    $departments_count = count($departments);
                    
                    // Get count by department (simplified)
                    $fire_suppression_count = 0;
                    $ems_count = 0;
                    $rescue_count = 0;
                    $hazmat_count = 0;
                    
                    foreach ($employees as $employee) {
                        switch ($employee['department']) {
                            case 'Fire Suppression':
                                $fire_suppression_count++;
                                break;
                            case 'Emergency Medical Services':
                                $ems_count++;
                                break;
                            case 'Rescue Operations':
                                $rescue_count++;
                                break;
                            case 'Hazardous Materials':
                                $hazmat_count++;
                                break;
                        }
                    }
                    ?>
                    
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_personnel; ?></div>
                            <div class="stats-label">Total Personnel</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $fire_suppression_count; ?></div>
                            <div class="stats-label">Fire Suppression</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $ems_count; ?></div>
                            <div class="stats-label">Emergency Medical</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $rescue_count + $hazmat_count; ?></div>
                            <div class="stats-label">Specialized Teams</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="filters-container animate-fade-in">
                            <h5 class="mb-3">Filter Personnel</h5>
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="search" class="form-label">Search by Name or ID</label>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   value="<?php echo htmlspecialchars($search_term); ?>" 
                                                   placeholder="Enter name or employee ID">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="department" class="form-label">Department</label>
                                            <select class="form-select" id="department" name="department">
                                                <option value="">All Departments</option>
                                                <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                                    <?php echo ($department_filter == $dept['department']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="position" class="form-label">Position</label>
                                            <select class="form-select" id="position" name="position">
                                                <option value="">All Positions</option>
                                                <?php foreach ($positions as $pos): ?>
                                                <option value="<?php echo htmlspecialchars($pos['position']); ?>" 
                                                    <?php echo ($position_filter == $pos['position']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($pos['position']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="mb-3 w-100">
                                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- View Toggle -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="view-toggle">
                            <div class="toggle-btn active" id="cardViewBtn">
                                <i class='bx bx-grid-alt'></i> Card View
                            </div>
                            <div class="toggle-btn" id="tableViewBtn">
                                <i class='bx bx-table'></i> Table View
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Personnel Roster - Card View -->
                <div class="row" id="cardView">
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $employee): ?>
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <div class="employee-card">
                                    <div class="employee-header d-flex align-items-center">
                                        <div class="me-3">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee['first_name'] . ' ' . $employee['last_name']); ?>&background=random&size=64&rounded=true&bold=true" 
                                                 alt="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>" 
                                                 class="employee-avatar">
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                            <small class="employee-id"><?php echo htmlspecialchars($employee['employee_id']); ?></small>
                                        </div>
                                    </div>
                                    <div class="employee-body">
                                        <div class="mb-2">
                                            <span class="department-badge bg-primary">
                                                <?php echo htmlspecialchars($employee['department']); ?>
                                            </span>
                                        </div>
                                        <p class="mb-1"><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                                        
                                        <div class="employee-contact">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">Status: 
                                                    <span class="badge bg-<?php echo $employee['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </small>
                                                <div>
                                                    <button class="btn btn-sm btn-outline-primary action-btn" data-bs-toggle="modal" data-bs-target="#employeeDetailsModal" data-id="<?php echo $employee['id']; ?>">
                                                        <i class='bx bx-show'></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary action-btn">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class='bx bx-user-x'></i>
                                <h5>No personnel found</h5>
                                <p>Try adjusting your search filters or check back later.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Personnel Roster - Table View (Hidden by Default) -->
                <div class="row d-none" id="tableView">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($employees) > 0): ?>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $employee['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary action-btn" data-bs-toggle="modal" data-bs-target="#employeeDetailsModal" data-id="<?php echo $employee['id']; ?>">
                                                        <i class='bx bx-show'></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary action-btn">
                                                        <i class='bx bx-edit'></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class='bx bx-user-x' style="font-size: 3rem; color: #dee2e6;"></i>
                                                <p class="text-muted mt-2">No personnel found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-labelledby="employeeDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeDetailsModalLabel">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="employeeDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Edit Profile</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Personnel Roster</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="formatPDF" value="pdf" checked>
                            <label class="form-check-label" for="formatPDF">
                                PDF Document
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="formatExcel" value="excel">
                            <label class="form-check-label" for="formatExcel">
                                Excel Spreadsheet
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="formatCSV" value="csv">
                            <label class="form-check-label" for="formatCSV">
                                CSV File
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Include Fields</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeContact" checked>
                            <label class="form-check-label" for="includeContact">
                                Contact Information
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeDepartment" checked>
                            <label class="form-check-label" for="includeDepartment">
                                Department Details
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeStatus" checked>
                            <label class="form-check-label" for="includeStatus">
                                Employment Status
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Export Roster</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View toggle functionality
        document.getElementById('cardViewBtn').addEventListener('click', function() {
            document.getElementById('cardView').classList.remove('d-none');
            document.getElementById('tableView').classList.add('d-none');
            document.getElementById('cardViewBtn').classList.add('active');
            document.getElementById('tableViewBtn').classList.remove('active');
        });
        
        document.getElementById('tableViewBtn').addEventListener('click', function() {
            document.getElementById('cardView').classList.add('d-none');
            document.getElementById('tableView').classList.remove('d-none');
            document.getElementById('cardViewBtn').classList.remove('active');
            document.getElementById('tableViewBtn').classList.add('active');
        });
        
        // Employee Details Modal
        const employeeDetailsModal = document.getElementById('employeeDetailsModal');
        if (employeeDetailsModal) {
            employeeDetailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const employeeId = button.getAttribute('data-id');
                
                // Load employee details via AJAX
                fetch(`get_employee_details.php?id=${employeeId}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('employeeDetailsContent').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('employeeDetailsContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class='bx bx-error'></i> Error loading employee details. Please try again.
                            </div>
                        `;
                    });
            });
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 1000);
            });
        }, 5000);
    </script>
</body>
</html>