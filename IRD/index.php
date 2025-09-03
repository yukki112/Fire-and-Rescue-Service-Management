<?php
session_start();

// Include the Database Manager first
require_once 'database_manager.php';

// Check if this is an API request
if (isset($_GET['api']) || isset($_POST['api']) || 
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)) {
    
    // Include and process API Gateway
    require_once 'api_gateway.php';
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
$active_submodule = $_GET['submodule'] ?? 'intake';

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
            header("Location: ?tab=modules&submodule=intake");
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
            header("Location: ?tab=modules&submodule=assignment");
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
            header("Location: ?tab=modules&submodule=monitoring");
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
            header("Location: ?tab=modules&submodule=communication");
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
            header("Location: ?tab=modules&submodule=reporting");
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
            header("Location: ?tab=modules&submodule=assignment");
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
    <title>Quezon City - AI-Enhanced Incident Response Dispatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #dc3545;
            --primary-dark: #c82333;
            --primary-light: #e25563;
            --primary-gradient: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
            --secondary: #64748b;
            --accent: #fd7e14;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-800: #334155;
            --sidebar-width: 280px;
            --header-height: 80px;
            --card-radius: 16px;
            --card-shadow: 0 10px 30px rgba(220, 53, 69, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f9fafb;
            color: #334155;
            font-weight: 400;
            overflow-x: hidden;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 1.5rem 1rem;
            transition: var(--transition);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 0.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 12px;
            object-fit: cover;
        }
        
        .sidebar-header .text {
            font-weight: 600;
            font-size: 16px;
            line-height: 1.3;
            font-family: 'Montserrat', sans-serif;
        }
        
        .sidebar-header .text small {
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
        }
        
        .sidebar-menu {
            margin-top: 2rem;
        }
        
        .sidebar-section {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.75rem 0.5rem;
            color: #94a3b8;
            font-weight: 600;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-link::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
            opacity: 0;
            transition: var(--transition);
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-link:hover::before, .sidebar-link.active::before {
            opacity: 1;
            left: 0;
        }
        
        .sidebar-link i {
            font-size: 1.25rem;
            margin-right: 12px;
            width: 24px;
            text-align: center;
            transition: var(--transition);
        }
        
        .sidebar-link:hover i, .sidebar-link.active i {
            color: var(--primary);
            transform: scale(1.1);
        }
        
        .sidebar-link .text {
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        /* Dropdown menu */
        .sidebar-dropdown {
            margin-left: 2rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .sidebar-dropdown.show {
            max-height: 500px;
        }
        
        .sidebar-dropdown-link {
            display: flex;
            align-items: center;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            color: #94a3b8;
            text-decoration: none;
            transition: var(--transition);
            font-size: 13px;
            margin-bottom: 0.2rem;
        }
        
        .sidebar-dropdown-link:hover, .sidebar-dropdown-link.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        
        .sidebar-dropdown-link i {
            font-size: 1rem;
            margin-right: 10px;
            width: 20px;
        }
        
        .dropdown-toggle {
            cursor: pointer;
        }
        
        .dropdown-toggle::after {
            content: '\f282';
            font-family: 'boxicons';
            font-size: 1.2rem;
            border: none;
            transition: transform 0.3s ease;
            margin-left: auto;
        }
        
        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(90deg);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
        }
        
        /* Header */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(220, 53, 69, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-family: 'Montserrat', sans-serif;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-title p {
            color: var(--secondary);
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(220, 53, 69, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card.incidents::before { background: var(--primary-gradient); }
        .stat-card.critical::before { background: linear-gradient(135deg, #fd7e14 0%, #ff9f43 100%); }
        .stat-card.units::before { background: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%); }
        .stat-card.personnel::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.resolved::before { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        .stat-card.available::before { background: linear-gradient(135deg, #6c757d 0%, #adb5bd 100%); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            background: rgba(220, 53, 69, 0.1);
            color: var(--primary);
        }
        
        .stat-card.incidents .stat-icon { background: rgba(220, 53, 69, 0.1); color: var(--primary); }
        .stat-card.critical .stat-icon { background: rgba(253, 126, 20, 0.1); color: var(--accent); }
        .stat-card.units .stat-icon { background: rgba(255, 193, 7, 0.1); color: var(--warning); }
        .stat-card.personnel .stat-icon { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .stat-card.resolved .stat-icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .stat-card.available .stat-icon { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(220, 53, 69, 0.1);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.15);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Tables */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .table th {
            background: rgba(220, 53, 69, 0.05);
            padding: 0.875rem 1rem;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover td {
            background: rgba(220, 53, 69, 0.03);
        }
        
        /* Status Badges */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .badge-dispatched {
            background: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
        }
        
        .badge-responding {
            background: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }
        
        .badge-onscene {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .badge-resolved {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .badge-critical {
            background: rgba(220, 53, 69, 0.15);
            color: var(--danger);
        }
        
        .badge-high {
            background: rgba(253, 126, 20, 0.15);
            color: var(--accent);
        }
        
        .badge-medium {
            background: rgba(255, 193, 7, 0.15);
            color: var(--warning);
        }
        
        .badge-low {
            background: rgba(32, 201, 151, 0.15);
            color: #20c997;
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 13px;
        }
        
        /* Weather Widget */
        .weather-widget {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
          .weather-widget::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="5" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="35" r="8" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="20" r="5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="70" r="6" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="60" r="10" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.5;
        }
        
        .weather-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .weather-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        
        .weather-location {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .weather-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }
        
        .weather-temp {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .weather-details {
            text-align: right;
        }
        
        .weather-condition {
            font-size: 1.125rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .weather-stats {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        /* Map Widget */
        .map-widget {
            height: 300px;
            background: var(--gray-100);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .map-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-800);
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .animate-slide-up {
            animation: slideUp 0.5s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Custom Utilities */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .shadow-primary {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .divider {
            height: 1px;
            background: var(--gray-200);
            margin: 1rem 0;
        }
        
        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: var(--transition);
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: white;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.8;
            color: white;
        }
        
        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid rgba(220, 53, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            color: var(--secondary);
            border-radius: 0;
            position: relative;
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary);
            background: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
            border: none;
        }
        
        .nav-tabs .nav-link.active::after {
            width: 100%;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        /* Modal */
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: rgba(248, 249, 250, 0.5);
        }
        
        .modal-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Loading Animation */
        .loading-spinner {
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(220, 53, 69, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        /* Fire Loading Animation */
        .fire-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .fire-loader.active {
            opacity: 1;
            visibility: visible;
        }
        
        .fire {
            position: relative;
            width: 60px;
            height: 70px;
        }
        
        .fire-center {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: #ff9800;
            border-radius: 50% 50% 20% 20%;
            box-shadow: 
                0 0 20px 10px #ff9800,
                0 0 40px 20px #ff5722,
                0 0 60px 30px #f44336;
            animation: flicker 1.5s infinite alternate;
        }
        
        .fire-left {
            position: absolute;
            bottom: 0;
            left: 20%;
            transform: translateX(-50%) rotate(-20deg);
            width: 25px;
            height: 35px;
            background: #ff9800;
            border-radius: 50% 50% 20% 20%;
            box-shadow: 
                0 0 15px 8px #ff9800,
                0 0 30px 15px #ff5722;
            animation: flickerLeft 1.2s infinite alternate;
        }
        
        .fire-right {
            position: absolute;
            bottom: 0;
            right: 20%;
            transform: translateX(50%) rotate(20deg);
            width: 25px;
            height: 35px;
            background: #ff9800;
            border-radius: 50% 50% 20% 20%;
            box-shadow: 
                0 0 15px 8px #ff9800,
                0 0 30px 15px #ff5722;
            animation: flickerRight 1.4s infinite alternate;
        }
        
        .fire-text {
            color: white;
            margin-top: 20px;
            font-weight: 500;
            text-align: center;
            text-shadow: 0 0 10px rgba(255, 87, 34, 0.8);
        }
        
        @keyframes flicker {
            0%, 100% { 
                height: 40px;
                box-shadow: 
                    0 0 20px 10px #ff9800,
                    0 0 40px 20px #ff5722,
                    0 0 60px 30px #f44336;
            }
            50% { 
                height: 45px;
                box-shadow: 
                    0 0 25px 12px #ff9800,
                    0 0 45px 22px #ff5722,
                    0 0 65px 32px #f44336;
            }
        }
        
        @keyframes flickerLeft {
            0%, 100% { 
                height: 35px;
                box-shadow: 
                    0 0 15px 8px #ff9800,
                    0 0 30px 15px #ff5722;
            }
            50% { 
                height: 40px;
                box-shadow: 
                    0 0 20px 10px #ff9800,
                    0 0 35px 17px #ff5722;
            }
        }
        
        @keyframes flickerRight {
            0%, 100% { 
                height: 35px;
                box-shadow: 
                    0 0 15px 8px #ff9800,
                    0 0 30px 15px #ff5722;
            }
            50% { 
                height: 38px;
                box-shadow: 
                    0 0 18px 9px #ff9800,
                    0 0 33px 16px #ff5722;
            }
        }
        
        /* Under Development Banner */
        .under-development {
            position: relative;
            overflow: hidden;
            border: 2px dashed var(--warning);
        }
        
        .under-development::before {
            content: 'Under Development';
            position: absolute;
            top: 10px;
            right: -30px;
            background: var(--warning);
            color: white;
            padding: 5px 40px;
            font-size: 12px;
            font-weight: 600;
            transform: rotate(45deg);
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        /* Submodule Navigation */
        .submodule-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
        }
        
        .submodule-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: var(--gray-100);
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .submodule-btn:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
        }
        
        .submodule-btn.active {
            background: var(--primary-gradient);
            color: white;
        }
        
        /* Map Container */
        .map-container {
            height: 400px;
            background: var(--gray-100);
            border-radius: var(--card-radius);
            overflow: hidden;
            position: relative;
        }
        
        .map-overlay {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 100;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Unit Status */
        .unit-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-available { background: var(--success); }
        .status-dispatched { background: var(--info); }
        .status-responding { background: var(--primary); }
        .status-onscene { background: var(--warning); }
        .status-unavailable { background: var(--secondary); }
        
        /* AI Prediction Box */
        .ai-prediction {
            background: linear-gradient(135deg, #6f42c1 0%, #7952b3 100%);
            color: white;
            border-radius: var(--card-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .ai-prediction h5 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Chatbot Interface */
        .chat-container {
            height: 400px;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-300);
            border-radius: var(--card-radius);
            overflow: hidden;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: var(--gray-100);
        }
        
        .message {
            max-width: 80%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            margin-bottom: 0.75rem;
            position: relative;
        }
        
        .message.bot {
            background: white;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        
        .message.user {
            background: var(--primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        
        .chat-input {
            display: flex;
            padding: 1rem;
            background: white;
            border-top: 1px solid var(--gray-300);
        }
        
        /* Heatmap Legend */
        .heatmap-legend {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 100;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Fire Loading Animation -->
    <div class="fire-loader" id="fireLoader">
        <div class="d-flex flex-column align-items-center">
            <div class="fire">
                <div class="fire-center"></div>
                <div class="fire-left"></div>
                <div class="fire-right"></div>
            </div>
            <div class="fire-text">Loading Module...</div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../img/FRSM.png" alt="Logo">
                <div class="text">
                    Quezon City<br>
                    <small>Incident Response Dispatch</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="?tab=dashboard" class="sidebar-link <?= $active_tab == 'dashboard' ? 'active' : '' ?>">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Incident Response Dispatch with Dropdown -->
                <div class="sidebar-section mt-4">Main Modules</div>
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'modules' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#irdDropdown" aria-expanded="<?= $active_tab == 'modules' ? 'true' : 'false' ?>">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'modules' ? 'show' : '' ?>" id="irdDropdown">
                    <a href="?tab=modules&submodule=intake" class="sidebar-dropdown-link <?= $active_submodule == 'intake' ? 'active' : '' ?>">
                        <i class='bx bx-clipboard'></i> Incident Intake
                    </a>
                    <a href="?tab=modules&submodule=mapping" class="sidebar-dropdown-link <?= $active_submodule == 'mapping' ? 'active' : '' ?>">
                        <i class='bx bx-map-pin'></i> Location Mapping
                    </a>
                    <a href="?tab=modules&submodule=assignment" class="sidebar-dropdown-link <?= $active_submodule == 'assignment' ? 'active' : '' ?>">
                        <i class='bx bx-group'></i> Unit Assignment
                    </a>
                    <a href="?tab=modules&submodule=communication" class="sidebar-dropdown-link <?= $active_submodule == 'communication' ? 'active' : '' ?>">
                        <i class='bx bx-chat'></i> Communication
                    </a>
                    <a href="?tab=modules&submodule=monitoring" class="sidebar-dropdown-link <?= $active_submodule == 'monitoring' ? 'active' : '' ?>">
                        <i class='bx bx-show'></i> Status Monitoring
                    </a>
                    <a href="?tab=modules&submodule=access" class="sidebar-dropdown-link <?= $active_submodule == 'access' ? 'active' : '' ?>">
                        <i class='bx bx-lock'></i> Access Control
                    </a>
                </div>
                
                <a href="../FSIET/index.php" class="sidebar-link">
                    <i class='bx bxs-package'></i>
                    <span class="text">Fire Station Inventory & Equipment Tracking</span>
                </a>
                <a href="../HWRM/index.php" class="sidebar-link">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant and Water Resource Mapping</span>
                </a>
                <a href="../PSS/index.php" class="sidebar-link">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Personnel Shift Scheduling</span>
                </a>
                <a href="../TCR/index.php" class="sidebar-link">
                    <i class='bx bxs-certification'></i>
                    <span class="text">Training and Certification Records</span>
                </a>
                <a href="../FICR/index.php" class="sidebar-link">
                    <i class='bx bxs-check-shield'></i>
                    <span class="text">Fire Inspection and Compliance Records</span>
                </a>
                <a href="../PIAR/index.php" class="sidebar-link ">
                    <i class='bx bxs-analyse'></i>
                    <span class="text">Post-Incident Analysis and Reporting</span>
                </a>
              
                
             
                <div class="sidebar-section mt-4">User</div>
                <a href="profile.php" class="sidebar-link">
                    <i class='bx bx-user'></i>
                    <span class="text">Profile</span>
                </a>
                <a href="settings.php" class="sidebar-link">
                    <i class='bx bx-cog'></i>
                    <span class="text">Settings</span>
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
            <div class="dashboard-header">
                <div class="page-title">
                    <h1>Incident Response Dispatch</h1>
                    <p>Welcome back, <?= htmlspecialchars($user['first_name'] ?? 'User') ?>! Here's the latest update.</p>
                </div>
                
                <div class="header-actions">
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" class="form-control" placeholder="Search incidents...">
                        <button class="btn btn-primary" type="button">
                            <i class='bx bx-search'></i>
                        </button>
                    </div>
                    
                    <button class="btn btn-outline-primary position-relative">
                        <i class='bx bx-bell'></i>
                        <span class="notification-badge">3</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class='bx bx-plus'></i> New Incident
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newIncidentModal">Report Incident</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#dispatchUnitModal">Dispatch Unit</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#generateReportModal">Generate Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger animate-fade-in" role="alert">
                <i class='bx bx-error-circle'></i> <?= $error_message ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success animate-fade-in" role="alert">
                <i class='bx bx-check-circle'></i> <?= $success_message ?>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'dashboard'): ?>
            <!-- Dashboard Content -->
            <div class="animate-fade-in">
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card incidents">
                        <div class="stat-icon">
                            <i class='bx bx-alarm-exclamation'></i>
                        </div>
                        <div class="stat-value"><?= $active_incidents ?></div>
                        <div class="stat-label">Active Incidents</div>
                    </div>
                    
                    <div class="stat-card critical">
                        <div class="stat-icon">
                            <i class='bx bx-error-circle'></i>
                        </div>
                        <div class="stat-value"><?= $critical_incidents ?></div>
                        <div class="stat-label">Critical Priority</div>
                    </div>
                    
                    <div class="stat-card units">
                        <div class="stat-icon">
                            <i class='bx bx-car'></i>
                        </div>
                        <div class="stat-value"><?= $responding_units ?></div>
                        <div class="stat-label">Responding Units</div>
                    </div>
                    
                    <div class="stat-card personnel">
                        <div class="stat-icon">
                            <i class='bx bx-group'></i>
                        </div>
                        <div class="stat-value"><?= $on_scene_personnel ?></div>
                        <div class="stat-label">On-Scene Personnel</div>
                    </div>
                    
                    <div class="stat-card resolved">
                        <div class="stat-icon">
                            <i class='bx bx-check-circle'></i>
                        </div>
                        <div class="stat-value"><?= $resolved_incidents ?></div>
                        <div class="stat-label">Resolved Today</div>
                    </div>
                    
                    <div class="stat-card available">
                        <div class="stat-icon">
                            <i class='bx bx-time-five'></i>
                        </div>
                        <div class="stat-value"><?= $available_units_count ?></div>
                        <div class="stat-label">Available Units</div>
                    </div>
                </div>
                
                <!-- Weather Widget -->
                <div class="weather-widget mb-4">
                    <div class="weather-header">
                        <div>
                            <h2 class="weather-title">Quezon City</h2>
                            <p class="weather-location">Metro Manila, Philippines</p>
                        </div>
                        <div class="weather-time">
                            <?= date('l, F j, Y') ?><br>
                            <?= date('h:i A') ?>
                        </div>
                    </div>
                    <div class="weather-content">
                        <div class="weather-temp"><?= $weather_data['temperature'] ?>°C</div>
                        <div class="weather-details">
                            <div class="weather-condition"><?= $weather_data['condition'] ?></div>
                            <div class="weather-stats">
                                Humidity: <?= $weather_data['humidity'] ?>%<br>
                                Wind: <?= $weather_data['wind_speed'] ?> km/h<br>
                                Pressure: <?= $weather_data['pressure'] ?> hPa
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div class="content-grid">
                    <!-- Recent Incidents -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class='bx bx-list-ul'></i> Recent Incidents</h3>
                            <a href="?tab=modules&submodule=intake" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_incidents)): ?>
                                            <?php foreach ($recent_incidents as $incident): ?>
                                            <tr>
                                                <td>#<?= $incident['id'] ?></td>
                                                <td><?= htmlspecialchars($incident['incident_type']) ?></td>
                                                <td><?= htmlspecialchars($incident['location']) ?></td>
                                                <td><span class="badge badge-<?= $incident['priority'] ?>"><?= $incident['priority'] ?></span></td>
                                                <td><span class="badge badge-<?= $incident['status'] ?>"><?= $incident['status'] ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewIncidentModal" data-id="<?= $incident['id'] ?>">
                                                        <i class='bx bx-show'></i>
                                                    </button>
                                                </td>
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class='bx bx-current-location'></i> Active Dispatches</h3>
                            <a href="?tab=modules&submodule=monitoring" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Incident</th>
                                            <th>Dispatched</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($active_dispatches)): ?>
                                            <?php foreach ($active_dispatches as $dispatch): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dispatch['unit_name']) ?></td>
                                                <td><?= htmlspecialchars($dispatch['incident_type']) ?></td>
                                                <td><?= date('M j, h:i A', strtotime($dispatch['dispatched_at'])) ?></td>
                                                <td><span class="badge badge-<?= $dispatch['status'] ?>"><?= $dispatch['status'] ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateDispatchModal" data-id="<?= $dispatch['id'] ?>">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
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
                
                <!-- Map and Available Units -->
                <div class="content-grid">
                    <!-- Quick Map -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class='bx bx-map-alt'></i> Incident Heatmap</h3>
                            <a href="?tab=modules&submodule=mapping" class="btn btn-sm btn-primary">Full Screen</a>
                        </div>
                        <div class="card-body">
                            <div class="map-widget">
                                <div class="map-placeholder">
                                    <i class='bx bx-map text-muted' style="font-size: 3rem;"></i>
                                    <span class="ms-2">Interactive Map Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Available Units -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class='bx bx-check-circle'></i> Available Units</h3>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#dispatchUnitModal">Dispatch</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Unit Name</th>
                                            <th>Type</th>
                                            <th>Personnel</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($available_units)): ?>
                                            <?php foreach ($available_units as $unit): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($unit['unit_name']) ?></td>
                                                <td><?= htmlspecialchars($unit['unit_type']) ?></td>
                                                <td><?= $unit['personnel_count'] ?></td>
                                                <td>
                                                    <div class="unit-status">
                                                        <span class="status-indicator status-<?= $unit['status'] ?>"></span>
                                                        <?= $unit['status'] ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewUnitModal" data-id="<?= $unit['id'] ?>">
                                                        <i class='bx bx-show'></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No available units</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($active_tab == 'modules'): ?>
            <!-- Submodules Content -->
            <div class="animate-fade-in">
                <!-- Submodule Navigation -->
                <div class="submodule-nav">
                    <a href="?tab=modules&submodule=intake" class="submodule-btn <?= $active_submodule == 'intake' ? 'active' : '' ?>">
                        <i class='bx bx-clipboard'></i> Incident Intake
                    </a>
                    <a href="?tab=modules&submodule=mapping" class="submodule-btn <?= $active_submodule == 'mapping' ? 'active' : '' ?>">
                        <i class='bx bx-map-pin'></i> Location Mapping
                    </a>
                    <a href="?tab=modules&submodule=assignment" class="submodule-btn <?= $active_submodule == 'assignment' ? 'active' : '' ?>">
                        <i class='bx bx-group'></i> Unit Assignment
                    </a>
                    <a href="?tab=modules&submodule=communication" class="submodule-btn <?= $active_submodule == 'communication' ? 'active' : '' ?>">
                        <i class='bx bx-chat'></i> Communication
                    </a>
                    <a href="?tab=modules&submodule=monitoring" class="submodule-btn <?= $active_submodule == 'monitoring' ? 'active' : '' ?>">
                        <i class='bx bx-show'></i> Status Monitoring
                    </a>
                    <a href="?tab=modules&submodule=reporting" class="submodule-btn <?= $active_submodule == 'reporting' ? 'active' : '' ?>">
                        <i class='bx bx-news'></i> Incident Reporting
                    </a>
                    <a href="?tab=modules&submodule=access" class="submodule-btn <?= $active_submodule == 'access' ? 'active' : '' ?>">
                        <i class='bx bx-lock'></i> Access Control
                    </a>
                </div>
                
                <!-- Submodule Content -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php 
                            $icons = [
                                'intake' => 'bx-clipboard',
                                'mapping' => 'bx-map-pin',
                                'assignment' => 'bx-group',
                                'communication' => 'bx-chat',
                                'monitoring' => 'bx-show',
                                'reporting' => 'bx-news',
                                'access' => 'bx-lock'
                            ];
                            ?>
                            <i class='bx <?= $icons[$active_submodule] ?? 'bx-cog' ?>'></i>
                            <?= ucwords(str_replace('_', ' ', $active_submodule)) ?> Module
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($active_submodule == 'intake'): ?>
                            <!-- Incident Intake Form -->
                            <h4 class="mb-4">New Incident Report</h4>
                            
                            <!-- AI Classification Prediction -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Classification Prediction</h5>
                                <p>Based on your input, our AI predicts this incident as: <strong>Fire Emergency</strong> with <strong>High</strong> priority</p>
                                <small>Confidence: 92% | Response time estimate: 5-7 minutes</small>
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="create_incident" value="1">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="incidentType" class="form-label">Incident Type</label>
                                        <select class="form-select" id="incidentType" name="incident_type" required>
                                            <option value="">Select incident type</option>
                                            <?php foreach ($incident_types as $type): ?>
                                                <option value="<?= $type['incident_type'] ?>"><?= htmlspecialchars($type['incident_type']) ?></option>
                                            <?php endforeach; ?>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="priority" class="form-label">Priority Level</label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="">Select priority</option>
                                            <option value="critical">Critical</option>
                                            <option value="high">High</option>
                                            <option value="medium">Medium</option>
                                            <option value="low">Low</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="barangay" class="form-label">Barangay</label>
                                        <select class="form-select" id="barangay" name="barangay" required>
                                            <option value="">Select Barangay</option>
                                            <?php 
                                            $barangays = $dbManager->fetchAll("ird", "SELECT DISTINCT barangay FROM incidents ORDER BY barangay");
                                            foreach ($barangays as $barangay): ?>
                                                <option value="<?= $barangay['barangay'] ?>"><?= htmlspecialchars($barangay['barangay']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reporterPhone" class="form-label">Reporter Phone</label>
                                        <input type="tel" class="form-control" id="reporterPhone" name="reporter_phone">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="location" class="form-label">Incident Location</label>
                                    <input type="text" class="form-control" id="location" name="location" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Incident Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="injuries" class="form-label">Injuries</label>
                                        <input type="number" class="form-control" id="injuries" name="injuries" min="0" value="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fatalities" class="form-label">Fatalities</label>
                                        <input type="number" class="form-control" id="fatalities" name="fatalities" min="0" value="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="peopleTrapped" class="form-label">People Trapped</label>
                                        <input type="number" class="form-control" id="peopleTrapped" name="people_trapped" min="0" value="0">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="hazardousMaterials" name="hazardous_materials" value="1">
                                        <label class="form-check-label" for="hazardousMaterials">
                                            Hazardous materials involved
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit Incident Report</button>
                                <button type="button" class="btn btn-outline-secondary" id="aiClassifyBtn">
                                    <i class='bx bx-brain'></i> AI Classify
                                </button>
                            </form>
                            
                        <?php elseif ($active_submodule == 'mapping'): ?>
                            <!-- Location Mapping -->
                            <h4 class="mb-4">Incident Location Mapping</h4>
                            
                            <!-- AI Prediction for Incident Spread -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Incident Spread Prediction</h5>
                                <p>Based on current wind patterns (<?= $weather_data['wind_speed'] ?> km/h from NW), this fire may spread Northeast at approximately 0.5 km/h</p>
                                <small>Evacuation recommended within 1km radius | Safe routes: East and Southeast</small>
                            </div>
                            
                            <div class="map-container mb-4">
                                <div class="map-placeholder">
                                    <i class='bx bx-map text-muted' style="font-size: 3rem;"></i>
                                    <span class="ms-2">Interactive Map Loading...</span>
                                </div>
                                <div class="map-overlay">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary active">Incidents</button>
                                        <button class="btn btn-sm btn-outline-primary">Units</button>
                                        <button class="btn btn-sm btn-outline-primary">Hazards</button>
                                    </div>
                                </div>
                                <div class="heatmap-legend">
                                    <h6>Heatmap Legend</h6>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #ff0000;"></div>
                                        <span>Critical</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #ff9900;"></div>
                                        <span>High</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #ffff00;"></div>
                                        <span>Medium</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #00ff00;"></div>
                                        <span>Low</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Recent Incident Locations</h5>
                                    <div class="list-group">
                                        <?php if (!empty($mapped_incidents)): ?>
                                            <?php foreach ($mapped_incidents as $incident): ?>
                                            <a href="#" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($incident['incident_type']) ?></h6>
                                                    <small class="text-muted"><?= date('M j, h:i A', strtotime($incident['created_at'])) ?></small>
                                                </div>
                                                <p class="mb-1"><?= htmlspecialchars($incident['location']) ?></p>
                                                <small class="text-muted">Priority: <span class="badge badge-<?= $incident['priority'] ?>"><?= $incident['priority'] ?></span></small>
                                            </a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="list-group-item">No recent incidents with location data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>GIS Tools</h5>
                                    <div class="d-grid gap-2 mb-3">
                                        <button class="btn btn-outline-primary">
                                            <i class='bx bx-map-alt'></i> Generate Evacuation Routes
                                        </button>
                                        <button class="btn btn-outline-primary">
                                            <i class='bx bx-water'></i> Locate Nearest Hydrants
                                        </button>
                                        <button class="btn btn-outline-primary">
                                            <i class='bx bx-building'></i> Identify At-Risk Structures
                                        </button>
                                    </div>
                                    
                                    <h5>Weather Conditions</h5>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small>Wind Speed</small>
                                                    <h6><?= $weather_data['wind_speed'] ?> km/h</h6>
                                                </div>
                                                <div class="col-6">
                                                    <small>Direction</small>
                                                    <h6>NW</h6>
                                                </div>
                                                <div class="col-6">
                                                    <small>Humidity</small>
                                                    <h6><?= $weather_data['humidity'] ?>%</h6>
                                                </div>
                                                <div class="col-6">
                                                    <small>Temperature</small>
                                                    <h6><?= $weather_data['temperature'] ?>°C</h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($active_submodule == 'assignment'): ?>
                            <!-- Unit Assignment -->
                            <h4 class="mb-4">Unit/Team Assignment</h4>
                            
                            <!-- AI Recommendation -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Assignment Recommendation</h5>
                                <p>Recommended units for this incident: <strong>FD103, FD107, AMB024</strong></p>
                                <small>Based on proximity, equipment, and past performance with similar incidents</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Available Units</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <?php if (!empty($available_units)): ?>
                                                    <?php foreach ($available_units as $unit): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1"><?= htmlspecialchars($unit['unit_name']) ?></h6>
                                                            <small><?= $unit['unit_type'] ?></small>
                                                        </div>
                                                        <p class="mb-1">Personnel: <?= $unit['personnel_count'] ?></p>
                                                        <p class="mb-1">Equipment: <?= $unit['equipment'] ?? 'Standard' ?></p>
                                                        <small>Status: <span class="badge badge-success">Available</span></small>
                                                        <div class="d-grid gap-2 mt-2">
                                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignUnitModal" data-unit-id="<?= $unit['id'] ?>">
                                                                <i class='bx bx-plus'></i> Assign to Incident
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="list-group-item">No available units</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Unit Performance Metrics</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Unit</th>
                                                            <th>Type</th>
                                                            <th>Response Time</th>
                                                            <th>Success Rate</th>
                                                            <th>Specialization</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($all_units_with_status)): ?>
                                                            <?php foreach ($all_units_with_status as $unit): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($unit['unit_name']) ?></td>
                                                                <td><?= htmlspecialchars($unit['unit_type']) ?></td>
                                                                <td><?= rand(3, 15) ?> mins</td>
                                                                <td><?= rand(85, 99) ?>%</td>
                                                                <td>
                                                                    <?php 
                                                                    $specializations = ['Fire', 'Medical', 'Rescue', 'Hazmat'];
                                                                    echo $specializations[array_rand($specializations)];
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center py-4">No unit data available</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Active Assignments</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Unit</th>
                                                            <th>Incident</th>
                                                            <th>Assigned</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($active_dispatches)): ?>
                                                            <?php foreach ($active_dispatches as $dispatch): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($dispatch['unit_name']) ?></td>
                                                                <td><?= htmlspecialchars($dispatch['incident_type']) ?></td>
                                                                <td><?= date('M j, h:i A', strtotime($dispatch['dispatched_at'])) ?></td>
                                                                <td><span class="badge badge-<?= $dispatch['status'] ?>"><?= $dispatch['status'] ?></span></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateAssignmentModal" data-id="<?= $dispatch['id'] ?>">
                                                                        <i class='bx bx-edit'></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center py-4">No active assignments</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($active_submodule == 'communication'): ?>
                            <!-- Communication Module -->
                            <h4 class="mb-4">Communication Center</h4>
                            
                            <!-- AI Chatbot -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI-Powered Chatbot Assistant</h5>
                                <p>Our AI assistant can help with quick reporting, status checks, and answering common questions</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Communication Channels</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <a href="#" class="list-group-item list-group-item-action active">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Dispatch Channel</h6>
                                                        <span class="badge bg-primary rounded-pill">14</span>
                                                    </div>
                                                    <small>Primary communication channel</small>
                                                </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Unit Coordination</h6>
                                                        <span class="badge bg-primary rounded-pill">8</span>
                                                    </div>
                                                    <small>Unit-to-unit communication</small>
                                                </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Emergency Alerts</h6>
                                                        <span class="badge bg-danger rounded-pill">3</span>
                                                    </div>
                                                    <small>Critical alerts only</small>
                                                </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">General Announcements</h6>
                                                        <span class="badge bg-primary rounded-pill">5</span>
                                                    </div>
                                                    <small>General information</small>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Quick Message Templates</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-outline-primary text-start">
                                                    <i class='bx bx-message'></i> Status Update Request
                                                </button>
                                                <button class="btn btn-outline-primary text-start">
                                                    <i class='bx bx-message'></i> Additional Resources Needed
                                                </button>
                                                <button class="btn btn-outline-primary text-start">
                                                    <i class='bx bx-message'></i> Situation Under Control
                                                </button>
                                                <button class="btn btn-outline-primary text-start">
                                                    <i class='bx bx-message'></i> Evacuation Complete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">AI Chatbot Assistant</h5>
                                        </div>
                                        <div class="chat-container">
                                            <div class="chat-messages">
                                                <div class="message bot">
                                                    Hello! I'm your AI assistant. How can I help you today?
                                                </div>
                                                <div class="message user">
                                                    I need to report a new incident
                                                </div>
                                                <div class="message bot">
                                                    I can help with that. What type of incident are you reporting?
                                                </div>
                                            </div>
                                            <div class="chat-input">
                                                <input type="text" class="form-control" placeholder="Type your message...">
                                                <button class="btn btn-primary ms-2">Send</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Send Message</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="send_message" value="1">
                                                <div class="mb-3">
                                                    <label class="form-label">Channel</label>
                                                    <select class="form-select" name="channel" required>
                                                        <option value="radio">Radio</option>
                                                        <option value="sms">SMS</option>
                                                        <option value="email">Email</option>
                                                        <option value="alert">Alert System</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Receiver</label>
                                                    <input type="text" class="form-control" name="receiver" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Message</label>
                                                    <textarea class="form-control" name="message" rows="3" required></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Incident (Optional)</label>
                                                    <select class="form-select" name="incident_id">
                                                        <option value="">Select Incident</option>
                                                        <?php foreach ($recent_incidents as $incident): ?>
                                                        <option value="<?= $incident['id'] ?>">#<?= $incident['id'] ?> - <?= $incident['incident_type'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Send Message</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($active_submodule == 'monitoring'): ?>
                            <!-- Status Monitoring -->
                            <h4 class="mb-4">Incident Status Monitoring</h4>
                            
                            <!-- AI Prediction for Resolution -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Resolution Prediction</h5>
                                <p>Estimated resolution time for current incidents: <strong>25-40 minutes</strong></p>
                                <small>Based on incident type, resources deployed, and historical data</small>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Active Incidents</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Type</th>
                                                            <th>Location</th>
                                                            <th>Priority</th>
                                                            <th>Status</th>
                                                            <th>Duration</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($monitored_incidents)): ?>
                                                            <?php foreach ($monitored_incidents as $incident): ?>
                                                            <tr>
                                                                <td>#<?= $incident['id'] ?></td>
                                                                <td><?= htmlspecialchars($incident['incident_type']) ?></td>
                                                                <td><?= htmlspecialchars($incident['location']) ?></td>
                                                                <td><span class="badge badge-<?= $incident['priority'] ?>"><?= $incident['priority'] ?></span></td>
                                                                <td><span class="badge badge-<?= $incident['status'] ?>"><?= $incident['status'] ?></span></td>
                                                                <td><?= $incident['minutes_ago'] ?> mins</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal" data-id="<?= $incident['id'] ?>">
                                                                        <i class='bx bx-edit'></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="7" class="text-center py-4">No active incidents</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Status Overview</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Active Incidents</span>
                                                <span class="fw-bold"><?= $active_incidents ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Critical Priority</span>
                                                <span class="fw-bold text-danger"><?= $critical_incidents ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Units Responding</span>
                                                <span class="fw-bold text-primary"><?= $responding_units ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Resolved Today</span>
                                                <span class="fw-bold text-success"><?= $resolved_incidents ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span>Avg Response Time</span>
                                                <span class="fw-bold">8.2 mins</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Update Incident Status</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="update_status" value="1">
                                                <div class="mb-3">
                                                    <label class="form-label">Incident</label>
                                                    <select class="form-select" name="incident_id" required>
                                                        <option value="">Select Incident</option>
                                                        <?php foreach ($monitored_incidents as $incident): ?>
                                                        <option value="<?= $incident['id'] ?>">#<?= $incident['id'] ?> - <?= $incident['incident_type'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="status" required>
                                                        <option value="pending">Pending</option>
                                                        <option value="dispatched">Dispatched</option>
                                                        <option value="responding">Responding</option>
                                                        <option value="onscene">On Scene</option>
                                                        <option value="resolved">Resolved</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Real-time Unit Tracking</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Unit</th>
                                                    <th>Incident</th>
                                                    <th>Status</th>
                                                    <th>Location</th>
                                                    <th>ETA</th>
                                                    <th>Last Update</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($active_dispatches)): ?>
                                                    <?php foreach ($active_dispatches as $dispatch): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dispatch['unit_name']) ?></td>
                                                        <td>#<?= $dispatch['incident_id'] ?> - <?= htmlspecialchars($dispatch['incident_type']) ?></td>
                                                        <td><span class="badge badge-<?= $dispatch['status'] ?>"><?= $dispatch['status'] ?></span></td>
                                                        <td>
                                                            <?php 
                                                            $locations = ['En route', 'On scene', '5km away', '2km away'];
                                                            echo $locations[array_rand($locations)];
                                                            ?>
                                                        </td>
                                                        <td><?= rand(2, 15) ?> mins</td>
                                                        <td><?= date('H:i:s') ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">No active units</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    </div>
                            </div>
                            
                        <?php elseif ($active_submodule == 'reporting'): ?>
                            <!-- Incident Reporting -->
                            <h4 class="mb-4">Incident Reporting</h4>
                            
                            <!-- AI Analysis -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Trend Analysis</h5>
                                <p>Trend detected: 23% increase in medical emergencies in Barangay Commonwealth in the last 30 days</p>
                                <small>Recommendation: Increase ambulance deployment during peak hours (6-9 PM)</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Report Templates</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Daily Activity Report</h6>
                                                        <small class="text-muted">DOCX</small>
                                                    </div>
                                                    <small>Summary of daily incidents and responses</small>
                                                </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Incident Summary</h6>
                                                        <small class="text-muted">PDF</small>
                                                    </div>
                                                    <small>Detailed report for a specific incident</small>
                                                                                                    </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">Resource Utilization</h6>
                                                        <small class="text-muted">XLSX</small>
                                                    </div>
                                                    <small>Unit and personnel deployment report</small>
                                                </a>
                                                <a href="#" class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1">After Action Review</h6>
                                                        <small class="text-muted">DOCX</small>
                                                    </div>
                                                    <small>Post-incident analysis and lessons learned</small>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Generate Quick Report</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="generate_report" value="1">
                                                <div class="mb-3">
                                                    <label class="form-label">Report Type</label>
                                                    <select class="form-select" name="report_type" required>
                                                        <option value="daily_summary">Daily Summary</option>
                                                        <option value="incident_report">Incident Report</option>
                                                        <option value="resource_report">Resource Report</option>
                                                        <option value="performance_metrics">Performance Metrics</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" class="form-control" name="title" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" rows="2"></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Date Range</label>
                                                    <div class="input-group">
                                                        <input type="date" class="form-control" name="start_date" required>
                                                        <span class="input-group-text">to</span>
                                                        <input type="date" class="form-control" name="end_date" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Format</label>
                                                    <select class="form-select" name="format" required>
                                                        <option value="pdf">PDF</option>
                                                        <option value="excel">Excel</option>
                                                        <option value="word">Word</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Incident Analytics & Trends</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <h6>Incidents by Type (Last 30 days)</h6>
                                                    <div class="card bg-light">
                                                        <div class="card-body">
                                                            <ul class="list-unstyled">
                                                                <li>Medical: 42% (126 incidents)</li>
                                                                <li>Fire: 28% (84 incidents)</li>
                                                                <li>Traffic: 18% (54 incidents)</li>
                                                                <li>Other: 12% (36 incidents)</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Response Metrics</h6>
                                                    <div class="card bg-light">
                                                        <div class="card-body">
                                                            <ul class="list-unstyled">
                                                                <li>Avg Response Time: 8.2 minutes</li>
                                                                <li>Resolution Rate: 94.7%</li>
                                                                <li>Peak Hours: 4-7 PM</li>
                                                                <li>Busiest Area: Barangay Commonwealth</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <h6>Recent Reports</h6>
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Report Name</th>
                                                            <th>Type</th>
                                                            <th>Generated</th>
                                                            <th>Size</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $recent_reports = $dbManager->fetchAll("ird", 
                                                            "SELECT * FROM reports ORDER BY created_at DESC LIMIT 5"
                                                        );
                                                        ?>
                                                        <?php if (!empty($recent_reports)): ?>
                                                            <?php foreach ($recent_reports as $report): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($report['title']) ?></td>
                                                                <td><?= htmlspecialchars($report['report_type']) ?></td>
                                                                <td><?= date('M j, Y H:i', strtotime($report['created_at'])) ?></td>
                                                                <td>2.4 MB</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary">
                                                                        <i class='bx bx-download'></i>
                                                                    </button>
                                                                </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center py-4">No reports available</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($active_submodule == 'access'): ?>
                            <!-- Access Control & Logging -->
                            <h4 class="mb-4">Access Control & Logging</h4>
                            
                            <!-- AI Security Monitoring -->
                            <div class="ai-prediction">
                                <h5><i class='bx bx-brain'></i> AI Security Monitoring</h5>
                                <p>System secure. No suspicious activities detected in the last 24 hours.</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">User Access Management</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>User</th>
                                                            <th>Role</th>
                                                            <th>Last Login</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($system_users)): ?>
                                                            <?php foreach ($system_users as $user): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                                <td><?= $user['is_admin'] ? 'Administrator' : 'User' ?></td>
                                                                <td><?= date('M j, Y H:i', strtotime($user['updated_at'])) ?></td>
                                                                <td><span class="badge badge-success">Active</span></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary">
                                                                        <i class='bx bx-edit'></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center py-4">No user data available</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Add New User</h5>
                                        </div>
                                        <div class="card-body">
                                            <form>
                                                <div class="mb-3">
                                                    <label class="form-label">Email Address</label>
                                                    <input type="email" class="form-control">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Full Name</label>
                                                    <input type="text" class="form-control">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <select class="form-select">
                                                        <option>Administrator</option>
                                                        <option>Dispatcher</option>
                                                        <option>Viewer</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Initial Password</label>
                                                    <input type="password" class="form-control">
                                                </div>
                                                <button type="submit" class="btn btn-primary">Create User</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Access Logs & Security Events</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>User</th>
                                                            <th>Action</th>
                                                            <th>Time</th>
                                                            <th>IP Address</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($access_logs)): ?>
                                                            <?php foreach ($access_logs as $log): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($log['user_id']) ?></td>
                                                                <td><?= htmlspecialchars($log['action']) ?></td>
                                                                <td><?= date('M j, H:i', strtotime($log['created_at'])) ?></td>
                                                                <td><?= $log['ip_address'] ?? 'N/A' ?></td>
                                                                <td><span class="badge badge-success">Success</span></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="5" class="text-center py-4">No access logs available</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Role Permissions</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Administrator</h6>
                                                    <ul class="list-unstyled">
                                                        <li><i class='bx bx-check'></i> Full system access</li>
                                                        <li><i class='bx bx-check'></i> User management</li>
                                                        <li><i class='bx bx-check'></i> Report generation</li>
                                                        <li><i class='bx bx-check'></i> System configuration</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Dispatcher</h6>
                                                    <ul class="list-unstyled">
                                                        <li><i class='bx bx-check'></i> Incident management</li>
                                                        <li><i class='bx bx-check'></i> Unit dispatch</li>
                                                        <li><i class='bx bx-check'></i> View reports</li>
                                                        <li><i class='bx bx-x'></i> User management</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <!-- Default/Unknown Submodule -->
                            <div class="text-center py-5">
                                <i class='bx bx-error-circle' style="font-size: 4rem; color: #dc3545;"></i>
                                <h3 class="mt-3">Module Not Found</h3>
                                <p class="text-muted">The requested module could not be loaded.</p>
                                <a href="?tab=modules&submodule=intake" class="btn btn-primary mt-2">Go to Incident Intake</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
            <!-- Default Content for Other Tabs -->
            <div class="animate-fade-in">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php 
                            $tabIcons = [
                                'incidents' => 'bx-alarm-exclamation',
                                'units' => 'bx-car',
                                'map' => 'bx-map',
                                'reports' => 'bx-file'
                            ];
                            ?>
                            <i class='bx <?= $tabIcons[$active_tab] ?? 'bx-info-circle' ?>'></i>
                            <?= ucwords($active_tab) ?> Overview
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class='bx bx-time' style="font-size: 4rem; color: #6c757d;"></i>
                            <h3 class="mt-3">Content Coming Soon</h3>
                            <p class="text-muted">This section is currently under development.</p>
                            <a href="?tab=dashboard" class="btn btn-primary mt-2">Return to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modals -->
    <!-- New Incident Modal -->
    <div class="modal fade" id="newIncidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report New Incident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="create_incident" value="1">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="modalIncidentType" class="form-label">Incident Type</label>
                                <select class="form-select" id="modalIncidentType" name="incident_type" required>
                                    <option value="">Select incident type</option>
                                    <option value="fire">Fire</option>
                                    <option value="medical">Medical Emergency</option>
                                    <option value="traffic">Traffic Accident</option>
                                    <option value="crime">Criminal Activity</option>
                                    <option value="hazard">Public Hazard</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="modalPriority" class="form-label">Priority Level</label>
                                <select class="form-select" id="modalPriority" name="priority" required>
                                    <option value="">Select priority</option>
                                    <option value="critical">Critical</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="modalBarangay" class="form-label">Barangay</label>
                                <select class="form-select" id="modalBarangay" name="barangay" required>
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?= $barangay['barangay'] ?>"><?= htmlspecialchars($barangay['barangay']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="modalReporterPhone" class="form-label">Reporter Phone</label>
                                <input type="tel" class="form-control" id="modalReporterPhone" name="reporter_phone">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalLocation" class="form-label">Incident Location</label>
                            <input type="text" class="form-control" id="modalLocation" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalDescription" class="form-label">Incident Description</label>
                            <textarea class="form-control" id="modalDescription" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalInjuries" class="form-label">Injuries/Casualties</label>
                            <input type="number" class="form-control" id="modalInjuries" name="injuries" min="0" value="0">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="newIncidentForm">Submit Incident</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dispatch Unit Modal -->
    <div class="modal fade" id="dispatchUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dispatch Response Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="dispatch_unit" value="1">
                        <div class="mb-3">
                            <label class="form-label">Select Incident</label>
                            <select class="form-select" name="incident_id" required>
                                <option value="">Select incident...</option>
                                <?php if (!empty($recent_incidents)): ?>
                                    <?php foreach ($recent_incidents as $incident): ?>
                                    <option value="<?= $incident['id'] ?>">#<?= $incident['id'] ?> - <?= htmlspecialchars($incident['incident_type']) ?> (<?= $incident['priority'] ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Unit</label>
                            <select class="form-select" name="unit_id" required>
                                <option value="">Select unit...</option>
                                <?php if (!empty($available_units)): ?>
                                    <?php foreach ($available_units as $unit): ?>
                                    <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['unit_name']) ?> (<?= $unit['unit_type'] ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dispatch Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Dispatch Unit</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Unit Modal -->
    <div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="add_unit" value="1">
                        <div class="mb-3">
                            <label class="form-label">Unit Name</label>
                            <input type="text" class="form-control" name="unit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Type</label>
                            <select class="form-select" name="unit_type" required>
                                <option value="Fire Engine">Fire Engine</option>
                                <option value="Ambulance">Ambulance</option>
                                <option value="Rescue Truck">Rescue Truck</option>
                                <option value="HazMat Unit">HazMat Unit</option>
                                <option value="Command Vehicle">Command Vehicle</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Station</label>
                            <input type="text" class="form-control" name="station" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['barangay'] ?>"><?= htmlspecialchars($barangay['barangay']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Personnel Count</label>
                            <input type="number" class="form-control" name="personnel_count" min="1" value="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Equipment</label>
                            <textarea class="form-control" name="equipment" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Unit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        // Fire loading animation function
        function showFireLoader() {
            const fireLoader = document.getElementById('fireLoader');
            fireLoader.classList.add('active');
        }
        
        function hideFireLoader() {
            const fireLoader = document.getElementById('fireLoader');
            fireLoader.classList.remove('active');
        }
        
        // Add loading animation to sidebar links
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function(e) {
                // Don't show loader for logout or external links
                if (this.getAttribute('href').includes('logout') || 
                    this.getAttribute('href').startsWith('http')) {
                    return;
                }
                
                e.preventDefault();
                const targetUrl = this.getAttribute('href');
                
                showFireLoader();
                
                // Simulate loading delay for demonstration
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 1500);
            });
        });
        
        // Add loading animation to form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                showFireLoader();
            });
        });
        
        // Add loading animation to button clicks
        document.querySelectorAll('.btn-primary').forEach(button => {
            if (!button.getAttribute('data-bs-toggle') && 
                !button.getAttribute('data-bs-dismiss') &&
                button.getAttribute('type') !== 'submit') {
                button.addEventListener('click', function(e) {
                    showFireLoader();
                    
                    // Simulate processing
                    setTimeout(() => {
                        hideFireLoader();
                    }, 1500);
                });
            }
        });
        
        // Modal functionality
        const viewIncidentModal = document.getElementById('viewIncidentModal');
        if (viewIncidentModal) {
            viewIncidentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const incidentId = button.getAttribute('data-id');
                
                // In a real application, you would fetch incident details based on the ID
                // For demo purposes, we'll just set some example data
                document.getElementById('incidentIdDisplay').textContent = '#' + incidentId;
                document.getElementById('incidentTypeDisplay').textContent = 'Fire Emergency';
                document.getElementById('incidentPriorityDisplay').textContent = 'Critical';
                document.getElementById('incidentPriorityDisplay').className = 'badge badge-critical';
                document.getElementById('incidentReporterDisplay').textContent = 'John Smith';
                document.getElementById('incidentContactDisplay').textContent = '+63 912 345 6789';
                document.getElementById('incidentStatusDisplay').textContent = 'Responding';
                document.getElementById('incidentStatusDisplay').className = 'badge badge-responding';
                document.getElementById('incidentReportedDisplay').textContent = 'Oct 15, 2023 08:15 AM';
                document.getElementById('incidentUpdatedDisplay').textContent = 'Oct 15, 2023 08:45 AM';
                document.getElementById('incidentLocationDisplay').textContent = '123 Main Street, Quezon City';
                document.getElementById('incidentDescriptionDisplay').textContent = 'Commercial building fire on the 3rd floor. Smoke visible from several blocks away. Residents have been evacuated.';
                document.getElementById('incidentInjuriesDisplay').textContent = '2';
                document.getElementById('incidentFatalitiesDisplay').textContent = '0';
                document.getElementById('incidentUnitsDisplay').textContent = '3';
                document.getElementById('incidentPersonnelDisplay').textContent = '12';
            });
        }
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Simulate real-time updates for demonstration
        function updateStatsPeriodically() {
            setInterval(() => {
                // Randomly increment or decrement stats for demo purposes
                const statCards = document.querySelectorAll('.stat-value');
                statCards.forEach(card => {
                    const currentValue = parseInt(card.textContent);
                    if (!isNaN(currentValue)) {
                        const change = Math.random() > 0.5 ? 1 : -1;
                        const newValue = Math.max(0, currentValue + change);
                        card.textContent = newValue;
                    }
                });
            }, 10000);
        }
        
        // AI Classify button functionality
        document.getElementById('aiClassifyBtn')?.addEventListener('click', function() {
            showFireLoader();
            
            // Simulate AI processing
            setTimeout(() => {
                hideFireLoader();
                
                // Set some example values based on "fire" content
                const description = document.getElementById('description').value.toLowerCase();
                
                if (description.includes('fire')) {
                    document.getElementById('incidentType').value = 'fire';
                    document.getElementById('priority').value = 'critical';
                    
                    // Show AI prediction box if it exists
                    const predictionBox = document.querySelector('.ai-prediction');
                    if (predictionBox) {
                        predictionBox.innerHTML = `
                            <h5><i class='bx bx-brain'></i> AI Classification Prediction</h5>
                            <p>Based on your input, our AI predicts this incident as: <strong>Fire Emergency</strong> with <strong>Critical</strong> priority</p>
                            <small>Confidence: 92% | Response time estimate: 5-7 minutes</small>
                        `;
                    }
                }
                
                alert('AI classification complete! The form has been auto-filled based on the analysis.');
            }, 2000);
        });
        
        // Start the demo updates
        updateStatsPeriodically();
    </script>
</body>
</html>