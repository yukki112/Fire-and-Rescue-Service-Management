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
$active_submodule = 'report_generation_and_archiving';

// Initialize variables
$error_message = '';
$success_message = '';
$reports = [];
$report_details = null;
$generation_mode = false;
$archiving_mode = false;

// Fetch all reports for listing
try {
    $query = "SELECT iar.*, u.first_name, u.last_name 
             FROM piar.incident_analysis_reports iar
             LEFT JOIN frsm.users u ON iar.created_by = u.id
             ORDER BY iar.created_at DESC";
    $reports = $dbManager->fetchAll("piar", $query);
} catch (Exception $e) {
    error_log("Fetch reports error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate Report
        if (isset($_POST['generate_report'])) {
            $query = "INSERT INTO piar.incident_analysis_reports 
                     (incident_id, report_title, incident_summary, response_timeline, 
                      personnel_involved, units_involved, cause_investigation, origin_investigation,
                      damage_assessment, lessons_learned, recommendations, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $_POST['incident_id'],
                $_POST['report_title'],
                $_POST['incident_summary'],
                $_POST['response_timeline'],
                $_POST['personnel_involved'],
                $_POST['units_involved'],
                $_POST['cause_investigation'],
                $_POST['origin_investigation'],
                $_POST['damage_assessment'],
                $_POST['lessons_learned'],
                $_POST['recommendations'],
                $_SESSION['user_id']
            ];
            
            $dbManager->query("piar", $query, $params);
            $report_id = $dbManager->getConnection("piar")->lastInsertId();
            $success_message = "Report generated successfully!";
            
        } elseif (isset($_POST['archive_report'])) {
            // Archive Report
            $report_id = $_POST['report_id'];
            
            // Update report status to archived
            $query = "UPDATE piar.incident_analysis_reports SET status = 'archived' WHERE id = ?";
            $dbManager->query("piar", $query, [$report_id]);
            
            // Create archive record
            $query = "INSERT INTO piar.report_archives 
                     (report_id, archive_date, archived_by, archive_reason) 
                     VALUES (?, CURDATE(), ?, ?)";
            
            $params = [
                $report_id,
                $_SESSION['user_id'],
                $_POST['archive_reason']
            ];
            
            $dbManager->query("piar", $query, $params);
            $success_message = "Report archived successfully!";
        }
    } catch (Exception $e) {
        error_log("Report error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Handle GET requests (view/archive)
if (isset($_GET['action'])) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if ($_GET['action'] === 'view' && $id) {
            $query = "SELECT iar.*, u.first_name, u.last_name 
                     FROM piar.incident_analysis_reports iar
                     LEFT JOIN frsm.users u ON iar.created_by = u.id
                     WHERE iar.id = ?";
            $report_details = $dbManager->fetch("piar", $query, [$id]);
            
        } elseif ($_GET['action'] === 'archive' && $id) {
            $query = "SELECT * FROM piar.incident_analysis_reports WHERE id = ?";
            $report_details = $dbManager->fetch("piar", $query, [$id]);
            $archiving_mode = true;
        }
    } catch (Exception $e) {
        error_log("Report action error: " . $e->getMessage());
        $error_message = "An error occurred while processing your request. Please try again.";
    }
}

// Count reports by status for statistics
$stats = [
    'total' => count($reports),
    'draft' => 0,
    'submitted' => 0,
    'approved' => 0,
    'archived' => 0
];

foreach ($reports as $report) {
    switch ($report['status']) {
        case 'draft': $stats['draft']++; break;
        case 'submitted': $stats['submitted']++; break;
        case 'approved': $stats['approved']++; break;
        case 'archived': $stats['archived']++; break;
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
        
        .report-table {
            font-size: 0.9rem;
        }
        
        .report-table th {
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
        .stat-draft { background-color: #6c757d; }
        .stat-submitted { background-color: #fd7e14; }
        .stat-approved { background-color: #198754; }
        .stat-archived { background-color: #6f42c1; }
        
        /* Status badges */
        .status-draft { background-color: #6c757d; }
        .status-submitted { background-color: #0dcaf0; color: #000; }
        .status-approved { background-color: #198754; }
        .status-archived { background-color: #6f42c1; }
        
        /* Report content styling */
        .report-content {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .report-section {
            margin-bottom: 25px;
        }
        
        .report-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #0d6efd;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
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
                <img src="img/frsmse1.png" alt="QC Logo">
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
                       <a href="../action_review_and_lessons_learned/arall.php" class="sidebar-dropdown-link">
                     <i class='bx bx-refresh'></i>
                        <span>Action Review and Lessons Learned</span>
                    </a>
                     <a href="rgaa.php" class="sidebar-dropdown-link active">
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
                    <h1>Report Generation and Archiving</h1>
                    <p>Generate and archive incident analysis reports</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                        <i class='bx bx-plus'></i> Generate Report
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
                <div class="col-md-3">
                    <div class="stat-card stat-total">
                        <i class='bx bx-file'></i>
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Total Reports</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-draft">
                        <i class='bx bx-edit'></i>
                        <div class="number"><?php echo $stats['draft']; ?></div>
                        <div class="label">Draft</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-submitted">
                        <i class='bx bx-send'></i>
                        <div class="number"><?php echo $stats['submitted']; ?></div>
                        <div class="label">Submitted</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card stat-approved">
                        <i class='bx bx-check-circle'></i>
                        <div class="number"><?php echo $stats['approved']; ?></div>
                        <div class="label">Approved</div>
                    </div>
                </div>
            </div>
            
            <!-- Report Details View -->
            <?php if ($report_details && !$archiving_mode): ?>
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Report Details</h4>
                        <div>
                            <span class="badge badge-status status-<?php echo $report_details['status']; ?>">
                                <?php echo ucfirst($report_details['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Report Title:</strong> <?php echo htmlspecialchars($report_details['report_title']); ?></p>
                            <p><strong>Incident ID:</strong> <?php echo htmlspecialchars($report_details['incident_id']); ?></p>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($report_details['first_name'] . ' ' . $report_details['last_name']); ?></p>
                            <p><strong>Created At:</strong> <?php echo date('M j, Y', strtotime($report_details['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <?php echo ucfirst($report_details['status']); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M j, Y', strtotime($report_details['updated_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="report-content">
                        <div class="report-section">
                            <h5 class="report-section-title">Incident Summary</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['incident_summary'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Response Timeline</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['response_timeline'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Personnel Involved</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['personnel_involved'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Units Involved</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['units_involved'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Cause Investigation</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['cause_investigation'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Origin Investigation</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['origin_investigation'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Damage Assessment</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['damage_assessment'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Lessons Learned</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['lessons_learned'])); ?></p>
                        </div>
                        
                        <div class="report-section">
                            <h5 class="report-section-title">Recommendations</h5>
                            <p><?php echo nl2br(htmlspecialchars($report_details['recommendations'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="rgaa.php" class="btn btn-secondary me-2">Back to List</a>
                        <?php if ($report_details['status'] !== 'archived'): ?>
                            <a href="rgaa.php?action=archive&id=<?php echo $report_details['id']; ?>" class="btn btn-warning me-2">Archive</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reports List -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Incident Analysis Reports</h4>
                        <div class="form-group">
                            <input type="text" class="form-control" placeholder="Search reports..." id="reportSearch">
                        </div>
                    </div>
                    
                    <?php if (!empty($reports)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover report-table">
                                <thead>
                                    <tr>
                                        <th>Report Title</th>
                                        <th>Incident ID</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['report_title']); ?></td>
                                            <td><?php echo htmlspecialchars($report['incident_id']); ?></td>
                                            <td>
                                                <span class="badge badge-status status-<?php echo $report['status']; ?>">
                                                    <?php echo ucfirst($report['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <a href="rgaa.php?action=view&id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                    <i class='bx bx-show'></i>
                                                </a>
                                                <?php if ($report['status'] !== 'archived'): ?>
                                                    <a href="rgaa.php?action=archive&id=<?php echo $report['id']; ?>" class="btn btn-sm btn-warning action-btn" title="Archive">
                                                        <i class='bx bx-archive'></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-file'></i>
                            <h5>No Reports Yet</h5>
                            <p>Generate your first incident analysis report to get started.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                                Generate Your First Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Generate Report Modal -->
    <div class="modal fade" id="generateReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate New Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="rgaa.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Incident ID</label>
                                    <input type="text" class="form-control" name="incident_id" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Report Title</label>
                                    <input type="text" class="form-control" name="report_title" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Incident Summary</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="incident_summary" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Response Timeline</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="response_timeline" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Personnel Involved</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="personnel_involved" rows="3" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Units Involved</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="units_involved" rows="3" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Cause Investigation</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="cause_investigation" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Origin Investigation</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="origin_investigation" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Damage Assessment</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="damage_assessment" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Lessons Learned</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="lessons_learned" rows="4" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h6 class="form-section-title">Recommendations</h6>
                            <div class="mb-3">
                                <textarea class="form-control" name="recommendations" rows="4" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Archive Report Modal -->
    <?php if ($archiving_mode && $report_details): ?>
        <div class="modal fade show" id="archiveReportModal" tabindex="-1" style="display: block; padding-right: 17px;" aria-modal="true" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Archive Report</h5>
                        <a href="rgaa.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="rgaa.php">
                        <input type="hidden" name="report_id" value="<?php echo $report_details['id']; ?>">
                        <div class="modal-body">
                            <p>Are you sure you want to archive the report <strong>"<?php echo htmlspecialchars($report_details['report_title']); ?>"</strong>?</p>
                            <div class="mb-3">
                                <label class="form-label">Reason for Archiving</label>
                                <textarea class="form-control" name="archive_reason" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="rgaa.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="archive_report" class="btn btn-warning">Archive Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Search functionality
        document.getElementById('reportSearch').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.report-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Auto-show archive modal if needed
        <?php if ($archiving_mode && $report_details): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const archiveModal = new bootstrap.Modal(document.getElementById('archiveReportModal'));
                archiveModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>