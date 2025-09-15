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
$active_submodule = 'evaluation_and_assessment_records';

// Initialize filter variables
$filter_employee = $_GET['filter_employee'] ?? '';
$filter_course = $_GET['filter_course'] ?? '';
$filter_assessment_type = $_GET['filter_assessment_type'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';

try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get all training courses
    $courses = $dbManager->fetchAll("tcr", "SELECT * FROM training_courses WHERE status = 'active' ORDER BY course_name");
    
    // Get unique assessment types
    $assessment_types = $dbManager->fetchAll("tcr", "SELECT DISTINCT assessment_type FROM training_assessments WHERE assessment_type IS NOT NULL AND assessment_type != '' ORDER BY assessment_type");
    
    // Build the base query for assessment data
    $query = "SELECT 
                ta.*,
                e.id as employee_id,
                e.first_name,
                e.last_name,
                e.employee_id as emp_id,
                e.department,
                e.position,
                tc.course_code,
                tc.course_name,
                ts.session_code,
                ts.title as session_title,
                te.enrollment_date,
                te.status as enrollment_status,
                CASE 
                    WHEN ta.actual_score IS NULL THEN 'pending'
                    WHEN ta.actual_score >= ta.passing_score THEN 'passed'
                    ELSE 'failed'
                END as result_status
            FROM tcr.training_assessments ta
            JOIN tcr.training_enrollments te ON ta.enrollment_id = te.id
            JOIN tcr.training_sessions ts ON te.session_id = ts.id
            JOIN tcr.training_courses tc ON ts.course_id = tc.id
            JOIN frsm.employees e ON te.user_id = e.id
            WHERE e.is_active = 1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filter_employee)) {
        $query .= " AND e.id = ?";
        $params[] = $filter_employee;
    }
    
    if (!empty($filter_course)) {
        $query .= " AND tc.id = ?";
        $params[] = $filter_course;
    }
    
    if (!empty($filter_assessment_type)) {
        $query .= " AND ta.assessment_type = ?";
        $params[] = $filter_assessment_type;
    }
    
    if (!empty($filter_date_from)) {
        $query .= " AND ta.assessment_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $query .= " AND ta.assessment_date <= ?";
        $params[] = $filter_date_to;
    }
    
    $query .= " ORDER BY ta.assessment_date DESC, e.last_name, e.first_name, tc.course_name";
    
    // Get assessment data
    $assessmentData = $dbManager->fetchAll("tcr", $query, $params);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_assessment'])) {
            // Add new assessment
            $enrollment_id = $_POST['enrollment_id'];
            $assessment_type = $_POST['assessment_type'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $max_score = $_POST['max_score'];
            $passing_score = $_POST['passing_score'];
            $actual_score = !empty($_POST['actual_score']) ? $_POST['actual_score'] : null;
            $assessment_date = !empty($_POST['assessment_date']) ? $_POST['assessment_date'] : null;
            $evaluator = $_POST['evaluator'];
            $feedback = $_POST['feedback'];
            
            try {
                $insertQuery = "INSERT INTO tcr.training_assessments 
                                (enrollment_id, assessment_type, title, description, max_score, passing_score, 
                                actual_score, assessment_date, evaluator, feedback, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $dbManager->query("tcr", $insertQuery, [
                    $enrollment_id, $assessment_type, $title, $description, $max_score, 
                    $passing_score, $actual_score, $assessment_date, $evaluator, $feedback
                ]);
                
                $_SESSION['success_message'] = "Assessment added successfully!";
                header("Location: eaar.php");
                exit;
            } catch (Exception $e) {
                $error_message = "Error adding assessment: " . $e->getMessage();
            }
        } elseif (isset($_POST['update_assessment'])) {
            // Update existing assessment
            $assessment_id = $_POST['assessment_id'];
            $actual_score = !empty($_POST['actual_score']) ? $_POST['actual_score'] : null;
            $assessment_date = !empty($_POST['assessment_date']) ? $_POST['assessment_date'] : null;
            $evaluator = $_POST['evaluator'];
            $feedback = $_POST['feedback'];
            
            try {
                $updateQuery = "UPDATE tcr.training_assessments 
                                SET actual_score = ?, assessment_date = ?, evaluator = ?, feedback = ?, updated_at = NOW() 
                                WHERE id = ?";
                
                $dbManager->query("tcr", $updateQuery, [
                    $actual_score, $assessment_date, $evaluator, $feedback, $assessment_id
                ]);
                
                $_SESSION['success_message'] = "Assessment updated successfully!";
                header("Location: eaar.php");
                exit;
            } catch (Exception $e) {
                $error_message = "Error updating assessment: " . $e->getMessage();
            }
        } elseif (isset($_POST['delete_assessment'])) {
            // Delete assessment
            $assessment_id = $_POST['assessment_id'];
            
            try {
                $deleteQuery = "DELETE FROM tcr.training_assessments WHERE id = ?";
                $dbManager->query("tcr", $deleteQuery, [$assessment_id]);
                
                $_SESSION['success_message'] = "Assessment deleted successfully!";
                header("Location: eaar.php");
                exit;
            } catch (Exception $e) {
                $error_message = "Error deleting assessment: " . $e->getMessage();
            }
        }
    }
    
    // Get enrollments for dropdown
    $enrollments = $dbManager->fetchAll("tcr", "
        SELECT te.id, e.first_name, e.last_name, tc.course_name, ts.session_code
        FROM tcr.training_enrollments te
        JOIN frsm.employees e ON te.user_id = e.id
        JOIN tcr.training_sessions ts ON te.session_id = ts.id
        JOIN tcr.training_courses tc ON ts.course_id = tc.id
        WHERE te.status IN ('attending', 'completed')
        ORDER BY e.last_name, e.first_name, tc.course_name
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $courses = [];
    $assessment_types = [];
    $assessmentData = [];
    $enrollments = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? $error_message ?? '';
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
        .assessment-badge {
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
        .assessment-table {
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
        .passed-row {
            background-color: #f0fff4;
        }
        .failed-row {
            background-color: #fff5f5;
        }
        .pending-row {
            background-color: #fff9db;
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
                     <a href="eaar.php" class="sidebar-dropdown-link active">
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
                    <h1>Evaluation and Assessment Records</h1>
                    <p>Manage and review training assessments and evaluation records.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssessmentModal">
                            <i class='bx bx-plus'></i> Add Assessment
                        </button>
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
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
            
            <!-- Assessment Records Content -->
            <div class="dashboard-content">
                <!-- Filter Section -->
                <div class="filter-section animate-fade-in">
                    <div class="filter-header" id="filterToggle">
                        <h5><i class='bx bx-filter-alt'></i> Filter Assessment Data</h5>
                        <i class='bx bx-chevron-up filter-toggle'></i>
                    </div>
                    
                    <div class="filter-content" id="filterContent">
                        <div class="filter-group">
                            <label for="filter_employee" class="form-label">Employee</label>
                            <select class="form-select" id="filter_employee" name="filter_employee">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $filter_employee == $employee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_course" class="form-label">Course</label>
                            <select class="form-select" id="filter_course" name="filter_course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_assessment_type" class="form-label">Assessment Type</label>
                            <select class="form-select" id="filter_assessment_type" name="filter_assessment_type">
                                <option value="">All Types</option>
                                <?php foreach ($assessment_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['assessment_type']); ?>" <?php echo $filter_assessment_type == $type['assessment_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($type['assessment_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="filter_date_from" name="filter_date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="filter_date_to" name="filter_date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="applyFilters" class="btn btn-primary">
                            <i class='bx bx-check'></i> Apply Filters
                        </button>
                        <a href="eaar.php" class="btn btn-outline-secondary">
                            <i class='bx bx-reset'></i> Clear Filters
                        </a>
                    </div>
                </div>
                
                <!-- Assessment Records Table -->
                <div class="calendar-container animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Assessment Records</h5>
                        <div class="text-muted">
                            <?php echo count($assessmentData); ?> assessment(s) found
                        </div>
                    </div>
                    
                    <?php if (count($assessmentData) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover assessment-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Course</th>
                                        <th>Assessment Type</th>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Max Score</th>
                                        <th>Passing Score</th>
                                        <th>Actual Score</th>
                                        <th>Evaluator</th>
                                        <th>Result</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessmentData as $assessment): 
                                        $rowClass = '';
                                        if ($assessment['result_status'] === 'passed') {
                                            $rowClass = 'passed-row';
                                        } elseif ($assessment['result_status'] === 'failed') {
                                            $rowClass = 'failed-row';
                                        } elseif ($assessment['result_status'] === 'pending') {
                                            $rowClass = 'pending-row';
                                        }
                                    ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($assessment['course_code'] . ' - ' . $assessment['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($assessment['assessment_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($assessment['title']); ?></td>
                                            <td><?php echo $assessment['assessment_date'] ? date('M d, Y', strtotime($assessment['assessment_date'])) : '<span class="text-muted">Not set</span>'; ?></td>
                                            <td><?php echo $assessment['max_score']; ?></td>
                                            <td><?php echo $assessment['passing_score']; ?></td>
                                            <td>
                                                <?php if ($assessment['actual_score'] !== null): ?>
                                                    <?php echo $assessment['actual_score']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $assessment['evaluator'] ? htmlspecialchars($assessment['evaluator']) : '<span class="text-muted">Not set</span>'; ?></td>
                                            <td>
                                                <?php if ($assessment['result_status'] === 'passed'): ?>
                                                    <span class="badge bg-success assessment-badge">Passed</span>
                                                <?php elseif ($assessment['result_status'] === 'failed'): ?>
                                                    <span class="badge bg-danger assessment-badge">Failed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning assessment-badge">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-action" 
                                                            data-bs-toggle="modal" data-bs-target="#editAssessmentModal"
                                                            data-id="<?php echo $assessment['id']; ?>"
                                                            data-enrollment-id="<?php echo $assessment['enrollment_id']; ?>"
                                                            data-assessment-type="<?php echo htmlspecialchars($assessment['assessment_type']); ?>"
                                                            data-title="<?php echo htmlspecialchars($assessment['title']); ?>"
                                                            data-description="<?php echo htmlspecialchars($assessment['description']); ?>"
                                                            data-max-score="<?php echo $assessment['max_score']; ?>"
                                                            data-passing-score="<?php echo $assessment['passing_score']; ?>"
                                                            data-actual-score="<?php echo $assessment['actual_score']; ?>"
                                                            data-assessment-date="<?php echo $assessment['assessment_date']; ?>"
                                                            data-evaluator="<?php echo htmlspecialchars($assessment['evaluator']); ?>"
                                                            data-feedback="<?php echo htmlspecialchars($assessment['feedback']); ?>">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-action" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteAssessmentModal"
                                                            data-id="<?php echo $assessment['id']; ?>"
                                                            data-title="<?php echo htmlspecialchars($assessment['title']); ?>">
                                                        <i class='bx bx-trash'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-clipboard'></i>
                            <h5>No assessment records found</h5>
                            <p>There are no assessment records matching your criteria. Try adjusting your filters or add a new assessment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Assessment Modal -->
    <div class="modal fade" id="addAssessmentModal" tabindex="-1" aria-labelledby="addAssessmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAssessmentModalLabel">Add New Assessment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="enrollment_id" class="form-label">Enrollment *</label>
                                <select class="form-select" id="enrollment_id" name="enrollment_id" required>
                                    <option value="">Select Enrollment</option>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <option value="<?php echo $enrollment['id']; ?>">
                                            <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name'] . ' - ' . $enrollment['course_name'] . ' (' . $enrollment['session_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="assessment_type" class="form-label">Assessment Type *</label>
                                <select class="form-select" id="assessment_type" name="assessment_type" required>
                                    <option value="">Select Type</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="exam">Exam</option>
                                    <option value="practical">Practical Test</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="participation">Participation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max_score" class="form-label">Maximum Score *</label>
                                <input type="number" class="form-control" id="max_score" name="max_score" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="passing_score" class="form-label">Passing Score *</label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="actual_score" class="form-label">Actual Score</label>
                                <input type="number" class="form-control" id="actual_score" name="actual_score" min="0" step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="assessment_date" class="form-label">Assessment Date</label>
                                <input type="date" class="form-control" id="assessment_date" name="assessment_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="evaluator" class="form-label">Evaluator</label>
                                <input type="text" class="form-control" id="evaluator" name="evaluator">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="feedback" class="form-label">Feedback/Comments</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_assessment" class="btn btn-primary">Add Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Assessment Modal -->
    <div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-labelledby="editAssessmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" id="edit_assessment_id" name="assessment_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAssessmentModalLabel">Edit Assessment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_assessment_type" class="form-label">Assessment Type *</label>
                                <select class="form-select" id="edit_assessment_type" name="assessment_type" required>
                                    <option value="">Select Type</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="exam">Exam</option>
                                    <option value="practical">Practical Test</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="participation">Participation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_score" class="form-label">Maximum Score *</label>
                                <input type="number" class="form-control" id="edit_max_score" name="max_score" min="0" step="0.01" required readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="edit_title" name="title" required readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3" readonly></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_passing_score" class="form-label">Passing Score *</label>
                                <input type="number" class="form-control" id="edit_passing_score" name="passing_score" min="0" step="0.01" required readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_actual_score" class="form-label">Actual Score</label>
                                <input type="number" class="form-control" id="edit_actual_score" name="actual_score" min="0" step="0.01">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_assessment_date" class="form-label">Assessment Date</label>
                                <input type="date" class="form-control" id="edit_assessment_date" name="assessment_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_evaluator" class="form-label">Evaluator</label>
                                <input type="text" class="form-control" id="edit_evaluator" name="evaluator">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_feedback" class="form-label">Feedback/Comments</label>
                                <textarea class="form-control" id="edit_feedback" name="feedback" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_assessment" class="btn btn-primary">Update Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Assessment Modal -->
    <div class="modal fade" id="deleteAssessmentModal" tabindex="-1" aria-labelledby="deleteAssessmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" id="delete_assessment_id" name="assessment_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteAssessmentModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the assessment "<span id="delete_assessment_title"></span>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_assessment" class="btn btn-danger">Delete Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Filter toggle functionality
        const filterToggle = document.getElementById('filterToggle');
        const filterContent = document.getElementById('filterContent');
        const filterToggleIcon = filterToggle.querySelector('.filter-toggle');
        
        filterToggle.addEventListener('click', function() {
            filterContent.classList.toggle('show');
            filterToggleIcon.classList.toggle('collapsed');
        });
        
        // Apply filters button
        document.getElementById('applyFilters').addEventListener('click', function() {
            const employee = document.getElementById('filter_employee').value;
            const course = document.getElementById('filter_course').value;
            const assessmentType = document.getElementById('filter_assessment_type').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            
            let url = 'eaar.php?';
            if (employee) url += `filter_employee=${employee}&`;
            if (course) url += `filter_course=${course}&`;
            if (assessmentType) url += `filter_assessment_type=${assessmentType}&`;
            if (dateFrom) url += `filter_date_from=${dateFrom}&`;
            if (dateTo) url += `filter_date_to=${dateTo}&`;
            
            // Remove trailing & or ? if no parameters
            if (url.endsWith('&') || url.endsWith('?')) {
                url = url.slice(0, -1);
            }
            
            window.location.href = url;
        });
        
        // Edit Assessment Modal
        const editAssessmentModal = document.getElementById('editAssessmentModal');
        if (editAssessmentModal) {
            editAssessmentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const enrollmentId = button.getAttribute('data-enrollment-id');
                const assessmentType = button.getAttribute('data-assessment-type');
                const title = button.getAttribute('data-title');
                const description = button.getAttribute('data-description');
                const maxScore = button.getAttribute('data-max-score');
                const passingScore = button.getAttribute('data-passing-score');
                const actualScore = button.getAttribute('data-actual-score');
                const assessmentDate = button.getAttribute('data-assessment-date');
                const evaluator = button.getAttribute('data-evaluator');
                const feedback = button.getAttribute('data-feedback');
                
                document.getElementById('edit_assessment_id').value = id;
                document.getElementById('edit_assessment_type').value = assessmentType;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_max_score').value = maxScore;
                document.getElementById('edit_passing_score').value = passingScore;
                document.getElementById('edit_actual_score').value = actualScore;
                document.getElementById('edit_assessment_date').value = assessmentDate;
                document.getElementById('edit_evaluator').value = evaluator;
                document.getElementById('edit_feedback').value = feedback;
            });
        }
        
        // Delete Assessment Modal
        const deleteAssessmentModal = document.getElementById('deleteAssessmentModal');
        if (deleteAssessmentModal) {
            deleteAssessmentModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                
                document.getElementById('delete_assessment_id').value = id;
                document.getElementById('delete_assessment_title').textContent = title;
            });
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(function() {
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