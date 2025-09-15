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
$active_submodule = 'clearance_and_certification_management';

// Initialize variables
$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_clearance'])) {
            // Add new clearance
            $establishment_id = $_POST['establishment_id'];
            $inspection_id = $_POST['inspection_id'];
            $clearance_number = $_POST['clearance_number'];
            $type = $_POST['type'];
            $issue_date = $_POST['issue_date'];
            $expiry_date = $_POST['expiry_date'];
            $notes = $_POST['notes'];
            
            $query = "INSERT INTO clearances 
                     (establishment_id, inspection_id, clearance_number, type, 
                      issue_date, expiry_date, status, issued_by, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)";
            
            $params = [
                $establishment_id, $inspection_id, $clearance_number, $type,
                $issue_date, $expiry_date, $_SESSION['user_id'], $notes
            ];
            
            $dbManager->query("ficr", $query, $params);
            $clearance_id = $dbManager->getConnection("ficr")->lastInsertId();
            
            $success_message = "Clearance created successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'create', 'clearances', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], 
                $clearance_id,
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['update_clearance'])) {
            // Update clearance
            $id = $_POST['id'];
            $establishment_id = $_POST['establishment_id'];
            $inspection_id = $_POST['inspection_id'];
            $clearance_number = $_POST['clearance_number'];
            $type = $_POST['type'];
            $issue_date = $_POST['issue_date'];
            $expiry_date = $_POST['expiry_date'];
            $status = $_POST['status'];
            $notes = $_POST['notes'];
            
            $query = "UPDATE clearances SET 
                     establishment_id = ?, inspection_id = ?, clearance_number = ?, 
                     type = ?, issue_date = ?, expiry_date = ?, status = ?, 
                     notes = ?, updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [
                $establishment_id, $inspection_id, $clearance_number, $type,
                $issue_date, $expiry_date, $status, $notes, $id
            ];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Clearance updated successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'update', 'clearances', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['delete_clearance'])) {
            // Delete clearance
            $id = $_POST['id'];
            
            $query = "DELETE FROM clearances WHERE id = ?";
            $dbManager->query("ficr", $query, [$id]);
            
            $success_message = "Clearance deleted successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'delete', 'clearances', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        elseif (isset($_POST['renew_clearance'])) {
            // Renew clearance
            $id = $_POST['id'];
            $new_expiry_date = $_POST['new_expiry_date'];
            
            $query = "UPDATE clearances SET 
                     expiry_date = ?, status = 'active', updated_at = NOW() 
                     WHERE id = ?";
            
            $params = [$new_expiry_date, $id];
            
            $dbManager->query("ficr", $query, $params);
            
            $success_message = "Clearance renewed successfully!";
            
            // Log the action
            $log_query = "INSERT INTO ficr.audit_logs 
                         (user_id, action, table_name, record_id, ip_address, user_agent) 
                         VALUES (?, 'renew', 'clearances', ?, ?, ?)";
            $dbManager->query("ficr", $log_query, [
                $_SESSION['user_id'], $id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
            ]);
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "An error occurred. Please try again.";
    }
}

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Get current user
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Build query for clearances with filters
    $query = "SELECT c.*, 
                     e.name as establishment_name,
                     CONCAT(u.first_name, ' ', u.last_name) as issuer_name,
                     i.inspection_date
              FROM ficr.clearances c
              LEFT JOIN ficr.establishments e ON c.establishment_id = e.id
              LEFT JOIN ficr.inspection_results i ON c.inspection_id = i.id
              LEFT JOIN frsm.users u ON c.issued_by = u.id
              WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (c.clearance_number LIKE ? OR e.name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter_type)) {
        $query .= " AND c.type = ?";
        $params[] = $filter_type;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND c.status = ?";
        $params[] = $filter_status;
    }
    
    $query .= " ORDER BY c.issue_date DESC";
    
    // Get clearances data
    $clearances = $dbManager->fetchAll("ficr", $query, $params);
    
    // Get establishments for dropdown
    $establishments = $dbManager->fetchAll("ficr", "SELECT id, name FROM establishments WHERE status = 'active' ORDER BY name");
    
    // Get inspections for dropdown
    $inspections = $dbManager->fetchAll("ficr", 
        "SELECT id, inspection_date, establishment_id 
         FROM inspection_results 
         ORDER BY inspection_date DESC");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $clearances = [];
    $establishments = [];
    $inspections = [];
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
        .clearance-badge {
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
        .clearance-table {
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
        .badge-expired {
            background-color: #dc3545;
            color: white;
        }
        .badge-revoked {
            background-color: #6c757d;
            color: white;
        }
        .badge-suspended {
            background-color: #ffc107;
            color: #212529;
        }
        
        /* Type badges */
        .badge-fire_safety {
            background-color: #dc3545;
            color: white;
        }
        .badge-business_permit {
            background-color: #0d6efd;
            color: white;
        }
        .badge-occupancy {
            background-color: #6f42c1;
            color: white;
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
        
        /* Expiring soon warning */
        .expiring-soon {
            background-color: #fff3cd !important;
        }
        
        /* Date status indicators */
        .date-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .date-valid {
            background-color: #28a745;
        }
        .date-warning {
            background-color: #ffc107;
        }
        .date-expired {
            background-color: #dc3545;
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
                    <a href="../violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link">
                           <i class='bx bx-shield-x'></i>
                        <span>Violation and Compliance Tracking</span>
                    </a>
                    <a href="../clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link active">
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
                    <h1>Clearance and Certification Management</h1>
                    <p>Manage fire safety clearances and certifications for establishments</p>
                </div>
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClearanceModal">
                            <i class='bx bx-plus'></i> New Clearance
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
                    <h5><i class='bx bx-filter-alt'></i> Filter Clearances</h5>
                    <i class='bx bx-chevron-up filter-toggle'></i>
                </div>
                <div class="collapse show" id="filterContent">
                    <form method="GET" action="">
                        <div class="filter-content">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by clearance number or establishment">
                            </div>
                            <div class="form-group">
                                <label for="filter_type">Type</label>
                                <select class="form-select" id="filter_type" name="filter_type">
                                    <option value="">All Types</option>
                                    <option value="fire_safety" <?php echo ($filter_type === 'fire_safety') ? 'selected' : ''; ?>>Fire Safety</option>
                                    <option value="business_permit" <?php echo ($filter_type === 'business_permit') ? 'selected' : ''; ?>>Business Permit</option>
                                    <option value="occupancy" <?php echo ($filter_type === 'occupancy') ? 'selected' : ''; ?>>Occupancy</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filter_status">Status</label>
                                <select class="form-select" id="filter_status" name="filter_status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="expired" <?php echo ($filter_status === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                    <option value="revoked" <?php echo ($filter_status === 'revoked') ? 'selected' : ''; ?>>Revoked</option>
                                    <option value="suspended" <?php echo ($filter_status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-filter'></i> Apply Filters
                            </button>
                            <a href="cacm.php" class="btn btn-outline-secondary">
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
            
            <!-- Clearances Table -->
            <div class="table-responsive">
                <table class="table table-hover clearance-table">
                    <thead>
                        <tr>
                            <th>Clearance Number</th>
                            <th>Establishment</th>
                            <th>Type</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Issued By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clearances) > 0): ?>
                            <?php foreach ($clearances as $clearance): 
                                // Check if clearance is expiring soon (within 30 days)
                                $expiring_soon = false;
                                if ($clearance['status'] === 'active') {
                                    $expiry_date = new DateTime($clearance['expiry_date']);
                                    $today = new DateTime();
                                    $diff = $today->diff($expiry_date);
                                    if ($diff->days <= 30 && $diff->invert === 0) {
                                        $expiring_soon = true;
                                    }
                                }
                            ?>
                                <tr class="<?php echo $expiring_soon ? 'expiring-soon' : ''; ?>">
                                    <td><?php echo htmlspecialchars($clearance['clearance_number']); ?></td>
                                    <td><?php echo htmlspecialchars($clearance['establishment_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo htmlspecialchars($clearance['type']); ?>">
                                            <?php 
                                            $type_labels = [
                                                'fire_safety' => 'Fire Safety',
                                                'business_permit' => 'Business Permit',
                                                'occupancy' => 'Occupancy'
                                            ];
                                            echo htmlspecialchars($type_labels[$clearance['type']] ?? $clearance['type']); 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($clearance['issue_date'] ? date('M j, Y', strtotime($clearance['issue_date'])) : 'N/A'); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $expiry_date = $clearance['expiry_date'];
                                        $date_status = '';
                                        if ($expiry_date) {
                                            $today = new DateTime();
                                            $expiry = new DateTime($expiry_date);
                                            $interval = $today->diff($expiry);
                                            
                                            if ($today > $expiry) {
                                                $date_status = 'date-expired';
                                            } elseif ($interval->days <= 30) {
                                                $date_status = 'date-warning';
                                            } else {
                                                $date_status = 'date-valid';
                                            }
                                            
                                            echo '<span class="date-status ' . $date_status . '"></span>';
                                            echo htmlspecialchars(date('M j, Y', strtotime($expiry_date)));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo htmlspecialchars($clearance['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($clearance['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($clearance['issuer_name'] ?? 'System'); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#viewClearanceModal"
                                                    data-id="<?php echo $clearance['id']; ?>"
                                                    data-clearance-number="<?php echo htmlspecialchars($clearance['clearance_number']); ?>"
                                                    data-establishment="<?php echo htmlspecialchars($clearance['establishment_name'] ?? 'N/A'); ?>"
                                                    data-type="<?php echo htmlspecialchars($clearance['type']); ?>"
                                                    data-issue-date="<?php echo htmlspecialchars($clearance['issue_date']); ?>"
                                                    data-expiry-date="<?php echo htmlspecialchars($clearance['expiry_date']); ?>"
                                                    data-status="<?php echo htmlspecialchars($clearance['status']); ?>"
                                                    data-issued-by="<?php echo htmlspecialchars($clearance['issuer_name'] ?? 'System'); ?>"
                                                    data-notes="<?php echo htmlspecialchars($clearance['notes'] ?? ''); ?>"
                                                    data-inspection-date="<?php echo htmlspecialchars($clearance['inspection_date'] ?? 'N/A'); ?>">
                                                <i class='bx bx-show'></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="modal" data-bs-target="#editClearanceModal"
                                                    data-id="<?php echo $clearance['id']; ?>"
                                                    data-establishment-id="<?php echo $clearance['establishment_id']; ?>"
                                                    data-inspection-id="<?php echo $clearance['inspection_id']; ?>"
                                                    data-clearance-number="<?php echo htmlspecialchars($clearance['clearance_number']); ?>"
                                                    data-type="<?php echo htmlspecialchars($clearance['type']); ?>"
                                                    data-issue-date="<?php echo htmlspecialchars($clearance['issue_date']); ?>"
                                                    data-expiry-date="<?php echo htmlspecialchars($clearance['expiry_date']); ?>"
                                                    data-status="<?php echo htmlspecialchars($clearance['status']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($clearance['notes'] ?? ''); ?>">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <?php if ($clearance['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        data-bs-toggle="modal" data-bs-target="#renewClearanceModal"
                                                        data-id="<?php echo $clearance['id']; ?>"
                                                        data-clearance-number="<?php echo htmlspecialchars($clearance['clearance_number']); ?>"
                                                        data-expiry-date="<?php echo htmlspecialchars($clearance['expiry_date']); ?>">
                                                    <i class='bx bx-reset'></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteClearanceModal"
                                                    data-id="<?php echo $clearance['id']; ?>"
                                                    data-clearance-number="<?php echo htmlspecialchars($clearance['clearance_number']); ?>">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class='bx bx-file'></i>
                                        <h5>No clearances found</h5>
                                        <p>There are no clearances matching your criteria.</p>
                                        <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addClearanceModal">
                                            <i class='bx bx-plus'></i> Create New Clearance
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if (count($clearances) > 0): ?>
                <nav aria-label="Clearances pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <li class="page-item active" aria-current="page">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Clearance Modal -->
    <div class="modal fade" id="addClearanceModal" tabindex="-1" aria-labelledby="addClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addClearanceModalLabel">Add New Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="establishment_id" class="form-label">Establishment</label>
                                <select class="form-select" id="establishment_id" name="establishment_id" required>
                                    <option value="">Select Establishment</option>
                                    <?php foreach ($establishments as $establishment): ?>
                                        <option value="<?php echo $establishment['id']; ?>">
                                            <?php echo htmlspecialchars($establishment['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="inspection_id" class="form-label">Inspection</label>
                                <select class="form-select" id="inspection_id" name="inspection_id">
                                    <option value="">Select Inspection (Optional)</option>
                                    <?php foreach ($inspections as $inspection): ?>
                                        <option value="<?php echo $inspection['id']; ?>" data-establishment="<?php echo $inspection['establishment_id']; ?>">
                                            <?php echo htmlspecialchars(date('M j, Y', strtotime($inspection['inspection_date']))) . ' - Est. ' . $inspection['establishment_id']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clearance_number" class="form-label">Clearance Number</label>
                                <input type="text" class="form-control" id="clearance_number" name="clearance_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="fire_safety">Fire Safety</option>
                                    <option value="business_permit">Business Permit</option>
                                    <option value="occupancy">Occupancy</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="issue_date" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_clearance" class="btn btn-primary">Save Clearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Clearance Modal -->
    <div class="modal fade" id="viewClearanceModal" tabindex="-1" aria-labelledby="viewClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewClearanceModalLabel">Clearance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Clearance Number:</strong>
                        <p id="view-clearance-number" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Establishment:</strong>
                        <p id="view-establishment" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Type:</strong>
                        <p id="view-type" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Issue Date:</strong>
                        <p id="view-issue-date" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Expiry Date:</strong>
                        <p id="view-expiry-date" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong>
                        <p id="view-status" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Issued By:</strong>
                        <p id="view-issued-by" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Inspection Date:</strong>
                        <p id="view-inspection-date" class="mb-2"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Notes:</strong>
                        <p id="view-notes" class="mb-2"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Clearance Modal -->
    <div class="modal fade" id="editClearanceModal" tabindex="-1" aria-labelledby="editClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClearanceModalLabel">Edit Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-establishment_id" class="form-label">Establishment</label>
                                <select class="form-select" id="edit-establishment_id" name="establishment_id" required>
                                    <option value="">Select Establishment</option>
                                    <?php foreach ($establishments as $establishment): ?>
                                        <option value="<?php echo $establishment['id']; ?>">
                                            <?php echo htmlspecialchars($establishment['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-inspection_id" class="form-label">Inspection</label>
                                <select class="form-select" id="edit-inspection_id" name="inspection_id">
                                    <option value="">Select Inspection (Optional)</option>
                                    <?php foreach ($inspections as $inspection): ?>
                                        <option value="<?php echo $inspection['id']; ?>" data-establishment="<?php echo $inspection['establishment_id']; ?>">
                                            <?php echo htmlspecialchars(date('M j, Y', strtotime($inspection['inspection_date']))) . ' - Est. ' . $inspection['establishment_id']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-clearance_number" class="form-label">Clearance Number</label>
                                <input type="text" class="form-control" id="edit-clearance_number" name="clearance_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-type" class="form-label">Type</label>
                                <select class="form-select" id="edit-type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="fire_safety">Fire Safety</option>
                                    <option value="business_permit">Business Permit</option>
                                    <option value="occupancy">Occupancy</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-issue_date" class="form-label">Issue Date</label>
                                <input type="date" class="form-control" id="edit-issue_date" name="issue_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="edit-expiry_date" name="expiry_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-status" class="form-label">Status</label>
                                <select class="form-select" id="edit-status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="edit-notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit-notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_clearance" class="btn btn-primary">Update Clearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Renew Clearance Modal -->
    <div class="modal fade" id="renewClearanceModal" tabindex="-1" aria-labelledby="renewClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="renewClearanceModalLabel">Renew Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="renew-id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <p>You are renewing clearance: <strong id="renew-clearance-number"></strong></p>
                            <p>Current expiry date: <strong id="renew-current-expiry"></strong></p>
                        </div>
                        <div class="mb-3">
                            <label for="new_expiry_date" class="form-label">New Expiry Date</label>
                            <input type="date" class="form-control" id="new_expiry_date" name="new_expiry_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="renew_clearance" class="btn btn-primary">Renew Clearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Clearance Modal -->
    <div class="modal fade" id="deleteClearanceModal" tabindex="-1" aria-labelledby="deleteClearanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteClearanceModalLabel">Delete Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="delete-id" name="id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete the clearance: <strong id="delete-clearance-number"></strong>?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_clearance" class="btn btn-danger">Delete Clearance</button>
                    </div>
                </form>
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
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const filterSection = document.getElementById('filterSection');
            filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
        });
        
        // View Clearance Modal
        const viewClearanceModal = document.getElementById('viewClearanceModal');
        viewClearanceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const clearanceNumber = button.getAttribute('data-clearance-number');
            const establishment = button.getAttribute('data-establishment');
            const type = button.getAttribute('data-type');
            const issueDate = button.getAttribute('data-issue-date');
            const expiryDate = button.getAttribute('data-expiry-date');
            const status = button.getAttribute('data-status');
            const issuedBy = button.getAttribute('data-issued-by');
            const notes = button.getAttribute('data-notes');
            const inspectionDate = button.getAttribute('data-inspection-date');
            
            // Format dates
            const formatDate = (dateString) => {
                if (!dateString || dateString === 'N/A') return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            };
            
            // Format type
            const formatType = (type) => {
                const typeMap = {
                    'fire_safety': 'Fire Safety',
                    'business_permit': 'Business Permit',
                    'occupancy': 'Occupancy'
                };
                return typeMap[type] || type;
            };
            
            document.getElementById('view-clearance-number').textContent = clearanceNumber;
            document.getElementById('view-establishment').textContent = establishment;
            document.getElementById('view-type').textContent = formatType(type);
            document.getElementById('view-issue-date').textContent = formatDate(issueDate);
            document.getElementById('view-expiry-date').textContent = formatDate(expiryDate);
            document.getElementById('view-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
            document.getElementById('view-issued-by').textContent = issuedBy;
            document.getElementById('view-notes').textContent = notes || 'No notes available';
            document.getElementById('view-inspection-date').textContent = formatDate(inspectionDate);
        });
        
        // Edit Clearance Modal
        const editClearanceModal = document.getElementById('editClearanceModal');
        editClearanceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit-id').value = button.getAttribute('data-id');
            document.getElementById('edit-establishment_id').value = button.getAttribute('data-establishment-id');
            document.getElementById('edit-inspection_id').value = button.getAttribute('data-inspection-id');
            document.getElementById('edit-clearance_number').value = button.getAttribute('data-clearance-number');
            document.getElementById('edit-type').value = button.getAttribute('data-type');
            document.getElementById('edit-issue_date').value = button.getAttribute('data-issue-date');
            document.getElementById('edit-expiry_date').value = button.getAttribute('data-expiry-date');
            document.getElementById('edit-status').value = button.getAttribute('data-status');
            document.getElementById('edit-notes').value = button.getAttribute('data-notes');
        });
        
        // Renew Clearance Modal
        const renewClearanceModal = document.getElementById('renewClearanceModal');
        renewClearanceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('renew-id').value = button.getAttribute('data-id');
            document.getElementById('renew-clearance-number').textContent = button.getAttribute('data-clearance-number');
            
            const expiryDate = button.getAttribute('data-expiry-date');
            const formattedDate = new Date(expiryDate).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'short', day: 'numeric' 
            });
            document.getElementById('renew-current-expiry').textContent = formattedDate;
            
            // Set default new expiry date to 1 year from today
            const today = new Date();
            const oneYearFromNow = new Date(today.setFullYear(today.getFullYear() + 1));
            const formattedOneYear = oneYearFromNow.toISOString().split('T')[0];
            document.getElementById('new_expiry_date').value = formattedOneYear;
        });
        
        // Delete Clearance Modal
        const deleteClearanceModal = document.getElementById('deleteClearanceModal');
        deleteClearanceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('delete-id').value = button.getAttribute('data-id');
            document.getElementById('delete-clearance-number').textContent = button.getAttribute('data-clearance-number');
        });
        
        // Filter toggle animation
        document.querySelector('.filter-header').addEventListener('click', function() {
            const toggleIcon = this.querySelector('.filter-toggle');
            toggleIcon.classList.toggle('collapsed');
        });
        
        // Filter inspection options based on selected establishment
        document.getElementById('establishment_id').addEventListener('change', function() {
            const establishmentId = this.value;
            const inspectionSelect = document.getElementById('inspection_id');
            
            // Reset to default option
            inspectionSelect.value = '';
            
            // Show/hide options based on establishment
            Array.from(inspectionSelect.options).forEach(option => {
                if (option.value === '') return; // Skip the default option
                
                const optionEstablishmentId = option.getAttribute('data-establishment');
                if (establishmentId === optionEstablishmentId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
        
        // Same for edit modal
        document.getElementById('edit-establishment_id').addEventListener('change', function() {
            const establishmentId = this.value;
            const inspectionSelect = document.getElementById('edit-inspection_id');
            
            // Reset to default option
            inspectionSelect.value = '';
            
            // Show/hide options based on establishment
            Array.from(inspectionSelect.options).forEach(option => {
                if (option.value === '') return; // Skip the default option
                
                const optionEstablishmentId = option.getAttribute('data-establishment');
                if (establishmentId === optionEstablishmentId) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>