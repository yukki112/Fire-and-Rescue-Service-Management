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
$active_submodule = 'repair_management';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get equipment data
    $equipment = $dbManager->fetchAll("fsiet", "SELECT * FROM equipment ORDER BY name");
    
    // Get repair requests with equipment names
    $repair_requests = $dbManager->fetchAll("fsiet", "
        SELECT rr.*, e.name as equipment_name, e.serial_no, e.type as equipment_type,
               CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
        FROM repair_requests rr 
        LEFT JOIN equipment e ON rr.equipment_id = e.id 
        LEFT JOIN frsm.users u ON rr.reported_by = u.id
        ORDER BY rr.reported_date DESC
    ");
    
    // Get vendors
    $vendors = $dbManager->fetchAll("fsiet", "SELECT * FROM vendors WHERE is_active = 1 ORDER BY name");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $equipment = [];
    $repair_requests = [];
    $vendors = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add repair request
        if (isset($_POST['add_repair_request'])) {
            $query = "INSERT INTO repair_requests (equipment_id, reported_by, issue_description, priority, assigned_vendor, estimated_cost) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $params = [
                $_POST['equipment_id'],
                $_SESSION['user_id'],
                $_POST['issue_description'],
                $_POST['priority'],
                $_POST['assigned_vendor'],
                $_POST['estimated_cost']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            // Update equipment status to maintenance
            $update_equipment = "UPDATE equipment SET status = 'maintenance' WHERE id = ?";
            $dbManager->query("fsiet", $update_equipment, [$_POST['equipment_id']]);
            
            $_SESSION['success_message'] = "Repair request submitted successfully!";
            header("Location: rm.php");
            exit;
        }
        
        // Update repair request
        if (isset($_POST['update_repair_request'])) {
            $query = "UPDATE repair_requests SET equipment_id = ?, issue_description = ?, priority = ?, 
                     assigned_vendor = ?, estimated_cost = ?, actual_cost = ?, status = ?, 
                     start_date = ?, completion_date = ?, notes = ? WHERE id = ?";
            
            // Handle date conversion for empty values
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
            
            $params = [
                $_POST['equipment_id'],
                $_POST['issue_description'],
                $_POST['priority'],
                $_POST['assigned_vendor'],
                $_POST['estimated_cost'],
                $_POST['actual_cost'],
                $_POST['status'],
                $start_date,
                $completion_date,
                $_POST['notes'],
                $_POST['request_id']
            ];
            $dbManager->query("fsiet", $query, $params);
            
            // Update equipment status based on repair status
            if ($_POST['status'] === 'completed') {
                $update_equipment = "UPDATE equipment SET status = 'available' WHERE id = ?";
                $dbManager->query("fsiet", $update_equipment, [$_POST['equipment_id']]);
            } elseif ($_POST['status'] === 'in_progress' || $_POST['status'] === 'approved') {
                $update_equipment = "UPDATE equipment SET status = 'maintenance' WHERE id = ?";
                $dbManager->query("fsiet", $update_equipment, [$_POST['equipment_id']]);
            }
            
            $_SESSION['success_message'] = "Repair request updated successfully!";
            header("Location: rm.php");
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Repair operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    try {
        $id = $_GET['id'] ?? 0;
        
        if ($id > 0) {
            // First get the request to know which equipment to update
            $request = $dbManager->fetch("fsiet", "SELECT * FROM repair_requests WHERE id = ?", [$id]);
            
            // Delete the request
            $dbManager->query("fsiet", "DELETE FROM repair_requests WHERE id = ?", [$id]);
            
            // Update equipment status back to available if it was in maintenance due to this request
            if ($request && $request['status'] !== 'completed') {
                $update_equipment = "UPDATE equipment SET status = 'available' WHERE id = ?";
                $dbManager->query("fsiet", $update_equipment, [$request['equipment_id']]);
            }
            
            $_SESSION['success_message'] = "Repair request deleted successfully!";
        }
        
        header("Location: rm.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Delete operation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting repair request: " . $e->getMessage();
        header("Location: rm.php");
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
        .priority-critical {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .priority-high {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .priority-medium {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .priority-low {
            background-color: #cfe2ff;
            border-left: 4px solid #0d6efd;
        }
        .status-pending {
            background-color: #fff3cd;
        }
        .status-approved {
            background-color: #cfe2ff;
        }
        .status-in_progress {
            background-color: #d1e7dd;
        }
        .status-completed {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .status-cancelled {
            background-color: #f8d7da;
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
                     <a href="rm.php" class="sidebar-dropdown-link active">
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
                    <h1>Repair & Out-of-Service Management</h1>
                    <p>Manage equipment repair requests and track out-of-service equipment.</p>
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
            
            <!-- Repair Management Content -->
            <div class="dashboard-content">
                <div class="row">
                    <div class="col-md-4">
                        <div class="inventory-container animate-fade-in">
                            <form method="POST" action="">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-plus'></i>
                                        New Repair Request
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                        <select class="form-select" name="equipment_id" required>
                                            <option value="">Select Equipment</option>
                                            <?php foreach ($equipment as $eq): 
                                                // Only show available or in-use equipment for repair requests
                                                if ($eq['status'] === 'available' || $eq['status'] === 'in-use'): ?>
                                                    <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>) - <?php echo ucfirst($eq['status']); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                                        <select class="form-select" name="priority" required>
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="issue_description" rows="3" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Assigned Vendor</label>
                                        <select class="form-select" name="assigned_vendor">
                                            <option value="">Select Vendor</option>
                                            <?php foreach ($vendors as $vendor): ?>
                                                <option value="<?php echo htmlspecialchars($vendor['name']); ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estimated Cost (₱)</label>
                                        <input type="number" step="0.01" class="form-control" name="estimated_cost">
                                    </div>
                                    <button type="submit" name="add_repair_request" class="btn btn-primary w-100">
                                        <i class='bx bx-plus'></i> Submit Repair Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card animate-fade-in">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Repair Requests</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Equipment</th>
                                                <th>Priority</th>
                                                <th>Reported</th>
                                                <th>Status</th>
                                                <th>Vendor</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($repair_requests) > 0): ?>
                                                <?php foreach ($repair_requests as $rr): 
                                                    $priority_class = '';
                                                    $status_class = '';
                                                    
                                                    switch ($rr['priority']) {
                                                        case 'critical':
                                                            $priority_class = 'priority-critical';
                                                            break;
                                                        case 'high':
                                                            $priority_class = 'priority-high';
                                                            break;
                                                        case 'medium':
                                                            $priority_class = 'priority-medium';
                                                            break;
                                                        case 'low':
                                                            $priority_class = 'priority-low';
                                                            break;
                                                    }
                                                    
                                                    switch ($rr['status']) {
                                                        case 'pending':
                                                            $status_class = 'status-pending';
                                                            break;
                                                        case 'approved':
                                                            $status_class = 'status-approved';
                                                            break;
                                                        case 'in_progress':
                                                            $status_class = 'status-in_progress';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'status-completed';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'status-cancelled';
                                                            break;
                                                    }
                                                ?>
                                                <tr class="<?php echo $priority_class; ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($rr['equipment_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($rr['serial_no']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge status-badge bg-<?php 
                                                            echo $rr['priority'] === 'critical' ? 'danger' : 
                                                                ($rr['priority'] === 'high' ? 'warning' : 
                                                                ($rr['priority'] === 'medium' ? 'success' : 'info')); 
                                                        ?>">
                                                            <?php echo ucfirst($rr['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($rr['reported_date'])); ?>
                                                        <br><small class="text-muted">by <?php echo htmlspecialchars($rr['reported_by_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge status-badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $rr['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($rr['assigned_vendor'] ?? 'Not assigned'); ?></td>
                                                    <td class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRequestModal" 
                                                            data-id="<?php echo $rr['id']; ?>"
                                                            data-equipment="<?php echo $rr['equipment_id']; ?>"
                                                            data-priority="<?php echo $rr['priority']; ?>"
                                                            data-description="<?php echo htmlspecialchars($rr['issue_description']); ?>"
                                                            data-vendor="<?php echo htmlspecialchars($rr['assigned_vendor']); ?>"
                                                            data-estimated="<?php echo $rr['estimated_cost']; ?>"
                                                            data-actual="<?php echo $rr['actual_cost']; ?>"
                                                            data-status="<?php echo $rr['status']; ?>"
                                                            data-start-date="<?php echo $rr['start_date']; ?>"
                                                            data-completion-date="<?php echo $rr['completion_date']; ?>"
                                                            data-notes="<?php echo htmlspecialchars($rr['notes']); ?>">
                                                            <i class='bx bx-edit'></i>
                                                        </button>
                                                        <a href="rm.php?delete=request&id=<?php echo $rr['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this repair request?')">
                                                            <i class='bx bx-trash'></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <p class="text-muted">No repair requests found</p>
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
    
    <!-- Edit Request Modal -->
    <div class="modal fade" id="editRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" id="edit_request_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Repair Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Equipment <span class="text-danger">*</span></label>
                                    <select class="form-select" name="equipment_id" id="edit_equipment_id" required>
                                        <option value="">Select Equipment</option>
                                        <?php foreach ($equipment as $eq): ?>
                                            <option value="<?php echo $eq['id']; ?>"><?php echo htmlspecialchars($eq['name']); ?> (<?php echo htmlspecialchars($eq['serial_no']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select class="form-select" name="priority" id="edit_priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="issue_description" id="edit_description" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assigned Vendor</label>
                                    <select class="form-select" name="assigned_vendor" id="edit_vendor">
                                        <option value="">Select Vendor</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?php echo htmlspecialchars($vendor['name']); ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="edit_status">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Estimated Cost (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="estimated_cost" id="edit_estimated_cost">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Actual Cost (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="actual_cost" id="edit_actual_cost">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" id="edit_start_date">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Completion Date</label>
                                    <input type="date" class="form-control" name="completion_date" id="edit_completion_date">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_repair_request" class="btn btn-primary">Update Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize modals
        const editRequestModal = document.getElementById('editRequestModal');
        if (editRequestModal) {
            editRequestModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('edit_request_id').value = button.getAttribute('data-id');
                document.getElementById('edit_equipment_id').value = button.getAttribute('data-equipment');
                document.getElementById('edit_priority').value = button.getAttribute('data-priority');
                document.getElementById('edit_description').value = button.getAttribute('data-description');
                document.getElementById('edit_vendor').value = button.getAttribute('data-vendor');
                document.getElementById('edit_estimated_cost').value = button.getAttribute('data-estimated');
                document.getElementById('edit_actual_cost').value = button.getAttribute('data-actual');
                document.getElementById('edit_status').value = button.getAttribute('data-status');
                document.getElementById('edit_start_date').value = button.getAttribute('data-start-date');
                document.getElementById('edit_completion_date').value = button.getAttribute('data-completion-date');
                document.getElementById('edit_notes').value = button.getAttribute('data-notes');
            });
        }
        
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>