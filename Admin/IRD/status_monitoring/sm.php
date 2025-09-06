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
$active_module = 'ird';
$active_submodule = 'status_monitoring';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get incidents for status monitoring
    $incidents = $dbManager->fetchAll("ird", "SELECT * FROM incidents ORDER BY created_at DESC");
    
    // Get units with their current status
    $units = $dbManager->fetchAll("ird", "SELECT * FROM units ORDER BY status, unit_name");
    
    // Get dispatches with unit and incident details
    $dispatches = $dbManager->fetchAll("ird", "
        SELECT d.*, i.incident_type, i.location, i.barangay, i.priority, i.status as incident_status,
               u.unit_name, u.unit_type, u.station
        FROM dispatches d
        LEFT JOIN incidents i ON d.incident_id = i.id
        LEFT JOIN units u ON d.unit_id = u.id
        ORDER BY d.dispatched_at DESC
    ");
    
    // Get status counts for dashboard
    $incident_status_counts = $dbManager->fetchAll("ird", "
        SELECT status, COUNT(*) as count 
        FROM incidents 
        GROUP BY status
    ");
    
    $unit_status_counts = $dbManager->fetchAll("ird", "
        SELECT status, COUNT(*) as count 
        FROM units 
        GROUP BY status
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $incidents = [];
    $units = [];
    $dispatches = [];
    $incident_status_counts = [];
    $unit_status_counts = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_incident_status'])) {
            // Update incident status
            $query = "UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?";
            $params = [$_POST['status'], $_POST['incident_id']];
            $dbManager->query("ird", $query, $params);
            
            // Log the status change
            $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $log_params = [
                $_POST['incident_id'],
                $_SESSION['user_id'],
                'Status Updated',
                "Status changed to " . $_POST['status']
            ];
            $dbManager->query("ird", $log_query, $log_params);
            
            $_SESSION['success_message'] = "Incident status updated successfully!";
            header("Location: sm.php");
            exit;
        }
        
        if (isset($_POST['update_unit_status'])) {
            // Update unit status
            $query = "UPDATE units SET status = ?, updated_at = NOW() WHERE id = ?";
            $params = [$_POST['status'], $_POST['unit_id']];
            $dbManager->query("ird", $query, $params);
            
            $_SESSION['success_message'] = "Unit status updated successfully!";
            header("Location: sm.php");
            exit;
        }
        
        if (isset($_POST['assign_unit'])) {
            // Assign unit to incident
            $query = "INSERT INTO dispatches (incident_id, unit_id, dispatched_at, status) VALUES (?, ?, NOW(), 'dispatched')";
            $params = [$_POST['incident_id'], $_POST['unit_id']];
            $dbManager->query("ird", $query, $params);
            
            // Update incident status to dispatched if it was pending
            $update_incident_query = "UPDATE incidents SET status = 'dispatched', updated_at = NOW() WHERE id = ? AND status = 'pending'";
            $dbManager->query("ird", $update_incident_query, [$_POST['incident_id']]);
            
            // Update unit status to dispatched
            $update_unit_query = "UPDATE units SET status = 'dispatched', updated_at = NOW() WHERE id = ?";
            $dbManager->query("ird", $update_unit_query, [$_POST['unit_id']]);
            
            // Log the assignment
            $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $log_params = [
                $_POST['incident_id'],
                $_SESSION['user_id'],
                'Unit Assigned',
                "Unit " . $_POST['unit_id'] . " assigned to incident"
            ];
            $dbManager->query("ird", $log_query, $log_params);
            
            $_SESSION['success_message'] = "Unit assigned successfully!";
            header("Location: sm.php");
            exit;
        }
        
        if (isset($_POST['update_dispatch_status'])) {
            // Update dispatch status
            $query = "UPDATE dispatches SET status = ? WHERE id = ?";
            $params = [$_POST['status'], $_POST['dispatch_id']];
            $dbManager->query("ird", $query, $params);
            
            // If status is completed, update unit status to available
            if ($_POST['status'] === 'completed') {
                $unit_update_query = "UPDATE units SET status = 'available', updated_at = NOW() WHERE id = (SELECT unit_id FROM dispatches WHERE id = ?)";
                $dbManager->query("ird", $unit_update_query, [$_POST['dispatch_id']]);
                
                // Also update incident status to resolved if all dispatches are completed
                $incident_update_query = "
                    UPDATE incidents 
                    SET status = 'resolved', updated_at = NOW() 
                    WHERE id = (SELECT incident_id FROM dispatches WHERE id = ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM dispatches 
                        WHERE incident_id = (SELECT incident_id FROM dispatches WHERE id = ?) 
                        AND status != 'completed'
                    )
                ";
                $dbManager->query("ird", $incident_update_query, [$_POST['dispatch_id'], $_POST['dispatch_id']]);
            }
            
            $_SESSION['success_message'] = "Dispatch status updated successfully!";
            header("Location: sm.php");
            exit;
        }
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
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
        .status-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .status-card {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .status-pending { border-left: 4px solid #ffc107; background-color: #fffbf0; }
        .status-dispatched { border-left: 4px solid #17a2b8; background-color: #e8f4f8; }
        .status-responding { border-left: 4px solid #fd7e14; background-color: #fff4e8; }
        .status-onscene { border-left: 4px solid #20c997; background-color: #e8f8f4; }
        .status-resolved { border-left: 4px solid #28a745; background-color: #f0f9f0; }
        .status-completed { border-left: 4px solid #28a745; background-color: #f0f9f0; }
        .status-available { border-left: 4px solid #28a745; background-color: #f0f9f0; }
        .status-critical { border-left: 4px solid #dc3545; background-color: #fdf2f2; }
        .status-high { border-left: 4px solid #fd7e14; background-color: #fff4e8; }
        .status-medium { border-left: 4px solid #ffc107; background-color: #fffbf0; }
        .status-low { border-left: 4px solid #17a2b8; background-color: #e8f4f8; }
        
        .badge-pending { background-color: #ffc107; }
        .badge-dispatched { background-color: #17a2b8; }
        .badge-responding { background-color: #fd7e14; }
        .badge-onscene { background-color: #20c997; }
        .badge-resolved { background-color: #28a745; }
        .badge-completed { background-color: #28a745; }
        .badge-available { background-color: #28a745; }
        .badge-critical { background-color: #dc3545; }
        .badge-high { background-color: #fd7e14; }
        .badge-medium { background-color: #ffc107; }
        .badge-low { background-color: #17a2b8; }
        
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .card-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-responsive {
            border-radius: 10px;
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
        
        .incident-details {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .unit-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .unit-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#irdMenu" role="button">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
            
                <div class="sidebar-dropdown collapse show" id="irdMenu">
                    <a href="../../dashboard.php" class="sidebar-dropdown-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="../incident_intake/ii.php" class="sidebar-dropdown-link">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="../incident_location_mapping/ilm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-map'></i>
                        <span>Incident Location Mapping</span>
                    </a>
                    <a href="../unit_assignment/ua.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="../communication/comm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="sm.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-show'></i>
                        <span>Status Monitoring</span>
                    </a>
                    <a href="../reporting/report.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
              <!-- Fire Station Inventory & Equipment Tracking -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="fsietMenu">
                    <a href="../../FSIET/inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="../../equipment_tracking/et.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="../../FSIET/maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../../FSIET/repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../../FSIET/inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
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
                    <a href="../incident_analysis/analysis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-line-chart'></i>
                        <span>Incident Analysis</span>
                    </a>
                    <a href="../lessons_learned/lessons.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-bookmark'></i>
                        <span>Lessons Learned</span>
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
                    <h1>Status Monitoring</h1>
                    <p>Monitor incident and unit status in real-time.</p>
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
            
            <!-- Status Monitoring Content -->
            <div class="dashboard-content">
                <!-- Status Overview Cards -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="status-container animate-fade-in">
                            <h4 class="mb-4">Incident Status Overview</h4>
                            <div class="row">
                                <?php 
                                $status_labels = [
                                    'pending' => 'Pending',
                                    'dispatched' => 'Dispatched',
                                    'responding' => 'Responding',
                                    'resolved' => 'Resolved'
                                ];
                                
                                foreach ($incident_status_counts as $count): 
                                    $status = $count['status'];
                                    $label = $status_labels[$status] ?? ucfirst($status);
                                ?>
                                <div class="col-6 mb-3">
                                    <div class="stats-card status-<?php echo $status; ?>">
                                        <div class="stats-number"><?php echo $count['count']; ?></div>
                                        <div class="stats-label"><?php echo $label; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="status-container animate-fade-in">
                            <h4 class="mb-4">Unit Status Overview</h4>
                            <div class="row">
                                <?php 
                                $unit_status_labels = [
                                    'available' => 'Available',
                                    'dispatched' => 'Dispatched',
                                    'responding' => 'Responding',
                                    'onscene' => 'On Scene',
                                    'returning' => 'Returning'
                                ];
                                
                                foreach ($unit_status_counts as $count): 
                                    $status = $count['status'];
                                    $label = $unit_status_labels[$status] ?? ucfirst($status);
                                ?>
                                <div class="col-6 mb-3">
                                    <div class="stats-card status-<?php echo $status; ?>">
                                        <div class="stats-number"><?php echo $count['count']; ?></div>
                                        <div class="stats-label"><?php echo $label; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Incidents -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="status-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Active Incidents</h4>
                                <span class="badge bg-primary"><?php echo count($incidents); ?> Total</span>
                            </div>
                            
                            <?php if (count($incidents) > 0): ?>
                                <?php foreach ($incidents as $incident): ?>
                                <div class="status-card status-<?php echo $incident['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6>
                                                #<?php echo $incident['id']; ?> - 
                                                <?php echo htmlspecialchars($incident['incident_type']); ?>
                                                <span class="badge status-<?php echo $incident['priority']; ?> ms-2">
                                                    <?php echo ucfirst($incident['priority']); ?>
                                                </span>
                                            </h6>
                                            <p class="mb-1">
                                                <i class='bx bx-map'></i> 
                                                <?php echo htmlspecialchars($incident['location']); ?>, 
                                                <?php echo htmlspecialchars($incident['barangay']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class='bx bx-time'></i> 
                                                <?php echo date('M j, Y H:i', strtotime($incident['incident_date'] . ' ' . $incident['incident_time'])); ?>
                                            </p>
                                            <p class="mb-2"><?php echo htmlspecialchars($incident['description']); ?></p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge badge-<?php echo $incident['status']; ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                            
                                            <!-- Update Status Form -->
                                            <form method="POST" class="mt-2">
                                                <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                                                <div class="input-group input-group-sm">
                                                    <select class="form-select form-select-sm" name="status">
                                                        <option value="pending" <?php echo $incident['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="dispatched" <?php echo $incident['status'] == 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                                        <option value="responding" <?php echo $incident['status'] == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                                        <option value="resolved" <?php echo $incident['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                    <button type="submit" name="update_incident_status" class="btn btn-primary btn-sm">
                                                        <i class='bx bx-refresh'></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Assign Unit Form -->
                                    <div class="mt-3 pt-2 border-top">
                                        <form method="POST" class="row g-2 align-items-center">
                                            <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                                            <div class="col-auto">
                                                <label class="col-form-label">Assign Unit:</label>
                                            </div>
                                            <div class="col-auto">
                                                <select class="form-select form-select-sm" name="unit_id" required>
                                                    <option value="">Select a unit...</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <?php if ($unit['status'] == 'available'): ?>
                                                        <option value="<?php echo $unit['id']; ?>">
                                                            <?php echo htmlspecialchars($unit['unit_name']); ?> - 
                                                            <?php echo htmlspecialchars($unit['unit_type']); ?>
                                                        </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-auto">
                                                <button type="submit" name="assign_unit" class="btn btn-success btn-sm">
                                                    <i class='bx bx-plus'></i> Assign
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-muted">No active incidents found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Unit Status -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="status-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Unit Status</h4>
                                <span class="badge bg-primary"><?php echo count($units); ?> Total</span>
                            </div>
                            
                            <div class="row">
                                <?php if (count($units) > 0): ?>
                                    <?php foreach ($units as $unit): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="unit-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6><?php echo htmlspecialchars($unit['unit_name']); ?></h6>
                                                    <p class="mb-1">
                                                        <i class='bx bx-category'></i> 
                                                        <?php echo htmlspecialchars($unit['unit_type']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class='bx bx-building'></i> 
                                                        <?php echo htmlspecialchars($unit['station']); ?>, 
                                                        <?php echo htmlspecialchars($unit['barangay']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class='bx bx-user'></i> 
                                                        <?php echo $unit['personnel_count']; ?> personnel
                                                    </p>
                                                    <?php if ($unit['specialization']): ?>
                                                    <p class="mb-1">
                                                        <i class='bx bx-star'></i> 
                                                        <?php echo htmlspecialchars($unit['specialization']); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge badge-<?php echo $unit['status']; ?>">
                                                        <?php echo ucfirst($unit['status']); ?>
                                                    </span>
                                                    
                                                    <!-- Update Unit Status Form -->
                                                    <form method="POST" class="mt-2">
                                                        <input type="hidden" name="unit_id" value="<?php echo $unit['id']; ?>">
                                                        <div class="input-group input-group-sm">
                                                            <select class="form-select form-select-sm" name="status">
                                                                <option value="available" <?php echo $unit['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                                                <option value="dispatched" <?php echo $unit['status'] == 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                                                <option value="responding" <?php echo $unit['status'] == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                                                <option value="onscene" <?php echo $unit['status'] == 'onscene' ? 'selected' : ''; ?>>On Scene</option>
                                                                <option value="returning" <?php echo $unit['status'] == 'returning' ? 'selected' : ''; ?>>Returning</option>
                                                            </select>
                                                            <button type="submit" name="update_unit_status" class="btn btn-primary btn-sm">
                                                                <i class='bx bx-refresh'></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center py-4">
                                        <p class="text-muted">No units found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dispatch History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="status-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Dispatch History</h4>
                                <span class="badge bg-primary"><?php echo count($dispatches); ?> Total</span>
                            </div>
                            
                            <?php if (count($dispatches) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Dispatch ID</th>
                                                <th>Unit</th>
                                                <th>Incident</th>
                                                <th>Dispatched At</th>
                                                <th>Arrived At</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dispatches as $dispatch): ?>
                                            <tr>
                                                <td>#<?php echo $dispatch['id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($dispatch['unit_name']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($dispatch['unit_type']); ?></small>
                                                </td>
                                                <td>
                                                    #<?php echo $dispatch['incident_id']; ?> - 
                                                    <?php echo htmlspecialchars($dispatch['incident_type']); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($dispatch['location']); ?>, 
                                                        <?php echo htmlspecialchars($dispatch['barangay']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y H:i', strtotime($dispatch['dispatched_at'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($dispatch['arrived_at']): ?>
                                                        <?php echo date('M j, Y H:i', strtotime($dispatch['arrived_at'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not arrived</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $dispatch['status']; ?>">
                                                        <?php echo ucfirst($dispatch['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="dispatch_id" value="<?php echo $dispatch['id']; ?>">
                                                        <div class="input-group input-group-sm">
                                                            <select class="form-select form-select-sm" name="status">
                                                                <option value="dispatched" <?php echo $dispatch['status'] == 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                                                <option value="responding" <?php echo $dispatch['status'] == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                                                <option value="onscene" <?php echo $dispatch['status'] == 'onscene' ? 'selected' : ''; ?>>On Scene</option>
                                                                <option value="completed" <?php echo $dispatch['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
                                                            <button type="submit" name="update_dispatch_status" class="btn btn-primary btn-sm">
                                                                <i class='bx bx-refresh'></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-muted">No dispatch history found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toast notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const toasts = document.querySelectorAll('.toast');
                toasts.forEach(function(toast) {
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