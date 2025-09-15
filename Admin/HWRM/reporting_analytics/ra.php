<?php
session_start();

// Include the Database Manager first
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'hwrm';
$active_submodule = 'reporting_analytics';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get hydrant statistics for reporting
    $hydrant_stats = $dbManager->fetchAll("hwrm", 
        "SELECT 
            status,
            COUNT(*) as count,
            AVG(pressure) as avg_pressure,
            AVG(flow_rate) as avg_flow_rate
         FROM hydrants 
         GROUP BY status"
    );
    
    // Get water source statistics
    $water_source_stats = $dbManager->fetchAll("hwrm", 
        "SELECT 
            source_type,
            COUNT(*) as count,
            AVG(pressure) as avg_pressure,
            AVG(flow_rate) as avg_flow_rate
         FROM water_sources 
         GROUP BY source_type"
    );
    
    // Get inspection records for the last 6 months
    $inspection_stats = $dbManager->fetchAll("hwrm", 
        "SELECT 
            MONTH(inspection_date) as month,
            YEAR(inspection_date) as year,
            COUNT(*) as inspection_count,
            AVG(pressure) as avg_pressure,
            AVG(flow_rate) as avg_flow_rate
         FROM water_source_inspections 
         WHERE inspection_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY YEAR(inspection_date), MONTH(inspection_date)
         ORDER BY year, month"
    );
    
    // Get maintenance records
    $maintenance_stats = $dbManager->fetchAll("hwrm", 
        "SELECT 
            maintenance_type,
            COUNT(*) as count,
            AVG(cost) as avg_cost,
            AVG(hours_spent) as avg_hours
         FROM water_source_maintenance 
         GROUP BY maintenance_type"
    );
    
    // Get status change history
    $status_changes = $dbManager->fetchAll("hwrm", 
        "SELECT 
            old_status,
            new_status,
            COUNT(*) as count
         FROM water_source_status_log 
         GROUP BY old_status, new_status"
    );
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $hydrant_stats = [];
    $water_source_stats = [];
    $inspection_stats = [];
    $maintenance_stats = [];
    $status_changes = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process date filter requests
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Display error messages
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// Display success messages
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Prepare month names for JavaScript
$month_names = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
];

// Prepare inspection chart labels in PHP
$inspection_labels = [];
foreach ($inspection_stats as $stat) {
    $inspection_labels[] = $month_names[$stat['month']] . ' ' . $stat['year'];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        .stats-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .stat-card.active {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .stat-card.maintenance {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        .stat-card.inactive {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 300px;
        }
        .report-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .filters-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .progress {
            height: 10px;
        }
    </style>
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="hwrmMenu">
                    <a href="../hydrant_resources_mapping/hrm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Hydrant resources mapping</span>
                    </a>
                      <a href="../water_source_database/wsd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-water'></i>
                        <span>Water Source Database</span>
                    </a>
                     <a href="../water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-droplet'></i>
                        <span>Water Source Status Monitoring</span>
                    </a>
                    <a href="../inspection_maintenance_records/imr.php" class="sidebar-dropdown-link">
                      <i class='bx bx-wrench'></i>
    <span> Inspection & Maintenance Records</span>
                    </a>
                    <a href="../reporting_analytics/ra.php" class="sidebar-dropdown-link active">
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
                    <h1>Reporting & Analytics</h1>
                    <p>Analyze hydrant and water source data with comprehensive reports and visualizations.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class='bx bx-printer'></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Toast Notifications -->
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
            
            <!-- Reporting Content -->
            <div class="dashboard-content">
                <!-- Date Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="filters-container">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class='bx bx-filter'></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-container">
                            <?php
                            $total_hydrants = 0;
                            $active_hydrants = 0;
                            
                            foreach ($hydrant_stats as $stat) {
                                $total_hydrants += $stat['count'];
                                if ($stat['status'] == 'active') {
                                    $active_hydrants = $stat['count'];
                                }
                            }
                            ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $total_hydrants; ?></div>
                                <div class="stat-label">Total Hydrants</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <div class="stat-card active">
                                <div class="stat-number"><?php echo $active_hydrants; ?></div>
                                <div class="stat-label">Active Hydrants</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <?php
                            $total_water_sources = 0;
                            foreach ($water_source_stats as $stat) {
                                $total_water_sources += $stat['count'];
                            }
                            ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $total_water_sources; ?></div>
                                <div class="stat-label">Water Sources</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-container">
                            <?php
                            $total_inspections = 0;
                            foreach ($inspection_stats as $stat) {
                                $total_inspections += $stat['inspection_count'];
                            }
                            ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $total_inspections; ?></div>
                                <div class="stat-label">Inspections (6M)</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row 1 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>Hydrant Status Distribution</h5>
                            <canvas id="hydrantStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>Water Source Types</h5>
                            <canvas id="waterSourceTypeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row 2 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>Monthly Inspections</h5>
                            <canvas id="monthlyInspectionsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>Maintenance Types</h5>
                            <canvas id="maintenanceTypeChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Reports -->
                <div class="row">
                    <div class="col-12">
                        <div class="report-table">
                            <h5>Hydrant Performance Metrics</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Count</th>
                                            <th>Avg Pressure (PSI)</th>
                                            <th>Avg Flow Rate (L/min)</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($hydrant_stats) > 0): ?>
                                            <?php 
                                            $total_hydrants = 0;
                                            foreach ($hydrant_stats as $stat) {
                                                $total_hydrants += $stat['count'];
                                            }
                                            ?>
                                            <?php foreach ($hydrant_stats as $stat): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge status-badge status-<?php echo $stat['status']; ?>">
                                                        <?php echo ucfirst($stat['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $stat['count']; ?></td>
                                                <td><?php echo round($stat['avg_pressure'], 1); ?></td>
                                                <td><?php echo round($stat['avg_flow_rate'], 1); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress w-100 me-2">
                                                            <div class="progress-bar 
                                                                <?php echo $stat['status'] == 'active' ? 'bg-success' : ''; ?>
                                                                <?php echo $stat['status'] == 'maintenance' ? 'bg-warning' : ''; ?>
                                                                <?php echo $stat['status'] == 'inactive' ? 'bg-danger' : ''; ?>"
                                                                role="progressbar" 
                                                                style="width: <?php echo ($stat['count'] / $total_hydrants) * 100; ?>%" 
                                                                aria-valuenow="<?php echo ($stat['count'] / $total_hydrants) * 100; ?>" 
                                                                aria-valuemin="0" 
                                                                aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <span><?php echo round(($stat['count'] / $total_hydrants) * 100, 1); ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <p class="text-muted">No hydrant data available</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Water Source Reports -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="report-table">
                            <h5>Water Source Performance Metrics</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Source Type</th>
                                            <th>Count</th>
                                            <th>Avg Pressure (PSI)</th>
                                            <th>Avg Flow Rate (L/min)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($water_source_stats) > 0): ?>
                                            <?php foreach ($water_source_stats as $stat): ?>
                                            <tr>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $stat['source_type'])); ?></td>
                                                <td><?php echo $stat['count']; ?></td>
                                                <td><?php echo round($stat['avg_pressure'], 1); ?></td>
                                                <td><?php echo round($stat['avg_flow_rate'], 1); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <p class="text-muted">No water source data available</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Hydrant Status Chart
            const hydrantStatusCtx = document.getElementById('hydrantStatusChart').getContext('2d');
            const hydrantStatusChart = new Chart(hydrantStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        'Active', 
                        'Maintenance', 
                        'Inactive'
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            $active = 0;
                            $maintenance = 0;
                            $inactive = 0;
                            
                            foreach ($hydrant_stats as $stat) {
                                if ($stat['status'] == 'active') $active = $stat['count'];
                                if ($stat['status'] == 'maintenance') $maintenance = $stat['count'];
                                if ($stat['status'] == 'inactive') $inactive = $stat['count'];
                            }
                            echo $active . ', ' . $maintenance . ', ' . $inactive;
                            ?>
                        ],
                        backgroundColor: [
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Water Source Type Chart
            const waterSourceTypeCtx = document.getElementById('waterSourceTypeChart').getContext('2d');
            const waterSourceTypeChart = new Chart(waterSourceTypeCtx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php
                        $labels = [];
                        foreach ($water_source_stats as $stat) {
                            $labels[] = "'" . ucfirst(str_replace('_', ' ', $stat['source_type'])) . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php
                            $data = [];
                            foreach ($water_source_stats as $stat) {
                                $data[] = $stat['count'];
                            }
                            echo implode(', ', $data);
                            ?>
                        ],
                        backgroundColor: [
                            '#007bff',
                            '#6610f2',
                            '#6f42c1',
                            '#e83e8c',
                            '#fd7e14',
                            '#20c997'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Monthly Inspections Chart
            const monthlyInspectionsCtx = document.getElementById('monthlyInspectionsChart').getContext('2d');
            const monthlyInspectionsChart = new Chart(monthlyInspectionsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $labels = [];
                        foreach ($inspection_labels as $label) {
                            $labels[] = "'" . $label . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Inspections',
                        data: [
                            <?php
                            $inspectionData = [];
                            foreach ($inspection_stats as $stat) {
                                $inspectionData[] = $stat['inspection_count'];
                            }
                            echo implode(', ', $inspectionData);
                            ?>
                        ],
                        backgroundColor: '#17a2b8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Inspections'
                            }
                        }
                    }
                }
            });
            
            // Maintenance Type Chart
            const maintenanceTypeCtx = document.getElementById('maintenanceTypeChart').getContext('2d');
            const maintenanceTypeChart = new Chart(maintenanceTypeCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $maintenanceLabels = [];
                        foreach ($maintenance_stats as $stat) {
                            $maintenanceLabels[] = "'" . ucfirst(str_replace('_', ' ', $stat['maintenance_type'])) . "'";
                        }
                        echo implode(', ', $maintenanceLabels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Maintenance Count',
                        data: [
                            <?php
                            $maintenanceData = [];
                            foreach ($maintenance_stats as $stat) {
                                $maintenanceData[] = $stat['count'];
                            }
                            echo implode(', ', $maintenanceData);
                            ?>
                        ],
                        backgroundColor: '#6c757d',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Maintenance Tasks'
                            }
                        }
                    }
                }
            });
        });
        
        // Auto-hide toasts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.toast').forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>