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
$active_submodule = 'certification_tracking';

// Initialize filter variables
$filter_employee = $_GET['filter_employee'] ?? '';
$filter_course = $_GET['filter_course'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_expiry = $_GET['filter_expiry'] ?? '';

try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all training courses for dropdown
    $courses = $dbManager->fetchAll("tcr", "SELECT * FROM training_courses WHERE status = 'active' ORDER BY course_name");
    
    // Get all employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Build the base query with filters
    $query = "SELECT c.*, e.first_name, e.last_name, e.employee_id, tc.course_name, tc.course_code 
              FROM certifications c 
              JOIN frsm.employees e ON c.user_id = e.id 
              JOIN training_courses tc ON c.course_id = tc.id 
              WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filter_employee)) {
        $query .= " AND c.user_id = ?";
        $params[] = $filter_employee;
    }
    
    if (!empty($filter_course)) {
        $query .= " AND c.course_id = ?";
        $params[] = $filter_course;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND c.status = ?";
        $params[] = $filter_status;
    }
    
    // Apply expiry filters
    if (!empty($filter_expiry)) {
        $today = date('Y-m-d');
        if ($filter_expiry === 'expired') {
            $query .= " AND c.expiry_date < ? AND c.expiry_date IS NOT NULL";
            $params[] = $today;
        } elseif ($filter_expiry === 'expiring_soon') {
            $nextMonth = date('Y-m-d', strtotime('+30 days'));
            $query .= " AND c.expiry_date BETWEEN ? AND ? AND c.expiry_date IS NOT NULL";
            $params[] = $today;
            $params[] = $nextMonth;
        } elseif ($filter_expiry === 'not_expiring') {
            $query .= " AND (c.expiry_date IS NULL OR c.expiry_date = '0000-00-00')";
        }
    }
    
    $query .= " ORDER BY c.issue_date DESC, e.last_name, e.first_name";
    
    // Get all certifications with applied filters
    $certifications = $dbManager->fetchAll("tcr", $query, $params);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_certification'])) {
            $employee_id = $_POST['employee_id'];
            $course_id = $_POST['course_id'];
            $certification_number = $_POST['certification_number'];
            $issue_date = $_POST['issue_date'];
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
            $status = $_POST['status'];
            $issuing_authority = $_POST['issuing_authority'];
            $verification_url = $_POST['verification_url'];
            $notes = $_POST['notes'];
            
            try {
                // Check if certification number already exists
                $existing = $dbManager->fetch("tcr", 
                    "SELECT * FROM certifications WHERE certification_number = ?", 
                    [$certification_number]
                );
                
                if ($existing) {
                    $error_message = "Certification number already exists. Please use a unique certification number.";
                } else {
                    // Add new certification
                    $dbManager->query("tcr", 
                        "INSERT INTO certifications (user_id, course_id, certification_number, issue_date, expiry_date, status, issuing_authority, verification_url, notes) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$employee_id, $course_id, $certification_number, $issue_date, $expiry_date, $status, $issuing_authority, $verification_url, $notes]
                    );
                    
                    $success_message = "Certification successfully added.";
                }
            } catch (Exception $e) {
                $error_message = "Error adding certification: " . $e->getMessage();
            }
        }
        
        if (isset($_POST['edit_certification'])) {
            $certification_id = $_POST['certification_id'];
            $employee_id = $_POST['employee_id'];
            $course_id = $_POST['course_id'];
            $certification_number = $_POST['certification_number'];
            $issue_date = $_POST['issue_date'];
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
            $status = $_POST['status'];
            $issuing_authority = $_POST['issuing_authority'];
            $verification_url = $_POST['verification_url'];
            $notes = $_POST['notes'];
            
            try {
                // Check if certification number already exists for another certification
                $existing = $dbManager->fetch("tcr", 
                    "SELECT * FROM certifications WHERE certification_number = ? AND id != ?", 
                    [$certification_number, $certification_id]
                );
                
                if ($existing) {
                    $error_message = "Certification number already exists. Please use a unique certification number.";
                } else {
                    // Update certification
                    $dbManager->query("tcr", 
                        "UPDATE certifications SET user_id = ?, course_id = ?, certification_number = ?, issue_date = ?, expiry_date = ?, 
                         status = ?, issuing_authority = ?, verification_url = ?, notes = ? WHERE id = ?",
                        [$employee_id, $course_id, $certification_number, $issue_date, $expiry_date, $status, $issuing_authority, $verification_url, $notes, $certification_id]
                    );
                    
                    $success_message = "Certification successfully updated.";
                }
            } catch (Exception $e) {
                $error_message = "Error updating certification: " . $e->getMessage();
            }
        }
        
        if (isset($_POST['delete_certification'])) {
            $certification_id = $_POST['certification_id'];
            
            try {
                // Delete certification
                $dbManager->query("tcr", 
                    "DELETE FROM certifications WHERE id = ?",
                    [$certification_id]
                );
                
                $success_message = "Certification successfully deleted.";
            } catch (Exception $e) {
                $error_message = "Error deleting certification: " . $e->getMessage();
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
    $employees = [];
    $certifications = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? $success_message ?? '';
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
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .certifications-table {
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
        
        /* Expired certification styling */
        .expired-certification {
            background-color: #fff5f5;
        }
        .expiring-soon {
            background-color: #fff9db;
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
                    <a href="../training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link">
                       <i class='bx bx-calendar'></i>
        <span>Training Calendar and Scheduling</span>
                    </a>
                    <a href="ct.php" class="sidebar-dropdown-link active">
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
                    <h1>Certification Tracking</h1>
                    <p>View and manage employee certifications and training records.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                            <i class='bx bx-plus'></i> Add New Certification
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
            
            <!-- Certification Tracking Content -->
            <div class="dashboard-content">
                <!-- Filter Section -->
                <div class="filter-section animate-fade-in">
                    <div class="filter-header" id="filterToggle">
                        <h5><i class='bx bx-filter-alt'></i> Filter Certifications</h5>
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
                            <label for="filter_status" class="form-label">Status</label>
                            <select class="form-select" id="filter_status" name="filter_status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $filter_status == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="revoked" <?php echo $filter_status == 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_expiry" class="form-label">Expiry Status</label>
                            <select class="form-select" id="filter_expiry" name="filter_expiry">
                                <option value="">All</option>
                                <option value="expired" <?php echo $filter_expiry == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="expiring_soon" <?php echo $filter_expiry == 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                                <option value="not_expiring" <?php echo $filter_expiry == 'not_expiring' ? 'selected' : ''; ?>>No Expiry Date</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="applyFilters" class="btn btn-primary">
                            <i class='bx bx-check'></i> Apply Filters
                        </button>
                        <a href="ct.php" class="btn btn-outline-secondary">
                            <i class='bx bx-reset'></i> Clear Filters
                        </a>
                    </div>
                </div>
                
                <!-- Certifications List -->
                <div class="calendar-container animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Employee Certifications</h5>
                        <div class="text-muted">
                            <?php echo count($certifications); ?> certification(s) found
                        </div>
                    </div>
                    
                    <?php if (count($certifications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover certifications-table">
                                <thead>
                                    <tr>
                                        <th>Certification #</th>
                                        <th>Employee</th>
                                        <th>Employee ID</th>
                                        <th>Course</th>
                                        <th>Issue Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Issuing Authority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certifications as $cert): 
                                        // Check if certification is expired or expiring soon
                                        $isExpired = false;
                                        $isExpiringSoon = false;
                                        
                                        if ($cert['expiry_date'] && $cert['status'] == 'active') {
                                            $expiryDate = new DateTime($cert['expiry_date']);
                                            $today = new DateTime();
                                            $daysUntilExpiry = $today->diff($expiryDate)->days;
                                            
                                            if ($expiryDate < $today) {
                                                $isExpired = true;
                                            } elseif ($daysUntilExpiry <= 30) {
                                                $isExpiringSoon = true;
                                            }
                                        }
                                        
                                        $rowClass = '';
                                        if ($isExpired) {
                                            $rowClass = 'expired-certification';
                                        } elseif ($isExpiringSoon) {
                                            $rowClass = 'expiring-soon';
                                        }
                                    ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo htmlspecialchars($cert['certification_number']); ?></td>
                                            <td><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($cert['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($cert['course_code'] . ' - ' . $cert['course_name']); ?></td>
                                            <td><?php echo $cert['issue_date'] != '0000-00-00' ? date('M j, Y', strtotime($cert['issue_date'])) : 'Not Issued'; ?></td>
                                            <td><?php echo $cert['expiry_date'] ? date('M j, Y', strtotime($cert['expiry_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-badge 
                                                    <?php if ($cert['status'] == 'active') echo 'bg-success';
                                                    elseif ($cert['status'] == 'expired') echo 'bg-danger';
                                                    else echo 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($cert['status']); ?>
                                                    <?php if ($isExpired): ?>
                                                        <i class='bx bx-error' title="Expired"></i>
                                                    <?php elseif ($isExpiringSoon): ?>
                                                        <i class='bx bx-time-five' title="Expiring Soon"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($cert['issuing_authority']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary view-certification" 
                                                        data-bs-toggle="modal" data-bs-target="#certificationDetailModal"
                                                        data-id="<?php echo $cert['id']; ?>"
                                                        data-number="<?php echo htmlspecialchars($cert['certification_number']); ?>"
                                                        data-employee="<?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?>"
                                                        data-employee-id="<?php echo htmlspecialchars($cert['employee_id']); ?>"
                                                        data-course="<?php echo htmlspecialchars($cert['course_code'] . ' - ' . $cert['course_name']); ?>"
                                                        data-issue-date="<?php echo $cert['issue_date'] != '0000-00-00' ? date('M j, Y', strtotime($cert['issue_date'])) : 'Not Issued'; ?>"
                                                        data-expiry-date="<?php echo $cert['expiry_date'] ? date('M j, Y', strtotime($cert['expiry_date'])) : 'N/A'; ?>"
                                                        data-status="<?php echo $cert['status']; ?>"
                                                        data-authority="<?php echo htmlspecialchars($cert['issuing_authority']); ?>"
                                                        data-url="<?php echo htmlspecialchars($cert['verification_url']); ?>"
                                                        data-notes="<?php echo htmlspecialchars($cert['notes']); ?>">
                                                        <i class='bx bx-show'></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning edit-certification" 
                                                        data-bs-toggle="modal" data-bs-target="#editCertificationModal"
                                                        data-id="<?php echo $cert['id']; ?>"
                                                        data-user-id="<?php echo $cert['user_id']; ?>"
                                                        data-course-id="<?php echo $cert['course_id']; ?>"
                                                        data-certification-number="<?php echo htmlspecialchars($cert['certification_number']); ?>"
                                                        data-issue-date="<?php echo $cert['issue_date']; ?>"
                                                        data-expiry-date="<?php echo $cert['expiry_date']; ?>"
                                                        data-status="<?php echo $cert['status']; ?>"
                                                        data-issuing-authority="<?php echo htmlspecialchars($cert['issuing_authority']); ?>"
                                                        data-verification-url="<?php echo htmlspecialchars($cert['verification_url']); ?>"
                                                        data-notes="<?php echo htmlspecialchars($cert['notes']); ?>">
                                                        <i class='bx bx-edit'></i> Edit
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this certification?');">
                                                        <input type="hidden" name="certification_id" value="<?php echo $cert['id']; ?>">
                                                        <button type="submit" name="delete_certification" class="btn btn-sm btn-outline-danger">
                                                            <i class='bx bx-trash'></i> Delete
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
                            <i class='bx bx-certification'></i>
                            <h5>No certifications found</h5>
                            <p>No certifications match your current filters. Try adjusting your filters or add a new certification.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                <i class='bx bx-plus'></i> Add New Certification
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Certification Modal -->
    <div class="modal fade" id="addCertificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Certification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="employee_id" class="form-label">Employee</label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="certification_number" class="form-label">Certification Number</label>
                                <input type="text" class="form-control" id="certification_number" name="certification_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="issuing_authority" class="form-label">Issuing Authority</label>
                                <input type="text" class="form-control" id="issuing_authority" name="issuing_authority" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="issue_date" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                <div class="form-text">Leave blank if certification does not expire</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="verification_url" class="form-label">Verification URL</label>
                                <input type="url" class="form-control" id="verification_url" name="verification_url">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_certification" class="btn btn-primary">Add Certification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Certification Modal -->
    <div class="modal fade" id="editCertificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Certification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="certification_id" id="edit_certification_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_employee_id" class="form-label">Employee</label>
                                <select class="form-select" id="edit_employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_course_id" class="form-label">Course</label>
                                <select class="form-select" id="edit_course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_certification_number" class="form-label">Certification Number</label>
                                <input type="text" class="form-control" id="edit_certification_number" name="certification_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_issuing_authority" class="form-label">Issuing Authority</label>
                                <input type="text" class="form-control" id="edit_issuing_authority" name="issuing_authority" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_issue_date" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="edit_issue_date" name="issue_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date">
                                <div class="form-text">Leave blank if certification does not expire</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_verification_url" class="form-label">Verification URL</label>
                                <input type="url" class="form-control" id="edit_verification_url" name="verification_url">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_certification" class="btn btn-primary">Update Certification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Certification Detail Modal -->
    <div class="modal fade" id="certificationDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Certification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Certification Number</label>
                            <p id="detail_certification_number" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Employee</label>
                            <p id="detail_employee" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Employee ID</label>
                            <p id="detail_employee_id" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Course</label>
                            <p id="detail_course" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Issue Date</label>
                            <p id="detail_issue_date" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Expiry Date</label>
                            <p id="detail_expiry_date" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <p id="detail_status" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Issuing Authority</label>
                            <p id="detail_authority" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Verification URL</label>
                            <p id="detail_url" class="form-control-plaintext">
                                <a href="#" target="_blank" id="detail_url_link"></a>
                            </p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Notes</label>
                            <p id="detail_notes" class="form-control-plaintext"></p>
                        </div>
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
        // Mobile sidebar toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Filter toggle functionality
        const filterToggle = document.getElementById('filterToggle');
        const filterContent = document.getElementById('filterContent');
        const filterIcon = filterToggle.querySelector('.filter-toggle');
        
        filterToggle.addEventListener('click', function() {
            filterContent.classList.toggle('d-none');
            filterIcon.classList.toggle('collapsed');
        });
        
        // Apply filters button
        document.getElementById('applyFilters').addEventListener('click', function() {
            const employee = document.getElementById('filter_employee').value;
            const course = document.getElementById('filter_course').value;
            const status = document.getElementById('filter_status').value;
            const expiry = document.getElementById('filter_expiry').value;
            
            const params = new URLSearchParams();
            if (employee) params.set('filter_employee', employee);
            if (course) params.set('filter_course', course);
            if (status) params.set('filter_status', status);
            if (expiry) params.set('filter_expiry', expiry);
            
            window.location.href = 'ct.php?' + params.toString();
        });
        
        // Edit certification modal population
        const editButtons = document.querySelectorAll('.edit-certification');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const userId = this.getAttribute('data-user-id');
                const courseId = this.getAttribute('data-course-id');
                const certificationNumber = this.getAttribute('data-certification-number');
                const issueDate = this.getAttribute('data-issue-date');
                const expiryDate = this.getAttribute('data-expiry-date');
                const status = this.getAttribute('data-status');
                const issuingAuthority = this.getAttribute('data-issuing-authority');
                const verificationUrl = this.getAttribute('data-verification-url');
                const notes = this.getAttribute('data-notes');
                
                document.getElementById('edit_certification_id').value = id;
                document.getElementById('edit_employee_id').value = userId;
                document.getElementById('edit_course_id').value = courseId;
                document.getElementById('edit_certification_number').value = certificationNumber;
                document.getElementById('edit_issue_date').value = issueDate;
                document.getElementById('edit_expiry_date').value = expiryDate;
                document.getElementById('edit_status').value = status;
                document.getElementById('edit_issuing_authority').value = issuingAuthority;
                document.getElementById('edit_verification_url').value = verificationUrl;
                document.getElementById('edit_notes').value = notes;
            });
        });
        
        // View certification modal population
        const viewButtons = document.querySelectorAll('.view-certification');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('detail_certification_number').textContent = this.getAttribute('data-number');
                document.getElementById('detail_employee').textContent = this.getAttribute('data-employee');
                document.getElementById('detail_employee_id').textContent = this.getAttribute('data-employee-id');
                document.getElementById('detail_course').textContent = this.getAttribute('data-course');
                document.getElementById('detail_issue_date').textContent = this.getAttribute('data-issue-date');
                document.getElementById('detail_expiry_date').textContent = this.getAttribute('data-expiry-date');
                
                const status = this.getAttribute('data-status');
                const statusElement = document.getElementById('detail_status');
                statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                
                document.getElementById('detail_authority').textContent = this.getAttribute('data-authority');
                
                const url = this.getAttribute('data-url');
                const urlLink = document.getElementById('detail_url_link');
                if (url) {
                    urlLink.href = url;
                    urlLink.textContent = url;
                    document.getElementById('detail_url').style.display = 'block';
                } else {
                    document.getElementById('detail_url').style.display = 'none';
                }
                
                const notes = this.getAttribute('data-notes');
                document.getElementById('detail_notes').textContent = notes || 'No notes available';
            });
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>