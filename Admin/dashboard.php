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

// Get active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'dashboard';

// Get active submodule from URL parameter
$active_submodule = $_GET['submodule'] ?? '';

$active_module = $_GET['module'] ?? '';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get recent incidents
    $recent_incidents = $dbManager->fetchAll("ird", "
        SELECT * FROM incidents 
        ORDER BY created_at DESC 
        LIMIT 10
    ");

    // Get available units
    $available_units = $dbManager->fetchAll("ird", "
        SELECT * FROM units 
        WHERE status = 'available' 
        ORDER BY unit_name
    ");

    // Get all units
    $all_units = $dbManager->fetchAll("ird", "
        SELECT * FROM units 
        ORDER BY unit_name
    ");

    // Get active dispatches
    $active_dispatches = $dbManager->fetchAll("ird", "
        SELECT d.*, u.unit_name, u.unit_type, i.incident_type, i.location 
        FROM dispatches d 
        JOIN units u ON d.unit_id = u.id 
        JOIN incidents i ON d.incident_id = i.id 
        WHERE d.status IN ('dispatched', 'responding', 'onscene')
        ORDER BY d.dispatched_at DESC 
        LIMIT 5
    ");

    // Calculate stats for dashboard
    $active_incidents = $dbManager->fetch("ird", 
        "SELECT COUNT(*) as count FROM incidents WHERE status IN ('pending', 'dispatched', 'responding')"
    )['count'];
    
    $critical_incidents = $dbManager->fetch("ird", 
        "SELECT COUNT(*) as count FROM incidents WHERE priority = 'critical' AND status IN ('pending', 'dispatched', 'responding')"
    )['count'];
    
    $responding_units = $dbManager->fetch("ird", 
        "SELECT COUNT(*) as count FROM units WHERE status IN ('dispatched', 'responding', 'onscene')"
    )['count'];
    
    $on_scene_personnel = $dbManager->fetch("ird", 
        "SELECT SUM(personnel_count) as count FROM units WHERE status = 'onscene'"
    )['count'] ?? 0;
    
    $today = date('Y-m-d');
    $resolved_incidents = $dbManager->fetch("ird", 
        "SELECT COUNT(*) as count FROM incidents WHERE status = 'resolved' AND DATE(created_at) = ?", 
        [$today]
    )['count'];
    
    $available_units_count = $dbManager->fetch("ird", 
        "SELECT COUNT(*) as count FROM units WHERE status = 'available'"
    )['count'];
    
    // Weather data (mock data for now)
    $weather_data = [
        'temperature' => rand(25, 35),
        'condition' => 'Partly Cloudy',
        'humidity' => rand(60, 85),
        'wind_speed' => rand(5, 15),
        'pressure' => rand(1000, 1020),
        'icon' => '02d'
    ];
    
    // Get data for Fire Station Inventory & Equipment Tracking
    $inventory_items = $dbManager->fetchAll("fsiet", "
        SELECT i.*, c.name as category_name 
        FROM inventory_items i 
        LEFT JOIN inventory_categories c ON i.category_id = c.id 
        ORDER BY i.name 
        LIMIT 10
    ");
    
    $equipment_list = $dbManager->fetchAll("fsiet", "
        SELECT * FROM equipment 
        ORDER BY name 
        LIMIT 10
    ");
    
    $maintenance_schedules = $dbManager->fetchAll("fsiet", "
        SELECT ms.*, e.name as equipment_name 
        FROM maintenance_schedules ms 
        LEFT JOIN equipment e ON ms.equipment_id = e.id 
        WHERE ms.status = 'pending' 
        ORDER BY ms.next_maintenance 
        LIMIT 10
    ");
    
    // Get data for Hydrant and Water Resource Mapping
    $hydrants = $dbManager->fetchAll("hwrm", "
        SELECT * FROM hydrants 
        ORDER BY hydrant_id 
        LIMIT 10
    ");
    
    $water_sources = $dbManager->fetchAll("hwrm", "
        SELECT * FROM water_sources 
        ORDER BY name 
        LIMIT 10
    ");
    
    // Get data for Personnel Shift Scheduling
    $shift_schedules = $dbManager->fetchAll("pss", "
        SELECT ss.*, st.name as shift_name, st.start_time, st.end_time 
        FROM shift_schedules ss 
        LEFT JOIN shift_types st ON ss.shift_type_id = st.id 
        WHERE ss.date >= CURDATE() 
        ORDER BY ss.date, st.start_time 
        LIMIT 10
    ");
    
    $leave_requests = $dbManager->fetchAll("pss", "
        SELECT lr.*, lt.name as leave_type_name 
        FROM leave_requests lr 
        LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
        WHERE lr.status = 'pending' 
        ORDER BY lr.created_at DESC 
        LIMIT 10
    ");
    
    // Get data for Training and Certification Records
    $training_sessions = $dbManager->fetchAll("tcr", "
        SELECT ts.*, tc.course_name 
        FROM training_sessions ts 
        LEFT JOIN training_courses tc ON ts.course_id = tc.id 
        WHERE ts.status IN ('scheduled', 'ongoing') 
        ORDER BY ts.start_date 
        LIMIT 10
    ");
    
    $certifications = $dbManager->fetchAll("tcr", "
        SELECT * FROM certifications 
        WHERE status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        ORDER BY expiry_date 
        LIMIT 10
    ");
    
    // Get data for Fire Inspection and Compliance Records
    $inspection_schedules = $dbManager->fetchAll("ficr", "
        SELECT ins.*, e.name as establishment_name 
        FROM inspection_schedules ins 
        LEFT JOIN establishments e ON ins.establishment_id = e.id 
        WHERE ins.status = 'scheduled' 
        ORDER BY ins.scheduled_date 
        LIMIT 10
    ");
    
    $violations = $dbManager->fetchAll("ficr", "
        SELECT v.*, e.name as establishment_name 
        FROM violations v 
        LEFT JOIN inspection_results ir ON v.inspection_id = ir.id 
        LEFT JOIN establishments e ON ir.establishment_id = ir.id 
        WHERE v.status IN ('open', 'in_progress') 
        ORDER BY v.deadline 
        LIMIT 10
    ");
    
    // Get data for Post-Incident Analysis and Reporting
    $analysis_reports = $dbManager->fetchAll("piar", "
        SELECT * FROM incident_analysis_reports 
        WHERE status IN ('draft', 'submitted') 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $lessons_learned = $dbManager->fetchAll("piar", "
        SELECT * FROM lessons_learned 
        WHERE implementation_status = 'pending' 
        ORDER BY priority DESC, created_at DESC 
        LIMIT 10
    ");
    
    // Get data for submodules
    if ($active_tab == 'modules') {
        // For incident intake - get incident types for classification
        $incident_types = $dbManager->fetchAll("ird", "SELECT DISTINCT incident_type FROM incidents ORDER BY incident_type");
        
        // For mapping - get incidents with coordinates
        $mapped_incidents = $dbManager->fetchAll("ird", 
            "SELECT * FROM incidents WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY created_at DESC LIMIT 20"
        );
        
        // For assignment - get all units with status
        $all_units_with_status = $dbManager->fetchAll("ird", "SELECT * FROM units ORDER BY status, unit_name");
        
        // For communication - get recent communications
        $recent_communications = $dbManager->fetchAll("ird", "SELECT * FROM communications ORDER BY created_at DESC LIMIT 10");
        
        // For monitoring - get incidents with timestamps
        $monitored_incidents = $dbManager->fetchAll("ird", 
            "SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago FROM incidents WHERE status != 'resolved' ORDER BY priority DESC, created_at DESC"
        );
        
        // For reporting - get report types
        $report_types = $dbManager->fetchAll("ird", "SELECT DISTINCT report_type FROM reports ORDER BY report_type");
        
        // For access control - get users and logs
        $system_users = $dbManager->fetchAll("frsm", "SELECT * FROM users ORDER BY is_admin DESC, created_at DESC");
        $access_logs = $dbManager->fetchAll("frsm", "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 20");
    }
    
    // Get additional stats for all modules for the dashboard
    $fsiet_stats = [
        'total_inventory' => $dbManager->fetch("fsiet", "SELECT COUNT(*) as count FROM inventory_items")['count'],
        'low_stock' => $dbManager->fetch("fsiet", "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'low_stock'")['count'],
        'maintenance_due' => $dbManager->fetch("fsiet", "SELECT COUNT(*) as count FROM maintenance_schedules WHERE status = 'pending' AND next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")['count']
    ];
    
    $hwrm_stats = [
        'total_hydrants' => $dbManager->fetch("hwrm", "SELECT COUNT(*) as count FROM hydrants")['count'],
        'inactive_hydrants' => $dbManager->fetch("hwrm", "SELECT COUNT(*) as count FROM hydrants WHERE status != 'active'")['count'],
        'water_sources' => $dbManager->fetch("hwrm", "SELECT COUNT(*) as count FROM water_sources")['count']
    ];
    
    $pss_stats = [
        'total_shifts' => $dbManager->fetch("pss", "SELECT COUNT(*) as count FROM shift_schedules WHERE date >= CURDATE()")['count'],
        'pending_leave' => $dbManager->fetch("pss", "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")['count'],
        'active_personnel' => $dbManager->fetch("pss", "SELECT COUNT(DISTINCT employee_id) as count FROM shift_schedules WHERE date = CURDATE()")['count']
    ];
    
    $tcr_stats = [
        'active_training' => $dbManager->fetch("tcr", "SELECT COUNT(*) as count FROM training_sessions WHERE status IN ('scheduled', 'ongoing')")['count'],
        'certifications_expiring' => $dbManager->fetch("tcr", "SELECT COUNT(*) as count FROM certifications WHERE status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")['count'],
        'total_courses' => $dbManager->fetch("tcr", "SELECT COUNT(*) as count FROM training_courses WHERE status = 'active'")['count']
    ];
    
    $ficr_stats = [
        'scheduled_inspections' => $dbManager->fetch("ficr", "SELECT COUNT(*) as count FROM inspection_schedules WHERE status = 'scheduled'")['count'],
        'open_violations' => $dbManager->fetch("ficr", "SELECT COUNT(*) as count FROM violations WHERE status IN ('open', 'in_progress')")['count'],
        'total_establishments' => $dbManager->fetch("ficr", "SELECT COUNT(*) as count FROM establishments WHERE status = 'active'")['count']
    ];
    
    $piar_stats = [
        'pending_analysis' => $dbManager->fetch("piar", "SELECT COUNT(*) as count FROM incident_analysis_reports WHERE status IN ('draft', 'submitted')")['count'],
        'pending_lessons' => $dbManager->fetch("piar", "SELECT COUNT(*) as count FROM lessons_learned WHERE implementation_status = 'pending'")['count'],
        'completed_reports' => $dbManager->fetch("piar", "SELECT COUNT(*) as count FROM incident_analysis_reports WHERE status = 'approved'")['count']
    ];
    
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log("Database error: " . $e->getMessage());
    $recent_incidents = [];
    $available_units = [];
    $all_units = [];
    $active_dispatches = [];
    $active_incidents = 0;
    $critical_incidents = 0;
    $responding_units = 0;
    $on_scene_personnel = 0;
    $resolved_incidents = 0;
    $available_units_count = 0;
    $weather_data = [
        'temperature' => 30,
        'condition' => 'Sunny',
        'humidity' => 75,
        'wind_speed' => 10,
        'pressure' => 1010,
        'icon' => '01d'
    ];
    $user = ['first_name' => 'User'];
    $error_message = "System temporarily unavailable. Please try again later.";
    
    // Default empty arrays for submodule data
    $incident_types = [];
    $mapped_incidents = [];
    $all_units_with_status = [];
    $recent_communications = [];
    $monitored_incidents = [];
    $report_types = [];
    $system_users = [];
    $access_logs = [];
    
    // Default empty stats
    $fsiet_stats = ['total_inventory' => 0, 'low_stock' => 0, 'maintenance_due' => 0];
    $hwrm_stats = ['total_hydrants' => 0, 'inactive_hydrants' => 0, 'water_sources' => 0];
    $pss_stats = ['total_shifts' => 0, 'pending_leave' => 0, 'active_personnel' => 0];
    $tcr_stats = ['active_training' => 0, 'certifications_expiring' => 0, 'total_courses' => 0];
    $ficr_stats = ['scheduled_inspections' => 0, 'open_violations' => 0, 'total_establishments' => 0];
    $piar_stats = ['pending_analysis' => 0, 'pending_lessons' => 0, 'completed_reports' => 0];
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_incident'])) {
            // Create new incident
            $query = "INSERT INTO incidents (incident_type, barangay, location, description, injuries, fatalities, people_trapped, hazardous_materials, priority, reported_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
        }
        elseif (isset($_POST['dispatch_unit'])) {
            // Dispatch unit
            $query = "INSERT INTO dispatches (incident_id, unit_id, dispatched_at, status) VALUES (?, ?, NOW(), 'dispatched')";
            $dbManager->query("ird", $query, [$_POST['incident_id'], $_POST['unit_id']]);
            
            // Update unit status
            $update_query = "UPDATE units SET status = 'dispatched' WHERE id = ?";
            $dbManager->query("ird", $update_query, [$_POST['unit_id']]);
            
            // Update incident status
            $incident_query = "UPDATE incidents SET status = 'dispatched' WHERE id = ?";
            $dbManager->query("ird", $incident_query, [$_POST['incident_id']]);
            
            // Log the action
            $unit = $dbManager->fetch("ird", "SELECT unit_name FROM units WHERE id = ?", [$_POST['unit_id']]);
            $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $dbManager->query("ird", $log_query, [
                $_POST['incident_id'], 
                $_SESSION['user_id'], 
                'Unit Dispatched', 
                "Unit {$unit['unit_name']} dispatched to incident."
            ]);
            
            $_SESSION['success_message'] = "Unit dispatched successfully!";
            header("Location: ua.php");
            exit;
        }
        elseif (isset($_POST['update_status'])) {
            // Update incident status
            $query = "UPDATE incidents SET status = ? WHERE id = ?";
            $dbManager->query("ird", $query, [$_POST['status'], $_POST['incident_id']]);
            
            // Log the action
            $log_query = "INSERT INTO incident_logs (incident_id, user_id, action, details) VALUES (?, ?, ?, ?)";
            $dbManager->query("ird", $log_query, [
                $_POST['incident_id'], 
                $_SESSION['user_id'], 
                'Status Updated', 
                "Status changed to {$_POST['status']}"
            ]);
            
            $_SESSION['success_message'] = "Incident status updated successfully!";
            header("Location: sm.php");
            exit;
        }
        elseif (isset($_POST['send_message'])) {
            // Send communication
            $query = "INSERT INTO communications (channel, sender, receiver, message, incident_id) VALUES (?, ?, ?, ?, ?)";
            $dbManager->query("ird", $query, [
                $_POST['channel'],
                $_SESSION['user_name'] ?? 'System',
                $_POST['receiver'],
                $_POST['message'],
                $_POST['incident_id'] ?? null
            ]);
            
            $_SESSION['success_message'] = "Message sent successfully!";
            header("Location: comm.php");
            exit;
        }
        elseif (isset($_POST['generate_report'])) {
            // Generate report
            $query = "INSERT INTO reports (report_type, title, description, start_date, end_date, barangay_filter, incident_type_filter, format, generated_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $dbManager->query("ird", $query, [
                $_POST['report_type'],
                $_POST['title'],
                $_POST['description'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['barangay_filter'] ?? null,
                $_POST['incident_type_filter'] ?? null,
                $_POST['format'],
                $_SESSION['user_id']
            ]);
            
            $_SESSION['success_message'] = "Report generated successfully!";
            header("Location: reporting.php");
            exit;
        }
        elseif (isset($_POST['add_unit'])) {
            // Add new unit
            $query = "INSERT INTO units (unit_name, unit_type, station, barangay, personnel_count, equipment, specialization, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $dbManager->query("ird", $query, [
                $_POST['unit_name'],
                $_POST['unit_type'],
                $_POST['station'],
                $_POST['barangay'],
                $_POST['personnel_count'],
                $_POST['equipment'],
                $_POST['specialization'],
                'available'
            ]);
            
            $_SESSION['success_message'] = "Unit added successfully!";
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
                
                <a href="dashboard.php" class="sidebar-link <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
                
                <div class="sidebar-section">Modules</div>
                
                <!-- Incident Response Dispatch -->
                <a class="sidebar-link dropdown-toggle <?php echo $active_module == 'ird' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#irdMenu" role="button">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
            
                <div class="sidebar-dropdown collapse <?php echo $active_module == 'ird' ? 'show' : ''; ?>" id="irdMenu">
                   
                    <a href="IRD/incident_intake/ii.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'ii' ? 'active' : ''; ?>">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="IRD/incident_location_mapping/ilm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'mapping' ? 'active' : ''; ?>">
                        <i class='bx bx-map'></i>
                        <span>Incident Location Mapping</span>
                    </a>
                    <a href="IRD/unit_assignment/ua.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'ua' ? 'active' : ''; ?>">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="IRD/communication/comm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'comm' ? 'active' : ''; ?>">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="IRD/status_monitoring/sm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'sm' ? 'active' : ''; ?>">
                        <i class='bx bx-show'></i>
                        <span>Status Monitoring</span>
                    </a>
                    <a href="IRD/reporting/report.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'reporting' ? 'active' : ''; ?>">
                        <i class='bx bx-file'></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
                <!-- Fire Station Inventory & Equipment Tracking -->
                <a class="sidebar-link dropdown-toggle <?php echo $active_module == 'fsiet' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Fire Station Inventory & Equipment Tracking</span>
                </a>
                <div class="sidebar-dropdown collapse <?php echo $active_module == 'fsiet' ? 'show' : ''; ?>" id="fsietMenu">
                    <a href="FSIET/inventory_management/im.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'inventory' ? 'active' : ''; ?>">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="FSIET/equipment_location_tracking/elt.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'equipment' ? 'active' : ''; ?>">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="FSIET/maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'maintenance' ? 'active' : ''; ?>">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="FSIET/repair_management/rm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'maintenance' ? 'active' : ''; ?>">
                        <i class='bx bx-calendar'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="FSIET/inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'maintenance' ? 'active' : ''; ?>">
                        <i class='bx bx-calendar'></i>
                        <span>Inventory Reports & Audit Logs</span>
                    </a>
                    
                </div>
                
                <!-- Hydrant and Water Resource Mapping -->
                <a class="sidebar-link dropdown-toggle <?php echo $active_module == 'hwrm' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse <?php echo $active_module == 'hwrm' ? 'show' : ''; ?>" id="hwrmMenu">
                    <a href="HWRM/hydrant_resources_mapping/hrm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'hydrants' ? 'active' : ''; ?>">
                        <i class='bx bx-map-alt'></i>
                        <span> Hydrant resources mapping</span>
                    </a>
                    <a href="HWRM/water_source_database/wsd.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'water-sources' ? 'active' : ''; ?>">
                        <i class='bx bx-water'></i>
                        <span> Water Source Database</span>
                    </a>
                   <a href="HWRM/water_source_status_monitoring/wssm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'water-sources-status' ? 'active' : ''; ?>">
    <i class='bx bx-droplet'></i>
    <span> Water Source Status Monitoring</span>
</a>  

<a href="HWRM/inspection_maintenance_records/imr.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'inspection-records' ? 'active' : ''; ?>">
    <i class='bx bx-wrench'></i>
    <span> Inspection & Maintenance Records</span>
</a>   

<a href="HWRM/reporting_analytics/ra.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'reporting-analytics' ? 'active' : ''; ?>">
    <i class='bx bx-bar-chart-alt-2'></i>
    <span> Reporting & Analytics</span>
</a>   


                </div>
                
                
                <!-- Personnel Shift Scheduling -->
                <a class="sidebar-link dropdown-toggle <?php echo $active_module == 'pss' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#pssMenu" role="button">
                    <i class='bx bx-calendar-event'></i>
                    <span class="text">Shift Scheduling</span>
                </a>
              <div class="sidebar-dropdown collapse <?php echo $active_module == 'pss' ? 'show' : ''; ?>" id="pssMenu">
    <a href="PSS/shift_calendar_management/scm.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'shifts' ? 'active' : ''; ?>">
        <i class='bx bx-time'></i>
        <span>Shift Calendar Management</span>
    </a>

    <a href="PSS/personel_roster/pr.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'roster' ? 'active' : ''; ?>">
        <i class='bx bx-group'></i>
        <span>Personnel Roster</span>
    </a>

    <a href="PSS/shift_assignment/sa.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'assignment' ? 'active' : ''; ?>">
        <i class='bx bx-task'></i>
        <span>Shift Assignment</span>
    </a>

    <a href="PSS/leave_and_absence_management/laam.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'leave' ? 'active' : ''; ?>">
        <i class='bx bx-user-x'></i>
        <span>Leave and Absence Management</span>
    </a>

    <a href="PSS/notifications_and_alert/naa.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'notifications' ? 'active' : ''; ?>">
        <i class='bx bx-bell'></i>
        <span>Notifications and Alerts</span>
    </a>

    <a href="PSS/reporting_and_logs/ral.php" 
       class="sidebar-dropdown-link <?php echo $active_submodule == 'reports' ? 'active' : ''; ?>">
        <i class='bx bx-file'></i>
        <span>Reporting & Logs</span>
    </a>

    
</div>

                
               <!-- Training and Certification Records -->
<a class="sidebar-link dropdown-toggle <?php echo $active_module == 'tcr' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#tcrMenu" role="button">
    <i class='bx bx-medal'></i>
    <span class="text">Training and Certification <br>Records</span>
</a>
<div class="sidebar-dropdown collapse <?php echo $active_module == 'tcr' ? 'show' : ''; ?>" id="tcrMenu">

    <!-- Personnel Training Profiles -->
    <a href="TCR/personnel_training_profile/ptr.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'training' ? 'active' : ''; ?>">
        <i class='bx bx-book-reader'></i>
        <span>Personnel Training Profiles</span>
    </a>

    <!-- Training Course Management -->
    <a href="TCR/training_course_management/tcm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'course-management' ? 'active' : ''; ?>">
        <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
    </a>

    <!-- Training Calendar and Scheduling -->
    <a href="TCR/training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'calendar' ? 'active' : ''; ?>">
        <i class='bx bx-calendar'></i>
        <span>Training Calendar and Scheduling</span>
    </a>

    <!-- Certification Tracking -->
    <a href="TCR/certification_tracking/ct.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'certification-tracking' ? 'active' : ''; ?>">
        <i class='bx bx-badge-check'></i>
        <span>Certification Tracking</span>
    </a>

    <!-- Training Compliance Monitoring -->
    <a href="TCR/training_compliance_monitoring/tcm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'compliance' ? 'active' : ''; ?>">
        <i class='bx bx-check-shield'></i>
        <span>Training Compliance Monitoring</span>
    </a>

    <!-- Evaluation and Assessment Records -->
    <a href="TCR/evaluation_and_assessment_recoreds/eaar.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'evaluation' ? 'active' : ''; ?>">
        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
    </a>

    <!-- Reporting and Audit Logs -->
    <a href="TCR/reporting_and_auditlogs/ral.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'reporting' ? 'active' : ''; ?>">
        <i class='bx bx-file'></i>
        <span>Reporting and Audit Logs</span>
    </a>
</div>

                



               <!-- Fire Inspection and Compliance Records -->
<a class="sidebar-link dropdown-toggle <?php echo $active_module == 'ficr' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#ficrMenu" role="button">
    <i class='bx bx-clipboard'></i>
    <span class="text">Inspection & Compliance</span>
</a>
<div class="sidebar-dropdown collapse <?php echo $active_module == 'ficr' ? 'show' : ''; ?>" id="ficrMenu">

    <!-- Establishment/Property Registry -->
    <a href="FICR/establishment_registry/er.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'establishment_registry' ? 'active' : ''; ?>">
        <i class='bx bx-building-house'></i>
        <span>Establishment/Property Registry</span>
    </a>

    <!-- Inspection Scheduling and Assignment -->
    <a href="FICR/inspection_scheduling_and_assignment/isaa.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'inspection_scheduling' ? 'active' : ''; ?>">
        <i class='bx bx-calendar-event'></i>
        <span>Inspection Scheduling and Assignment</span>
    </a>

    <!-- Inspection Checklist Management -->
    <a href="FICR/inspection_checklist_management/icm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'inspection_checklist' ? 'active' : ''; ?>">
        <i class='bx bx-list-check'></i>
        <span>Inspection Checklist Management</span>
    </a>

    <!-- Violation and Compliance Tracking -->
    <a href="FICR/violation_and_compliance_tracking/vact.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'violation_tracking' ? 'active' : ''; ?>">
        <i class='bx bx-shield-x'></i>
        <span>Violation and Compliance Tracking</span>
    </a>

    <!-- Clearance and Certification Management -->
    <a href="FICR/clearance_and_certification_management/cacm.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'clearance_certification' ? 'active' : ''; ?>">
        <i class='bx bx-file'></i>
        <span>Clearance and Certification Management</span>
    </a>

    <!-- Reporting and Analytics -->
    <a href="FICR/reporting_and_analytics/raa.php" class="sidebar-dropdown-link <?php echo $active_submodule == 'reporting_analytics' ? 'active' : ''; ?>">
        <i class='bx bx-bar-chart-alt-2'></i>
        <span>Reporting and Analytics</span>
    </a>
</div>

                
                <!-- Post-Incident Analysis and Reporting -->
                <a class="sidebar-link dropdown-toggle <?php echo $active_module == 'piar' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#piarMenu" role="button">
                    <i class='bx bx-analyse'></i>
                    <span class="text">Post-Incident Analysis</span>
                </a>
                <div class="sidebar-dropdown collapse <?php echo $active_module == 'piar' ? 'show' : ''; ?>" id="piarMenu">
                  <a href="PIAR/incident_summary_documentation/isd.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'isd' ? 'active' : ''; ?>">
    <i class='bx bx-file'></i>
    <span>Incident Summary Documentation</span>
</a>

<a href="PIAR/response_timeline_tracking/rtt.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'rtt' ? 'active' : ''; ?>">
    <i class='bx bx-time-five'></i>
    <span>Response Timeline Tracking</span>
</a>

<a href="PIAR/personnel_and_unit_involvement/paui.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'paui' ? 'active' : ''; ?>">
    <i class='bx bx-group'></i>
    <span>Personnel and Unit Involvement</span>
</a>

<a href="PIAR/cause_and_origin_investigation/caoi.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'caoi' ? 'active' : ''; ?>">
    <i class='bx bx-search-alt'></i>
    <span>Cause and Origin Investigation</span>
</a>

<a href="PIAR/damage_assessment/da.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'da' ? 'active' : ''; ?>">
    <i class='bx bx-building-house'></i>
    <span>Damage Assessment</span>
</a>

<a href="PIAR/action_review_and_lessons_learned/arall.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'arall' ? 'active' : ''; ?>">
    <i class='bx bx-refresh'></i>
    <span>Action Review and Lessons Learned</span>
</a>

<a href="PIAR/report_generation_and_archiving/rgaa.php" 
   class="sidebar-dropdown-link <?php echo $active_submodule == 'rgaa' ? 'active' : ''; ?>">
    <i class='bx bx-archive'></i>
    <span>Report Generation and Archiving</span>
</a>

                </div>
                
                <div class="sidebar-section">System</div>
                
                <a href="SETTINGS/settings.php" class="sidebar-link <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
                    <i class='bx bx-cog'></i>
                    <span class="text">Settings</span>
                </a>
                
                <a href="help.php" class="sidebar-link <?php echo $active_tab == 'help' ? 'active' : ''; ?>">
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
                    <h1>Dashboardss</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?>! Here's an overview of the system.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary">
                            <i class='bx bx-refresh'></i> Refresh Data
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
                            <i class='bx bx-plus'></i> New Incident
                        </button>
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
            
            <!-- Weather Widget -->
            <div class="weather-widget animate-fade-in">
                <div class="weather-temp"><?php echo $weather_data['temperature']; ?>°C</div>
                <div class="weather-condition"><?php echo $weather_data['condition']; ?></div>
                <div class="weather-details">
                    <div class="weather-detail">
                        <i class='bx bx-water'></i>
                        <span>Humidity: <?php echo $weather_data['humidity']; ?>%</span>
                    </div>
                    <div class="weather-detail">
                        <i class='bx bx-wind'></i>
                        <span>Wind: <?php echo $weather_data['wind_speed']; ?> km/h</span>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Incident Response Stats -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card stat-card-danger animate-fade-in">
                            <div class="stat-icon">
                                <i class='bx bx-error'></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $active_incidents; ?></div>
                                <div class="stat-label">Active Incidents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-warning animate-fade-in" style="animation-delay: 0.1s;">
                            <div class="stat-icon">
                                <i class='bx bx-alarm-exclamation'></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $critical_incidents; ?></div>
                                <div class="stat-label">Critical Priority</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-info animate-fade-in" style="animation-delay: 0.2s;">
                            <div class="stat-icon">
                                <i class='bx bx-group'></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $responding_units; ?></div>
                                <div class="stat-label">Responding Units</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-card-success animate-fade-in" style="animation-delay: 0.3s;">
                            <div class="stat-icon">
                                <i class='bx bx-check-circle'></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $resolved_incidents; ?></div>
                                <div class="stat-label">Resolved Today</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Dashboard Row -->
                <div class="row mt-4">
                    <!-- Recent Incidents -->
                    <div class="col-lg-8">
                        <div class="card animate-fade-in" style="animation-delay: 0.4s;">
                            <div class="card-header">
                                <h5>Recent Incidents</h5>
                                <a href="IRD/incident_intake/ii.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($recent_incidents) > 0): ?>
                                                <?php foreach ($recent_incidents as $incident): ?>
                                                <tr>
                                                    <td><a href="#" class="text-primary">#<?php echo $incident['id']; ?></a></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
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
                                                    <td><?php echo date('H:i', strtotime($incident['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">No recent incidents</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Dispatches -->
                        <div class="card mt-4 animate-fade-in" style="animation-delay: 0.5s;">
                            <div class="card-header">
                                <h5>Active Dispatches</h5>
                                <a href="IRD/unit_assignment/ua.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Unit</th>
                                                <th>Incident</th>
                                                <th>Location</th>
                                                <th>Dispatched</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($active_dispatches) > 0): ?>
                                                <?php foreach ($active_dispatches as $dispatch): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="unit-badge me-2">
                                                                <?php echo substr($dispatch['unit_name'], 0, 1); ?>
                                                            </div>
                                                            <?php echo htmlspecialchars($dispatch['unit_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($dispatch['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($dispatch['location']); ?></td>
                                                    <td><?php echo date('H:i', strtotime($dispatch['dispatched_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $dispatch['status'] == 'responding' ? 'info' : 
                                                                ($dispatch['status'] == 'onscene' ? 'success' : 'primary'); 
                                                        ?>">
                                                            <?php echo ucfirst($dispatch['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">No active dispatches</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar with Available Units -->
                    <div class="col-lg-4">
                        <!-- Available Units -->
                        <div class="card animate-fade-in" style="animation-delay: 0.4s;">
                            <div class="card-header">
                                <h5>Available Units (<?php echo $available_units_count; ?>)</h5>
                                <a href="IRD/unit_assignment/ua.php" class="btn btn-sm btn-outline-primary">Manage</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if (count($available_units) > 0): ?>
                                        <?php foreach ($available_units as $unit): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex align-items-center">
                                                <div class="unit-badge me-3">
                                                    <?php echo substr($unit['unit_name'], 0, 1); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($unit['unit_name']); ?></div>
                                                    <div class="small text-muted"><?php echo ucfirst($unit['unit_type']); ?></div>
                                                </div>
                                                <span class="badge bg-success">Available</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="list-group-item text-center py-4">
                                            <i class='bx bx-group text-muted' style="font-size: 2rem;"></i>
                                            <p class="mt-2 mb-0">No available units</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card mt-4 animate-fade-in" style="animation-delay: 0.5s;">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="IRD/incident_intake/ii.php" class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3">
                                            <i class='bx bx-plus-medical mb-2' style="font-size: 1.5rem;"></i>
                                            <span>New Incident</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="IRD/unit_assignment/ua.php" class="btn btn-outline-info w-100 d-flex flex-column align-items-center py-3">
                                            <i class='bx bx-group mb-2' style="font-size: 1.5rem;"></i>
                                            <span>Assign Unit</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="IRD/incident_location_mapping/ilm.php" class="btn btn-outline-success w-100 d-flex flex-column align-items-center py-3">
                                            <i class='bx bx-map mb-2' style="font-size: 1.5rem;"></i>
                                            <span>View Map</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="IRD/reporting/report.php" class="btn btn-outline-warning w-100 d-flex flex-column align-items-center py-3">
                                            <i class='bx bx-file mb-2' style="font-size: 1.5rem;"></i>
                                            <span>Generate Report</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Status -->
                        <div class="card mt-4 animate-fade-in" style="animation-delay: 0.6s;">
                            <div class="card-header">
                                <h5>System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="system-status-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Database Connection</span>
                                        <span class="badge bg-success">Operational</span>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div class="system-status-item mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>API Services</span>
                                        <span class="badge bg-success">Operational</span>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div class="system-status-item mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Response Time</span>
                                        <span class="badge bg-info">Fast</span>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-info" style="width: 85%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Module Stats -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h4 class="mb-3">Module Overview</h4>
                    </div>
                    
                    <!-- Fire Station Inventory & Equipment Tracking -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 0.6s;">
                            <div class="module-icon bg-info">
                                <i class='bx bxs-cog'></i>
                            </div>
                            <h6>Inventory & Equipment</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $fsiet_stats['total_inventory']; ?></span>
                                    <span class="stat-label">Total Items</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $fsiet_stats['low_stock']; ?></span>
                                    <span class="stat-label">Low Stock</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $fsiet_stats['maintenance_due']; ?></span>
                                    <span class="stat-label">Maintenance Due</span>
                                </div>
                            </div>
                            <a href="FSIET/inventory_management/im.php" class="btn btn-sm btn-outline-info mt-2">Manage</a>
                        </div>
                    </div>
                    
                    <!-- Hydrant and Water Resource Mapping -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 0.7s;">
                            <div class="module-icon bg-primary">
                                <i class='bx bx-water'></i>
                            </div>
                            <h6>Hydrant & Water</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $hwrm_stats['total_hydrants']; ?></span>
                                    <span class="stat-label">Total Hydrants</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $hwrm_stats['inactive_hydrants']; ?></span>
                                    <span class="stat-label">Inactive</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $hwrm_stats['water_sources']; ?></span>
                                    <span class="stat-label">Water Sources</span>
                                </div>
                            </div>
                            <a href="HWRM/hydrant_resources_mapping/hrm.php" class="btn btn-sm btn-outline-primary mt-2">View Map</a>
                        </div>
                    </div>
                    
                    <!-- Personnel Shift Scheduling -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 0.8s;">
                            <div class="module-icon bg-warning">
                                <i class='bx bx-calendar-event'></i>
                            </div>
                            <h6>Shift Scheduling</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $pss_stats['total_shifts']; ?></span>
                                    <span class="stat-label">Scheduled Shifts</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $pss_stats['pending_leave']; ?></span>
                                    <span class="stat-label">Pending Leave</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $pss_stats['active_personnel']; ?></span>
                                    <span class="stat-label">Active Personnel</span>
                                </div>
                            </div>
                            <a href="PSS/shift_calendar_management/scm.php" class="btn btn-sm btn-outline-warning mt-2">Manage</a>
                        </div>
                    </div>
                    
                    <!-- Training and Certification Records -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 0.9s;">
                            <div class="module-icon bg-success">
                                <i class='bx bx-certification'></i>
                            </div>
                            <h6>Training & Certification</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $tcr_stats['active_training']; ?></span>
                                    <span class="stat-label">Active Training</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $tcr_stats['certifications_expiring']; ?></span>
                                    <span class="stat-label">Expiring Certs</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $tcr_stats['total_courses']; ?></span>
                                    <span class="stat-label">Total Courses</span>
                                </div>
                            </div>
                            <a href="TCR/personnel_training_profile/ptr.php" class="btn btn-sm btn-outline-success mt-2">View</a>
                        </div>
                    </div>
                    
                    <!-- Fire Inspection and Compliance Records -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 1.0s;">
                            <div class="module-icon bg-purple">
                                <i class='bx bx-clipboard'></i>
                            </div>
                            <h6>Inspection & Compliance</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $ficr_stats['scheduled_inspections']; ?></span>
                                    <span class="stat-label">Scheduled</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $ficr_stats['open_violations']; ?></span>
                                    <span class="stat-label">Open Violations</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $ficr_stats['total_establishments']; ?></span>
                                    <span class="stat-label">Establishments</span>
                                </div>
                            </div>
                            <a href="FICR/establishment_registry/er.php" class="btn btn-sm btn-outline-purple mt-2">Schedule</a>
                        </div>
                    </div>
                    
                    <!-- Post-Incident Analysis and Reporting -->
                    <div class="col-md-4 col-lg-2">
                        <div class="module-stat-card animate-fade-in" style="animation-delay: 1.1s;">
                            <div class="module-icon bg-indigo">
                                <i class='bx bx-analyse'></i>
                            </div>
                            <h6>Post-Incident Analysis</h6>
                            <div class="module-stats">
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $piar_stats['pending_analysis']; ?></span>
                                    <span class="stat-label">Pending Analysis</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $piar_stats['pending_lessons']; ?></span>
                                    <span class="stat-label">Pending Lessons</span>
                                </div>
                                <div class="module-stat">
                                    <span class="stat-value"><?php echo $piar_stats['completed_reports']; ?></span>
                                    <span class="stat-label">Completed Reports</span>
                                </div>
                            </div>
                            <a href="PIAR/incident_summary_documentation/isd.php" class="btn btn-sm btn-outline-indigo mt-2">Analyze</a>
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
    
    <!-- New Incident Modal -->
    <div class="modal fade" id="newIncidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Incident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Incident Type</label>
                                    <select class="form-select" name="incident_type" required>
                                        <option value="">Select type</option>
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
                                    <label class="form-label">Priority</label>
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
                                    <label class="form-label">Barangay</label>
                                    <input type="text" class="form-control" name="barangay" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_incident" class="btn btn-primary">Create Incident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Incident Type Chart
            const incidentTypeCtx = document.getElementById('incidentTypeChart');
            if (incidentTypeCtx) {
                new Chart(incidentTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Fire', 'Medical', 'Rescue', 'Traffic', 'Hazmat', 'Other'],
                        datasets: [{
                            data: [12, 19, 7, 5, 3, 4],
                            backgroundColor: [
                                '#dc3545',
                                '#0dcaf0',
                                '#ffc107',
                                '#20c997',
                                '#6f42c1',
                                '#6c757d'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Response Time Chart
            const responseTimeCtx = document.getElementById('responseTimeChart');
            if (responseTimeCtx) {
                new Chart(responseTimeCtx, {
                    type: 'line',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Average Response Time (mins)',
                            data: [8.2, 7.8, 9.1, 8.5, 7.9, 10.2, 9.5],
                            borderColor: '#0d6efd',
                            tension: 0.3,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: false,
                                title: {
                                    display: true,
                                    text: 'Minutes'
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>