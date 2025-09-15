<?php
session_start();

// Include the Database Manager first
require_once 'config/database_manager.php';

// Check if this is an API request
if (isset($_GET['api']) || isset($_POST['api']) || 
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)) {
    
    // Include and process API Gateway
    require_once 'config/api_gateway.php';
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'fsiet';
$active_submodule = 'maintenance_inspection_scheduler';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get equipment data
    $equipment = $dbManager->fetchAll("fsiet", "SELECT * FROM equipment ORDER BY name");
    
    // Get maintenance schedules with equipment names
    $maintenance_schedules = $dbManager->fetchAll("fsiet", "
        SELECT ms.*, e.name as equipment_name, e.serial_no, e.type as equipment_type
        FROM maintenance_schedules ms 
        LEFT JOIN equipment e ON ms.equipment_id = e.id 
        ORDER BY ms.next_maintenance
    ");
    
    // Get maintenance logs with equipment names
    $maintenance_logs = $dbManager->fetchAll("fsiet", "
        SELECT ml.*, e.name as equipment_name, e.serial_no, 
               CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
        FROM maintenance_logs ml 
        LEFT JOIN equipment e ON ml.equipment_id = e.id 
        LEFT JOIN frsm.users u ON ml.performed_by = u.id
        ORDER BY ml.maintenance_date DESC
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $equipment = [];
    $maintenance_schedules = [];
    $maintenance_logs = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add maintenance schedule
        if (isset($_POST['add_maintenance'])) {
            $query = "INSERT INTO maintenance_schedules (equipment_id, schedule_type, next_maintenance, description, assigned_to, status) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $params = [
                $_POST['equipment_id'],
                $_POST['schedule_type'],
                $_POST['next_maintenance'],
                $_POST['description'],
                $_POST['assigned_to'],
                'pending'
            ];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Maintenance schedule added successfully!";
            header("Location: mis.php");
            exit;
        }
        
        // Update maintenance schedule
        if (isset($_POST['update_maintenance'])) {
            $query = "UPDATE maintenance_schedules SET equipment_id = ?, schedule_type = ?, next_maintenance = ?, 
                     description = ?, assigned_to = ?, status = ? WHERE id = ?";
            $params = [
                $_POST['equipment_id'],
                $_POST['schedule_type'],
                $_POST['next_maintenance'],
                $_POST['description'],
                $_POST['assigned_to'],
                $_POST['status'],
                $_POST['schedule_id']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Maintenance schedule updated successfully!";
            header("Location: mis.php");
            exit;
        }
        
        // Complete maintenance and add to log
        if (isset($_POST['complete_maintenance'])) {
            // First get the schedule details
            $schedule = $dbManager->fetch("fsiet", "SELECT * FROM maintenance_schedules WHERE id = ?", [$_POST['schedule_id']]);
            
            // Add to maintenance logs
            $log_query = "INSERT INTO maintenance_logs (schedule_id, equipment_id, maintenance_date, performed_by, description, parts_used, cost, hours_spent) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $log_params = [
                $_POST['schedule_id'],
                $schedule['equipment_id'],
                date('Y-m-d'),
                $_SESSION['user_id'],
                $_POST['description'],
                $_POST['parts_used'],
                $_POST['cost'],
                $_POST['hours_spent']
            ];
            $dbManager->query("fsiet", $log_query, $log_params);
            
            // Update equipment last maintenance date
            $update_equipment = "UPDATE equipment SET last_maintenance = ? WHERE id = ?";
            $dbManager->query("fsiet", $update_equipment, [date('Y-m-d'), $schedule['equipment_id']]);
            
            // Update schedule status to completed
            $update_schedule = "UPDATE maintenance_schedules SET status = 'completed', last_maintenance = ? WHERE id = ?";
            $dbManager->query("fsiet", $update_schedule, [date('Y-m-d'), $_POST['schedule_id']]);
            
            $_SESSION['success_message'] = "Maintenance completed and logged successfully!";
            header("Location: mis.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Maintenance operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    try {
        $type = $_GET['type'] ?? '';
        $id = $_GET['id'] ?? 0;
        
        if ($type === 'schedule' && $id > 0) {
            $dbManager->query("fsiet", "DELETE FROM maintenance_schedules WHERE id = ?", [$id]);
            $_SESSION['success_message'] = "Maintenance schedule deleted successfully!";
        }
        elseif ($type === 'log' && $id > 0) {
            $dbManager->query("fsiet", "DELETE FROM maintenance_logs WHERE id = ?", [$id]);
            $_SESSION['success_message'] = "Maintenance log deleted successfully!";
        }
        
        header("Location: mis.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Delete operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting item: " . $e->getMessage();
        header("Location: mis.php");
        exit;
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
        .inventory-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.4rem;
        }
        .card-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .maintenance-overdue {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .maintenance-due-soon {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .maintenance-completed {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="fsietMenu">
                    <a href="../inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="../equipment_location_tracking/elt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="mis.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Inventory Reports & Audit Logs</span>
                    </a>
                      
                </div>
                
             <!-- Hydrant and Water Resource Mapping -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="hwrmMenu">
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
                            <i class='bx bx-bell'></i>
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
                    <h1>Maintenance & Inspection Scheduler</h1>
                    <p>Schedule and track equipment maintenance and inspections.</p>
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
            
            <!-- Maintenance Content -->
            <div class="dashboard-content">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="maintenanceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#schedules" type="button" role="tab">
                            <i class='bx bx-calendar'></i> Maintenance Schedules
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                            <i class='bx bx-history'></i> Maintenance Logs
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="maintenanceTabsContent">
                    <!-- Schedules Tab -->
                    <div class="tab-pane fade show active" id="schedules" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-plus'></i>
                                                Schedule Maintenance
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                                <select class="form-select" name="equipment_id" required>
                                                    <option value="">Select Equipment</option>
                                                    <?php foreach ($equipment as $eq): ?>
                                                        <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Schedule Type <span class="text-danger">*</span></label>
                                                <select class="form-select" name="schedule_type" required>
                                                    <option value="routine">Routine Maintenance</option>
                                                    <option value="corrective">Corrective Maintenance</option>
                                                    <option value="preventive">Preventive Maintenance</option>
                                                    <option value="emergency">Emergency Repair</option>
                                                    <option value="inspection">Inspection</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Next Maintenance Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="next_maintenance" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assigned To</label>
                                                <input type="text" class="form-control" name="assigned_to">
                                            </div>
                                            <button type="submit" name="add_maintenance" class="btn btn-primary w-100">
                                                <i class='bx bx-plus'></i> Schedule Maintenance
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Maintenance Schedules</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Equipment</th>
                                                        <th>Type</th>
                                                        <th>Next Maintenance</th>
                                                        <th>Status</th>
                                                        <th>Assigned To</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($maintenance_schedules) > 0): ?>
                                                        <?php foreach ($maintenance_schedules as $ms): 
                                                            $days_until = floor((strtotime($ms['next_maintenance']) - time()) / (60 * 60 * 24));
                                                            $status_class = '';
                                                            if ($ms['status'] === 'completed') {
                                                                $row_class = 'maintenance-completed';
                                                                $status_class = 'bg-success';
                                                            } elseif ($days_until <= 0) {
                                                                $row_class = 'maintenance-overdue';
                                                                $status_class = 'bg-danger';
                                                            } elseif ($days_until <= 7) {
                                                                $row_class = 'maintenance-due-soon';
                                                                $status_class = 'bg-warning';
                                                            } else {
                                                                $row_class = '';
                                                                $status_class = 'bg-info';
                                                            }
                                                        ?>
                                                        <tr class="<?php echo $row_class; ?>">
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($ms['equipment_name']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($ms['serial_no']); ?> - <?php echo htmlspecialchars($ms['equipment_type']); ?></small>
                                                            </td>
                                                            <td><?php echo ucfirst(str_replace('_', ' ', $ms['schedule_type'])); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($ms['next_maintenance'])); ?></td>
                                                            <td>
                                                                <span class="badge status-badge <?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($ms['status']); ?>
                                                                    <?php if ($ms['status'] === 'pending'): ?>
                                                                        (<?php echo $days_until > 0 ? "in $days_until days" : "overdue"; ?>)
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($ms['assigned_to'] ?? 'Unassigned'); ?></td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editScheduleModal" 
                                                                    data-id="<?php echo $ms['id']; ?>"
                                                                    data-equipment="<?php echo $ms['equipment_id']; ?>"
                                                                    data-type="<?php echo $ms['schedule_type']; ?>"
                                                                    data-date="<?php echo $ms['next_maintenance']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($ms['description']); ?>"
                                                                    data-assigned="<?php echo htmlspecialchars($ms['assigned_to']); ?>"
                                                                    data-status="<?php echo $ms['status']; ?>">
                                                                    <i class='bx bx-edit'></i>
                                                                </button>
                                                                <?php if ($ms['status'] !== 'completed'): ?>
                                                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#completeMaintenanceModal" 
                                                                    data-id="<?php echo $ms['id']; ?>">
                                                                    <i class='bx bx-check'></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                <a href="mis.php?delete=schedule&id=<?php echo $ms['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this maintenance schedule?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No maintenance schedules found</p>
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
                    
                    <!-- Logs Tab -->
                    <div class="tab-pane fade" id="logs" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Maintenance History</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Equipment</th>
                                                        <th>Maintenance Date</th>
                                                        <th>Performed By</th>
                                                        <th>Description</th>
                                                        <th>Parts Used</th>
                                                        <th>Cost</th>
                                                        <th>Hours</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($maintenance_logs) > 0): ?>
                                                        <?php foreach ($maintenance_logs as $log): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($log['equipment_name']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($log['serial_no']); ?></small>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($log['maintenance_date'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['performed_by_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($log['description'] ?? 'No description'); ?></td>
                                                            <td><?php echo htmlspecialchars($log['parts_used'] ?? 'N/A'); ?></td>
                                                            <td><?php echo $log['cost'] ? '₱' . number_format($log['cost'], 2) : 'N/A'; ?></td>
                                                            <td><?php echo $log['hours_spent'] ?? 'N/A'; ?></td>
                                                            <td class="action-buttons">
                                                                <a href="mis.php?delete=log&id=<?php echo $log['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this maintenance log?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center py-4">
                                                                <p class="text-muted">No maintenance logs found</p>
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Maintenance Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Equipment <span class="text-danger">*</span></label>
                            <select class="form-select" name="equipment_id" id="edit_equipment_id" required>
                                <option value="">Select Equipment</option>
                                <?php foreach ($equipment as $eq): ?>
                                    <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="schedule_type" id="edit_schedule_type" required>
                                <option value="routine">Routine Maintenance</option>
                                <option value="corrective">Corrective Maintenance</option>
                                <option value="preventive">Preventive Maintenance</option>
                                <option value="emergency">Emergency Repair</option>
                                <option value="inspection">Inspection</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next Maintenance Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="next_maintenance" id="edit_next_maintenance" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned To</label>
                            <input type="text" class="form-control" name="assigned_to" id="edit_assigned_to">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_maintenance" class="btn btn-primary">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Complete Maintenance Modal -->
    <div class="modal fade" id="completeMaintenanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="schedule_id" id="complete_schedule_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Maintenance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Description of Work Performed <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parts Used</label>
                            <textarea class="form-control" name="parts_used" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cost (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="cost">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hours Spent</label>
                                    <input type="number" step="0.5" class="form-control" name="hours_spent">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="complete_maintenance" class="btn btn-success">Complete Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize modals
        const editScheduleModal = document.getElementById('editScheduleModal');
        if (editScheduleModal) {
            editScheduleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('edit_schedule_id').value = button.getAttribute('data-id');
                document.getElementById('edit_equipment_id').value = button.getAttribute('data-equipment');
                document.getElementById('edit_schedule_type').value = button.getAttribute('data-type');
                document.getElementById('edit_next_maintenance').value = button.getAttribute('data-date');
                document.getElementById('edit_description').value = button.getAttribute('data-description');
                document.getElementById('edit_assigned_to').value = button.getAttribute('data-assigned');
                document.getElementById('edit_status').value = button.getAttribute('data-status');
            });
        }
        
        const completeMaintenanceModal = document.getElementById('completeMaintenanceModal');
        if (completeMaintenanceModal) {
            completeMaintenanceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('complete_schedule_id').value = button.getAttribute('data-id');
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
    </script>
</body>
</html>