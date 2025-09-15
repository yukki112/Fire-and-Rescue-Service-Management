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
$active_submodule = 'personnel_training_profiles';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all employees with their training data
    $employees = $dbManager->fetchAll("frsm", 
        "SELECT e.*, 
                COUNT(te.id) as total_trainings,
                COUNT(CASE WHEN te.status = 'completed' THEN 1 END) as completed_trainings,
                COUNT(CASE WHEN te.status = 'registered' THEN 1 END) as pending_trainings
         FROM employees e
         LEFT JOIN tcr.training_enrollments te ON e.id = te.user_id
         WHERE e.is_active = 1
         GROUP BY e.id
         ORDER BY e.first_name, e.last_name"
    );
    
    // Get detailed training info for a specific employee if selected
    $selected_employee_id = $_GET['employee_id'] ?? null;
    $employee_trainings = [];
    $employee_certifications = [];
    
    if ($selected_employee_id) {
        // Get employee details
        $selected_employee = $dbManager->fetch("frsm", 
            "SELECT * FROM employees WHERE id = ?", 
            [$selected_employee_id]
        );
        
        // Get employee's training enrollments
        $employee_trainings = $dbManager->fetchAll("tcr", 
            "SELECT te.*, ts.session_code, ts.title as session_title, 
                    tc.course_code, tc.course_name, tc.category,
                    ts.start_date, ts.end_date, ts.instructor
             FROM training_enrollments te
             JOIN training_sessions ts ON te.session_id = ts.id
             JOIN training_courses tc ON ts.course_id = tc.id
             WHERE te.user_id = ?
             ORDER BY ts.start_date DESC",
            [$selected_employee_id]
        );
        
        // Get employee's certifications
        $employee_certifications = $dbManager->fetchAll("tcr", 
            "SELECT c.*, tc.course_code, tc.course_name
             FROM certifications c
             JOIN training_courses tc ON c.course_id = tc.id
             WHERE c.user_id = ?
             ORDER BY c.issue_date DESC",
            [$selected_employee_id]
        );
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $employee_trainings = [];
    $employee_certifications = [];
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
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .training-container {
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
        .employee-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        .progress {
            height: 8px;
        }
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
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
                    <a href="ptr.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-book-reader'></i>
                        <span>Personnel Training Profiles</span>
                    </a>
                    <a href="../training_course_management/tcm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
                    </a>
                    <a href="../training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link">
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
                    <h1>Personnel Training Profiles</h1>
                    <p>View and manage training records and certifications for all personnel.</p>
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
            
            <!-- Personnel Training Profiles Content -->
            <div class="dashboard-content">
                <!-- Employee List Section -->
                <div class="profile-card animate-fade-in">
                    <h5 class="mb-3">Personnel List</h5>
                    
                    <?php if (count($employees) > 0): ?>
                        <div class="row">
                            <?php foreach ($employees as $employee): 
                                $completion_rate = $employee['total_trainings'] > 0 
                                    ? ($employee['completed_trainings'] / $employee['total_trainings']) * 100 
                                    : 0;
                                $initials = substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1);
                            ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card employee-card <?php echo $selected_employee_id == $employee['id'] ? 'border-primary' : ''; ?>" 
                                         onclick="window.location.href='?employee_id=<?php echo $employee['id']; ?>'">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="employee-avatar me-3">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo $employee['employee_id']; ?></small>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Department: <?php echo htmlspecialchars($employee['department']); ?></small>
                                                    </div>
                                                    <div class="mt-2">
                                                        <small class="text-muted d-block">Trainings: <?php echo $employee['completed_trainings']; ?> completed of <?php echo $employee['total_trainings']; ?> total</small>
                                                        <div class="progress mt-1">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $completion_rate; ?>%;" 
                                                                 aria-valuenow="<?php echo $completion_rate; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state mt-3">
                            <i class='bx bx-user'></i>
                            <h5>No personnel found</h5>
                            <p>There are no active employees in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Selected Employee Details -->
                <?php if ($selected_employee_id && isset($selected_employee)): ?>
                    <div class="training-container animate-fade-in">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">
                                Training Profile: 
                                <span class="text-primary"><?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?></span>
                            </h5>
                            <span class="badge bg-light text-dark">ID: <?php echo $selected_employee['employee_id']; ?></span>
                        </div>
                        
                        <!-- Training Tabs -->
                        <ul class="nav nav-tabs mb-3" id="trainingTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="enrollments-tab" data-bs-toggle="tab" data-bs-target="#enrollments" type="button" role="tab">
                                    <i class='bx bx-book'></i> Training Enrollments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="certifications-tab" data-bs-toggle="tab" data-bs-target="#certifications" type="button" role="tab">
                                    <i class='bx bx-certificate'></i> Certifications
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="trainingTabContent">
                            <!-- Training Enrollments Tab -->
                            <div class="tab-pane fade show active" id="enrollments" role="tabpanel">
                                <?php if (count($employee_trainings) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Session</th>
                                                    <th>Dates</th>
                                                    <th>Instructor</th>
                                                    <th>Status</th>
                                                    <th>Grade</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employee_trainings as $training): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($training['course_code'] . ' - ' . $training['course_name']); ?></strong>
                                                            <br><small class="text-muted">Category: <?php echo htmlspecialchars($training['category']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($training['session_code']); ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($training['session_title']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($training['start_date'])); ?> 
                                                            to 
                                                            <?php echo date('M j, Y', strtotime($training['end_date'])); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($training['instructor']); ?></td>
                                                        <td>
                                                            <span class="status-badge bg-<?php 
                                                                switch($training['status']) {
                                                                    case 'completed': echo 'success'; break;
                                                                    case 'attending': echo 'info'; break;
                                                                    case 'registered': echo 'warning'; break;
                                                                    case 'failed': echo 'danger'; break;
                                                                    case 'cancelled': echo 'secondary'; break;
                                                                    default: echo 'secondary';
                                                                }
                                                            ?>">
                                                                <?php echo ucfirst($training['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($training['final_grade']): ?>
                                                                <span class="badge bg-light text-dark"><?php echo $training['final_grade']; ?>%</span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state mt-3">
                                        <i class='bx bx-book-open'></i>
                                        <h5>No training enrollments</h5>
                                        <p>This employee has not enrolled in any training sessions.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Certifications Tab -->
                            <div class="tab-pane fade" id="certifications" role="tabpanel">
                                <?php if (count($employee_certifications) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Certification Number</th>
                                                    <th>Issue Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Status</th>
                                                    <th>Issuing Authority</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employee_certifications as $cert): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($cert['course_code'] . ' - ' . $cert['course_name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($cert['certification_number']); ?>
                                                            <?php if ($cert['verification_url']): ?>
                                                                <br><small><a href="<?php echo $cert['verification_url']; ?>" target="_blank">Verify</a></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $cert['issue_date'] != '0000-00-00' ? date('M j, Y', strtotime($cert['issue_date'])) : 'Pending'; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $cert['expiry_date'] ? date('M j, Y', strtotime($cert['expiry_date'])) : 'N/A'; ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge bg-<?php 
                                                                switch($cert['status']) {
                                                                    case 'active': echo 'success'; break;
                                                                    case 'expired': echo 'danger'; break;
                                                                    case 'revoked': echo 'secondary'; break;
                                                                    default: echo 'secondary';
                                                                }
                                                            ?>">
                                                                <?php echo ucfirst($cert['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cert['issuing_authority']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state mt-3">
                                        <i class='bx bx-certificate'></i>
                                        <h5>No certifications</h5>
                                        <p>This employee has not earned any certifications yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="training-container animate-fade-in">
                        <div class="empty-state">
                            <i class='bx bx-user'></i>
                            <h5>Select an Employee</h5>
                            <p>Click on an employee from the list above to view their training profile.</p>
                        </div>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>