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
$active_submodule = 'ua';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get incidents for assignment
    $incidents = $dbManager->fetchAll("ird", "SELECT * FROM incidents WHERE status IN ('pending', 'dispatched') ORDER BY created_at DESC");
    
    // Get available units
    $units = $dbManager->fetchAll("ird", "SELECT * FROM units WHERE status = 'available' ORDER BY unit_name");
    
    // Get existing dispatches
    $dispatches = $dbManager->fetchAll("ird", "
        SELECT d.*, i.incident_type, i.location, i.barangay, u.unit_name, u.unit_type 
        FROM dispatches d 
        JOIN incidents i ON d.incident_id = i.id 
        JOIN units u ON d.unit_id = u.id 
        WHERE d.status != 'completed'
        ORDER BY d.dispatched_at DESC
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $incidents = [];
    $units = [];
    $dispatches = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['assign_unit'])) {
            // Assign unit to incident
            $query = "INSERT INTO dispatches (incident_id, unit_id, dispatched_at, status) VALUES (?, ?, NOW(), 'dispatched')";
            $params = [
                $_POST['incident_id'],
                $_POST['unit_id']
            ];
            $dbManager->query("ird", $query, $params);
            
            // Update unit status
            $dbManager->query("ird", "UPDATE units SET status = 'dispatched' WHERE id = ?", [$_POST['unit_id']]);
            
            // Update incident status
            $dbManager->query("ird", "UPDATE incidents SET status = 'dispatched' WHERE id = ?", [$_POST['incident_id']]);
            
            // Log the action
            $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $dbManager->query("ird", $log_query, [
                $_POST['incident_id'], 
                $_SESSION['user_id'], 
                'Unit Assigned', 
                "Unit ID: {$_POST['unit_id']} assigned to incident"
            ]);
            
            $_SESSION['success_message'] = "Unit assigned successfully!";
            header("Location: ua.php");
            exit;
        }
        elseif (isset($_POST['update_status'])) {
            // Update dispatch status
            $dbManager->query("ird", "UPDATE dispatches SET status = ? WHERE id = ?", [
                $_POST['status'],
                $_POST['dispatch_id']
            ]);
            
            // If status is completed, mark unit as available
            if ($_POST['status'] === 'completed') {
                $dispatch = $dbManager->fetch("ird", "SELECT * FROM dispatches WHERE id = ?", [$_POST['dispatch_id']]);
                $dbManager->query("ird", "UPDATE units SET status = 'available' WHERE id = ?", [$dispatch['unit_id']]);
                
                // Check if all dispatches for this incident are completed
                $active_dispatches = $dbManager->fetch("ird", 
                    "SELECT COUNT(*) as count FROM dispatches WHERE incident_id = ? AND status != 'completed'", 
                    [$dispatch['incident_id']]
                );
                
                if ($active_dispatches['count'] == 0) {
                    $dbManager->query("ird", "UPDATE incidents SET status = 'resolved' WHERE id = ?", [$dispatch['incident_id']]);
                }
            }
            
            $_SESSION['success_message'] = "Status updated successfully!";
            header("Location: ua.php");
            exit;
        }
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
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
        .assignment-container {
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
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.6em;
        }
        .card-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-responsive {
            border-radius: 10px;
        }
        .incident-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .unit-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .unit-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .unit-card.available {
            border-left: 4px solid #28a745;
        }
        .unit-card.dispatched {
            border-left: 4px solid #ffc107;
        }
        .unit-card.responding {
            border-left: 4px solid #17a2b8;
        }
        .unit-card.onscene {
            border-left: 4px solid #007bff;
        }
        .unit-card.returning {
            border-left: 4px solid #6c757d;
        }
        .priority-high {
            border-left: 4px solid #dc3545 !important;
        }
        .priority-critical {
            border-left: 4px solid #6f42c1 !important;
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
                    <a href="IRD/dashboard/index.php" class="sidebar-dropdown-link">
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
                    <a href="ua.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="../communication/comm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="../status_monitoring/sm.php" class="sidebar-dropdown-link">
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
                    <a href="analysis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-line-chart'></i>
                        <span>Incident Analysis</span>
                    </a>
                    <a href="lessons.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-bookmark'></i>
                        <span>Lessons Learned</span>
                    </a>
                </div>
                
                <div class="sidebar-section">System</div>
                
                <a href="settings.php" class="sidebar-link">
                    <i class='bx bx-cog'></i>
                    <span class="text">Settings</span>
                </a>
                
                <a href="help.php" class="sidebar-link">
                    <i class='bx bx-help-circle'></i>
                    <span class="text">Help & Support</span>
                </a>
                
                <a href="logout.php" class="sidebar-link">
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
                    <h1>Unit Assignment</h1>
                    <p>Manage emergency unit assignments and dispatch operations.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="dashboard.php" class="btn btn-outline-primary">
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
            
            <!-- Unit Assignment Content -->
            <div class="dashboard-content">
                <!-- Assignment Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="assignment-container animate-fade-in">
                            <form method="POST" action="">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-group'></i>
                                        Assign Unit to Incident
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Incident <span class="text-danger">*</span></label>
                                                <select class="form-select" name="incident_id" required>
                                                    <option value="">Choose an incident...</option>
                                                    <?php foreach ($incidents as $incident): ?>
                                                        <option value="<?php echo $incident['id']; ?>">
                                                            #<?php echo $incident['id']; ?> - 
                                                            <?php echo htmlspecialchars($incident['incident_type']); ?> - 
                                                            <?php echo htmlspecialchars($incident['barangay']); ?>
                                                            (<?php echo strtoupper($incident['priority']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Unit <span class="text-danger">*</span></label>
                                                <select class="form-select" name="unit_id" required>
                                                    <option value="">Choose a unit...</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <option value="<?php echo $unit['id']; ?>">
                                                            <?php echo htmlspecialchars($unit['unit_name']); ?> - 
                                                            <?php echo htmlspecialchars($unit['unit_type']); ?> - 
                                                            <?php echo htmlspecialchars($unit['barangay']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="assign_unit" class="btn btn-primary">
                                            <i class='bx bx-send'></i> Assign Unit
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Active Dispatches -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Active Unit Assignments</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Dispatch ID</th>
                                                <th>Incident</th>
                                                <th>Unit</th>
                                                <th>Dispatched At</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($dispatches) > 0): ?>
                                                <?php foreach ($dispatches as $dispatch): ?>
                                                <tr>
                                                    <td>#<?php echo $dispatch['id']; ?></td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($dispatch['incident_type']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($dispatch['location']); ?>, <?php echo htmlspecialchars($dispatch['barangay']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($dispatch['unit_name']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($dispatch['unit_type']); ?></small>
                                                    </td>
                                                    <td><?php echo date('M j, H:i', strtotime($dispatch['dispatched_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $dispatch['status'] == 'dispatched' ? 'primary' : 
                                                                ($dispatch['status'] == 'responding' ? 'info' : 
                                                                ($dispatch['status'] == 'onscene' ? 'success' : 'secondary')); 
                                                        ?> status-badge">
                                                            <?php echo ucfirst($dispatch['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="dispatch_id" value="<?php echo $dispatch['id']; ?>">
                                                            <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                                                <option value="dispatched" <?php echo $dispatch['status'] == 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                                                <option value="responding" <?php echo $dispatch['status'] == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                                                <option value="onscene" <?php echo $dispatch['status'] == 'onscene' ? 'selected' : ''; ?>>On Scene</option>
                                                                <option value="completed" <?php echo $dispatch['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
                                                            <input type="hidden" name="update_status" value="1">
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">No active unit assignments</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Available Units -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Available Units</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (count($units) > 0): ?>
                                        <?php foreach ($units as $unit): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="unit-card available">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($unit['unit_name']); ?></h6>
                                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($unit['unit_type']); ?></p>
                                                        <p class="mb-1 small">
                                                            <i class='bx bx-map'></i> 
                                                            <?php echo htmlspecialchars($unit['station']); ?>, <?php echo htmlspecialchars($unit['barangay']); ?>
                                                        </p>
                                                        <p class="mb-0 small">
                                                            <i class='bx bx-user'></i> 
                                                            <?php echo $unit['personnel_count']; ?> personnel
                                                        </p>
                                                    </div>
                                                    <span class="badge bg-success">Available</span>
                                                </div>
                                                <?php if ($unit['specialization']): ?>
                                                <div class="mt-2">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($unit['specialization']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center py-4">
                                            <p class="text-muted">No available units at the moment</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Incidents -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Incidents Requiring Response</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Incident ID</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Reported</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($incidents) > 0): ?>
                                                <?php foreach ($incidents as $incident): ?>
                                                <tr class="<?php echo $incident['priority'] == 'high' ? 'priority-high' : ($incident['priority'] == 'critical' ? 'priority-critical' : ''); ?>">
                                                    <td><a href="#" class="text-primary">#<?php echo $incident['id']; ?></a></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['barangay']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $incident['priority'] == 'critical' ? 'danger' : 
                                                                ($incident['priority'] == 'high' ? 'warning' : 
                                                                ($incident['priority'] == 'medium' ? 'info' : 'secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst($incident['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $incident['status'] == 'resolved' ? 'success' : 
                                                                ($incident['status'] == 'responding' ? 'info' : 
                                                                ($incident['status'] == 'dispatched' ? 'primary' : 'secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst($incident['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, H:i', strtotime($incident['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">No incidents requiring response</td>
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
            
            <!-- Footer -->
            <footer class="dashboard-footer">
                <div class="footer-content">
                    <div class="footer-logo">
                        <img src="img/frsmse.png" alt="Quezon City Logo">
                        <span>Quezon City Fire & Rescue Service Management 2025</span>
                    </div>
                    <div class="footer-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Contact Us</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toasts after 5 seconds
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