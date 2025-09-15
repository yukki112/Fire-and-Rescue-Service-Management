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
$active_submodule = 'reporting_and_auditlogs';

// Initialize filter variables
$filter_action = $_GET['filter_action'] ?? '';
$filter_table = $_GET['filter_table'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$filter_record_id = $_GET['filter_record_id'] ?? '';

try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all users for filter dropdown
    $users = $dbManager->fetchAll("frsm", "SELECT id, first_name, last_name, username FROM users ORDER BY first_name, last_name");
    
    // Get unique actions for filter dropdown
    $actions = $dbManager->fetchAll("frsm", "SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL AND action != '' ORDER BY action");
    
    // Get unique tables for filter dropdown
    $tables = $dbManager->fetchAll("frsm", "SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL AND table_name != '' ORDER BY table_name");
    
    // Build the base query for audit logs
    $query = "SELECT 
                al.*,
                u.first_name,
                u.last_name,
                u.username,
                u.email as user_email
            FROM frsm.audit_logs al
            LEFT JOIN frsm.users u ON al.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filter_action)) {
        $query .= " AND al.action = ?";
        $params[] = $filter_action;
    }
    
    if (!empty($filter_table)) {
        $query .= " AND al.table_name = ?";
        $params[] = $filter_table;
    }
    
    if (!empty($filter_user)) {
        $query .= " AND al.user_id = ?";
        $params[] = $filter_user;
    }
    
    if (!empty($filter_date_from)) {
        $query .= " AND DATE(al.created_at) >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $query .= " AND DATE(al.created_at) <= ?";
        $params[] = $filter_date_to;
    }
    
    if (!empty($filter_record_id)) {
        $query .= " AND al.record_id = ?";
        $params[] = $filter_record_id;
    }
    
    $query .= " ORDER BY al.created_at DESC";
    
    // Get audit log data
    $auditLogs = $dbManager->fetchAll("frsm", $query, $params);
    
    // Generate reports
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
        $report_type = $_POST['report_type'];
        $report_format = $_POST['report_format'];
        
        // Generate report based on type
        switch ($report_type) {
            case 'user_activity':
                $report_title = "User Activity Report";
                $report_data = $dbManager->fetchAll("frsm", "
                    SELECT 
                        u.first_name,
                        u.last_name,
                        u.username,
                        u.email,
                        COUNT(al.id) as total_actions,
                        MIN(al.created_at) as first_action,
                        MAX(al.created_at) as last_action
                    FROM frsm.users u
                    LEFT JOIN frsm.audit_logs al ON u.id = al.user_id
                    GROUP BY u.id
                    ORDER BY total_actions DESC
                ");
                break;
                
            case 'action_summary':
                $report_title = "Action Summary Report";
                $report_data = $dbManager->fetchAll("frsm", "
                    SELECT 
                        action,
                        COUNT(*) as count,
                        MIN(created_at) as first_occurrence,
                        MAX(created_at) as last_occurrence
                    FROM frsm.audit_logs
                    GROUP BY action
                    ORDER BY count DESC
                ");
                break;
                
            case 'table_activity':
                $report_title = "Table Activity Report";
                $report_data = $dbManager->fetchAll("frsm", "
                    SELECT 
                        table_name,
                        COUNT(*) as count,
                        MIN(created_at) as first_occurrence,
                        MAX(created_at) as last_occurrence
                    FROM frsm.audit_logs
                    WHERE table_name IS NOT NULL AND table_name != ''
                    GROUP BY table_name
                    ORDER BY count DESC
                ");
                break;
                
            default:
                $report_title = "Audit Log Report";
                $report_data = $auditLogs;
                break;
        }
        
        // Generate report in requested format
        if ($report_format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $report_title . '_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add headers
            if (!empty($report_data)) {
                fputcsv($output, array_keys($report_data[0]));
                
                // Add data
                foreach ($report_data as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            exit;
        } else {
            // For HTML format, we'll just display the data in a table
            $_SESSION['report_data'] = $report_data;
            $_SESSION['report_title'] = $report_title;
            $_SESSION['report_type'] = $report_type;
        }
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $users = [];
    $actions = [];
    $tables = [];
    $auditLogs = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? $error_message ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Check if we have report data to display
$report_data = $_SESSION['report_data'] ?? [];
$report_title = $_SESSION['report_title'] ?? '';
$report_type = $_SESSION['report_type'] ?? '';
unset($_SESSION['report_data']);
unset($_SESSION['report_title']);
unset($_SESSION['report_type']);
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
        .audit-badge {
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
        .audit-table {
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
        
        /* Report section styles */
        .report-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        
        /* JSON display */
        .json-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
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
                    <a href="../certification_tracking/ct.php" class="sidebar-dropdown-link">
                       <i class='bx bx-badge-check'></i>
        <span>Certification Tracking</span>
                    </a>
                      <a href="../training_compliance_monitoring/tcm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-shield'></i>
        <span>Training Compliance Monitoring</span>
                    </a>
                     <a href="../evaluation_and_assessment_records/eaar.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
                    </a>
                    <a href="ral.php" class="sidebar-dropdown-link active">
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
                    <h1>Reporting and Audit Logs</h1>
                    <p>View system audit logs and generate comprehensive reports.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                            <i class='bx bx-file'></i> Generate Report
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
            
            <!-- Report Display Section -->
            <?php if (!empty($report_data) && $report_format !== 'csv'): ?>
            <div class="report-section animate-fade-in">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><?php echo htmlspecialchars($report_title); ?></h4>
                    <a href="ral.php?<?php echo http_build_query($_GET); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class='bx bx-x'></i> Close Report
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($report_data[0]) as $column): ?>
                                    <th><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $column))); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?php echo htmlspecialchars($value); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Audit Logs Content -->
            <div class="dashboard-content">
                <!-- Filter Section -->
                <div class="filter-section animate-fade-in">
                    <div class="filter-header" id="filterToggle">
                        <h5><i class='bx bx-filter-alt'></i> Filter Audit Logs</h5>
                        <i class='bx bx-chevron-up filter-toggle'></i>
                    </div>
                    
                    <div class="filter-content" id="filterContent">
                        <div class="filter-group">
                            <label for="filter_action" class="form-label">Action</label>
                            <select class="form-select" id="filter_action" name="filter_action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($action['action'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_table" class="form-label">Table</label>
                            <select class="form-select" id="filter_table" name="filter_table">
                                <option value="">All Tables</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?php echo htmlspecialchars($table['table_name']); ?>" <?php echo $filter_table == $table['table_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($table['table_name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_user" class="form-label">User</label>
                            <select class="form-select" id="filter_user" name="filter_user">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
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
                        
                        <div class="filter-group">
                            <label for="filter_record_id" class="form-label">Record ID</label>
                            <input type="number" class="form-control" id="filter_record_id" name="filter_record_id" value="<?php echo htmlspecialchars($filter_record_id); ?>" placeholder="Enter record ID">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="applyFilters" class="btn btn-primary">
                            <i class='bx bx-check'></i> Apply Filters
                        </button>
                        <a href="ral.php" class="btn btn-outline-secondary">
                            <i class='bx bx-reset'></i> Clear Filters
                        </a>
                    </div>
                </div>
                
                <!-- Audit Logs Table -->
                <div class="calendar-container animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Audit Log Records</h5>
                        <div class="text-muted">
                            <?php echo count($auditLogs); ?> log(s) found
                        </div>
                    </div>
                    
                    <?php if (count($auditLogs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover audit-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>IP Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['username'] . ')'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucfirst($log['action'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                            <td><?php echo $log['record_id'] ? $log['record_id'] : '<span class="text-muted">N/A</span>'; ?></td>
                                            <td><?php echo $log['ip_address'] ? htmlspecialchars($log['ip_address']) : '<span class="text-muted">N/A</span>'; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-action" 
                                                        data-bs-toggle="modal" data-bs-target="#viewLogDetailsModal"
                                                        data-id="<?php echo $log['id']; ?>"
                                                        data-user="<?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['username'] . ')'); ?>"
                                                        data-action="<?php echo htmlspecialchars($log['action']); ?>"
                                                        data-table="<?php echo htmlspecialchars($log['table_name']); ?>"
                                                        data-record-id="<?php echo $log['record_id']; ?>"
                                                        data-old-values="<?php echo htmlspecialchars($log['old_values']); ?>"
                                                        data-new-values="<?php echo htmlspecialchars($log['new_values']); ?>"
                                                        data-ip="<?php echo htmlspecialchars($log['ip_address']); ?>"
                                                        data-user-agent="<?php echo htmlspecialchars($log['user_agent']); ?>"
                                                        data-timestamp="<?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>">
                                                    <i class='bx bx-show'></i> View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-search-alt'></i>
                            <h5>No audit logs found</h5>
                            <p>Try adjusting your filters or perform actions in the system to generate logs.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Generate Report Modal -->
    <div class="modal fade" id="generateReportModal" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="generateReportModalLabel">Generate Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">Select Report Type</option>
                                <option value="user_activity">User Activity Report</option>
                                <option value="action_summary">Action Summary Report</option>
                                <option value="table_activity">Table Activity Report</option>
                                <option value="audit_logs">Complete Audit Log Report</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="report_format" class="form-label">Report Format</label>
                            <select class="form-select" id="report_format" name="report_format" required>
                                <option value="html">HTML (View in Browser)</option>
                                <option value="csv">CSV (Download)</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class='bx bx-info-circle'></i>
                            <span>HTML reports will be displayed on this page. CSV reports will be downloaded immediately.</span>
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
    
    <!-- View Log Details Modal -->
    <div class="modal fade" id="viewLogDetailsModal" tabindex="-1" aria-labelledby="viewLogDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLogDetailsModalLabel">Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Timestamp:</strong> <span id="detail-timestamp"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Action:</strong> <span id="detail-action"></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Table:</strong> <span id="detail-table"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Record ID:</strong> <span id="detail-record-id"></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>User:</strong> <span id="detail-user"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>IP Address:</strong> <span id="detail-ip"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>User Agent:</strong> <span id="detail-user-agent"></span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Old Values</h6>
                            <div class="json-display" id="detail-old-values"></div>
                        </div>
                        <div class="col-md-6">
                            <h6>New Values</h6>
                            <div class="json-display" id="detail-new-values"></div>
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
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Filter toggle
        document.getElementById('filterToggle').addEventListener('click', function() {
            const filterContent = document.getElementById('filterContent');
            const filterToggle = document.querySelector('.filter-toggle');
            
            filterContent.classList.toggle('d-none');
            filterToggle.classList.toggle('collapsed');
        });
        
        // Apply filters
        document.getElementById('applyFilters').addEventListener('click', function() {
            const filterAction = document.getElementById('filter_action').value;
            const filterTable = document.getElementById('filter_table').value;
            const filterUser = document.getElementById('filter_user').value;
            const filterDateFrom = document.getElementById('filter_date_from').value;
            const filterDateTo = document.getElementById('filter_date_to').value;
            const filterRecordId = document.getElementById('filter_record_id').value;
            
            const params = new URLSearchParams();
            
            if (filterAction) params.append('filter_action', filterAction);
            if (filterTable) params.append('filter_table', filterTable);
            if (filterUser) params.append('filter_user', filterUser);
            if (filterDateFrom) params.append('filter_date_from', filterDateFrom);
            if (filterDateTo) params.append('filter_date_to', filterDateTo);
            if (filterRecordId) params.append('filter_record_id', filterRecordId);
            
            window.location.href = 'ral.php?' + params.toString();
        });
        
        // View log details modal
        const viewLogDetailsModal = document.getElementById('viewLogDetailsModal');
        if (viewLogDetailsModal) {
            viewLogDetailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('detail-timestamp').textContent = button.getAttribute('data-timestamp');
                document.getElementById('detail-user').textContent = button.getAttribute('data-user');
                document.getElementById('detail-action').textContent = button.getAttribute('data-action');
                document.getElementById('detail-table').textContent = button.getAttribute('data-table');
                document.getElementById('detail-record-id').textContent = button.getAttribute('data-record-id') || 'N/A';
                document.getElementById('detail-ip').textContent = button.getAttribute('data-ip') || 'N/A';
                document.getElementById('detail-user-agent').textContent = button.getAttribute('data-user-agent') || 'N/A';
                
                // Format JSON data if available
                try {
                    const oldValues = button.getAttribute('data-old-values');
                    const newValues = button.getAttribute('data-new-values');
                    
                    document.getElementById('detail-old-values').textContent = oldValues ? 
                        JSON.stringify(JSON.parse(oldValues), null, 2) : 'No data';
                    document.getElementById('detail-new-values').textContent = newValues ? 
                        JSON.stringify(JSON.parse(newValues), null, 2) : 'No data';
                } catch (e) {
                    document.getElementById('detail-old-values').textContent = button.getAttribute('data-old-values') || 'No data';
                    document.getElementById('detail-new-values').textContent = button.getAttribute('data-new-values') || 'No data';
                }
            });
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>