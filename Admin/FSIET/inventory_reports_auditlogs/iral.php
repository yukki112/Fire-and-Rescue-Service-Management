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
$active_submodule = 'inventory_reports_auditlogs';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get inventory reports data
    $inventory_items = $dbManager->fetchAll("fsiet", "SELECT * FROM inventory_items ORDER BY name");
    $inventory_categories = $dbManager->fetchAll("fsiet", "SELECT * FROM inventory_categories ORDER BY name");
    
    // Get inventory transactions
    $inventory_transactions = [];
    try {
        $inventory_transactions = $dbManager->fetchAll("fsiet", "
            SELECT it.*, ii.name as item_name, CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
            FROM inventory_transactions it 
            LEFT JOIN inventory_items ii ON it.item_id = ii.id 
            LEFT JOIN frsm.users u ON it.performed_by = u.id
            ORDER BY it.created_at DESC
        ");
    } catch (Exception $e) {
        error_log("Inventory transactions query error: " . $e->getMessage());
    }
    
    // Get audit logs
    $audit_logs = [];
    try {
        $audit_logs = $dbManager->fetchAll("frsm", "
            SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
        ");
    } catch (Exception $e) {
        error_log("Audit logs query error: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $inventory_items = [];
    $inventory_categories = [];
    $inventory_transactions = [];
    $audit_logs = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate inventory report
        if (isset($_POST['generate_inventory_report'])) {
            $report_type = $_POST['report_type'];
            $category_id = $_POST['category_id'] ?? null;
            $status_filter = $_POST['status_filter'] ?? null;
            
            // Build query based on filters
            $query = "SELECT ii.*, ic.name as category_name 
                     FROM inventory_items ii 
                     LEFT JOIN inventory_categories ic ON ii.category_id = ic.id 
                     WHERE 1=1";
            $params = [];
            
            if ($category_id && $category_id != 'all') {
                $query .= " AND ii.category_id = ?";
                $params[] = $category_id;
            }
            
            if ($status_filter && $status_filter != 'all') {
                $query .= " AND ii.status = ?";
                $params[] = $status_filter;
            }
            
            $query .= " ORDER BY ii.name";
            
            $report_data = $dbManager->fetchAll("fsiet", $query, $params);
            
            // Store report data in session for display
            $_SESSION['inventory_report_data'] = $report_data;
            $_SESSION['inventory_report_filters'] = [
                'report_type' => $report_type,
                'category_id' => $category_id,
                'status_filter' => $status_filter
            ];
            
            $_SESSION['success_message'] = "Inventory report generated successfully!";
            header("Location: iral.php");
            exit;
        }
        
        // Generate audit log report
        if (isset($_POST['generate_audit_report'])) {
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $action_filter = $_POST['action_filter'] ?? null;
            
            // Build query based on filters
            $query = "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email
                     FROM audit_logs al 
                     LEFT JOIN users u ON al.user_id = u.id
                     WHERE 1=1";
            $params = [];
            
            if ($start_date) {
                $query .= " AND DATE(al.created_at) >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $query .= " AND DATE(al.created_at) <= ?";
                $params[] = $end_date;
            }
            
            if ($action_filter && $action_filter != 'all') {
                $query .= " AND al.action = ?";
                $params[] = $action_filter;
            }
            
            $query .= " ORDER BY al.created_at DESC";
            
            $report_data = $dbManager->fetchAll("frsm", $query, $params);
            
            // Store report data in session for display
            $_SESSION['audit_report_data'] = $report_data;
            $_SESSION['audit_report_filters'] = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'action_filter' => $action_filter
            ];
            
            $_SESSION['success_message'] = "Audit report generated successfully!";
            header("Location: iral.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
    }
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get report data from session if available
$inventory_report_data = $_SESSION['inventory_report_data'] ?? [];
$inventory_report_filters = $_SESSION['inventory_report_filters'] ?? [];
$audit_report_data = $_SESSION['audit_report_data'] ?? [];
$audit_report_filters = $_SESSION['audit_report_filters'] ?? [];

// Clear session data after displaying
unset($_SESSION['inventory_report_data']);
unset($_SESSION['inventory_report_filters']);
unset($_SESSION['audit_report_data']);
unset($_SESSION['audit_report_filters']);
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
        .status-in_stock {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-low_stock {
            background-color: #fff3cd;
            color: #664d03;
        }
        .status-out_of_stock {
            background-color: #f8d7da;
            color: #842029;
        }
        .report-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .report-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
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
                    <a href="../maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link active">
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
                    <h1>Inventory Reports & Audit Logs</h1>
                    <p>Generate inventory reports and view system audit logs.</p>
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
            
            <!-- Reports & Audit Logs Content -->
            <div class="dashboard-content">
                <ul class="nav nav-tabs mb-4" id="reportsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="inventory-reports-tab" data-bs-toggle="tab" data-bs-target="#inventory-reports" type="button" role="tab" aria-controls="inventory-reports" aria-selected="true">
                            <i class='bx bx-package'></i> Inventory Reports
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="audit-logs-tab" data-bs-toggle="tab" data-bs-target="#audit-logs" type="button" role="tab" aria-controls="audit-logs" aria-selected="false">
                            <i class='bx bx-history'></i> Audit Logs
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="reportsTabsContent">
                    <!-- Inventory Reports Tab -->
                    <div class="tab-pane fade show active" id="inventory-reports" role="tabpanel" aria-labelledby="inventory-reports-tab">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-report'></i>
                                                Generate Inventory Report
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Report Type</label>
                                                <select class="form-select" name="report_type" required>
                                                    <option value="inventory_summary">Inventory Summary</option>
                                                    <option value="stock_levels">Stock Levels Report</option>
                                                    <option value="category_breakdown">Category Breakdown</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Category</label>
                                                <select class="form-select" name="category_id">
                                                    <option value="all">All Categories</option>
                                                    <?php foreach ($inventory_categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status_filter">
                                                    <option value="all">All Statuses</option>
                                                    <option value="in_stock">In Stock</option>
                                                    <option value="low_stock">Low Stock</option>
                                                    <option value="out_of_stock">Out of Stock</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="generate_inventory_report" class="btn btn-primary w-100">
                                                <i class='bx bx-download'></i> Generate Report
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <?php if (!empty($inventory_report_data)): ?>
                                <div class="card animate-fade-in">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Inventory Report</h5>
                                        <div class="report-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                                <i class='bx bx-printer'></i> Print
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="exportToCSV('inventory-report')">
                                                <i class='bx bx-download'></i> Export CSV
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="report-summary">
                                            <h6>Report Summary</h6>
                                            <p>
                                                Generated on: <?php echo date('F j, Y, g:i a'); ?><br>
                                                Report Type: <?php echo ucfirst(str_replace('_', ' ', $inventory_report_filters['report_type'])); ?><br>
                                                <?php if ($inventory_report_filters['category_id'] != 'all'): ?>
                                                Category: <?php 
                                                    $category_name = 'All';
                                                    foreach ($inventory_categories as $cat) {
                                                        if ($cat['id'] == $inventory_report_filters['category_id']) {
                                                            $category_name = $cat['name'];
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($category_name);
                                                ?><br>
                                                <?php endif; ?>
                                                <?php if ($inventory_report_filters['status_filter'] != 'all'): ?>
                                                Status: <?php echo ucfirst(str_replace('_', ' ', $inventory_report_filters['status_filter'])); ?><br>
                                                <?php endif; ?>
                                                Total Items: <?php echo count($inventory_report_data); ?>
                                            </p>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="inventory-report">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Item Name</th>
                                                        <th>Category</th>
                                                        <th>Quantity</th>
                                                        <th>Min Stock</th>
                                                        <th>Status</th>
                                                        <th>Location</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($inventory_report_data as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                                        <td><?php echo $item['min_stock_level']; ?></td>
                                                        <td>
                                                            <span class="badge status-badge status-<?php echo $item['status']; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($item['storage_location'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card animate-fade-in">
                                    <div class="card-body text-center py-5">
                                        <i class='bx bx-report bx-md text-muted mb-3'></i>
                                        <h5>No Report Generated</h5>
                                        <p class="text-muted">Use the form to generate an inventory report.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Audit Logs Tab -->
                    <div class="tab-pane fade" id="audit-logs" role="tabpanel" aria-labelledby="audit-logs-tab">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-history'></i>
                                                Generate Audit Report
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" class="form-control" name="start_date">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">End Date</label>
                                                <input type="date" class="form-control" name="end_date">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Action Type</label>
                                                <select class="form-select" name="action_filter">
                                                    <option value="all">All Actions</option>
                                                    <option value="create">Create</option>
                                                    <option value="update">Update</option>
                                                    <option value="delete">Delete</option>
                                                    <option value="login">Login</option>
                                                    <option value="logout">Logout</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="generate_audit_report" class="btn btn-primary w-100">
                                                <i class='bx bx-download'></i> Generate Report
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <?php if (!empty($audit_report_data)): ?>
                                <div class="card animate-fade-in">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Audit Log Report</h5>
                                        <div class="report-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                                <i class='bx bx-printer'></i> Print
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="exportToCSV('audit-report')">
                                                <i class='bx bx-download'></i> Export CSV
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="report-summary">
                                            <h6>Report Summary</h6>
                                            <p>
                                                Generated on: <?php echo date('F j, Y, g:i a'); ?><br>
                                                <?php if ($audit_report_filters['start_date']): ?>
                                                Start Date: <?php echo date('M j, Y', strtotime($audit_report_filters['start_date'])); ?><br>
                                                <?php endif; ?>
                                                <?php if ($audit_report_filters['end_date']): ?>
                                                End Date: <?php echo date('M j, Y', strtotime($audit_report_filters['end_date'])); ?><br>
                                                <?php endif; ?>
                                                <?php if ($audit_report_filters['action_filter'] != 'all'): ?>
                                                Action: <?php echo ucfirst($audit_report_filters['action_filter']); ?><br>
                                                <?php endif; ?>
                                                Total Records: <?php echo count($audit_report_data); ?>
                                            </p>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="audit-report">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Timestamp</th>
                                                        <th>User</th>
                                                        <th>Action</th>
                                                        <th>Table</th>
                                                        <th>Record ID</th>
                                                        <th>IP Address</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($audit_report_data as $log): ?>
                                                    <tr>
                                                        <td><?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                switch(strtolower($log['action'])) {
                                                                    case 'create': echo 'success'; break;
                                                                    case 'update': echo 'warning'; break;
                                                                    case 'delete': echo 'danger'; break;
                                                                    case 'login': echo 'info'; break;
                                                                    case 'logout': echo 'secondary'; break;
                                                                    default: echo 'primary';
                                                                }
                                                            ?>">
                                                                <?php echo htmlspecialchars($log['action']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                                        <td><?php echo $log['record_id'] ?? 'N/A'; ?></td>
                                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card animate-fade-in">
                                    <div class="card-body">
                                        <h5 class="card-title">Audit Logs</h5>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Timestamp</th>
                                                        <th>User</th>
                                                        <th>Action</th>
                                                        <th>Table</th>
                                                        <th>Record ID</th>
                                                        <th>IP Address</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($audit_logs) > 0): ?>
                                                        <?php foreach ($audit_logs as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php 
                                                                    switch(strtolower($log['action'])) {
                                                                        case 'create': echo 'success'; break;
                                                                        case 'update': echo 'warning'; break;
                                                                        case 'delete': echo 'danger'; break;
                                                                        case 'login': echo 'info'; break;
                                                                        case 'logout': echo 'secondary'; break;
                                                                        default: echo 'primary';
                                                                    }
                                                                ?>">
                                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                                            <td><?php echo $log['record_id'] ?? 'N/A'; ?></td>
                                                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No audit logs found</p>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
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
        // Function to export table data to CSV
        function exportToCSV(tableId) {
            const table = document.getElementById(tableId);
            let csv = [];
            
            // Get headers
            const headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                headers.push(table.rows[0].cells[i].innerText);
            }
            csv.push(headers.join(','));
            
            // Get rows
            for (let i = 1; i < table.rows.length; i++) {
                const row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    row.push('"' + table.rows[i].cells[j].innerText.replace(/"/g, '""') + '"');
                }
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvString = csv.join('\n');
            const filename = tableId + '_' + new Date().toISOString().slice(0, 10) + '.csv';
            
            const link = document.createElement('a');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-hide toast notifications after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>