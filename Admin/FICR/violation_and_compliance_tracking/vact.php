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
            $resolution_notes = filter_input(INPUT_POST, 'resolution_notes', FILTER_SANITIZE_STRING);
            
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
            $resolution_notes = $resolution_notes ?: null;
            
            $query = "UPDATE violations SET 
                     violation_code = ?, description = ?, severity = ?, 
                     corrective_action = ?, deadline = ?, fine_amount = ?,
                     status = ?, paid_amount = ?, payment_date = ?, resolution_notes = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [$violation_code, $description, $severity, $corrective_action, 
                      $deadline, $fine_amount, $status, $paid_amount, $payment_date, $resolution_notes, $id];
            
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
                
                
                <div class="sidebar-section">Account</div>
                
                <a href="../../settings.php" class="sidebar-link">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
                
                <a href="../../logout.php" class="sidebar-link">
                    <i class='bx bx-log-out'></i>
                    <span class="text">Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Violation and Compliance Tracking</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="#">FICR</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Violation and Compliance Tracking</li>
                        </ol>
                    </nav>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addViolationModal">
                            <i class='bx bx-plus'></i> Add New Violation
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="exportBtn">
                            <i class='bx bx-download'></i> Export
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                    <h5><i class='bx bx-filter-alt'></i> Filter Violations</h5>
                    <i class='bx bx-chevron-up filter-toggle'></i>
                </div>
                
                <div class="collapse show" id="filterCollapse">
                    <form method="GET" action="">
                        <div class="filter-content">
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by code, description, or establishment" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="filter_status" class="form-label">Status</label>
                                <select class="form-select" id="filter_status" name="filter_status">
                                    <option value="">All Statuses</option>
                                    <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="filter_severity" class="form-label">Severity</label>
                                <select class="form-select" id="filter_severity" name="filter_severity">
                                    <option value="">All Severities</option>
                                    <option value="minor" <?php echo $filter_severity === 'minor' ? 'selected' : ''; ?>>Minor</option>
                                    <option value="major" <?php echo $filter_severity === 'major' ? 'selected' : ''; ?>>Major</option>
                                    <option value="critical" <?php echo $filter_severity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="filter_establishment" class="form-label">Establishment</label>
                                <select class="form-select" id="filter_establishment" name="filter_establishment">
                                    <option value="">All Establishments</option>
                                    <?php foreach ($establishments as $est): ?>
                                    <option value="<?php echo $est['id']; ?>" <?php echo $filter_establishment == $est['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
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
            <div class="card animate-slide-in">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover violations-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Establishment</th>
                                    <th>Severity</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($violations) > 0): ?>
                                <?php foreach ($violations as $violation): 
                                    // Calculate days until deadline
                                    $deadline = new DateTime($violation['deadline']);
                                    $today = new DateTime();
                                    $days_remaining = $today->diff($deadline)->format('%r%a');
                                    
                                    // Determine deadline class
                                    $deadline_class = '';
                                    if ($days_remaining < 0) {
                                        $deadline_class = 'deadline-passed';
                                    } elseif ($days_remaining <= 7) {
                                        $deadline_class = 'deadline-near';
                                    } else {
                                        $deadline_class = 'deadline-future';
                                    }
                                    
                                    // Determine payment status
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
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($violation['description']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($violation['item_text']); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($violation['establishment_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($violation['establishment_address']); ?></small>
                                    </td>
                                    <td>
                                        <span class="violation-badge badge-<?php echo $violation['severity']; ?>">
                                            <?php echo ucfirst($violation['severity']); ?>
                                        </span>
                                    </td>
                                    <td class="<?php echo $deadline_class; ?>">
                                        <?php echo date('M j, Y', strtotime($violation['deadline'])); ?>
                                        <?php if ($days_remaining >= 0): ?>
                                        <br><small>(<?php echo $days_remaining; ?> days remaining)</small>
                                        <?php else: ?>
                                        <br><small>(<?php echo abs($days_remaining); ?> days overdue)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="violation-badge badge-<?php echo $violation['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $violation['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($violation['fine_amount'] > 0): ?>
                                        <div>₱<?php echo number_format($violation['fine_amount'], 2); ?></div>
                                        <span class="payment-status <?php echo $payment_status; ?>">
                                            <?php echo $payment_text; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">No fine</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-action view-violation" 
                                                    data-bs-toggle="modal" data-bs-target="#viewViolationModal"
                                                    data-id="<?php echo $violation['id']; ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-description="<?php echo htmlspecialchars($violation['description']); ?>"
                                                    data-severity="<?php echo $violation['severity']; ?>"
                                                    data-corrective-action="<?php echo htmlspecialchars($violation['corrective_action']); ?>"
                                                    data-deadline="<?php echo $violation['deadline']; ?>"
                                                    data-fine-amount="<?php echo $violation['fine_amount']; ?>"
                                                    data-status="<?php echo $violation['status']; ?>"
                                                    data-paid-amount="<?php echo $violation['paid_amount']; ?>"
                                                    data-payment-date="<?php echo $violation['payment_date']; ?>"
                                                    data-resolution-notes="<?php echo htmlspecialchars($violation['resolution_notes']); ?>"
                                                    data-establishment-name="<?php echo htmlspecialchars($violation['establishment_name']); ?>"
                                                    data-establishment-address="<?php echo htmlspecialchars($violation['establishment_address']); ?>"
                                                    data-inspection-date="<?php echo date('M j, Y', strtotime($violation['inspection_date'])); ?>"
                                                    data-overall-rating="<?php echo $violation['overall_rating']; ?>"
                                                    data-inspector-name="<?php echo htmlspecialchars($violation['inspector_name']); ?>"
                                                    data-item-text="<?php echo htmlspecialchars($violation['item_text']); ?>">
                                                <i class='bx bx-show'></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-action edit-violation"
                                                    data-bs-toggle="modal" data-bs-target="#editViolationModal"
                                                    data-id="<?php echo $violation['id']; ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-description="<?php echo htmlspecialchars($violation['description']); ?>"
                                                    data-severity="<?php echo $violation['severity']; ?>"
                                                    data-corrective-action="<?php echo htmlspecialchars($violation['corrective_action']); ?>"
                                                    data-deadline="<?php echo $violation['deadline']; ?>"
                                                    data-fine-amount="<?php echo $violation['fine_amount']; ?>"
                                                    data-status="<?php echo $violation['status']; ?>"
                                                    data-paid-amount="<?php echo $violation['paid_amount']; ?>"
                                                    data-payment-date="<?php echo $violation['payment_date']; ?>"
                                                    data-resolution-notes="<?php echo htmlspecialchars($violation['resolution_notes']); ?>">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <?php if ($violation['status'] !== 'resolved'): ?>
                                            <button type="button" class="btn btn-outline-success btn-action resolve-violation"
                                                    data-bs-toggle="modal" data-bs-target="#resolveViolationModal"
                                                    data-id="<?php echo $violation['id']; ?>"
                                                    data-violation-code="<?php echo htmlspecialchars($violation['violation_code']); ?>"
                                                    data-description="<?php echo htmlspecialchars($violation['description']); ?>">
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
                                            <p>Try adjusting your search or filter criteria</p>
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
    <div class="modal fade" id="addViolationModal" tabindex="-1" aria-labelledby="addViolationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addViolationModalLabel">Add New Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="addViolationForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="inspection_id" class="form-label">Inspection <span class="text-danger">*</span></label>
                                <select class="form-select" id="inspection_id" name="inspection_id" required>
                                    <option value="">Select Inspection</option>
                                    <?php foreach ($inspections as $inspection): ?>
                                    <option value="<?php echo $inspection['id']; ?>">
                                        <?php echo htmlspecialchars($inspection['establishment_name'] . ' - ' . date('M j, Y', strtotime($inspection['inspection_date']))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an inspection</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="item_id" class="form-label">Checklist Item <span class="text-danger">*</span></label>
                                <select class="form-select" id="item_id" name="item_id" required>
                                    <option value="">Select Checklist Item</option>
                                    <?php foreach ($checklist_items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['checklist_name'] . ' - ' . $item['item_text']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a checklist item</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="violation_code" class="form-label">Violation Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="violation_code" name="violation_code" required>
                                <div class="invalid-feedback">Please enter a violation code</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="severity" class="form-label">Severity <span class="text-danger">*</span></label>
                                <select class="form-select" id="severity" name="severity" required>
                                    <option value="">Select Severity</option>
                                    <option value="minor">Minor</option>
                                    <option value="major">Major</option>
                                    <option value="critical">Critical</option>
                                </select>
                                <div class="invalid-feedback">Please select a severity level</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">Please enter a description</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="corrective_action" class="form-label">Corrective Action <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="corrective_action" name="corrective_action" rows="3" required></textarea>
                            <div class="invalid-feedback">Please enter a corrective action</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="deadline" name="deadline" required>
                                <div class="invalid-feedback">Please select a deadline</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fine_amount" class="form-label">Fine Amount (₱)</label>
                                <input type="number" class="form-control" id="fine_amount" name="fine_amount" min="0" step="0.01">
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
    
    <!-- View Violation Modal -->
    <div class="modal fade" id="viewViolationModal" tabindex="-1" aria-labelledby="viewViolationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewViolationModalLabel">Violation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Violation Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Code:</th>
                                    <td id="view-violation-code"></td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td id="view-description"></td>
                                </tr>
                                <tr>
                                    <th>Severity:</th>
                                    <td><span class="violation-badge" id="view-severity"></span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td><span class="violation-badge" id="view-status"></span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Establishment Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Name:</th>
                                    <td id="view-establishment-name"></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td id="view-establishment-address"></td>
                                </tr>
                                <tr>
                                    <th>Inspection Date:</th>
                                    <td id="view-inspection-date"></td>
                                </tr>
                                <tr>
                                    <th>Inspector:</th>
                                    <td id="view-inspector-name"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Compliance Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Checklist Item:</th>
                                    <td id="view-item-text"></td>
                                </tr>
                                <tr>
                                    <th>Corrective Action:</th>
                                    <td id="view-corrective-action"></td>
                                </tr>
                                <tr>
                                    <th>Deadline:</th>
                                    <td id="view-deadline"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Financial Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Fine Amount:</th>
                                    <td id="view-fine-amount"></td>
                                </tr>
                                <tr>
                                    <th>Paid Amount:</th>
                                    <td id="view-paid-amount"></td>
                                </tr>
                                <tr>
                                    <th>Payment Date:</th>
                                    <td id="view-payment-date"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Resolution Notes</h6>
                        <div class="card">
                            <div class="card-body">
                                <p id="view-resolution-notes" class="mb-0"></p>
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
    
    <!-- Edit Violation Modal -->
    <div class="modal fade" id="editViolationModal" tabindex="-1" aria-labelledby="editViolationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editViolationModalLabel">Edit Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editViolationForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-violation_code" class="form-label">Violation Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-violation_code" name="violation_code" required>
                                <div class="invalid-feedback">Please enter a violation code</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-severity" class="form-label">Severity <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-severity" name="severity" required>
                                    <option value="minor">Minor</option>
                                    <option value="major">Major</option>
                                    <option value="critical">Critical</option>
                                </select>
                                <div class="invalid-feedback">Please select a severity level</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit-description" name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">Please enter a description</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-corrective_action" class="form-label">Corrective Action <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit-corrective_action" name="corrective_action" rows="3" required></textarea>
                            <div class="invalid-feedback">Please enter a corrective action</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit-deadline" name="deadline" required>
                                <div class="invalid-feedback">Please select a deadline</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-status" name="status" required>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                                <div class="invalid-feedback">Please select a status</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-fine_amount" class="form-label">Fine Amount (₱)</label>
                                <input type="number" class="form-control" id="edit-fine_amount" name="fine_amount" min="0" step="0.01">
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-paid_amount" class="form-label">Paid Amount (₱)</label>
                                <input type="number" class="form-control" id="edit-paid_amount" name="paid_amount" min="0" step="0.01">
                                <div class="invalid-feedback">Please enter a valid amount</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-payment_date" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="edit-payment_date" name="payment_date">
                                <div class="invalid-feedback">Please select a valid date</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-resolution_notes" class="form-label">Resolution Notes</label>
                            <textarea class="form-control" id="edit-resolution_notes" name="resolution_notes" rows="3"></textarea>
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
    <div class="modal fade" id="resolveViolationModal" tabindex="-1" aria-labelledby="resolveViolationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveViolationModalLabel">Resolve Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="resolveViolationForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="resolve-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <p>You are about to mark the following violation as resolved:</p>
                            <div class="alert alert-info">
                                <strong id="resolve-violation-code"></strong>
                                <p id="resolve-description" class="mb-0"></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="resolution_notes" class="form-label">Resolution Notes <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="4" required placeholder="Describe how this violation was resolved..."></textarea>
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
            
            // Filter toggle animation
            const filterHeader = document.querySelector('.filter-header');
            const filterToggle = document.querySelector('.filter-toggle');
            
            if (filterHeader && filterToggle) {
                filterHeader.addEventListener('click', function() {
                    filterToggle.classList.toggle('collapsed');
                });
            }
            
            // View violation modal
            const viewViolationButtons = document.querySelectorAll('.view-violation');
            viewViolationButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = document.getElementById('viewViolationModal');
                    
                    // Populate modal with data
                    document.getElementById('view-violation-code').textContent = this.getAttribute('data-violation-code');
                    document.getElementById('view-description').textContent = this.getAttribute('data-description');
                    document.getElementById('view-corrective-action').textContent = this.getAttribute('data-corrective-action');
                    document.getElementById('view-resolution-notes').textContent = this.getAttribute('data-resolution-notes') || 'No resolution notes provided.';
                    
                    // Set severity badge
                    const severity = this.getAttribute('data-severity');
                    const severityBadge = document.getElementById('view-severity');
                    severityBadge.textContent = severity.charAt(0).toUpperCase() + severity.slice(1);
                    severityBadge.className = 'violation-badge badge-' + severity;
                    
                    // Set status badge
                    const status = this.getAttribute('data-status');
                    const statusBadge = document.getElementById('view-status');
                    statusBadge.textContent = status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    statusBadge.className = 'violation-badge badge-' + status;
                    
                    // Set establishment info
                    document.getElementById('view-establishment-name').textContent = this.getAttribute('data-establishment-name');
                    document.getElementById('view-establishment-address').textContent = this.getAttribute('data-establishment-address');
                    document.getElementById('view-inspection-date').textContent = this.getAttribute('data-inspection-date');
                    document.getElementById('view-inspector-name').textContent = this.getAttribute('data-inspector-name');
                    document.getElementById('view-item-text').textContent = this.getAttribute('data-item-text');
                    
                    // Format deadline
                    const deadline = new Date(this.getAttribute('data-deadline'));
                    document.getElementById('view-deadline').textContent = deadline.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    
                    // Set financial info
                    const fineAmount = parseFloat(this.getAttribute('data-fine-amount'));
                    const paidAmount = parseFloat(this.getAttribute('data-paid-amount'));
                    
                    document.getElementById('view-fine-amount').textContent = fineAmount > 0 ? '₱' + fineAmount.toFixed(2) : 'No fine';
                    document.getElementById('view-paid-amount').textContent = paidAmount > 0 ? '₱' + paidAmount.toFixed(2) : 'Not paid';
                    
                    const paymentDate = this.getAttribute('data-payment-date');
                    document.getElementById('view-payment-date').textContent = paymentDate ? 
                        new Date(paymentDate).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        }) : 'N/A';
                });
            });
            
            // Edit violation modal
            const editViolationButtons = document.querySelectorAll('.edit-violation');
            editViolationButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Populate form with data
                    document.getElementById('edit-id').value = this.getAttribute('data-id');
                    document.getElementById('edit-violation_code').value = this.getAttribute('data-violation-code');
                    document.getElementById('edit-description').value = this.getAttribute('data-description');
                    document.getElementById('edit-corrective_action').value = this.getAttribute('data-corrective-action');
                    document.getElementById('edit-deadline').value = this.getAttribute('data-deadline');
                    document.getElementById('edit-fine_amount').value = this.getAttribute('data-fine-amount');
                    document.getElementById('edit-paid_amount').value = this.getAttribute('data-paid-amount');
                    document.getElementById('edit-payment_date').value = this.getAttribute('data-payment-date');
                    document.getElementById('edit-resolution_notes').value = this.getAttribute('data-resolution-notes') || '';
                    
                    // Set select values
                    document.getElementById('edit-severity').value = this.getAttribute('data-severity');
                    document.getElementById('edit-status').value = this.getAttribute('data-status');
                });
            });
            
            // Resolve violation modal
            const resolveViolationButtons = document.querySelectorAll('.resolve-violation');
            resolveViolationButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('resolve-id').value = this.getAttribute('data-id');
                    document.getElementById('resolve-violation-code').textContent = this.getAttribute('data-violation-code');
                    document.getElementById('resolve-description').textContent = this.getAttribute('data-description');
                });
            });
            
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
            
            // Export functionality
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    // Get current filter parameters
                    const params = new URLSearchParams(window.location.search);
                    
                    // Redirect to export script with current filters
                    window.location.href = 'export_violations.php?' + params.toString();
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>