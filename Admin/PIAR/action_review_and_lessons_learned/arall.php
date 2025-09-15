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
$active_submodule = 'action_review_and_lessons_learned';

// Initialize variables
$error_message = '';
$success_message = '';
$lessons = [];
$lesson_details = null;
$edit_mode = false;
$incidents = [];
$personnel = [];

// Fetch all incidents for dropdown
try {
    $query = "SELECT id, report_title FROM piar.incident_analysis_reports WHERE status != 'archived' ORDER BY created_at DESC";
    $incidents = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch incidents error: " . $e->getMessage());
}

// Fetch all personnel for dropdown
try {
    $query = "SELECT id, first_name, last_name, employee_id FROM frsm.employees WHERE is_active = 1 ORDER BY last_name, first_name";
    $personnel = $dbManager->fetchAll("frsm", $query);
} catch (Exception $e) {
    error_log("Fetch personnel error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create Lesson
        if (isset($_POST['create_lesson'])) {
            $query = "INSERT INTO piar.lessons_learned 
                     (incident_id, category, lesson_description, recommendation, priority, 
                      responsible_department, implementation_status, target_completion_date, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['category'],
                $_POST['lesson_description'],
                $_POST['recommendation'],
                $_POST['priority'],
                $_POST['responsible_department'] ?: null,
                $_POST['implementation_status'],
                $_POST['target_completion_date'] ?: null,
                $_SESSION['user_id']
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Lesson learned created successfully!";
            
        } elseif (isset($_POST['update_lesson'])) {
            // Update Lesson
            $lesson_id = $_POST['lesson_id'];
            $query = "UPDATE piar.lessons_learned 
                     SET incident_id = ?, category = ?, lesson_description = ?, recommendation = ?, 
                         priority = ?, responsible_department = ?, implementation_status = ?, 
                         target_completion_date = ?, actual_completion_date = ?
                     WHERE id = ?";
            
            $actual_completion_date = ($_POST['implementation_status'] == 'completed' && !empty($_POST['actual_completion_date'])) 
                ? $_POST['actual_completion_date'] 
                : null;
            
            $params = [
                $_POST['incident_id'],
                $_POST['category'],
                $_POST['lesson_description'],
                $_POST['recommendation'],
                $_POST['priority'],
                $_POST['responsible_department'] ?: null,
                $_POST['implementation_status'],
                $_POST['target_completion_date'] ?: null,
                $actual_completion_date,
                $lesson_id
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Lesson learned updated successfully!";
        }
    } catch (Exception $e) {
        error_log("Lesson error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/edit/delete)
if (isset($_GET['action'])) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if ($_GET['action'] === 'view' && $id) {
            $query = "SELECT ll.*, iar.report_title, u.first_name, u.last_name 
                     FROM piar.lessons_learned ll
                     LEFT JOIN piar.incident_analysis_reports iar ON ll.incident_id = iar.id
                     LEFT JOIN frsm.users u ON ll.created_by = u.id
                     WHERE ll.id = ?";
            $lesson_details = $dbManager->fetch("piar", $query, [$id]);
            
        } elseif ($_GET['action'] === 'edit' && $id) {
            $query = "SELECT * FROM piar.lessons_learned WHERE id = ?";
            $lesson_details = $dbManager->fetch("piar", $query, [$id]);
            $edit_mode = true;
            
        } elseif ($_GET['action'] === 'delete' && $id) {
            $query = "DELETE FROM piar.lessons_learned WHERE id = ?";
            $dbManager->query("piar", $query, [$id]);
            $success_message = "Lesson learned deleted successfully!";
        }
    } catch (Exception $e) {
        error_log("Lesson action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Fetch all lessons
try {
    $query = "SELECT ll.*, iar.report_title, u.first_name, u.last_name 
             FROM piar.lessons_learned ll
             LEFT JOIN piar.incident_analysis_reports iar ON ll.incident_id = iar.id
             LEFT JOIN frsm.users u ON ll.created_by = u.id
             ORDER BY ll.created_at DESC";
    $lessons = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch lessons error: " . $e->getMessage());
}

// Count lessons by status for statistics
$stats = [
    'total' => count($lessons),
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'critical' => 0,
    'high' => 0
];

foreach ($lessons as $lesson) {
    switch ($lesson['implementation_status']) {
        case 'pending': $stats['pending']++; break;
        case 'in_progress': $stats['in_progress']++; break;
        case 'completed': $stats['completed']++; break;
    }
    
    switch ($lesson['priority']) {
        case 'critical': $stats['critical']++; break;
        case 'high': $stats['high']++; break;
    }
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
        
        .lesson-table {
            font-size: 0.9rem;
        }
        
        .lesson-table th {
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
        
        .action-btn {
            margin-right: 5px;
        }
        
        .badge-priority {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
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
        
        /* Stats cards colors */
        .stat-total { background-color: #0d6efd; }
        .stat-pending { background-color: #fd7e14; }
        .stat-progress { background-color: #ffc107; color: #000; }
        .stat-completed { background-color: #198754; }
        .stat-critical { background-color: #dc3545; }
        .stat-high { background-color: #6f42c1; }
        
        /* Priority badges */
        .priority-critical { background-color: #dc3545; }
        .priority-high { background-color: #fd7e14; }
        .priority-medium { background-color: #ffc107; color: #000; }
        .priority-low { background-color: #6c757d; }
        
        /* Status badges */
        .status-pending { background-color: #6c757d; }
        .status-in_progress { background-color: #0dcaf0; color: #000; }
        .status-completed { background-color: #198754; }
        .status-cancelled { background-color: #dc3545; }
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
                    <a href="../incident_summary_documentation/isd.php" class="sidebar-dropdown-link">
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
                       <a href="arall.php" class="sidebar-dropdown-link active">
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
                    <h1>Action Review and Lessons Learned</h1>
                    <p>Document and track lessons learned from incidents</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLessonModal">
                        <i class='bx bx-plus'></i> New Lesson
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
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stat-card stat-total">
                        <i class='bx bx-book'></i>
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Total Lessons</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card stat-pending">
                        <i class='bx bx-time'></i>
                        <div class="number"><?php echo $stats['pending']; ?></div>
                        <div class="label">Pending</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card stat-progress">
                        <i class='bx bx-trending-up'></i>
                        <div class="number"><?php echo $stats['in_progress']; ?></div>
                        <div class="label">In Progress</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card stat-completed">
                        <i class='bx bx-check-circle'></i>
                        <div class="number"><?php echo $stats['completed']; ?></div>
                        <div class="label">Completed</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card stat-critical">
                        <i class='bx bx-error-circle'></i>
                        <div class="number"><?php echo $stats['critical']; ?></div>
                        <div class="label">Critical Priority</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card stat-high">
                        <i class='bx bx-alarm-exclamation'></i>
                        <div class="number"><?php echo $stats['high']; ?></div>
                        <div class="label">High Priority</div>
                    </div>
                </div>
            </div>
            
            <!-- Lesson Details View -->
            <?php if ($lesson_details && !$edit_mode): ?>
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Lesson Learned Details</h4>
                        <div>
                            <span class="badge badge-priority priority-<?php echo $lesson_details['priority']; ?>">
                                <?php echo ucfirst($lesson_details['priority']); ?> Priority
                            </span>
                            <span class="badge badge-status status-<?php echo $lesson_details['implementation_status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $lesson_details['implementation_status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Incident:</strong> <?php echo htmlspecialchars($lesson_details['report_title'] ?? 'Unknown Incident'); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($lesson_details['category']); ?></p>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($lesson_details['first_name'] . ' ' . $lesson_details['last_name']); ?></p>
                            <p><strong>Created At:</strong> <?php echo date('M j, Y', strtotime($lesson_details['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Responsible Department:</strong> <?php echo htmlspecialchars($lesson_details['responsible_department'] ?? 'Not assigned'); ?></p>
                            <p><strong>Target Completion:</strong> <?php echo $lesson_details['target_completion_date'] ? date('M j, Y', strtotime($lesson_details['target_completion_date'])) : 'Not set'; ?></p>
                            <?php if ($lesson_details['implementation_status'] == 'completed' && $lesson_details['actual_completion_date']): ?>
                                <p><strong>Actual Completion:</strong> <?php echo date('M j, Y', strtotime($lesson_details['actual_completion_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Lesson Description</h5>
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br(htmlspecialchars($lesson_details['lesson_description'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Recommendation</h5>
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br(htmlspecialchars($lesson_details['recommendation'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="arall.php" class="btn btn-secondary me-2">Back to List</a>
                        <a href="arall.php?action=edit&id=<?php echo $lesson_details['id']; ?>" class="btn btn-primary me-2">Edit</a>
                        
                        <!-- Delete Form -->
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $lesson_details['id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this lesson learned record?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Lessons List -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Lessons Learned Records</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search lessons..." id="lessonSearch">
                        </div>
                    </div>
                    
                    <?php if (!empty($lessons)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover lesson-table">
                                <thead>
                                    <tr>
                                        <th>Incident</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Responsible Department</th>
                                        <th>Target Completion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($lesson['report_title'] ?? 'Unknown Incident'); ?></td>
                                            <td><?php echo htmlspecialchars($lesson['category']); ?></td>
                                            <td>
                                                <span class="badge badge-priority priority-<?php echo $lesson['priority']; ?>">
                                                    <?php echo ucfirst($lesson['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-status status-<?php echo $lesson['implementation_status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $lesson['implementation_status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($lesson['responsible_department'] ?? 'Not assigned'); ?></td>
                                            <td><?php echo $lesson['target_completion_date'] ? date('M j, Y', strtotime($lesson['target_completion_date'])) : 'Not set'; ?></td>
                                            <td>
                                                <a href="arall.php?action=view&id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <a href="arall.php?action=edit&id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary action-btn" title="Edit">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                                <a href="arall.php?action=delete&id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-danger action-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this lesson learned record?')">
                                                    <i class='bx bx-trash'></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-book-open'></i>
                            <h5>No Lessons Learned Yet</h5>
                            <p>Start documenting lessons learned from incidents to improve future responses.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createLessonModal">
                                Create Your First Lesson
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Lesson Modal -->
    <div class="modal fade" id="createLessonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Lesson Learned</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-section">
                            <h6 class="form-section-title">Basic Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Incident *</label>
                                    <select class="form-select" name="incident_id" required>
                                        <option value="">Select Incident</option>
                                        <?php foreach ($incidents as $incident): ?>
                                            <option value="<?php echo $incident['id']; ?>"><?php echo htmlspecialchars($incident['report_title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="response">Response</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="communication">Communication</option>
                                        <option value="coordination">Coordination</option>
                                        <option value="training">Training</option>
                                        <option value="safety">Safety</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Lesson Details</h6>
                            <div class="mb-3">
                                <label class="form-label">Lesson Description *</label>
                                <textarea class="form-control" name="lesson_description" rows="4" required placeholder="Describe the lesson learned from the incident..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Recommendation *</label>
                                <textarea class="form-control" name="recommendation" rows="4" required placeholder="Provide recommendations for improvement..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Implementation Details</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Priority *</label>
                                    <select class="form-select" name="priority" required>
                                        <option value="">Select Priority</option>
                                        <option value="critical">Critical</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Responsible Department</label>
                                    <input type="text" class="form-control" name="responsible_department" placeholder="e.g., Operations, Training">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Implementation Status *</label>
                                    <select class="form-select" name="implementation_status" required>
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Target Completion Date</label>
                                    <input type="date" class="form-control" name="target_completion_date">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_lesson" class="btn btn-primary">Create Lesson</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Lesson Modal -->
    <?php if ($edit_mode && $lesson_details): ?>
        <div class="modal fade show" id="editLessonModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 17px;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Lesson Learned</h5>
                        <a href="arall.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="lesson_id" value="<?php echo $lesson_details['id']; ?>">
                        <div class="modal-body">
                            <div class="form-section">
                                <h6 class="form-section-title">Basic Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Incident *</label>
                                        <select class="form-select" name="incident_id" required>
                                            <option value="">Select Incident</option>
                                            <?php foreach ($incidents as $incident): ?>
                                                <option value="<?php echo $incident['id']; ?>" <?php echo $incident['id'] == $lesson_details['incident_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($incident['report_title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category *</label>
                                        <select class="form-select" name="category" required>
                                            <option value="">Select Category</option>
                                            <option value="response" <?php echo $lesson_details['category'] == 'response' ? 'selected' : ''; ?>>Response</option>
                                            <option value="equipment" <?php echo $lesson_details['category'] == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                            <option value="communication" <?php echo $lesson_details['category'] == 'communication' ? 'selected' : ''; ?>>Communication</option>
                                            <option value="coordination" <?php echo $lesson_details['category'] == 'coordination' ? 'selected' : ''; ?>>Coordination</option>
                                            <option value="training" <?php echo $lesson_details['category'] == 'training' ? 'selected' : ''; ?>>Training</option>
                                            <option value="safety" <?php echo $lesson_details['category'] == 'safety' ? 'selected' : ''; ?>>Safety</option>
                                            <option value="other" <?php echo $lesson_details['category'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h6 class="form-section-title">Lesson Details</h6>
                                <div class="mb-3">
                                    <label class="form-label">Lesson Description *</label>
                                    <textarea class="form-control" name="lesson_description" rows="4" required><?php echo htmlspecialchars($lesson_details['lesson_description']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Recommendation *</label>
                                    <textarea class="form-control" name="recommendation" rows="4" required><?php echo htmlspecialchars($lesson_details['recommendation']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h6 class="form-section-title">Implementation Details</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Priority *</label>
                                        <select class="form-select" name="priority" required>
                                            <option value="critical" <?php echo $lesson_details['priority'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                                            <option value="high" <?php echo $lesson_details['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="medium" <?php echo $lesson_details['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="low" <?php echo $lesson_details['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Responsible Department</label>
                                        <input type="text" class="form-control" name="responsible_department" value="<?php echo htmlspecialchars($lesson_details['responsible_department'] ?? ''); ?>" placeholder="e.g., Operations, Training">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Implementation Status *</label>
                                        <select class="form-select" name="implementation_status" required>
                                            <option value="pending" <?php echo $lesson_details['implementation_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $lesson_details['implementation_status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $lesson_details['implementation_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $lesson_details['implementation_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Target Completion Date</label>
                                        <input type="date" class="form-control" name="target_completion_date" value="<?php echo $lesson_details['target_completion_date'] ? date('Y-m-d', strtotime($lesson_details['target_completion_date'])) : ''; ?>">
                                    </div>
                                    <?php if ($lesson_details['implementation_status'] == 'completed'): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Actual Completion Date</label>
                                            <input type="date" class="form-control" name="actual_completion_date" value="<?php echo $lesson_details['actual_completion_date'] ? date('Y-m-d', strtotime($lesson_details['actual_completion_date'])) : ''; ?>">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="arall.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="update_lesson" class="btn btn-primary">Update Lesson</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Search functionality
        document.getElementById('lessonSearch').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.lesson-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>