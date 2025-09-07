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
$active_submodule = 'violation_and_compliance_tracking';

// Initialize variables
$error_message = '';
$success_message = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }
        
        if (isset($_POST['add_violation'])) {
            // Validate and sanitize inputs
            $inspection_id = filter_input(INPUT_POST, 'inspection_id', FILTER_VALIDATE_INT);
            $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
            $violation_code = filter_input(INPUT_POST, 'violation_code', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $severity = filter_input(INPUT_POST, 'severity', FILTER_SANITIZE_STRING);
            $corrective_action = filter_input(INPUT_POST, 'corrective_action', FILTER_SANITIZE_STRING);
            $deadline = filter_input(INPUT_POST, 'deadline', FILTER_SANITIZE_STRING);
            $fine_amount = filter_input(INPUT_POST, 'fine_amount', FILTER_VALIDATE_FLOAT);
            
            // Validate required fields
            if (!$inspection_id || !$item_id || empty($violation_code) || empty($description) || 
                empty($severity) || empty($corrective_action) || empty($deadline)) {
                throw new Exception("All required fields must be filled");
            }
            
            // Validate severity
            if (!in_array($severity, ['minor', 'major', 'critical'])) {
                throw new Exception("Invalid severity value");
            }
            
            // Validate date format
            if (!strtotime($deadline) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                throw new Exception("Invalid deadline format");
            }
            
            // Ensure fine_amount is not negative
            if ($fine_amount !== false && $fine_amount < 0) {
                throw new Exception("Fine amount cannot be negative");
            }
            
            $fine_amount = $fine_amount ?: 0;
            
            $query = "INSERT INTO violations 
                     (inspection_id, item_id, violation_code, description, severity, 
                     corrective_action, deadline, fine_amount, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')";
            
            $params = [$inspection_id, $item_id, $violation_code, $description, 
                      $severity, $corrective_action, $deadline, $fine_amount];
            
            $dbManager->query("ficr", $query, $params);
            $violation_id = $dbManager->getConnection("ficr")->lastInsertId();
            
            $success_message = "Violation recorded successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'create', 'violations', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], 
                $violation_id,
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_violation'])) {
            // Validate and sanitize inputs
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $violation_code = filter_input(INPUT_POST, 'violation_code', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $severity = filter_input(INPUT_POST, 'severity', FILTER_SANITIZE_STRING);
            $corrective_action = filter_input(INPUT_POST, 'corrective_action', FILTER_SANITIZE_STRING);
            $deadline = filter_input(INPUT_POST, 'deadline', FILTER_SANITIZE_STRING);
            $fine_amount = filter_input(INPUT_POST, 'fine_amount', FILTER_VALIDATE_FLOAT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            $paid_amount = filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT);
            $payment_date = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_STRING);
            
            // Validate required fields
            if (!$id || empty($violation_code) || empty($description) || 
                empty($severity) || empty($corrective_action) || empty($deadline) || empty($status)) {
                throw new Exception("All required fields must be filled");
            }
            
            // Validate severity
            if (!in_array($severity, ['minor', 'major', 'critical'])) {
                throw new Exception("Invalid severity value");
            }
            
            // Validate status
            if (!in_array($status, ['open', 'in_progress', 'resolved', 'overdue'])) {
                throw new Exception("Invalid status value");
            }
            
            // Validate date format
            if (!strtotime($deadline) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                throw new Exception("Invalid deadline format");
            }
            
            // Validate payment date if provided
            if (!empty($payment_date) && (!strtotime($payment_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date))) {
                throw new Exception("Invalid payment date format");
            }
            
            // Ensure amounts are not negative
            if ($fine_amount !== false && $fine_amount < 0) {
                throw new Exception("Fine amount cannot be negative");
            }
            
            if ($paid_amount !== false && $paid_amount < 0) {
                throw new Exception("Paid amount cannot be negative");
            }
            
            $fine_amount = $fine_amount ?: 0;
            $paid_amount = $paid_amount ?: 0;
            $payment_date = $payment_date ?: null;
            
            $query = "UPDATE violations SET 
                     violation_code = ?, description = ?, severity = ?, 
                     corrective_action = ?, deadline = ?, fine_amount = ?,
                     status = ?, paid_amount = ?, payment_date = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [$violation_code, $description, $severity, $corrective_action, 
                      $deadline, $fine_amount, $status, $paid_amount, $payment_date, $id];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Violation updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'violations', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['resolve_violation'])) {
            // Validate and sanitize inputs
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $resolution_notes = filter_input(INPUT_POST, 'resolution_notes', FILTER_SANITIZE_STRING);
            
            // Validate required fields
            if (!$id || empty($resolution_notes)) {
                throw new Exception("Resolution notes are required");
            }
            
            $query = "UPDATE violations SET 
                     status = 'resolved', resolution_notes = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $dbManager->query("ficr", $query, [$resolution_notes, $id]);
            
            $success_message = "Violation resolved successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'resolve', 'violations', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Get filter parameters with sanitization
$filter_status = isset($_GET['filter_status']) ? filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_STRING) : '';
$filter_severity = isset($_GET['filter_severity']) ? filter_input(INPUT_GET, 'filter_severity', FILTER_SANITIZE_STRING) : '';
$filter_establishment = isset($_GET['filter_establishment']) ? filter_input(INPUT_GET, 'filter_establishment', FILTER_VALIDATE_INT) : '';
$search = isset($_GET['search']) ? filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) : '';

try {
    // Get current user
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Build query for violations with filters
    $query = "SELECT v.*, 
                     e.name as establishment_name,
                     e.address as establishment_address,
                     ir.inspection_date,
                     ir.overall_rating,
                     ci.item_text,
                     CONCAT(u.first_name, ' ', u.last_name) as inspector_name
              FROM ficr.violations v
              LEFT JOIN ficr.inspection_results ir ON v.inspection_id = ir.id
              LEFT JOIN ficr.establishments e ON ir.establishment_id = e.id
              LEFT JOIN ficr.checklist_items ci ON v.item_id = ci.id
              LEFT JOIN frsm.users u ON ir.inspector_id = u.id
              WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (v.violation_code LIKE ? OR v.description LIKE ? OR e.name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND v.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_severity)) {
        $query .= " AND v.severity = ?";
        $params[] = $filter_severity;
    }
    
    if (!empty($filter_establishment)) {
        $query .= " AND e.id = ?";
        $params[] = $filter_establishment;
    }
    
    $query .= " ORDER BY v.deadline ASC, v.severity DESC";
    
    // Get violations data
    $violations = $dbManager->fetchAll("ficr", $query, $params);
    
    // Get establishments for dropdown
    $establishments = $dbManager->fetchAll("ficr", 
        "SELECT id, name FROM establishments WHERE status = 'active' ORDER BY name");
    
    // Get inspections for dropdown
    $inspections = $dbManager->fetchAll("ficr", 
        "SELECT ir.id, e.name as establishment_name, ir.inspection_date 
         FROM inspection_results ir 
         LEFT JOIN establishments e ON ir.establishment_id = e.id 
         ORDER BY ir.inspection_date DESC");
    
    // Get checklist items for dropdown
    $checklist_items = $dbManager->fetchAll("ficr", 
        "SELECT ci.id, ci.item_text, c.name as checklist_name 
         FROM checklist_items ci 
         LEFT JOIN inspection_checklists c ON ci.checklist_id = c.id 
         ORDER BY c.name, ci.order_index");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $violations = [];
    $establishments = [];
    $inspections = [];
    $checklist_items = [];
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .violation-badge {
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
        .violations-table {
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
        
        /* Status badges */
        .badge-open {
            background-color: #dc3545;
            color: white;
        }
        .badge-in_progress {
            background-color: #fd7e14;
            color: white;
        }
        .badge-resolved {
            background-color: #28a745;
            color: white;
        }
        .badge-overdue {
            background-color: #6f42c1;
            color: white;
        }
        
        /* Severity badges */
        .badge-minor {
            background-color: #17a2b8;
            color: white;
        }
        .badge-major {
            background-color: #ffc107;
            color: black;
        }
        .badge-critical {
            background-color: #dc3545;
            color: white;
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
        
        /* Timeline for violation history */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin: 20px 0;
        }
        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #0d6efd;
        }
        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .timeline-content {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 3px solid #0d6efd;
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
        
        /* Payment status */
        .payment-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .payment-full {
            background-color: #28a745;
            color: white;
        }
        .payment-partial {
            background-color: #ffc107;
            color: black;
        }
        .payment-none {
            background-color: #dc3545;
            color: white;
        }
        
        /* Deadline indicator */
        .deadline-near {
            color: #dc3545;
            font-weight: 600;
        }
        .deadline-future {
            color: #28a745;
        }
        .deadline-passed {
            color: #6c757d;
            text-decoration: line-through;
        }
        
        /* Validation error styles */
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback {
            display: block;
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
                    <a href="IRD/dashboard/index.php" class="sidebar-dropdown-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
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
                    <a href="../violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link active">
                           <i class='bx bx-shield-x'></i>
                        <span>Violation and Compliance Tracking</span>
                    </a>
                    <a href="../clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link">
                          <i class='bx bx-file'></i>
                        <span>Clearance and Certification Management</span>
                    </a>
                     <a href="../reporting_and_analytics/raa.php" class="sidebar-dropdown-link">
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
                    <a href="../incident_analysis/analysis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-line-chart'></i>
                        <span>Incident Analysis</span>
                    </a>
                    <a href="../lessons_learned/lessons.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-open'></i>
                        <span>Lessons Learned</span>
                    </a>
                    <a href="../recommendation_tracking/recommendation.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-square'></i>
                        <span>Recommendation Tracking</span>
                    </a>
                    <a href="../report_generation/report.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Report Generation</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></div>
                </div>
                <a href="../../logout.php" class="sidebar-link logout">
                    <i class='bx bx-log-out'></i>
                    <span class="text">Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h1>Violation and Compliance Tracking</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addViolationModal">
                        <i class='bx bx-plus'></i> Add New Violation
                    </button>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class='bx bx-check-circle me-2'></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle me-2'></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                    <h5><i class='bx bx-filter-alt'></i> Filter Violations</h5>
                    <i class='bx bx-chevron-up filter-toggle'></i>
                </div>
                
                <div class="collapse show" id="filterCollapse">
                    <form method="GET" id="filterForm">
                        <div class="filter-content">
                            <div>
                                <label class="form-label">Status</label>
                                <select class="form-select" name="filter_status">
                                    <option value="">All Statuses</option>
                                    <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Severity</label>
                                <select class="form-select" name="filter_severity">
                                    <option value="">All Severities</option>
                                    <option value="minor" <?php echo $filter_severity === 'minor' ? 'selected' : ''; ?>>Minor</option>
                                    <option value="major" <?php echo $filter_severity === 'major' ? 'selected' : ''; ?>>Major</option>
                                    <option value="critical" <?php echo $filter_severity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Establishment</label>
                                <select class="form-select" name="filter_establishment">
                                    <option value="">All Establishments</option>
                                    <?php foreach ($establishments as $est): ?>
                                    <option value="<?php echo htmlspecialchars($est['id']); ?>" 
                                        <?php echo $filter_establishment == $est['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Search violations..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-filter'></i> Apply Filters
                            </button>
                            <a href="vact.php" class="btn btn-outline-secondary">
                                <i class='bx bx-reset'></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Violations Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover violations-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Establishment</th>
                                    <th>Violation Description</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                    <th>Fine Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($violations) > 0): ?>
                                <?php foreach ($violations as $violation): 
                                    $deadline_class = '';
                                    $deadline_date = new DateTime($violation['deadline']);
                                    $today = new DateTime();
                                    
                                    if ($deadline_date < $today && $violation['status'] !== 'resolved') {
                                        $deadline_class = 'deadline-passed';
                                    } elseif ($deadline_date->diff($today)->days <= 7 && $violation['status'] !== 'resolved') {
                                        $deadline_class = 'deadline-near';
                                    } else {
                                        $deadline_class = 'deadline-future';
                                    }
                                    
                                    // Payment status
                                    $payment_status = '';
                                    if ($violation['paid_amount'] >= $violation['fine_amount']) {
                                        $payment_status = 'payment-full';
                                        $payment_text = 'Paid';
                                    } elseif ($violation['paid_amount'] > 0) {
                                        $payment_status = 'payment-partial';
                                        $payment_text = 'Partial';
                                    } else {
                                        $payment_status = 'payment-none';
                                        $payment_text = 'Unpaid';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($violation['violation_code']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($violation['inspection_date'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($violation['establishment_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($violation['establishment_address']); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($violation['item_text']); ?></div>
                                        <small><?php echo htmlspecialchars($violation['description']); ?></small>
                                    </td>
                                    <td>
                                        <span class="violation-badge badge-<?php echo htmlspecialchars($violation['severity']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($violation['severity'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="violation-badge badge-<?php echo htmlspecialchars($violation['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $violation['status']))); ?>
                                        </span>
                                    </td>
                                    <td class="<?php echo $deadline_class; ?>">
                                        <?php echo date('M d, Y', strtotime($violation['deadline'])); ?>
                                    </td>
                                    <td>
                                        <div>₱<?php echo number_format($violation['fine_amount'], 2); ?></div>
                                        <span class="payment-status <?php echo $payment_status; ?>">
                                            <?php echo $payment_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewViolationModal" 
                                                    data-id="<?php echo htmlspecialchars($violation['id']); ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-description="<?php echo htmlspecialchars($violation['description']); ?>"
                                                    data-severity="<?php echo htmlspecialchars($violation['severity']); ?>"
                                                    data-status="<?php echo htmlspecialchars($violation['status']); ?>"
                                                    data-corrective-action="<?php echo htmlspecialchars($violation['corrective_action']); ?>"
                                                    data-deadline="<?php echo htmlspecialchars($violation['deadline']); ?>"
                                                    data-fine-amount="<?php echo htmlspecialchars($violation['fine_amount']); ?>"
                                                    data-paid-amount="<?php echo htmlspecialchars($violation['paid_amount']); ?>"
                                                    data-payment-date="<?php echo htmlspecialchars($violation['payment_date']); ?>"
                                                    data-resolution-notes="<?php echo htmlspecialchars($violation['resolution_notes']); ?>"
                                                    data-establishment-name="<?php echo htmlspecialchars($violation['establishment_name']); ?>"
                                                    data-establishment-address="<?php echo htmlspecialchars($violation['establishment_address']); ?>"
                                                    data-inspection-date="<?php echo htmlspecialchars($violation['inspection_date']); ?>"
                                                    data-inspector-name="<?php echo htmlspecialchars($violation['inspector_name']); ?>"
                                                    data-item-text="<?php echo htmlspecialchars($violation['item_text']); ?>">
                                                <i class='bx bx-show'></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editViolationModal"
                                                    data-id="<?php echo htmlspecialchars($violation['id']); ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-description="<?php echo htmlspecialchars($violation['description']); ?>"
                                                    data-severity="<?php echo htmlspecialchars($violation['severity']); ?>"
                                                    data-status="<?php echo htmlspecialchars($violation['status']); ?>"
                                                    data-corrective-action="<?php echo htmlspecialchars($violation['corrective_action']); ?>"
                                                    data-deadline="<?php echo htmlspecialchars($violation['deadline']); ?>"
                                                    data-fine-amount="<?php echo htmlspecialchars($violation['fine_amount']); ?>"
                                                    data-paid-amount="<?php echo htmlspecialchars($violation['paid_amount']); ?>"
                                                    data-payment-date="<?php echo htmlspecialchars($violation['payment_date']); ?>">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <?php if ($violation['status'] !== 'resolved'): ?>
                                            <button class="btn btn-sm btn-outline-success btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#resolveViolationModal"
                                                    data-id="<?php echo htmlspecialchars($violation['id']); ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-establishment-name="<?php echo htmlspecialchars($violation['establishment_name']); ?>">
                                                <i class='bx bx-check-circle'></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class='bx bx-search-alt'></i>
                                            <h5>No violations found</h5>
                                            <p>Try adjusting your filters or add a new violation.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Violation Modal -->
    <div class="modal fade" id="addViolationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="addViolationForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Inspection *</label>
                                <select class="form-select" name="inspection_id" required>
                                    <option value="">Select Inspection</option>
                                    <?php foreach ($inspections as $inspection): ?>
                                    <option value="<?php echo htmlspecialchars($inspection['id']); ?>">
                                        <?php echo htmlspecialchars($inspection['establishment_name'] . ' - ' . date('M d, Y', strtotime($inspection['inspection_date']))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an inspection</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Checklist Item *</label>
                                <select class="form-select" name="item_id" required>
                                    <option value="">Select Checklist Item</option>
                                    <?php foreach ($checklist_items as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item['id']); ?>">
                                        <?php echo htmlspecialchars($item['checklist_name'] . ' - ' . $item['item_text']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a checklist item</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Violation Code *</label>
                                <input type="text" class="form-control" name="violation_code" required>
                                <div class="invalid-feedback">Please enter a violation code</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Severity *</label>
                                <select class="form-select" name="severity" required>
                                    <option value="">Select Severity</option>
                                    <option value="minor">Minor</option>
                                    <option value="major">Major</option>
                                    <option value="critical">Critical</option>
                                </select>
                                <div class="invalid-feedback">Please select a severity level</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control" name="description" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter a description</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Corrective Action *</label>
                                <textarea class="form-control" name="corrective_action" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter corrective action</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Deadline *</label>
                                <input type="date" class="form-control" name="deadline" required>
                                <div class="invalid-feedback">Please select a deadline</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fine Amount (₱)</label>
                                <input type="number" class="form-control" name="fine_amount" min="0" step="0.01">
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_violation" class="btn btn-primary">Add Violation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Violation Modal -->
    <div class="modal fade" id="editViolationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editViolationForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Violation Code *</label>
                                <input type="text" class="form-control" name="violation_code" id="edit_violation_code" required>
                                <div class="invalid-feedback">Please enter a violation code</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Severity *</label>
                                <select class="form-select" name="severity" id="edit_severity" required>
                                    <option value="minor">Minor</option>
                                    <option value="major">Major</option>
                                    <option value="critical">Critical</option>
                                </select>
                                <div class="invalid-feedback">Please select a severity level</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter a description</div>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Corrective Action *</label>
                                <textarea class="form-control" name="corrective_action" id="edit_corrective_action" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter corrective action</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Deadline *</label>
                                <input type="date" class="form-control" name="deadline" id="edit_deadline" required>
                                <div class="invalid-feedback">Please select a deadline</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                                <div class="invalid-feedback">Please select a status</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Fine Amount (₱)</label>
                                <input type="number" class="form-control" name="fine_amount" id="edit_fine_amount" min="0" step="0.01">
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Paid Amount (₱)</label>
                                <input type="number" class="form-control" name="paid_amount" id="edit_paid_amount" min="0" step="0.01">
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Date</label>
                                <input type="date" class="form-control" name="payment_date" id="edit_payment_date">
                                <div class="invalid-feedback">Please select a valid date</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_violation" class="btn btn-primary">Update Violation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Resolve Violation Modal -->
    <div class="modal fade" id="resolveViolationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="resolveViolationForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" id="resolve_id">
                        
                        <div class="mb-3">
                            <p>You are about to resolve the following violation:</p>
                            <div class="alert alert-info">
                                <strong id="resolve_violation_code"></strong>
                                <br>
                                <span id="resolve_establishment_name"></span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Resolution Notes *</label>
                            <textarea class="form-control" name="resolution_notes" rows="4" required></textarea>
                            <div class="invalid-feedback">Please provide resolution notes</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="resolve_violation" class="btn btn-success">Mark as Resolved</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Violation Modal -->
    <div class="modal fade" id="viewViolationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Violation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Violation Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Code:</th>
                                    <td id="view_violation_code"></td>
                                </tr>
                                <tr>
                                    <th>Severity:</th>
                                    <td><span class="violation-badge" id="view_severity_badge"></span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td><span class="violation-badge" id="view_status_badge"></span></td>
                                </tr>
                                <tr>
                                    <th>Deadline:</th>
                                    <td id="view_deadline"></td>
                                </tr>
                                <tr>
                                    <th>Fine Amount:</th>
                                    <td id="view_fine_amount"></td>
                                </tr>
                                <tr>
                                    <th>Paid Amount:</th>
                                    <td id="view_paid_amount"></td>
                                </tr>
                                <tr>
                                    <th>Payment Date:</th>
                                    <td id="view_payment_date"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Establishment Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Name:</th>
                                    <td id="view_establishment_name"></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td id="view_establishment_address"></td>
                                </tr>
                                <tr>
                                    <th>Inspection Date:</th>
                                    <td id="view_inspection_date"></td>
                                </tr>
                                <tr>
                                    <th>Inspector:</th>
                                    <td id="view_inspector_name"></td>
                                </tr>
                                <tr>
                                    <th>Checklist Item:</th>
                                    <td id="view_item_text"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6>Description</h6>
                            <div class="card card-body bg-light">
                                <p id="view_description"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6>Corrective Action</h6>
                            <div class="card card-body bg-light">
                                <p id="view_corrective_action"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6>Resolution Notes</h6>
                            <div class="card card-body bg-light">
                                <p id="view_resolution_notes">No resolution notes provided.</p>
                            </div>
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
        
        // Filter toggle animation
        const filterHeader = document.querySelector('.filter-header');
        const filterToggle = document.querySelector('.filter-toggle');
        
        filterHeader.addEventListener('click', function() {
            filterToggle.classList.toggle('collapsed');
        });
        
        // Modal data handling
        const editViolationModal = document.getElementById('editViolationModal');
        if (editViolationModal) {
            editViolationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const modal = this;
                
                modal.querySelector('#edit_id').value = button.getAttribute('data-id');
                modal.querySelector('#edit_violation_code').value = button.getAttribute('data-violation-code');
                modal.querySelector('#edit_description').value = button.getAttribute('data-description');
                modal.querySelector('#edit_severity').value = button.getAttribute('data-severity');
                modal.querySelector('#edit_status').value = button.getAttribute('data-status');
                modal.querySelector('#edit_corrective_action').value = button.getAttribute('data-corrective-action');
                modal.querySelector('#edit_deadline').value = button.getAttribute('data-deadline');
                modal.querySelector('#edit_fine_amount').value = button.getAttribute('data-fine-amount');
                modal.querySelector('#edit_paid_amount').value = button.getAttribute('data-paid-amount') || '';
                modal.querySelector('#edit_payment_date').value = button.getAttribute('data-payment-date') || '';
            });
        }
        
        const resolveViolationModal = document.getElementById('resolveViolationModal');
        if (resolveViolationModal) {
            resolveViolationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const modal = this;
                
                modal.querySelector('#resolve_id').value = button.getAttribute('data-id');
                modal.querySelector('#resolve_violation_code').textContent = button.getAttribute('data-violation-code');
                modal.querySelector('#resolve_establishment_name').textContent = button.getAttribute('data-establishment-name');
            });
        }
        
        const viewViolationModal = document.getElementById('viewViolationModal');
        if (viewViolationModal) {
            viewViolationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const modal = this;
                
                // Set basic info
                modal.querySelector('#view_violation_code').textContent = button.getAttribute('data-violation-code');
                modal.querySelector('#view_description').textContent = button.getAttribute('data-description');
                modal.querySelector('#view_corrective_action').textContent = button.getAttribute('data-corrective-action');
                
                // Set severity badge
                const severity = button.getAttribute('data-severity');
                modal.querySelector('#view_severity_badge').textContent = severity.charAt(0).toUpperCase() + severity.slice(1);
                modal.querySelector('#view_severity_badge').className = 'violation-badge badge-' + severity;
                
                // Set status badge
                const status = button.getAttribute('data-status');
                modal.querySelector('#view_status_badge').textContent = status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                modal.querySelector('#view_status_badge').className = 'violation-badge badge-' + status;
                
                // Set dates
                modal.querySelector('#view_deadline').textContent = new Date(button.getAttribute('data-deadline')).toLocaleDateString();
                
                // Set amounts
                modal.querySelector('#view_fine_amount').textContent = '₱' + parseFloat(button.getAttribute('data-fine-amount') || 0).toFixed(2);
                modal.querySelector('#view_paid_amount').textContent = '₱' + parseFloat(button.getAttribute('data-paid-amount') || 0).toFixed(2);
                
                // Set payment date
                const paymentDate = button.getAttribute('data-payment-date');
                modal.querySelector('#view_payment_date').textContent = paymentDate ? new Date(paymentDate).toLocaleDateString() : 'N/A';
                
                // Set establishment info
                modal.querySelector('#view_establishment_name').textContent = button.getAttribute('data-establishment-name');
                modal.querySelector('#view_establishment_address').textContent = button.getAttribute('data-establishment-address');
                modal.querySelector('#view_inspection_date').textContent = new Date(button.getAttribute('data-inspection-date')).toLocaleDateString();
                modal.querySelector('#view_inspector_name').textContent = button.getAttribute('data-inspector-name');
                modal.querySelector('#view_item_text').textContent = button.getAttribute('data-item-text');
                
                // Set resolution notes
                const resolutionNotes = button.getAttribute('data-resolution-notes');
                if (resolutionNotes) {
                    modal.querySelector('#view_resolution_notes').textContent = resolutionNotes;
                }
            });
        }
        
        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
        
        // Add form validation to modals
        document.getElementById('addViolationForm').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
        
        document.getElementById('editViolationForm').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
        
        document.getElementById('resolveViolationForm').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
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