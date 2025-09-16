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
$active_submodule = 'inventory_management';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get inventory categories
    $categories = $dbManager->fetchAll("fsiet", "SELECT * FROM inventory_categories ORDER BY name");
    
    // Get inventory items with category names
    $inventory_items = $dbManager->fetchAll("fsiet", "
        SELECT i.*, c.name as category_name 
        FROM inventory_items i 
        LEFT JOIN inventory_categories c ON i.category_id = c.id 
        ORDER BY i.name
    ");
    
    // Get equipment data
    $equipment = $dbManager->fetchAll("fsiet", "SELECT * FROM equipment ORDER BY name");
    
    // Get maintenance schedules
    $maintenance_schedules = $dbManager->fetchAll("fsiet", "
        SELECT ms.*, e.name as equipment_name 
        FROM maintenance_schedules ms 
        LEFT JOIN equipment e ON ms.equipment_id = e.id 
        ORDER BY ms.next_maintenance
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $categories = [];
    $inventory_items = [];
    $equipment = [];
    $maintenance_schedules = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new category
        if (isset($_POST['add_category'])) {
            $query = "INSERT INTO inventory_categories (name, description) VALUES (?, ?)";
            $params = [$_POST['category_name'], $_POST['category_description']];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Category added successfully!";
            header("Location: im.php");
            exit;
        }
        
        // Add new inventory item
        if (isset($_POST['add_item'])) {
            $query = "INSERT INTO inventory_items (name, description, category_id, quantity, min_stock_level, unit, storage_location) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $_POST['item_name'],
                $_POST['item_description'],
                $_POST['category_id'],
                $_POST['quantity'],
                $_POST['min_stock_level'],
                $_POST['unit'],
                $_POST['storage_location']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            // Log transaction
            $item_id = $dbManager->getConnection("fsiet")->lastInsertId();
            $transaction_query = "INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reason, performed_by) 
                                VALUES (?, 'in', ?, 0, ?, 'Initial stock', ?)";
            $dbManager->query("fsiet", $transaction_query, [$item_id, $_POST['quantity'], $_POST['quantity'], $_SESSION['user_id']]);
            
            $_SESSION['success_message'] = "Inventory item added successfully!";
            header("Location: im.php");
            exit;
        }
        
        // Update inventory item
        if (isset($_POST['update_item'])) {
            // First get current stock
            $current_item = $dbManager->fetch("fsiet", "SELECT quantity FROM inventory_items WHERE id = ?", [$_POST['item_id']]);
            $current_quantity = $current_item['quantity'];
            $new_quantity = $_POST['quantity'];
            
            $query = "UPDATE inventory_items SET name = ?, description = ?, category_id = ?, quantity = ?, 
                     min_stock_level = ?, unit = ?, storage_location = ? WHERE id = ?";
            $params = [
                $_POST['item_name'],
                $_POST['item_description'],
                $_POST['category_id'],
                $new_quantity,
                $_POST['min_stock_level'],
                $_POST['unit'],
                $_POST['storage_location'],
                $_POST['item_id']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            // Log transaction if quantity changed
            if ($current_quantity != $new_quantity) {
                $transaction_type = ($new_quantity > $current_quantity) ? 'in' : 'out';
                $quantity_change = abs($new_quantity - $current_quantity);
                
                $transaction_query = "INSERT INTO inventory_transactions (item_id, transaction_type, quantity, previous_stock, new_stock, reason, performed_by) 
                                    VALUES (?, ?, ?, ?, ?, 'Manual adjustment', ?)";
                $dbManager->query("fsiet", $transaction_query, [
                    $_POST['item_id'], 
                    $transaction_type, 
                    $quantity_change, 
                    $current_quantity, 
                    $new_quantity, 
                    $_SESSION['user_id']
                ]);
            }
            
            $_SESSION['success_message'] = "Inventory item updated successfully!";
            header("Location: im.php");
            exit;
        }
        
        // Add equipment
        if (isset($_POST['add_equipment'])) {
            $query = "INSERT INTO equipment (name, type, serial_no, status, assigned_unit, last_maintenance, next_maintenance) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $_POST['equipment_name'],
                $_POST['equipment_type'],
                $_POST['serial_no'],
                $_POST['status'],
                !empty($_POST['assigned_unit']) ? $_POST['assigned_unit'] : null,
                !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null,
                !empty($_POST['next_maintenance']) ? $_POST['next_maintenance'] : null
            ];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Equipment added successfully!";
            header("Location: im.php");
            exit;
        }
        
        // Add maintenance schedule
        if (isset($_POST['add_maintenance'])) {
            $query = "INSERT INTO maintenance_schedules (equipment_id, schedule_type, next_maintenance, description, assigned_to, status) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $params = [
                $_POST['equipment_id'],
                $_POST['schedule_type'],
                $_POST['next_maintenance'],
                $_POST['description'],
                $_POST['assigned_to'],
                'pending'
            ];
            $dbManager->query("fsiet", $query, $params);
            
            $_SESSION['success_message'] = "Maintenance schedule added successfully!";
            header("Location: im.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Inventory operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    try {
        $type = $_GET['type'] ?? '';
        $id = $_GET['id'] ?? 0;
        
        if ($type === 'item' && $id > 0) {
            $dbManager->query("fsiet", "DELETE FROM inventory_items WHERE id = ?", [$id]);
            $_SESSION['success_message'] = "Inventory item deleted successfully!";
        }
        elseif ($type === 'category' && $id > 0) {
            // Check if category has items
            $items_count = $dbManager->fetch("fsiet", "SELECT COUNT(*) as count FROM inventory_items WHERE category_id = ?", [$id]);
            if ($items_count['count'] > 0) {
                $_SESSION['error_message'] = "Cannot delete category that has inventory items!";
            } else {
                $dbManager->query("fsiet", "DELETE FROM inventory_categories WHERE id = ?", [$id]);
                $_SESSION['success_message'] = "Category deleted successfully!";
            }
        }
        elseif ($type === 'equipment' && $id > 0) {
            $dbManager->query("fsiet", "DELETE FROM equipment WHERE id = ?", [$id]);
            $_SESSION['success_message'] = "Equipment deleted successfully!";
        }
        elseif ($type === 'maintenance' && $id > 0) {
            $dbManager->query("fsiet", "DELETE FROM maintenance_schedules WHERE id = ?", [$id]);
            $_SESSION['success_message'] = "Maintenance schedule deleted successfully!";
        }
        
        header("Location: im.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Delete operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting item: " . $e->getMessage();
        header("Location: im.php");
        exit;
    }
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
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
        .stock-low {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .stock-out {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
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
                    <a href="im.php" class="sidebar-dropdown-link active">
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
                    <a href="../inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
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
                    <h1>Inventory Management</h1>
                    <p>Manage inventory items, equipment, and maintenance schedules.</p>
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
            
            <!-- Inventory Content -->
            <div class="dashboard-content">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="inventoryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">
                            <i class='bx bx-package'></i> Inventory Items
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                            <i class='bx bx-category'></i> Categories
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="equipment-tab" data-bs-toggle="tab" data-bs-target="#equipment" type="button" role="tab">
                            <i class='bx bx-wrench'></i> Equipment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                            <i class='bx bx-calendar'></i> Maintenance
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="inventoryTabsContent">
                    <!-- Inventory Items Tab -->
                    <div class="tab-pane fade show active" id="items" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-plus'></i>
                                                Add New Item
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="item_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="item_description" rows="2"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                                <select class="form-select" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                                        <input type="number" class="form-control" name="quantity" min="0" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Min Stock Level</label>
                                                        <input type="number" class="form-control" name="min_stock_level" min="1" value="5">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Unit</label>
                                                        <input type="text" class="form-control" name="unit" value="pcs">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Storage Location</label>
                                                        <input type="text" class="form-control" name="storage_location">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="add_item" class="btn btn-primary w-100">
                                                <i class='bx bx-plus'></i> Add Item
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Inventory Items</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Category</th>
                                                        <th>Quantity</th>
                                                        <th>Status</th>
                                                        <th>Location</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($inventory_items) > 0): ?>
                                                        <?php foreach ($inventory_items as $item): 
                                                            $status_class = '';
                                                            $status_text = 'In Stock';
                                                            if ($item['quantity'] <= 0) {
                                                                $status_class = 'stock-out';
                                                                $status_text = 'Out of Stock';
                                                            } elseif ($item['quantity'] <= $item['min_stock_level']) {
                                                                $status_class = 'stock-low';
                                                                $status_text = 'Low Stock';
                                                            }
                                                        ?>
                                                        <tr class="<?php echo $status_class; ?>">
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                                <?php if ($item['description']): ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>...</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                            <td>
                                                                <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?>
                                                                <?php if ($item['quantity'] <= $item['min_stock_level']): ?>
                                                                    <br><small class="text-danger">Min: <?php echo $item['min_stock_level']; ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge status-badge 
                                                                    <?php echo $item['quantity'] <= 0 ? 'bg-danger' : ($item['quantity'] <= $item['min_stock_level'] ? 'bg-warning' : 'bg-success'); ?>">
                                                                    <?php echo $status_text; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['storage_location']); ?></td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItemModal" 
                                                                    data-id="<?php echo $item['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                                    data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                                    data-category="<?php echo $item['category_id']; ?>"
                                                                    data-quantity="<?php echo $item['quantity']; ?>"
                                                                    data-minstock="<?php echo $item['min_stock_level']; ?>"
                                                                    data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                                                    data-location="<?php echo htmlspecialchars($item['storage_location']); ?>">
                                                                    <i class='bx bx-edit'></i>
                                                                </button>
                                                                <a href="im.php?delete=item&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this item?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No inventory items found</p>
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
                    </div>
                    
                    <!-- Categories Tab -->
                    <div class="tab-pane fade" id="categories" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-category'></i>
                                                Add New Category
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="category_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="category_description" rows="3"></textarea>
                                            </div>
                                            <button type="submit" name="add_category" class="btn btn-primary w-100">
                                                <i class='bx bx-plus'></i> Add Category
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Categories</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Description</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($categories) > 0): ?>
                                                        <?php foreach ($categories as $category): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></td>
                                                            <td class="action-buttons">
                                                                <a href="im.php?delete=category&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center py-4">
                                                                <p class="text-muted">No categories found</p>
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
                    </div>
                    
                    <!-- Equipment Tab -->
                    <div class="tab-pane fade" id="equipment" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-plus'></i>
                                                Add New Equipment
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Equipment Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="equipment_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="equipment_type" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="serial_no" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="available">Available</option>
                                                    <option value="in-use">In Use</option>
                                                    <option value="maintenance">Maintenance</option>
                                                    <option value="retired">Retired</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assigned Unit ID</label>
                                                <input type="number" class="form-control" name="assigned_unit">
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Last Maintenance</label>
                                                        <input type="date" class="form-control" name="last_maintenance">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Next Maintenance</label>
                                                        <input type="date" class="form-control" name="next_maintenance">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="add_equipment" class="btn btn-primary w-100">
                                                <i class='bx bx-plus'></i> Add Equipment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Equipment</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Type</th>
                                                        <th>Serial No</th>
                                                        <th>Status</th>
                                                        <th>Last Maintenance</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($equipment) > 0): ?>
                                                        <?php foreach ($equipment as $eq): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($eq['name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($eq['type']); ?></td>
                                                            <td><?php echo htmlspecialchars($eq['serial_no']); ?></td>
                                                            <td>
                                                                <span class="badge status-badge 
                                                                    <?php echo $eq['status'] === 'available' ? 'bg-success' : 
                                                                          ($eq['status'] === 'in-use' ? 'bg-primary' : 
                                                                          ($eq['status'] === 'maintenance' ? 'bg-warning' : 'bg-secondary')); ?>">
                                                                    <?php echo ucfirst($eq['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $eq['last_maintenance'] ? date('M j, Y', strtotime($eq['last_maintenance'])) : 'Never'; ?></td>
                                                            <td class="action-buttons">
                                                                <a href="im.php?delete=equipment&id=<?php echo $eq['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this equipment?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No equipment found</p>
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
                    </div>
                    
                    <!-- Maintenance Tab -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="inventory-container animate-fade-in">
                                    <form method="POST" action="">
                                        <div class="form-section">
                                            <div class="form-section-title">
                                                <i class='bx bx-plus'></i>
                                                Schedule Maintenance
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                                <select class="form-select" name="equipment_id" required>
                                                    <option value="">Select Equipment</option>
                                                    <?php foreach ($equipment as $eq): ?>
                                                        <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Schedule Type <span class="text-danger">*</span></label>
                                                <select class="form-select" name="schedule_type" required>
                                                    <option value="routine">Routine Maintenance</option>
                                                    <option value="corrective">Corrective Maintenance</option>
                                                    <option value="preventive">Preventive Maintenance</option>
                                                    <option value="emergency">Emergency Repair</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Next Maintenance Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="next_maintenance" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assigned To</label>
                                                <input type="text" class="form-control" name="assigned_to">
                                            </div>
                                            <button type="submit" name="add_maintenance" class="btn btn-primary w-100">
                                                <i class='bx bx-plus'></i> Schedule Maintenance
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card animate-fade-in">
                                    <div class="card-header">
                                        <h5>Maintenance Schedules</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Equipment</th>
                                                        <th>Schedule Type</th>
                                                        <th>Next Maintenance</th>
                                                        <th>Status</th>
                                                        <th>Assigned To</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($maintenance_schedules) > 0): ?>
                                                        <?php foreach ($maintenance_schedules as $ms): 
                                                            $days_until = floor((strtotime($ms['next_maintenance']) - time()) / (60 * 60 * 24));
                                                            $status_class = '';
                                                            if ($ms['status'] === 'completed') {
                                                                $status_class = 'bg-success';
                                                            } elseif ($days_until <= 0) {
                                                                $status_class = 'bg-danger';
                                                            } elseif ($days_until <= 7) {
                                                                $status_class = 'bg-warning';
                                                            } else {
                                                                $status_class = 'bg-info';
                                                            }
                                                        ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($ms['equipment_name']); ?></strong></td>
                                                            <td><?php echo ucfirst($ms['schedule_type']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($ms['next_maintenance'])); ?></td>
                                                            <td>
                                                                <span class="badge status-badge <?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($ms['status']); ?>
                                                                    <?php if ($ms['status'] === 'pending'): ?>
                                                                        (<?php echo $days_until > 0 ? "in $days_until days" : "overdue"; ?>)
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($ms['assigned_to'] ?? 'Unassigned'); ?></td>
                                                            <td class="action-buttons">
                                                                <a href="im.php?delete=maintenance&id=<?php echo $ms['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this maintenance schedule?')">
                                                                    <i class='bx bx-trash'></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <p class="text-muted">No maintenance schedules found</p>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Inventory Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="item_name" id="edit_item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="item_description" id="edit_item_description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="edit_category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="quantity" id="edit_quantity" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Min Stock Level</label>
                                    <input type="number" class="form-control" name="min_stock_level" id="edit_min_stock_level" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" class="form-control" name="unit" id="edit_unit">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Storage Location</label>
                                    <input type="text" class="form-control" name="storage_location" id="edit_storage_location">
                                </div>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit modal data population
        document.addEventListener('DOMContentLoaded', function() {
            var editItemModal = document.getElementById('editItemModal');
            if (editItemModal) {
                editItemModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    document.getElementById('edit_item_id').value = button.getAttribute('data-id');
                    document.getElementById('edit_item_name').value = button.getAttribute('data-name');
                    document.getElementById('edit_item_description').value = button.getAttribute('data-description');
                    document.getElementById('edit_category_id').value = button.getAttribute('data-category');
                    document.getElementById('edit_quantity').value = button.getAttribute('data-quantity');
                    document.getElementById('edit_min_stock_level').value = button.getAttribute('data-minstock');
                    document.getElementById('edit_unit').value = button.getAttribute('data-unit');
                    document.getElementById('edit_storage_location').value = button.getAttribute('data-location');
                });
            }
            
            // Auto-hide toasts after 5 seconds
            setTimeout(function() {
                var toasts = document.querySelectorAll('.toast');
                toasts.forEach(function(toast) {
                    toast.classList.remove('animate-slide-in');
                    toast.classList.add('animate-slide-out');
                    setTimeout(function() {
                        toast.remove();
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>