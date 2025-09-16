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
$active_submodule = 'inspection_checklist_management';

// Initialize variables
$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_checklist'])) {
            // Add new inspection checklist
            $name = $_POST['name'];
            $description = $_POST['description'];
            $category = $_POST['category'];
            
            $query = "INSERT INTO inspection_checklists 
                     (name, description, category, created_by) 
                     VALUES (?, ?, ?, ?)";
            
            $params = [$name, $description, $category, $_SESSION['user_id']];
            
            $dbManager->query("ficr", $query, $params);
            $checklist_id = $dbManager->getConnection("ficr")->lastInsertId();
            
            $success_message = "Checklist created successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'create', 'inspection_checklists', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], 
                $checklist_id,
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_checklist'])) {
            // Update inspection checklist
            $id = $_POST['id'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            $category = $_POST['category'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $query = "UPDATE inspection_checklists SET 
                     name = ?, description = ?, category = ?, 
                     is_active = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [$name, $description, $category, $is_active, $id];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Checklist updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'inspection_checklists', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['delete_checklist'])) {
            // Delete inspection checklist
            $id = $_POST['id'];
            
            $query = "DELETE FROM inspection_checklists WHERE id = ?";
            $dbManager->query("ficr", $query, [$id]);
            
            $success_message = "Checklist deleted successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'delete', 'inspection_checklists', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['add_item'])) {
            // Add new checklist item
            $checklist_id = $_POST['checklist_id'];
            $item_text = $_POST['item_text'];
            $item_type = $_POST['item_type'];
            $weight = $_POST['weight'];
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            
            // Get the highest order index for this checklist
            $order_query = "SELECT MAX(order_index) as max_order FROM checklist_items WHERE checklist_id = ?";
            $order_result = $dbManager->fetch("ficr", $order_query, [$checklist_id]);
            $order_index = $order_result['max_order'] ? $order_result['max_order'] + 1 : 1;
            
            $query = "INSERT INTO checklist_items 
                     (checklist_id, item_text, item_type, weight, is_required, order_index) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [$checklist_id, $item_text, $item_type, $weight, $is_required, $order_index];
            
            $dbManager->query("ficr", $query, $params);
            $item_id = $dbManager->getConnection("ficr")->lastInsertId();
            
            $success_message = "Checklist item added successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'create', 'checklist_items', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], 
                $item_id,
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_item'])) {
            // Update checklist item
            $id = $_POST['id'];
            $item_text = $_POST['item_text'];
            $item_type = $_POST['item_type'];
            $weight = $_POST['weight'];
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            
            $query = "UPDATE checklist_items SET 
                     item_text = ?, item_type = ?, weight = ?, 
                     is_required = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [$item_text, $item_type, $weight, $is_required, $id];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Checklist item updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'checklist_items', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['delete_item'])) {
            // Delete checklist item
            $id = $_POST['id'];
            
            $query = "DELETE FROM checklist_items WHERE id = ?";
            $dbManager->query("ficr", $query, [$id]);
            
            $success_message = "Checklist item deleted successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'delete', 'checklist_items', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['reorder_items'])) {
            // Reorder checklist items
            $order_data = $_POST['item_order'];
            $orders = json_decode($order_data, true);
            
            foreach ($orders as $order) {
                $query = "UPDATE checklist_items SET order_index = ? WHERE id = ?";
                $dbManager->query("ficr", $query, [$order['order'], $order['id']]);
            }
            
            $success_message = "Items reordered successfully!";
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "An error occurred. Please try again.";
    }
}

// Get filter parameters
$filter_category = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Get current user
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Build query for inspection checklists with filters
    $query = "SELECT c.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as creator_name,
                     (SELECT COUNT(*) FROM checklist_items WHERE checklist_id = c.id) as item_count
              FROM ficr.inspection_checklists c
              LEFT JOIN frsm.users u ON c.created_by = u.id
              WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter_category)) {
        $query .= " AND c.category = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_status)) {
        if ($filter_status === 'active') {
            $query .= " AND c.is_active = 1";
        } elseif ($filter_status === 'inactive') {
            $query .= " AND c.is_active = 0";
        }
    }
    
    $query .= " ORDER BY c.name ASC";
    
    // Get inspection checklists data
    $checklists = $dbManager->fetchAll("ficr", $query, $params);
    
    // Get checklist categories for dropdown
    $categories = $dbManager->fetchAll("ficr", "SELECT DISTINCT category FROM inspection_checklists WHERE category IS NOT NULL AND category != '' ORDER BY category");
    
    // Get items for a specific checklist if requested
    $checklist_items = [];
    $selected_checklist = null;
    
    if (isset($_GET['view_items']) && is_numeric($_GET['view_items'])) {
        $checklist_id = $_GET['view_items'];
        $items_query = "SELECT * FROM checklist_items WHERE checklist_id = ? ORDER BY order_index ASC";
        $checklist_items = $dbManager->fetchAll("ficr", $items_query, [$checklist_id]);
        
        // Get the checklist details
        $checklist_query = "SELECT * FROM inspection_checklists WHERE id = ?";
        $selected_checklist = $dbManager->fetch("ficr", $checklist_query, [$checklist_id]);
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $checklists = [];
    $categories = [];
    $checklist_items = [];
    $selected_checklist = null;
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
        .checklist-badge {
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
        .checklist-table {
            min-width: 800px;
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
        
        /* Status badges */
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        .badge-inactive {
            background-color: #6c757d;
            color: white;
        }
        
        /* Item list styles */
        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .item-list li {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .item-list li:last-child {
            border-bottom: none;
        }
        .item-list li:hover {
            background-color: #f8f9fa;
        }
        .item-handle {
            cursor: move;
            color: #6c757d;
            margin-right: 10px;
        }
        .item-content {
            flex-grow: 1;
        }
        .item-type-badge {
            font-size: 0.7rem;
            margin-left: 8px;
        }
        .item-required {
            color: #dc3545;
            margin-left: 8px;
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
            .item-list li {
                flex-direction: column;
                align-items: flex-start;
            }
            .item-actions {
                margin-top: 10px;
                align-self: flex-end;
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
        
        /* Items panel */
        .items-panel {
            display: none;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .items-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .items-panel-title {
            margin: 0;
        }
        .back-to-list {
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .back-to-list:hover {
            color: #0d6efd;
        }
        
        /* Sortable items */
        .sortable-ghost {
            opacity: 0.5;
        }
        .sortable-chosen {
            background-color: #e9ecef;
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
                    <a href="../inspection_checklist_management/icm.php" class="sidebar-dropdown-link active">
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
                    <h1>Inspection Checklist Management</h1>
                    <p>Create and manage inspection checklists for fire safety compliance</p>
                </div>
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                            <i class='bx bx-plus'></i> New Checklist
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="toggleFilters">
                            <i class='bx bx-filter-alt'></i> Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section" id="filterSection">
                <div class="filter-header" data-bs-toggle="collapse" data-bs-target="#filterContent" aria-expanded="true">
                    <h5><i class='bx bx-filter-alt'></i> Filter Checklists</h5>
                    <i class='bx bx-chevron-up filter-toggle'></i>
                </div>
                <div class="collapse show" id="filterContent">
                    <form method="GET" action="">
                        <div class="filter-content">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or description">
                            </div>
                            <div class="form-group">
                                <label for="filter_category">Category</label>
                                <select class="form-select" id="filter_category" name="filter_category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category']); ?>" 
                                            <?php echo ($filter_category === $category['category']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filter_status">Status</label>
                                <select class="form-select" id="filter_status" name="filter_status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($filter_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-filter'></i> Apply Filters
                            </button>
                            <a href="icm.php" class="btn btn-outline-secondary">
                                <i class='bx bx-reset'></i> Clear Filters
                            </a>
                        </div>
                    </form>
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
            
            <!-- Checklists Table -->
            <div class="table-responsive">
                <table class="table table-hover checklist-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($checklists) > 0): ?>
                            <?php foreach ($checklists as $checklist): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($checklist['name']); ?></td>
                                    <td><?php echo htmlspecialchars($checklist['description']); ?></td>
                                    <td>
                                        <?php if ($checklist['category']): ?>
                                            <span class="checklist-badge bg-light text-dark">
                                                <?php echo htmlspecialchars($checklist['category']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $checklist['item_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($checklist['is_active']): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($checklist['creator_name']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?view_items=<?php echo $checklist['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Items">
                                                <i class='bx bx-list-check'></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    data-bs-toggle="modal" data-bs-target="#editChecklistModal" 
                                                    data-id="<?php echo $checklist['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($checklist['name']); ?>"
                                                    data-description="<?php echo htmlspecialchars($checklist['description']); ?>"
                                                    data-category="<?php echo htmlspecialchars($checklist['category']); ?>"
                                                    data-is-active="<?php echo $checklist['is_active']; ?>"
                                                    title="Edit Checklist">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $checklist['id']; ?>">
                                                <button type="submit" name="delete_checklist" 
                                                        class="btn btn-outline-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this checklist?')"
                                                        title="Delete Checklist">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class='bx bx-clipboard'></i>
                                        <h5>No Checklists Found</h5>
                                        <p>Create your first checklist to get started</p>
                                        <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                                            <i class='bx bx-plus'></i> Create Checklist
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Items Panel (shown when viewing items) -->
            <?php if ($selected_checklist): ?>
                <div class="items-panel" id="itemsPanel">
                    <div class="items-panel-header">
                        <div>
                            <h3 class="items-panel-title"><?php echo htmlspecialchars($selected_checklist['name']); ?></h3>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($selected_checklist['description']); ?></p>
                        </div>
                        <a href="icm.php" class="back-to-list">
                            <i class='bx bx-arrow-back'></i> Back to Checklists
                        </a>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Checklist Items</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class='bx bx-plus'></i> Add Item
                        </button>
                    </div>
                    
                    <?php if (count($checklist_items) > 0): ?>
                        <form method="POST" id="reorderForm">
                            <input type="hidden" name="item_order" id="itemOrder">
                            <input type="hidden" name="reorder_items" value="1">
                            <button type="submit" class="btn btn-outline-primary btn-sm mb-3" id="saveOrderBtn" style="display: none;">
                                <i class='bx bx-save'></i> Save Order
                            </button>
                        </form>
                        
                        <ul class="item-list" id="sortableItems">
                            <?php foreach ($checklist_items as $item): ?>
                                <li data-id="<?php echo $item['id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <span class="item-handle"><i class='bx bx-menu'></i></span>
                                        <div class="item-content">
                                            <?php echo htmlspecialchars($item['item_text']); ?>
                                            <span class="item-type-badge badge bg-secondary">
                                                <?php echo htmlspecialchars($item['item_type']); ?>
                                            </span>
                                            <?php if ($item['is_required']): ?>
                                                <span class="item-required" title="Required">
                                                    <i class='bx bx-star'></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="modal" data-bs-target="#editItemModal"
                                                data-id="<?php echo $item['id']; ?>"
                                                data-item-text="<?php echo htmlspecialchars($item['item_text']); ?>"
                                                data-item-type="<?php echo htmlspecialchars($item['item_type']); ?>"
                                                data-weight="<?php echo $item['weight']; ?>"
                                                data-is-required="<?php echo $item['is_required']; ?>"
                                                title="Edit Item">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_item" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this item?')"
                                                    title="Delete Item">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-list-check'></i>
                            <h5>No Items Found</h5>
                            <p>Add items to this checklist</p>
                            <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class='bx bx-plus'></i> Add Item
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <script>
                    // Show items panel when viewing items
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('itemsPanel').style.display = 'block';
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Checklist Modal -->
    <div class="modal fade" id="addChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Checklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category" 
                                   list="categoryOptions">
                            <datalist id="categoryOptions">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['category']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_checklist" class="btn btn-primary">Create Checklist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Checklist Modal -->
    <div class="modal fade" id="editChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Checklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_checklist_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="edit_category" name="category" 
                                   list="categoryOptions">
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_checklist" class="btn btn-primary">Update Checklist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Checklist Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="checklist_id" value="<?php echo isset($_GET['view_items']) ? $_GET['view_items'] : ''; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="item_text" class="form-label">Item Text *</label>
                            <textarea class="form-control" id="item_text" name="item_text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="item_type" name="item_type">
                                <option value="text">Text</option>
                                <option value="yes/no">Yes/No</option>
                                <option value="rating">Rating</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="file_upload">File Upload</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="weight" class="form-label">Weight (for scoring)</label>
                            <input type="number" class="form-control" id="weight" name="weight" value="1" min="1" max="10">
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1">
                            <label class="form-check-label" for="is_required">Required</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Checklist Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_item_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_item_text" class="form-label">Item Text *</label>
                            <textarea class="form-control" id="edit_item_text" name="item_text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_item_type" class="form-label">Item Type</label>
                            <select class="form-select" id="edit_item_type" name="item_type">
                                <option value="text">Text</option>
                                <option value="yes/no">Yes/No</option>
                                <option value="rating">Rating</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="file_upload">File Upload</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_weight" class="form-label">Weight (for scoring)</label>
                            <input type="number" class="form-control" id="edit_weight" name="weight" min="1" max="10">
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_is_required" name="is_required" value="1">
                            <label class="form-check-label" for="edit_is_required">Required</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_item" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Filter toggle
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const filterSection = document.getElementById('filterSection');
            filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
        });
        
        // Filter collapse icon animation
        document.querySelector('.filter-header').addEventListener('click', function() {
            const icon = this.querySelector('.filter-toggle');
            icon.classList.toggle('collapsed');
        });
        
        // Edit Checklist Modal
        const editChecklistModal = document.getElementById('editChecklistModal');
        if (editChecklistModal) {
            editChecklistModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const description = button.getAttribute('data-description');
                const category = button.getAttribute('data-category');
                const isActive = button.getAttribute('data-is-active');
                
                document.getElementById('edit_checklist_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_category').value = category;
                document.getElementById('edit_is_active').checked = isActive === '1';
            });
        }
        
        // Edit Item Modal
        const editItemModal = document.getElementById('editItemModal');
        if (editItemModal) {
            editItemModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const itemText = button.getAttribute('data-item-text');
                const itemType = button.getAttribute('data-item-type');
                const weight = button.getAttribute('data-weight');
                const isRequired = button.getAttribute('data-is-required');
                
                document.getElementById('edit_item_id').value = id;
                document.getElementById('edit_item_text').value = itemText;
                document.getElementById('edit_item_type').value = itemType;
                document.getElementById('edit_weight').value = weight;
                document.getElementById('edit_is_required').checked = isRequired === '1';
            });
        }
        
        // Initialize Sortable for checklist items
        const sortableItems = document.getElementById('sortableItems');
        if (sortableItems) {
            const sortable = new Sortable(sortableItems, {
                handle: '.item-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                animation: 150,
                onEnd: function() {
                    // Show save button when order changes
                    document.getElementById('saveOrderBtn').style.display = 'block';
                    
                    // Update the hidden input with the new order
                    const orderData = [];
                    const items = sortableItems.querySelectorAll('li');
                    items.forEach((item, index) => {
                        orderData.push({
                            id: item.getAttribute('data-id'),
                            order: index + 1
                        });
                    });
                    
                    document.getElementById('itemOrder').value = JSON.stringify(orderData);
                }
            });
        }
    </script>
</body>
</html>