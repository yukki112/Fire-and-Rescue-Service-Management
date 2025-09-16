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
$active_submodule = 'leave_and_absence_management';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all active employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get leave types
    $leave_types = $dbManager->fetchAll("pss", "SELECT * FROM leave_types ORDER BY name");
    
    // Get leave requests for the logged-in user
    $leave_requests = $dbManager->fetchAll("pss", 
        "SELECT lr.*, lt.name as leave_type_name, lt.color as leave_type_color 
         FROM leave_requests lr 
         JOIN leave_types lt ON lr.leave_type_id = lt.id 
         WHERE lr.employee_id = ? 
         ORDER BY lr.created_at DESC", 
        [$_SESSION['user_id']]
    );
    
    // For admins, get all leave requests
    if ($user['is_admin'] == 1) {
        $all_leave_requests = $dbManager->fetchAll("pss", 
            "SELECT lr.*, lt.name as leave_type_name, lt.color as leave_type_color, 
                    e.first_name, e.last_name, e.employee_id
             FROM leave_requests lr 
             JOIN leave_types lt ON lr.leave_type_id = lt.id 
             JOIN frsm.employees e ON lr.employee_id = e.id 
             ORDER BY lr.created_at DESC"
        );
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $leave_types = [];
    $leave_requests = [];
    $all_leave_requests = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_leave_request'])) {
        // Submit new leave request
        $employee_id = $_POST['employee_id'];
        $leave_type_id = $_POST['leave_type_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = $_POST['reason'] ?? '';
        
        try {
            // Check if dates are valid
            if (strtotime($start_date) > strtotime($end_date)) {
                $_SESSION['error_message'] = "End date must be after start date.";
            } else {
                // Insert new leave request
                $dbManager->query("pss", 
                    "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$employee_id, $leave_type_id, $start_date, $end_date, $reason]
                );
                
                $_SESSION['success_message'] = "Leave request submitted successfully!";
            }
        } catch (Exception $e) {
            error_log("Leave request submission error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to submit leave request. Please try again.";
        }
        
        header("Location: laam.php");
        exit;
    }
    
    if (isset($_POST['update_leave_status'])) {
        // Update leave request status (admin only)
        if ($user['is_admin'] == 1) {
            $leave_id = $_POST['leave_id'];
            $status = $_POST['status'];
            
            try {
                $dbManager->query("pss", 
                    "UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW() 
                     WHERE id = ?",
                    [$status, $_SESSION['user_id'], $leave_id]
                );
                
                $_SESSION['success_message'] = "Leave request updated successfully!";
            } catch (Exception $e) {
                error_log("Leave status update error: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to update leave request. Please try again.";
            }
        }
        
        header("Location: laam.php");
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
        .leave-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .leave-card {
            border-left: 4px solid #007bff;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .leave-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .leave-body {
            padding: 15px;
        }
        .leave-badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 15px;
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
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
                    <a href="../personel_roster/pr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Personnel Roster</span>
                    </a>
                    <a href="../shift_assignment/sa.php" class="sidebar-dropdown-link">
                          <i class='bx bx-task'></i>
                        <span>Shift Assignment</span>
                    </a>
                      <a href="../leave_and_absence_management/laam.php" class="sidebar-dropdown-link active">
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
                    <h1>Leave and Absence Management</h1>
                    <p>Manage leave requests and track employee absences in the Quezon City Fire and Rescue Department.</p>
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
            
            <!-- Leave Management Content -->
            <div class="dashboard-content">
                <!-- Submit Leave Request Form -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="leave-container animate-fade-in">
                            <h5 class="mb-3">Submit Leave Request</h5>
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="employee_id" class="form-label">Employee</label>
                                            <select class="form-select" id="employee_id" name="employee_id" required>
                                                <?php if ($user['is_admin'] == 1): ?>
                                                    <option value="">Select Employee</option>
                                                    <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>">
                                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php 
                                                    $employee = $dbManager->fetch("frsm", "SELECT * FROM employees WHERE id = ?", [$_SESSION['user_id']]);
                                                    if ($employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>" selected>
                                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                                    </option>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="leave_type_id" class="form-label">Leave Type</label>
                                            <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                                <option value="">Select Leave Type</option>
                                                <?php foreach ($leave_types as $leave): ?>
                                                <option value="<?php echo $leave['id']; ?>" data-color="<?php echo $leave['color']; ?>">
                                                    <?php echo htmlspecialchars($leave['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="start_date" class="form-label">Start Date</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">End Date</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="mb-3 w-100">
                                            <button type="submit" name="submit_leave_request" class="btn btn-primary w-100">Submit Request</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="reason" class="form-label">Reason</label>
                                            <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="Provide a reason for your leave request"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Requests Tabs -->
                <div class="row">
                    <div class="col-12">
                        <div class="leave-container animate-fade-in">
                            <ul class="nav nav-tabs" id="leaveTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="my-leaves-tab" data-bs-toggle="tab" data-bs-target="#my-leaves" type="button" role="tab" aria-controls="my-leaves" aria-selected="true">
                                        My Leave Requests
                                    </button>
                                </li>
                                <?php if ($user['is_admin'] == 1): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="all-leaves-tab" data-bs-toggle="tab" data-bs-target="#all-leaves" type="button" role="tab" aria-controls="all-leaves" aria-selected="false">
                                        All Leave Requests
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                            
                            <div class="tab-content" id="leaveTabsContent">
                                <!-- My Leave Requests Tab -->
                                <div class="tab-pane fade show active" id="my-leaves" role="tabpanel" aria-labelledby="my-leaves-tab">
                                    <?php if (count($leave_requests) > 0): ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Leave Type</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Duration</th>
                                                        <th>Reason</th>
                                                        <th>Status</th>
                                                        <th>Submitted On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($leave_requests as $request): 
                                                        $start = new DateTime($request['start_date']);
                                                        $end = new DateTime($request['end_date']);
                                                        $interval = $start->diff($end);
                                                        $duration = $interval->days + 1; // Include both start and end dates
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge" style="background-color: <?php echo $request['leave_type_color']; ?>;">
                                                                    <?php echo $request['leave_type_name']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                                            <td><?php echo $duration; ?> day(s)</td>
                                                            <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <span class="status-badge bg-<?php 
                                                                    switch($request['status']) {
                                                                        case 'pending': echo 'warning'; break;
                                                                        case 'approved': echo 'success'; break;
                                                                        case 'rejected': echo 'danger'; break;
                                                                        case 'cancelled': echo 'secondary'; break;
                                                                        default: echo 'secondary';
                                                                    }
                                                                ?>">
                                                                    <?php echo ucfirst($request['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state mt-3">
                                            <i class='bx bx-calendar-x'></i>
                                            <h5>No leave requests found</h5>
                                            <p>Submit your first leave request using the form above.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- All Leave Requests Tab (Admin Only) -->
                                <?php if ($user['is_admin'] == 1): ?>
                                <div class="tab-pane fade" id="all-leaves" role="tabpanel" aria-labelledby="all-leaves-tab">
                                    <?php if (count($all_leave_requests) > 0): ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Employee</th>
                                                        <th>Leave Type</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Duration</th>
                                                        <th>Reason</th>
                                                        <th>Status</th>
                                                        <th>Submitted On</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($all_leave_requests as $request): 
                                                        $start = new DateTime($request['start_date']);
                                                        $end = new DateTime($request['end_date']);
                                                        $interval = $start->diff($end);
                                                        $duration = $interval->days + 1; // Include both start and end dates
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                                <br><small class="text-muted"><?php echo $request['employee_id']; ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge" style="background-color: <?php echo $request['leave_type_color']; ?>;">
                                                                    <?php echo $request['leave_type_name']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                                            <td><?php echo $duration; ?> day(s)</td>
                                                            <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <span class="status-badge bg-<?php 
                                                                    switch($request['status']) {
                                                                        case 'pending': echo 'warning'; break;
                                                                        case 'approved': echo 'success'; break;
                                                                        case 'rejected': echo 'danger'; break;
                                                                        case 'cancelled': echo 'secondary'; break;
                                                                        default: echo 'secondary';
                                                                    }
                                                                ?>">
                                                                    <?php echo ucfirst($request['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                            <td>
                                                                <?php if ($request['status'] == 'pending'): ?>
                                                                <div class="btn-group">
                                                                    <form method="POST" action="" class="d-inline">
                                                                        <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                                        <input type="hidden" name="status" value="approved">
                                                                        <button type="submit" name="update_leave_status" class="btn btn-sm btn-outline-success action-btn">
                                                                            <i class='bx bx-check'></i> Approve
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" action="" class="d-inline">
                                                                        <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                                        <input type="hidden" name="status" value="rejected">
                                                                        <button type="submit" name="update_leave_status" class="btn btn-sm btn-outline-danger action-btn">
                                                                            <i class='bx bx-x'></i> Reject
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">No actions</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state mt-3">
                                            <i class='bx bx-calendar-x'></i>
                                            <h5>No leave requests found</h5>
                                            <p>There are no leave requests in the system.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
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
        
        // Set default dates for leave request form
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Set start date to tomorrow
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            startDateInput.value = tomorrow.toISOString().split('T')[0];
            
            // Set end date to day after tomorrow
            const dayAfterTomorrow = new Date(tomorrow);
            dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 1);
            endDateInput.value = dayAfterTomorrow.toISOString().split('T')[0];
            
            // Validate that end date is not before start date
            startDateInput.addEventListener('change', function() {
                const startDate = new Date(this.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate > endDate) {
                    endDateInput.value = this.value;
                }
            });
            
            endDateInput.addEventListener('change', function() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(this.value);
                
                if (endDate < startDate) {
                    this.value = startDateInput.value;
                    alert('End date cannot be before start date.');
                }
            });
        });
    </script>
</body>
</html>