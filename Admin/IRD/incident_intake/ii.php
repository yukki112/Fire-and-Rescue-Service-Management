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
$active_submodule = 'ii';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get barangays for dropdown
    $barangays = $dbManager->fetchAll("ird", "SELECT DISTINCT barangay FROM incidents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
    
    // Get incident types for dropdown
    $incident_types = $dbManager->fetchAll("ird", "SELECT DISTINCT incident_type FROM incidents WHERE incident_type IS NOT NULL AND incident_type != '' ORDER BY incident_type");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $barangays = [];
    $incident_types = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_incident'])) {
    try {
        // Create new incident
        $query = "INSERT INTO incidents (incident_type, barangay, location, description, injuries, fatalities, people_trapped, hazardous_materials, priority, reported_by, incident_date, incident_time) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())";
        $params = [
            $_POST['incident_type'],
            $_POST['barangay'],
            $_POST['location'],
            $_POST['description'],
            $_POST['injuries'] ?? 0,
            $_POST['fatalities'] ?? 0,
            $_POST['people_trapped'] ?? 0,
            isset($_POST['hazardous_materials']) ? 1 : 0,
            $_POST['priority'],
            $_SESSION['user_id']
        ];
        $dbManager->query("ird", $query, $params);
        
        // Log the action
        $incident_id = $dbManager->getConnection("ird")->lastInsertId();
        $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
        $dbManager->query("ird", $log_query, [$incident_id, $_SESSION['user_id'], 'Incident Created', $_POST['description']]);
        
        $_SESSION['success_message'] = "Incident created successfully!";
        header("Location: ii.php");
        exit;
    } catch (Exception $e) {
        error_log("Form submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error creating incident: " . $e->getMessage();
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
        .incident-form-container {
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
        .priority-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.6em;
        }
        .map-preview {
            height: 300px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }
        .character-count {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
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
                    <a href="ii.php" class="sidebar-dropdown-link active">
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
                    <a href="../../FSIET/equipment_tracking/et.php" class="sidebar-dropdown-link">
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
                    <h1>Incident Intake</h1>
                    <p>Report new incidents and emergencies in Quezon City.</p>
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
            
            <!-- Incident Intake Content -->
            <div class="dashboard-content">
                <div class="row">
                    <div class="col-12">
                        <div class="incident-form-container animate-fade-in">
                            <form method="POST" action="">
                                <!-- Incident Details Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-info-circle'></i>
                                        Incident Details
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Incident Type <span class="text-danger">*</span></label>
                                                <select class="form-select" name="incident_type" required>
                                                    <option value="">Select incident type</option>
                                                    <?php foreach ($incident_types as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type['incident_type']); ?>">
                                                            <?php echo htmlspecialchars($type['incident_type']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="Fire">Fire</option>
                                                    <option value="Medical Emergency">Medical Emergency</option>
                                                    <option value="Rescue">Rescue</option>
                                                    <option value="Hazardous Materials">Hazardous Materials</option>
                                                    <option value="Traffic Accident">Traffic Accident</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Priority <span class="text-danger">*</span></label>
                                                <select class="form-select" name="priority" required>
                                                    <option value="low">Low</option>
                                                    <option value="medium" selected>Medium</option>
                                                    <option value="high">High</option>
                                                    <option value="critical">Critical</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                                <select class="form-select" name="barangay" required>
                                                    <option value="">Select barangay</option>
                                                    <?php foreach ($barangays as $barangay): ?>
                                                        <option value="<?php echo htmlspecialchars($barangay['barangay']); ?>">
                                                            <?php echo htmlspecialchars($barangay['barangay']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="Commonwealth">Commonwealth</option>
                                                    <option value="Batasan Hills">Batasan Hills</option>
                                                    <option value="Payatas">Payatas</option>
                                                    <option value="Bagong Silangan">Bagong Silangan</option>
                                                    <option value="Holy Spirit">Holy Spirit</option>
                                                    <option value="Alicia">Alicia</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="location" placeholder="Enter exact location" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="description" rows="3" placeholder="Provide detailed description of the incident" required onkeyup="updateCharacterCount(this, 'desc-count')"></textarea>
                                        <div class="character-count" id="desc-count">0 characters</div>
                                    </div>
                                </div>
                                
                                <!-- Casualty Information Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-plus-medical'></i>
                                        Casualty Information
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Injuries</label>
                                                <input type="number" class="form-control" name="injuries" value="0" min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Fatalities</label>
                                                <input type="number" class="form-control" name="fatalities" value="0" min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">People Trapped</label>
                                                <input type="number" class="form-control" name="people_trapped" value="0" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="hazardous_materials" id="hazardousCheck">
                                            <label class="form-check-label" for="hazardousCheck">
                                                Hazardous Materials Involved
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Location Mapping Section -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-map'></i>
                                        Location Mapping
                                    </div>
                                    <div class="map-preview">
                                        <div class="text-center">
                                            <i class='bx bx-map-alt' style="font-size: 3rem;"></i>
                                            <p class="mt-2">Map integration will be available soon</p>
                                            <small class="text-muted">Coordinates will be automatically captured</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Section -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-text">
                                        Fields marked with <span class="text-danger">*</span> are required
                                    </div>
                                    <button type="submit" name="create_incident" class="btn btn-primary btn-lg">
                                        <i class='bx bx-plus'></i> Create Incident Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Incidents -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Recent Incidents</h5>
                                <a href="reporting.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Barangay</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Reported</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $recent_incidents = $dbManager->fetchAll("ird", "
                                                    SELECT * FROM incidents 
                                                    ORDER BY created_at DESC 
                                                    LIMIT 5
                                                ");
                                            } catch (Exception $e) {
                                                $recent_incidents = [];
                                            }
                                            ?>
                                            
                                            <?php if (count($recent_incidents) > 0): ?>
                                                <?php foreach ($recent_incidents as $incident): ?>
                                                <tr>
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
                                                    <td colspan="7" class="text-center py-4">No recent incidents</td>
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
        
        // Update character count for textareas
        function updateCharacterCount(textarea, countElementId) {
            const count = textarea.value.length;
            document.getElementById(countElementId).textContent = count + ' characters';
        }
        
        // Initialize form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                let valid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>