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
$active_module = 'ficr';
$active_submodule = 'reporting_and_analytics';

// Initialize variables
$error_message = '';
$success_message = '';
$reports = [];
$analytics = [];

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_report'])) {
            $report_type = $_POST['report_type'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $format = $_POST['format'];
            
            // Generate report based on type
            switch ($report_type) {
                case 'clearance_summary':
                    $query = "SELECT 
                                c.type,
                                COUNT(*) as total,
                                SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN c.status = 'expired' THEN 1 ELSE 0 END) as expired,
                                SUM(CASE WHEN c.status = 'revoked' THEN 1 ELSE 0 END) as revoked,
                                SUM(CASE WHEN c.status = 'suspended' THEN 1 ELSE 0 END) as suspended
                              FROM ficr.clearances c
                              WHERE c.issue_date BETWEEN ? AND ?
                              GROUP BY c.type
                              ORDER BY c.type";
                    $reports = $dbManager->fetchAll("ficr", $query, [$start_date, $end_date]);
                    break;
                    
                case 'inspection_compliance':
                    $query = "SELECT 
                                e.name as establishment,
                                ir.inspection_date,
                                ir.overall_score,
                                ir.status,
                                CONCAT(u.first_name, ' ', u.last_name) as inspector
                              FROM ficr.inspection_results ir
                              LEFT JOIN ficr.establishments e ON ir.establishment_id = e.id
                              LEFT JOIN frsm.users u ON ir.inspector_id = u.id
                              WHERE ir.inspection_date BETWEEN ? AND ?
                              ORDER BY ir.inspection_date DESC";
                    $reports = $dbManager->fetchAll("ficr", $query, [$start_date, $end_date]);
                    break;
                    
                case 'violations_summary':
                    $query = "SELECT 
                                v.violation_code,
                                COUNT(*) as count,
                                v.severity,
                                e.type as establishment_type
                              FROM ficr.violations v
                              LEFT JOIN ficr.inspection_results ir ON v.inspection_id = ir.id
                              LEFT JOIN ficr.establishments e ON ir.establishment_id = e.id
                              WHERE v.created_at BETWEEN ? AND ?
                              GROUP BY v.violation_code, v.severity, e.type
                              ORDER BY count DESC";
                    $reports = $dbManager->fetchAll("ficr", $query, [$start_date, $end_date]);
                    break;
                    
                case 'establishment_compliance':
                    $query = "SELECT 
                                e.name,
                                e.type,
                                COUNT(ir.id) as total_inspections,
                                AVG(ir.overall_score) as avg_score,
                                SUM(CASE WHEN ir.status = 'compliant' THEN 1 ELSE 0 END) as compliant_count,
                                SUM(CASE WHEN ir.status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant_count
                              FROM ficr.establishments e
                              LEFT JOIN ficr.inspection_results ir ON e.id = ir.establishment_id
                              WHERE ir.inspection_date BETWEEN ? AND ?
                              GROUP BY e.id, e.name, e.type
                              HAVING total_inspections > 0
                              ORDER BY avg_score DESC";
                    $reports = $dbManager->fetchAll("ficr", $query, [$start_date, $end_date]);
                    break;
            }
            
            // Handle export if requested
            if ($format === 'csv' && !empty($reports)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Add headers
                if (!empty($reports)) {
                    fputcsv($output, array_keys($reports[0]));
                    
                    // Add data
                    foreach ($reports as $row) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit;
            }
            
            $success_message = "Report generated successfully!";
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $error_message = "An error occurred while generating the report. Please try again.";
    }
}

// Get analytics data
try {
    // Clearance status analytics
    $clearance_analytics = $dbManager->fetchAll("ficr", 
        "SELECT status, COUNT(*) as count 
         FROM clearances 
         GROUP BY status");
    
    // Clearance type analytics
    $type_analytics = $dbManager->fetchAll("ficr", 
        "SELECT type, COUNT(*) as count 
         FROM clearances 
         GROUP BY type");
    
    // Monthly clearance issuance
    $monthly_analytics = $dbManager->fetchAll("ficr", 
        "SELECT DATE_FORMAT(issue_date, '%Y-%m') as month, 
                COUNT(*) as count 
         FROM clearances 
         WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
         ORDER BY month");
    
    // Inspection compliance rate
    $compliance_analytics = $dbManager->fetchAll("ficr", 
        "SELECT status, COUNT(*) as count 
         FROM inspection_results 
         WHERE inspection_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY status");
    
    // Violations by severity
    $violation_analytics = $dbManager->fetchAll("ficr", 
        "SELECT severity, COUNT(*) as count 
         FROM violations 
         GROUP BY severity");
    
    // Prepare data for charts
    $analytics = [
        'clearance_status' => $clearance_analytics,
        'clearance_type' => $type_analytics,
        'monthly_issuance' => $monthly_analytics,
        'compliance_rate' => $compliance_analytics,
        'violation_severity' => $violation_analytics
    ];
    
} catch (Exception $e) {
    error_log("Analytics data error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
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
        
        /* Status colors */
        .bg-active { background: linear-gradient(45deg, #28a745, #20c997); }
        .bg-expired { background: linear-gradient(45deg, #dc3545, #fd7e14); }
        .bg-revoked { background: linear-gradient(45deg, #6c757d, #5a6268); }
        .bg-suspended { background: linear-gradient(45deg, #ffc107, #ffce3a); }
        .bg-total { background: linear-gradient(45deg, #0d6efd, #4dabf7); }
        
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
            .chart-container {
                height: 250px;
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
        
        /* Tabs styling */
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1rem;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
            background: transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #dee2e6;
        }
        
        /* Card hover effects */
        .dashboard-card:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#ficrMenu" role="button">
                    <i class='bx bx-clipboard'></i>
                    <span class="text">Inspection & Compliance</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="ficrMenu">
                    <a href="../establishment_registry/er.php" class="sidebar-dropdown-link">
                           <i class='bx bx-building-house'></i>
                        <span>Establishment/Property Registry</span>
                    </a>
                    <a href="../inspection_scheduling_and_assignment/isaa.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar-event'></i>
                        <span>Inspection Scheduling and Assignment</span>
                    </a>
                    <a href="../inspection_checklist_management/icm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-list-check'></i>
                        <span>Inspection Checklist Management</span>
                    </a>
                    <a href="../violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link">
                           <i class='bx bx-shield-x'></i>
                        <span>Violation and Compliance Tracking</span>
                    </a>
                    <a href="../clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link">
                          <i class='bx bx-file'></i>
                        <span>Clearance and Certification Management</span>
                    </a>
                     <a href="../reporting_and_analytics/raa.php" class="sidebar-dropdown-link active">
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
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Reporting and Analytics</h1>
                    <p>Generate reports and view analytics for fire inspection and compliance data</p>
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
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="analyticsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab" aria-controls="analytics" aria-selected="true">
                        <i class='bx bx-stats'></i> Analytics Dashboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab" aria-controls="reports" aria-selected="false">
                        <i class='bx bx-file'></i> Report Generator
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="analyticsTabsContent">
                <!-- Analytics Dashboard -->
                <div class="tab-pane fade show active" id="analytics" role="tabpanel" aria-labelledby="analytics-tab">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card bg-total">
                                <i class='bx bx-file'></i>
                                <div class="number">
                                    <?php 
                                    $total_clearances = 0;
                                    foreach ($analytics['clearance_status'] as $stat) {
                                        $total_clearances += $stat['count'];
                                    }
                                    echo $total_clearances;
                                    ?>
                                </div>
                                <div class="label">Total Clearances</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-active">
                                <i class='bx bx-check-circle'></i>
                                <div class="number">
                                    <?php 
                                    $active_count = 0;
                                    foreach ($analytics['clearance_status'] as $stat) {
                                        if ($stat['status'] === 'active') {
                                            $active_count = $stat['count'];
                                            break;
                                        }
                                    }
                                    echo $active_count;
                                    ?>
                                </div>
                                <div class="label">Active Clearances</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-expired">
                                <i class='bx bx-time'></i>
                                <div class="number">
                                    <?php 
                                    $expired_count = 0;
                                    foreach ($analytics['clearance_status'] as $stat) {
                                        if ($stat['status'] === 'expired') {
                                            $expired_count = $stat['count'];
                                            break;
                                        }
                                    }
                                    echo $expired_count;
                                    ?>
                                </div>
                                <div class="label">Expired Clearances</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-revoked">
                                <i class='bx bx-x-circle'></i>
                                <div class="number">
                                    <?php 
                                    $revoked_count = 0;
                                    foreach ($analytics['clearance_status'] as $stat) {
                                        if ($stat['status'] === 'revoked') {
                                            $revoked_count = $stat['count'];
                                            break;
                                        }
                                    }
                                    echo $revoked_count;
                                    ?>
                                </div>
                                <div class="label">Revoked Clearances</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Clearance Status Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="clearanceStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Clearance Types</h5>
                                <div class="chart-container">
                                    <canvas id="clearanceTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Monthly Clearance Issuance (Last 12 Months)</h5>
                                <div class="chart-container">
                                    <canvas id="monthlyIssuanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Inspection Compliance Rate</h5>
                                <div class="chart-container">
                                    <canvas id="complianceRateChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Violations by Severity</h5>
                                <div class="chart-container">
                                    <canvas id="violationSeverityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Generator -->
                <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
                    <div class="filter-section">
                        <h5 class="mb-3"><i class='bx bx-filter-alt'></i> Generate Report</h5>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="report_type" class="form-label">Report Type</label>
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <option value="">Select Report Type</option>
                                        <option value="clearance_summary">Clearance Summary</option>
                                        <option value="inspection_compliance">Inspection Compliance</option>
                                        <option value="violations_summary">Violations Summary</option>
                                        <option value="establishment_compliance">Establishment Compliance</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="format" class="form-label">Format</label>
                                    <select class="form-select" id="format" name="format">
                                        <option value="html" selected>HTML</option>
                                        <option value="csv">CSV Export</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="generate_report" class="btn btn-primary">
                                    <i class='bx bx-file'></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (!empty($reports)): ?>
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Report Results</h5>
                                <span class="badge bg-primary">
                                    <?php echo count($reports); ?> records found
                                </span>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover report-table">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($reports[0]) as $column): ?>
                                                <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $row): ?>
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
                    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <div class="dashboard-card">
                            <div class="empty-state">
                                <i class='bx bx-search-alt'></i>
                                <h5>No Data Found</h5>
                                <p>No records match your search criteria. Try adjusting your filters.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-card">
                            <div class="empty-state">
                                <i class='bx bx-file'></i>
                                <h5>No Report Generated</h5>
                                <p>Select a report type and date range to generate a report.</p>
                            </div>
                        </div>
                    <?php endif; ?>
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
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Clearance Status Chart
            const statusCtx = document.getElementById('clearanceStatusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php foreach ($analytics['clearance_status'] as $stat): ?>
                            '<?php echo ucfirst($stat['status']); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($analytics['clearance_status'] as $stat): ?>
                                <?php echo $stat['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#28a745', // Active - green
                            '#dc3545', // Expired - red
                            '#6c757d', // Revoked - gray
                            '#ffc107', // Suspended - yellow
                            '#0d6efd'  // Other - blue
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Clearance Type Chart
            const typeCtx = document.getElementById('clearanceTypeChart').getContext('2d');
            const typeChart = new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php foreach ($analytics['clearance_type'] as $type): ?>
                            '<?php echo ucfirst($type['type']); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($analytics['clearance_type'] as $type): ?>
                                <?php echo $type['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#0d6efd', '#6610f2', '#6f42c1', '#d63384', 
                            '#dc3545', '#fd7e14', '#ffc107', '#20c997', 
                            '#198754', '#0dcaf0'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Monthly Issuance Chart
            const monthlyCtx = document.getElementById('monthlyIssuanceChart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php foreach ($analytics['monthly_issuance'] as $month): ?>
                            '<?php echo $month['month']; ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Clearances Issued',
                        data: [
                            <?php foreach ($analytics['monthly_issuance'] as $month): ?>
                                <?php echo $month['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Compliance Rate Chart
            const complianceCtx = document.getElementById('complianceRateChart').getContext('2d');
            const complianceChart = new Chart(complianceCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach ($analytics['compliance_rate'] as $comp): ?>
                            '<?php echo ucfirst(str_replace('_', ' ', $comp['status'])); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Number of Inspections',
                        data: [
                            <?php foreach ($analytics['compliance_rate'] as $comp): ?>
                                <?php echo $comp['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#198754', // Compliant - green
                            '#dc3545', // Non-compliant - red
                            '#6c757d'  // Pending - gray
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Violation Severity Chart
            const violationCtx = document.getElementById('violationSeverityChart').getContext('2d');
            const violationChart = new Chart(violationCtx, {
                type: 'polarArea',
                data: {
                    labels: [
                        <?php foreach ($analytics['violation_severity'] as $violation): ?>
                            '<?php echo ucfirst($violation['severity']); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($analytics['violation_severity'] as $violation): ?>
                                <?php echo $violation['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#dc3545', // Critical - red
                            '#fd7e14', // High - orange
                            '#ffc107', // Medium - yellow
                            '#20c997'  // Low - teal
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>