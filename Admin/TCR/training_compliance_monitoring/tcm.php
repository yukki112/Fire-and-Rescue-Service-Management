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
$active_submodule = 'training_compliance_monitoring';

// Initialize filter variables
$filter_employee = $_GET['filter_employee'] ?? '';
$filter_department = $_GET['filter_department'] ?? '';
$filter_compliance_status = $_GET['filter_compliance_status'] ?? '';
$filter_course = $_GET['filter_course'] ?? '';

try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get all training courses
    $courses = $dbManager->fetchAll("tcr", "SELECT * FROM training_courses WHERE status = 'active' ORDER BY course_name");
    
    // Get unique departments from employees
    $departments = $dbManager->fetchAll("frsm", "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    
    // Build the base query for compliance data
    $query = "SELECT 
                e.id as employee_id,
                e.first_name,
                e.last_name,
                e.employee_id as emp_id,
                e.department,
                e.position,
                tc.id as course_id,
                tc.course_code,
                tc.course_name,
                tc.validity_months,
                c.certification_number,
                c.issue_date,
                c.expiry_date,
                c.status as cert_status,
                CASE 
                    WHEN c.status = 'expired' OR c.status = 'revoked' THEN 'non-compliant'
                    WHEN c.expiry_date IS NULL OR c.expiry_date = '0000-00-00' THEN 'compliant'
                    WHEN c.expiry_date >= CURDATE() THEN 'compliant'
                    WHEN c.expiry_date < CURDATE() THEN 'non-compliant'
                    ELSE 'no-certification'
                END as compliance_status,
                DATEDIFF(COALESCE(NULLIF(c.expiry_date, '0000-00-00'), c.expiry_date), CURDATE()) as days_until_expiry
            FROM frsm.employees e
            LEFT JOIN tcr.certifications c ON e.id = c.user_id
            LEFT JOIN tcr.training_courses tc ON c.course_id = tc.id
            WHERE e.is_active = 1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filter_employee)) {
        $query .= " AND e.id = ?";
        $params[] = $filter_employee;
    }
    
    if (!empty($filter_department)) {
        $query .= " AND e.department = ?";
        $params[] = $filter_department;
    }
    
    if (!empty($filter_course)) {
        $query .= " AND tc.id = ?";
        $params[] = $filter_course;
    }
    
    $query .= " ORDER BY e.department, e.last_name, e.first_name, tc.course_name";
    
    // Get compliance data
    $complianceData = $dbManager->fetchAll("tcr", $query, $params);
    
    // Apply compliance status filter in PHP to handle complex logic
    if (!empty($filter_compliance_status)) {
        $complianceData = array_filter($complianceData, function($item) use ($filter_compliance_status) {
            if ($filter_compliance_status === 'compliant') {
                return $item['compliance_status'] === 'compliant';
            } elseif ($filter_compliance_status === 'non-compliant') {
                return $item['compliance_status'] === 'non-compliant';
            } elseif ($filter_compliance_status === 'expiring-soon') {
                return $item['days_until_expiry'] !== null && 
                       $item['days_until_expiry'] > 0 && 
                       $item['days_until_expiry'] <= 90 &&
                       $item['compliance_status'] === 'compliant';
            } elseif ($filter_compliance_status === 'no-certification') {
                return $item['course_id'] === null;
            }
            return true;
        });
        
        // Reindex array after filtering
        $complianceData = array_values($complianceData);
    }
    
    // Group compliance data by employee for summary view
    $employeeComplianceSummary = [];
    foreach ($complianceData as $record) {
        $empId = $record['employee_id'];
        
        if (!isset($employeeComplianceSummary[$empId])) {
            $employeeComplianceSummary[$empId] = [
                'employee_id' => $empId,
                'first_name' => $record['first_name'],
                'last_name' => $record['last_name'],
                'emp_id' => $record['emp_id'],
                'department' => $record['department'],
                'position' => $record['position'],
                'total_courses' => 0,
                'compliant_courses' => 0,
                'non_compliant_courses' => 0,
                'expiring_soon' => 0,
                'compliance_rate' => 0,
                'overall_status' => 'compliant'
            ];
        }
        
        if ($record['course_id']) {
            $employeeComplianceSummary[$empId]['total_courses']++;
            
            if ($record['compliance_status'] === 'compliant') {
                $employeeComplianceSummary[$empId]['compliant_courses']++;
                
                // Check if expiring soon (within 90 days)
                if ($record['days_until_expiry'] !== null && 
                    $record['days_until_expiry'] > 0 && 
                    $record['days_until_expiry'] <= 90) {
                    $employeeComplianceSummary[$empId]['expiring_soon']++;
                }
            } else {
                $employeeComplianceSummary[$empId]['non_compliant_courses']++;
                $employeeComplianceSummary[$empId]['overall_status'] = 'non-compliant';
            }
        }
    }
    
    // Calculate compliance rate for each employee
    foreach ($employeeComplianceSummary as $empId => $summary) {
        if ($summary['total_courses'] > 0) {
            $employeeComplianceSummary[$empId]['compliance_rate'] = 
                round(($summary['compliant_courses'] / $summary['total_courses']) * 100, 2);
        }
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $courses = [];
    $departments = [];
    $complianceData = [];
    $employeeComplianceSummary = [];
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
        .compliance-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .compliance-rate {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .progress {
            height: 10px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .compliance-table {
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
        .summary-card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .non-compliant-row {
            background-color: #fff5f5;
        }
        .expiring-soon-row {
            background-color: #fff9db;
        }
        .no-certification-row {
            background-color: #f8f9fa;
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
            .summary-card {
                margin-bottom: 15px;
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
            .summary-card {
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
                      <a href="tcm.php" class="sidebar-dropdown-link active">
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
                    <h1>Training Compliance Monitoring</h1>
                    <p>Monitor and track employee training compliance status across all courses.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../certification_tracking/ct.php" class="btn btn-outline-primary">
                            <i class='bx bx-certification'></i> Certification Tracking
                        </a>
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
            
            <!-- Compliance Monitoring Content -->
            <div class="dashboard-content">
                <!-- Filter Section -->
                <div class="filter-section animate-fade-in">
                    <div class="filter-header" id="filterToggle">
                        <h5><i class='bx bx-filter-alt'></i> Filter Compliance Data</h5>
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
                            <label for="filter_department" class="form-label">Department</label>
                            <select class="form-select" id="filter_department" name="filter_department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $filter_department == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
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
                            <label for="filter_compliance_status" class="form-label">Compliance Status</label>
                            <select class="form-select" id="filter_compliance_status" name="filter_compliance_status">
                                <option value="">All Statuses</option>
                                <option value="compliant" <?php echo $filter_compliance_status == 'compliant' ? 'selected' : ''; ?>>Compliant</option>
                                <option value="non-compliant" <?php echo $filter_compliance_status == 'non-compliant' ? 'selected' : ''; ?>>Non-Compliant</option>
                                <option value="expiring-soon" <?php echo $filter_compliance_status == 'expiring-soon' ? 'selected' : ''; ?>>Expiring Soon (90 days)</option>
                                <option value="no-certification" <?php echo $filter_compliance_status == 'no-certification' ? 'selected' : ''; ?>>No Certification</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="applyFilters" class="btn btn-primary">
                            <i class='bx bx-check'></i> Apply Filters
                        </button>
                        <a href="tcm.php" class="btn btn-outline-secondary">
                            <i class='bx bx-reset'></i> Clear Filters
                        </a>
                    </div>
                </div>
                
                <!-- Compliance Summary Cards -->
                <div class="row mb-4 animate-fade-in">
                    <div class="col-md-3">
                        <div class="card summary-card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Total Employees</h6>
                                <h3 class="compliance-rate text-primary"><?php echo count($employeeComplianceSummary); ?></h3>
                                <p class="card-text">Active personnel in system</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card summary-card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Fully Compliant</h6>
                                <h3 class="compliance-rate text-success">
                                    <?php 
                                        $fullyCompliant = array_filter($employeeComplianceSummary, function($emp) {
                                            return $emp['overall_status'] === 'compliant' && $emp['total_courses'] > 0;
                                        });
                                        echo count($fullyCompliant);
                                    ?>
                                </h3>
                                <p class="card-text">100% compliant employees</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card summary-card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Non-Compliant</h6>
                                <h3 class="compliance-rate text-danger">
                                    <?php 
                                        $nonCompliant = array_filter($employeeComplianceSummary, function($emp) {
                                            return $emp['overall_status'] === 'non-compliant';
                                        });
                                        echo count($nonCompliant);
                                    ?>
                                </h3>
                                <p class="card-text">Employees with expired certifications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card summary-card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">No Certifications</h6>
                                <h3 class="compliance-rate text-warning">
                                    <?php 
                                        $noCerts = array_filter($employeeComplianceSummary, function($emp) {
                                            return $emp['total_courses'] === 0;
                                        });
                                        echo count($noCerts);
                                    ?>
                                </h3>
                                <p class="card-text">Employees without certifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employee Compliance Summary -->
                <div class="calendar-container animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Employee Compliance Summary</h5>
                        <div class="text-muted">
                            <?php echo count($employeeComplianceSummary); ?> employee(s) found
                        </div>
                    </div>
                    
                    <?php if (count($employeeComplianceSummary) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover compliance-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Total Courses</th>
                                        <th>Compliant</th>
                                        <th>Non-Compliant</th>
                                        <th>Expiring Soon</th>
                                        <th>Compliance Rate</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeComplianceSummary as $emp): 
                                        $rowClass = '';
                                        if ($emp['overall_status'] === 'non-compliant') {
                                            $rowClass = 'non-compliant-row';
                                        } elseif ($emp['total_courses'] === 0) {
                                            $rowClass = 'no-certification-row';
                                        }
                                    ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($emp['emp_id']); ?></td>
                                            <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                            <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                            <td><?php echo $emp['total_courses']; ?></td>
                                            <td class="text-success"><?php echo $emp['compliant_courses']; ?></td>
                                            <td class="text-danger"><?php echo $emp['non_compliant_courses']; ?></td>
                                            <td class="text-warning"><?php echo $emp['expiring_soon']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="width: 60px;">
                                                        <div class="progress-bar <?php echo $emp['compliance_rate'] >= 90 ? 'bg-success' : ($emp['compliance_rate'] >= 70 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $emp['compliance_rate']; ?>%;" 
                                                             aria-valuenow="<?php echo $emp['compliance_rate']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <span><?php echo $emp['compliance_rate']; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($emp['total_courses'] === 0): ?>
                                                    <span class="badge bg-secondary compliance-badge">No Certifications</span>
                                                <?php elseif ($emp['overall_status'] === 'compliant'): ?>
                                                    <span class="badge bg-success compliance-badge">Compliant</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger compliance-badge">Non-Compliant</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <h5>No employees found</h5>
                            <p>Try adjusting your filters to see results</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detailed Compliance Data -->
                <?php if (count($complianceData) > 0): ?>
                <div class="calendar-container mt-4 animate-fade-in">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Detailed Certification Data</h5>
                        <div class="text-muted">
                            <?php echo count($complianceData); ?> certification(s) found
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover compliance-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Course</th>
                                    <th>Certification #</th>
                                    <th>Issue Date</th>
                                    <th>Expiry Date</th>
                                    <th>Days Until Expiry</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complianceData as $record): 
                                    $rowClass = '';
                                    if ($record['compliance_status'] === 'non-compliant') {
                                        $rowClass = 'non-compliant-row';
                                    } elseif ($record['days_until_expiry'] !== null && $record['days_until_expiry'] > 0 && $record['days_until_expiry'] <= 90) {
                                        $rowClass = 'expiring-soon-row';
                                    } elseif ($record['course_id'] === null) {
                                        $rowClass = 'no-certification-row';
                                    }
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['department']); ?></td>
                                        <td>
                                            <?php if ($record['course_id']): ?>
                                                <?php echo htmlspecialchars($record['course_code'] . ' - ' . $record['course_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No certification</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $record['certification_number'] ? htmlspecialchars($record['certification_number']) : '<span class="text-muted">N/A</span>'; ?></td>
                                        <td><?php echo $record['issue_date'] && $record['issue_date'] != '0000-00-00' ? date('M d, Y', strtotime($record['issue_date'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                        <td>
                                            <?php if ($record['expiry_date'] && $record['expiry_date'] != '0000-00-00'): ?>
                                                <?php echo date('M d, Y', strtotime($record['expiry_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['days_until_expiry'] !== null): ?>
                                                <?php if ($record['days_until_expiry'] > 0): ?>
                                                    <span class="text-<?php echo $record['days_until_expiry'] <= 90 ? 'warning' : 'success'; ?>">
                                                        <?php echo $record['days_until_expiry']; ?> days
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-danger">
                                                        Expired <?php echo abs($record['days_until_expiry']); ?> days ago
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['course_id']): ?>
                                                <?php if ($record['compliance_status'] === 'compliant'): ?>
                                                    <?php if ($record['days_until_expiry'] !== null && $record['days_until_expiry'] > 0 && $record['days_until_expiry'] <= 90): ?>
                                                        <span class="badge bg-warning compliance-badge">Expiring Soon</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success compliance-badge">Compliant</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-danger compliance-badge">Non-Compliant</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary compliance-badge">No Certification</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuButton && sidebar) {
                mobileMenuButton.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Filter toggle functionality
            const filterToggle = document.getElementById('filterToggle');
            const filterContent = document.getElementById('filterContent');
            const filterToggleIcon = filterToggle.querySelector('.filter-toggle');
            
            if (filterToggle && filterContent) {
                filterToggle.addEventListener('click', function() {
                    filterContent.classList.toggle('show');
                    filterToggleIcon.classList.toggle('collapsed');
                });
            }
            
            // Apply filters button
            const applyFiltersBtn = document.getElementById('applyFilters');
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', function() {
                    const employeeFilter = document.getElementById('filter_employee').value;
                    const departmentFilter = document.getElementById('filter_department').value;
                    const courseFilter = document.getElementById('filter_course').value;
                    const statusFilter = document.getElementById('filter_compliance_status').value;
                    
                    let url = 'tcm.php?';
                    
                    if (employeeFilter) url += `filter_employee=${employeeFilter}&`;
                    if (departmentFilter) url += `filter_department=${encodeURIComponent(departmentFilter)}&`;
                    if (courseFilter) url += `filter_course=${courseFilter}&`;
                    if (statusFilter) url += `filter_compliance_status=${statusFilter}&`;
                    
                    // Remove trailing & or ? if no filters
                    if (url.endsWith('&') || url.endsWith('?')) {
                        url = url.slice(0, -1);
                    }
                    
                    window.location.href = url;
                });
            }
            
            // Auto-hide toasts after 5 seconds
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.classList.add('animate-slide-out');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>