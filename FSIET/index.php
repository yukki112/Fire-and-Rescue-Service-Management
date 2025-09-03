<?php
session_start();

// Include the Database Manager
require_once '../database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user role and permissions
$user_id = $_SESSION['user_id'];
$stmt = $dbManager->query("frsm", "SELECT is_admin FROM users WHERE id = ?", [$user_id]);
$user = $stmt->fetch();

$is_admin = $user['is_admin'] ?? 0;

// Get active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'dashboard';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add inventory item
    if (isset($_POST['add_inventory'])) {
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        $quantity = $_POST['quantity'];
        $min_stock_level = $_POST['min_stock_level'];
        $storage_location = $_POST['storage_location'];
        $notes = $_POST['notes'] ?? '';
        
        // Determine status based on quantity
        $status = 'in_stock';
        if ($quantity == 0) {
            $status = 'out_of_stock';
        } elseif ($quantity <= $min_stock_level) {
            $status = 'low_stock';
        }
        
        $stmt = $dbManager->query("fsiet", "INSERT INTO inventory_items (name, category_id, quantity, min_stock_level, storage_location, status, description) VALUES (?, ?, ?, ?, ?, ?, ?)", 
            [$name, $category_id, $quantity, $min_stock_level, $storage_location, $status, $notes]);
        
        // Log the action
        $last_id = $dbManager->getConnection("fsiet")->lastInsertId();
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'CREATE', 'inventory_items', $last_id]);
        
        header('Location: index.php?tab=inventory&success=Item added successfully');
        exit;
    }
    
    // Add equipment
    if (isset($_POST['add_equipment'])) {
        $name = $_POST['name'];
        $type = $_POST['type'];
        $serial_no = $_POST['serial_no'] ?? '';
        $assigned_unit = $_POST['assigned_unit'] ?? null;
        
        $stmt = $dbManager->query("fsiet", "INSERT INTO equipment (name, type, serial_no, assigned_unit, status) VALUES (?, ?, ?, ?, 'available')", 
            [$name, $type, $serial_no, $assigned_unit]);
        
        // Log the action
        $last_id = $dbManager->getConnection("fsiet")->lastInsertId();
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'CREATE', 'equipment', $last_id]);
        
        header('Location: index.php?tab=equipment&success=Equipment added successfully');
        exit;
    }
    
    // Add maintenance schedule
    if (isset($_POST['add_maintenance'])) {
        $equipment_id = $_POST['equipment_id'];
        $schedule_type = $_POST['schedule_type'];
        $next_maintenance = $_POST['next_maintenance'];
        $description = $_POST['description'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?? null;
        
        $stmt = $dbManager->query("fsiet", "INSERT INTO maintenance_schedules (equipment_id, schedule_type, next_maintenance, description, assigned_to, status) VALUES (?, ?, ?, ?, ?, 'pending')", 
            [$equipment_id, $schedule_type, $next_maintenance, $description, $assigned_to]);
        
        // Log the action
        $last_id = $dbManager->getConnection("fsiet")->lastInsertId();
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'CREATE', 'maintenance_schedules', $last_id]);
        
        header('Location: index.php?tab=maintenance&success=Maintenance scheduled successfully');
        exit;
    }
    
    // Assign equipment
    if (isset($_POST['assign_equipment'])) {
        $equipment_id = $_POST['equipment_id'];
        $assigned_to = $_POST['assigned_to'];
        $expected_return = $_POST['expected_return'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $dbManager->query("fsiet", "INSERT INTO equipment_assignments (equipment_id, assigned_to, assigned_by, assigned_date, expected_return, notes, status) VALUES (?, ?, ?, CURDATE(), ?, ?, 'assigned')", 
            [$equipment_id, $assigned_to, $user_id, $expected_return, $notes]);
        
        // Update equipment status
        $stmt = $dbManager->query("fsiet", "UPDATE equipment SET status = 'in-use' WHERE id = ?", [$equipment_id]);
        
        // Log the action
        $last_id = $dbManager->getConnection("fsiet")->lastInsertId();
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'CREATE', 'equipment_assignments', $last_id]);
        
        header('Location: index.php?tab=equipment&success=Equipment assigned successfully');
        exit;
    }
    
    // Update inventory item
    if (isset($_POST['update_inventory'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        $quantity = $_POST['quantity'];
        $min_stock_level = $_POST['min_stock_level'];
        $storage_location = $_POST['storage_location'];
        $notes = $_POST['notes'] ?? '';
        
        // Determine status based on quantity
        $status = 'in_stock';
        if ($quantity == 0) {
            $status = 'out_of_stock';
        } elseif ($quantity <= $min_stock_level) {
            $status = 'low_stock';
        }
        
        $stmt = $dbManager->query("fsiet", "UPDATE inventory_items SET name = ?, category_id = ?, quantity = ?, min_stock_level = ?, storage_location = ?, status = ?, description = ? WHERE id = ?", 
            [$name, $category_id, $quantity, $min_stock_level, $storage_location, $status, $notes, $id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'UPDATE', 'inventory_items', $id]);
        
        header('Location: index.php?tab=inventory&success=Item updated successfully');
        exit;
    }
    
    // Update equipment
    if (isset($_POST['update_equipment'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $type = $_POST['type'];
        $serial_no = $_POST['serial_no'] ?? '';
        $assigned_unit = $_POST['assigned_unit'] ?? null;
        
        $stmt = $dbManager->query("fsiet", "UPDATE equipment SET name = ?, type = ?, serial_no = ?, assigned_unit = ? WHERE id = ?", 
            [$name, $type, $serial_no, $assigned_unit, $id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'UPDATE', 'equipment', $id]);
        
        header('Location: index.php?tab=equipment&success=Equipment updated successfully');
        exit;
    }
    
    // Return equipment
    if (isset($_POST['return_equipment'])) {
        $id = $_POST['id'];
        $condition = $_POST['condition'];
        $notes = $_POST['notes'] ?? '';
        
        // Get equipment ID from assignment
        $stmt = $dbManager->query("fsiet", "SELECT equipment_id FROM equipment_assignments WHERE id = ?", [$id]);
        $assignment = $stmt->fetch();
        $equipment_id = $assignment['equipment_id'];
        
        // Update assignment
        $return_notes = "\nReturned on " . date('Y-m-d') . " - Condition: " . $condition . " - " . $notes;
        $stmt = $dbManager->query("fsiet", "UPDATE equipment_assignments SET return_date = CURDATE(), status = 'returned', notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?", 
            [$return_notes, $id]);
        
        // Update equipment status
        $stmt = $dbManager->query("fsiet", "UPDATE equipment SET status = 'available' WHERE id = ?", [$equipment_id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'UPDATE', 'equipment_assignments', $id]);
        
        header('Location: index.php?tab=assignments&success=Equipment returned successfully');
        exit;
    }
}

// Handle delete actions
if (isset($_GET['delete'])) {
    $table = $_GET['table'];
    $id = $_GET['id'];
    
    if ($table === 'inventory') {
        $stmt = $dbManager->query("fsiet", "DELETE FROM inventory_items WHERE id = ?", [$id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'DELETE', 'inventory_items', $id]);
        
        header('Location: index.php?tab=inventory&success=Item deleted successfully');
        exit;
    } elseif ($table === 'equipment') {
        $stmt = $dbManager->query("fsiet", "DELETE FROM equipment WHERE id = ?", [$id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'DELETE', 'equipment', $id]);
        
        header('Location: index.php?tab=equipment&success=Equipment deleted successfully');
        exit;
    } elseif ($table === 'maintenance') {
        $stmt = $dbManager->query("fsiet", "DELETE FROM maintenance_schedules WHERE id = ?", [$id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'DELETE', 'maintenance_schedules', $id]);
        
        header('Location: index.php?tab=maintenance&success=Maintenance schedule deleted successfully');
        exit;
    }
}

// Handle status updates
if (isset($_GET['update_status'])) {
    $table = $_GET['table'];
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    if ($table === 'maintenance') {
        $stmt = $dbManager->query("fsiet", "UPDATE maintenance_schedules SET status = ? WHERE id = ?", [$status, $id]);
        
        // Log the action
        $stmt = $dbManager->query("fsiet", "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)", 
            [$user_id, 'UPDATE', 'maintenance_schedules', $id]);
        
        header('Location: index.php?tab=maintenance&success=Status updated successfully');
        exit;
    }
}

// Get inventory categories
$stmt = $dbManager->query("fsiet", "SELECT * FROM inventory_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get all inventory items
$stmt = $dbManager->query("fsiet", "SELECT ii.*, ic.name as category_name FROM inventory_items ii LEFT JOIN inventory_categories ic ON ii.category_id = ic.id ORDER BY ii.name");
$inventory_items = $stmt->fetchAll();

// Get all equipment
$stmt = $dbManager->query("fsiet", "SELECT * FROM equipment ORDER BY name");
$all_equipment = $stmt->fetchAll();

// Get all users for assignment
$stmt = $dbManager->query("frsm", "SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
$users = $stmt->fetchAll();

// Get all units for assignment
$stmt = $dbManager->query("ird", "SELECT id, unit_name FROM units ORDER BY unit_name");
$units = $stmt->fetchAll();

// Get all maintenance schedules
$stmt = $dbManager->query("fsiet", "
    SELECT ms.*, e.name as equipment_name, e.type as equipment_type
    FROM maintenance_schedules ms
    LEFT JOIN equipment e ON ms.equipment_id = e.id
    ORDER BY ms.next_maintenance ASC
");
$maintenance_schedules = $stmt->fetchAll();

// Get equipment assignments
$stmt = $dbManager->query("fsiet", "
    SELECT ea.*, e.name as equipment_name, e.type as equipment_type, 
           u.first_name, u.last_name
    FROM equipment_assignments ea
    LEFT JOIN equipment e ON ea.equipment_id = e.id
    LEFT JOIN frsm.users u ON ea.assigned_to = u.id
    ORDER BY ea.assigned_date DESC
");
$equipment_assignments = $stmt->fetchAll();

// Get audit logs
$stmt = $dbManager->query("frsm", "
    SELECT al.*, u.first_name, u.last_name
    FROM audit_logs al
    LEFT JOIN frsm.users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 50
");
$audit_logs = $stmt->fetchAll();

// Calculate stats
$total_items = count($inventory_items);
$in_stock = 0;
$low_stock = 0;
foreach ($inventory_items as $item) {
    if ($item['status'] === 'in_stock') $in_stock++;
    if ($item['status'] === 'low_stock') $low_stock++;
}

$total_equipment = count($all_equipment);
$available_equipment = 0;
$in_use_equipment = 0;
foreach ($all_equipment as $equip) {
    if ($equip['status'] === 'available') $available_equipment++;
    if ($equip['status'] === 'in-use') $in_use_equipment++;
}

$pending_maintenance = 0;
foreach ($maintenance_schedules as $schedule) {
    if ($schedule['status'] === 'pending' || $schedule['status'] === 'overdue') $pending_maintenance++;
}

$critical_alerts = $low_stock; // You can customize this based on your criteria

// Get user info
$stmt = $dbManager->query("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
$user_info = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Fire Station Inventory & Equipment Tracking</title>
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
            color: white;
        }
        
        .sidebar-header .text small {
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
            color: #cbd5e1;
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
        
        .stat-card.inventory::before { background: var(--primary-gradient); }
        .stat-card.equipment::before { background: linear-gradient(135deg, #fd7e14 0%, #ff9f43 100%); }
        .stat-card.maintenance::before { background: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%); }
        .stat-card.alerts::before { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); }
        
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
        
        .stat-card.equipment .stat-icon { 
            background: rgba(253, 126, 20, 0.1); 
            color: var(--accent); 
        }
        
        .stat-card.maintenance .stat-icon { 
            background: rgba(255, 193, 7, 0.1); 
            color: var(--warning); 
        }
        
        .stat-card.alerts .stat-icon { 
            background: rgba(220, 53, 69, 0.1); 
            color: var(--danger); 
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 14px;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stat-details {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid var(--gray-200);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
            font-size: 13px;
        }
        
        .stat-detail {
            display: flex;
            align-items: center;
            color: var(--secondary);
        }
        
        .stat-detail i {
            margin-right: 0.5rem;
        }
        
        .detail-success { color: var(--success); }
        .detail-warning { color: var(--warning); }
        .detail-danger { color: var(--danger); }
        
        /* Content Cards */
        .content-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(220, 53, 69, 0.1);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(220, 53, 69, 0.03);
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Filter Section */
        .filter-section {
            background: rgba(220, 53, 69, 0.03);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(220, 53, 69, 0.05);
        }
        
        /* Tables */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(220, 53, 69, 0.05);
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: rgba(220, 53, 69, 0.05);
            border-top: none;
            font-weight: 600;
            color: var(--dark);
            padding: 1rem;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Montserrat', sans-serif;
            border-bottom: 2px solid rgba(220, 53, 69, 0.1);
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid rgba(220, 53, 69, 0.05);
            font-size: 14px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(220, 53, 69, 0.03);
            transform: scale(1.01);
            transition: var(--transition);
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-in_stock, .status-available { background-color: var(--success); }
        .status-low_stock, .status-maintenance { background-color: var(--warning); }
        .status-out_of_stock, .status-in_use { background-color: var(--danger); }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 13px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffd54f 100%);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-outline-primary {
            border: 1px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-icon i {
            margin-right: 8px;
            font-size: 13px;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        /* Modals */
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: rgba(220, 53, 69, 0.03);
        }
        
        .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.25rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Utilities */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .spinner-border {
            width: 1.25rem;
            height: 1.25rem;
        }
        
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Pagination */
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 0.25rem;
            border: 1px solid var(--gray-200);
            color: var(--secondary);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover {
            background: rgba(220, 53, 69, 0.1);
            color: var(--primary);
            border-color: var(--primary-light);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: var(--primary);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block !important;
            }
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
            box-shadow: 0 0 20px 10px #ff9800;
            animation: fire-flicker 1.5s infinite alternate;
        }
        
        .fire-flame {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 50px;
            background: #ff5722;
            border-radius: 50% 50% 20% 20%;
            box-shadow: 0 0 20px 10px #ff5722;
            animation: fire-flicker 1s infinite alternate;
        }
        
        @keyframes fire-flicker {
            0% { transform: translateX(-50%) scaleY(1); }
            25% { transform: translateX(-50%) scaleY(1.1); }
            50% { transform: translateX(-50%) scaleY(0.9); }
            75% { transform: translateX(-50%) scaleY(1.05); }
            100% { transform: translateX(-50%) scaleY(1); }
        }
        
        .loading-text {
            color: white;
            margin-top: 120px;
            font-size: 18px;
            text-align: center;
            font-weight: 500;
            text-shadow: 0 0 10px rgba(255, 87, 34, 0.8);
        }
        
        /* Custom Tabs */
        .custom-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
        }
        
        .custom-tab {
            padding: 0.75rem 1.5rem;
            border-bottom: 3px solid transparent;
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .custom-tab:hover, .custom-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
            padding: 2px;
        }
        
        .user-info {
            line-height: 1.3;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
        }
        
        .user-role {
            font-size: 12px;
            color: var(--secondary);
        }
        
        /* Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            transition: var(--transition);
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.05);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(220, 53, 69, 0.1);
        }
        
        /* Activity Feed */
        .activity-feed {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            background: rgba(220, 53, 69, 0.1);
            color: var(--primary);
            font-size: 1rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
            font-size: 14px;
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--secondary);
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
    </style>
</head>
<body>
    <!-- Fire Loading Animation -->
    <div class="fire-loader" id="fireLoader">
        <div class="fire-container">
            <div class="fire">
                <div class="fire-center"></div>
                <div class="fire-flame"></div>
            </div>
            <div class="loading-text">Quezon City Fire Station</div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIiBmaWxsPSIjZmZmIj48cGF0aCBkPSJNMjU2IDBDMTE0LjYgMCAwIDExNC42IDAgMjU2czExNC42IDI1NiAyNTYgMjU2czI1Ni0xMTQuNiAyNTYtMjU2UzM5Ny40IDAgMjU2IDB6TTQwMCAyODhIMjcyVjUxMmgtMzJWMjg4SDExMlYyMjRoMTI4VjEyOGgxMjB2OTZINDAwVjI4OHoiLz48L3N2Zz4=" alt="Logo">
                <div class="text">
                    Quezon City Fire Station
                    <small>Inventory & Equipment</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="index.php?tab=dashboard" class="sidebar-link <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>
                <a href="index.php?tab=inventory" class="sidebar-link <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>">
                    <i class='bx bx-package'></i>
                    <span class="text">Inventory</span>
                </a>
                <a href="index.php?tab=equipment" class="sidebar-link <?php echo $active_tab == 'equipment' ? 'active' : ''; ?>">
                    <i class='bx bx-wrench'></i>
                    <span class="text">Equipment</span>
                </a>
                <a href="index.php?tab=maintenance" class="sidebar-link <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">
                    <i class='bx bx-calendar'></i>
                    <span class="text">Maintenance</span>
                </a>
                <a href="index.php?tab=assignments" class="sidebar-link <?php echo $active_tab == 'assignments' ? 'active' : ''; ?>">
                    <i class='bx bx-transfer'></i>
                    <span class="text">Assignments</span>
                </a>
                
                <?php if ($is_admin): ?>
                <div class="sidebar-section">Administration</div>
                <a href="index.php?tab=audit" class="sidebar-link <?php echo $active_tab == 'audit' ? 'active' : ''; ?>">
                    <i class='bx bx-history'></i>
                    <span class="text">Audit Logs</span>
                </a>
                <a href="index.php?tab=users" class="sidebar-link <?php echo $active_tab == 'users' ? 'active' : ''; ?>">
                    <i class='bx bx-user'></i>
                    <span class="text">User Management</span>
                </a>
                <?php endif; ?>
                
                <div class="sidebar-section">Account</div>
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
                <div class="page-title">
                    <h1>
                        <?php 
                        switch($active_tab) {
                            case 'dashboard': echo 'Dashboard'; break;
                            case 'inventory': echo 'Inventory Management'; break;
                            case 'equipment': echo 'Equipment Tracking'; break;
                            case 'maintenance': echo 'Maintenance Schedules'; break;
                            case 'assignments': echo 'Equipment Assignments'; break;
                            case 'audit': echo 'Audit Logs'; break;
                            case 'users': echo 'User Management'; break;
                            default: echo 'Dashboard';
                        }
                        ?>
                    </h1>
                    <p>Quezon City Fire Station Inventory & Equipment Tracking System</p>
                </div>
                
                <div class="header-actions">
                    <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                        <i class='bx bx-menu'></i>
                    </button>
                    
                    <div class="user-profile">
                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0NDggNTEyIj48cGF0aCBkPSJNMjI0IDI1NkE1NiA1NiAwIDEgMCAyMjQgMTQ0YTU2IDU2IDAgMSAwIDAgMTEyem0wLTIyNGM3MCAwIDIwOC41IDYwIDE5MiAxODRIMzJDMTAuNSA5MiA2MiAzMiAxMzIgMzJoOTJ6bTAgNDQ4Yy03MCAwLTIwOC41LTYwLTE5Mi0xODRoMzg0YzMwLjUgOTItNjIgMTUyLTEzMiAxNTJIMjI0eiIgZmlsbD0iI2RjMzU0NSIvPjwvc3ZnPg==" alt="User" class="user-avatar">
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></div>
                            <div class="user-role"><?php echo $is_admin ? 'Administrator' : 'User'; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class='bx bx-check-circle me-2'></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle me-2'></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <?php if ($active_tab == 'dashboard'): ?>
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card inventory fade-in">
                        <div class="stat-icon">
                            <i class='bx bx-package'></i>
                        </div>
                        <div class="stat-value"><?php echo $total_items; ?></div>
                        <div class="stat-label">Total Inventory Items</div>
                        <div class="stat-details">
                            <div class="stat-detail detail-success">
                                <i class='bx bx-check-circle'></i>
                                <span><?php echo $in_stock; ?> In Stock</span>
                            </div>
                            <div class="stat-detail detail-warning">
                                <i class='bx bx-error-circle'></i>
                                <span><?php echo $low_stock; ?> Low Stock</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card equipment fade-in">
                        <div class="stat-icon">
                            <i class='bx bx-wrench'></i>
                        </div>
                        <div class="stat-value"><?php echo $total_equipment; ?></div>
                        <div class="stat-label">Total Equipment</div>
                        <div class="stat-details">
                            <div class="stat-detail detail-success">
                                <i class='bx bx-check-circle'></i>
                                <span><?php echo $available_equipment; ?> Available</span>
                            </div>
                            <div class="stat-detail detail-danger">
                                <i class='bx bx-x-circle'></i>
                                <span><?php echo $in_use_equipment; ?> In Use</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card maintenance fade-in">
                        <div class="stat-icon">
                            <i class='bx bx-calendar'></i>
                        </div>
                        <div class="stat-value"><?php echo count($maintenance_schedules); ?></div>
                        <div class="stat-label">Maintenance Schedules</div>
                        <div class="stat-details">
                            <div class="stat-detail detail-warning">
                                <i class='bx bx-time-five'></i>
                                <span><?php echo $pending_maintenance; ?> Pending</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card alerts fade-in">
                        <div class="stat-icon">
                            <i class='bx bx-alarm'></i>
                        </div>
                        <div class="stat-value"><?php echo $critical_alerts; ?></div>
                        <div class="stat-label">Critical Alerts</div>
                        <div class="stat-details">
                            <div class="stat-detail detail-danger">
                                <i class='bx bx-error'></i>
                                <span><?php echo $critical_alerts; ?> Needs Attention</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Inventory Items -->
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Recent Inventory Items</h5>
                            <a href="index.php?tab=inventory" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recent_items = array_slice($inventory_items, 0, 5);
                                        foreach ($recent_items as $item): 
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>
                                                <span class="status-indicator status-<?php echo $item['status']; ?>"></span>
                                                <?php 
                                                if ($item['status'] == 'in_stock') echo 'In Stock';
                                                elseif ($item['status'] == 'low_stock') echo 'Low Stock';
                                                else echo 'Out of Stock';
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Equipment -->
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Recent Equipment</h5>
                            <a href="index.php?tab=equipment" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Equipment</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recent_equipment = array_slice($all_equipment, 0, 5);
                                        foreach ($recent_equipment as $equip): 
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($equip['name']); ?></td>
                                            <td><?php echo htmlspecialchars($equip['type']); ?></td>
                                            <td>
                                                <span class="status-indicator status-<?php echo $equip['status']; ?>"></span>
                                                <?php 
                                                if ($equip['status'] == 'available') echo 'Available';
                                                elseif ($equip['status'] == 'in-use') echo 'In Use';
                                                else echo 'Maintenance';
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <ul class="activity-feed">
                            <?php 
                            $recent_logs = array_slice($audit_logs, 0, 8);
                            foreach ($recent_logs as $log): 
                                $action_icon = 'bx-history';
                                $action_text = 'performed an action';
                                if ($log['action'] == 'CREATE') {
                                    $action_icon = 'bx-plus-circle';
                                    $action_text = 'added a new record';
                                } elseif ($log['action'] == 'UPDATE') {
                                    $action_icon = 'bx-edit';
                                    $action_text = 'updated a record';
                                } elseif ($log['action'] == 'DELETE') {
                                    $action_icon = 'bx-trash';
                                    $action_text = 'deleted a record';
                                }
                            ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class='bx <?php echo $action_icon; ?>'></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong> <?php echo $action_text; ?> in <?php echo $log['table_name']; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inventory Tab -->
            <?php if ($active_tab == 'inventory'): ?>
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Inventory Management</h5>
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                            <i class='bx bx-plus'></i> Add New Item
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" id="categoryFilter">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" id="statusFilter">
                                            <option value="">All Status</option>
                                            <option value="in_stock">In Stock</option>
                                            <option value="low_stock">Low Stock</option>
                                            <option value="out_of_stock">Out of Stock</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" id="searchInput" placeholder="Search by name or location...">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-outline-primary w-100" id="resetFilters">Reset</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventory Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="inventoryTable">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Min Stock</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo $item['min_stock_level']; ?></td>
                                        <td><?php echo htmlspecialchars($item['storage_location']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($item['status'] == 'in_stock') echo 'bg-success';
                                                elseif ($item['status'] == 'low_stock') echo 'bg-warning';
                                                else echo 'bg-danger';
                                                ?>
                                            ">
                                                <?php 
                                                if ($item['status'] == 'in_stock') echo 'In Stock';
                                                elseif ($item['status'] == 'low_stock') echo 'Low Stock';
                                                else echo 'Out of Stock';
                                                ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editInventoryModal<?php echo $item['id']; ?>">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <a href="index.php?tab=inventory&delete=1&table=inventory&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this item?')">
                                                <i class='bx bx-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Inventory Modal -->
                                    <div class="modal fade" id="editInventoryModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Inventory Item</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Item Name</label>
                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Category</label>
                                                            <select class="form-select" name="category_id" required>
                                                                <option value="">Select Category</option>
                                                                <?php foreach ($categories as $category): ?>
                                                                <option value="<?php echo $category['id']; ?>" <?php echo $item['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Quantity</label>
                                                                    <input type="number" class="form-control" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Min Stock Level</label>
                                                                    <input type="number" class="form-control" name="min_stock_level" value="<?php echo $item['min_stock_level']; ?>" min="1" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Storage Location</label>
                                                            <input type="text" class="form-control" name="storage_location" value="<?php echo htmlspecialchars($item['storage_location']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Notes</label>
                                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_inventory" class="btn btn-primary">Update Item</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Add Inventory Modal -->
                <div class="modal fade" id="addInventoryModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Inventory Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Item Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
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
                                                <label class="form-label">Quantity</label>
                                                <input type="number" class="form-control" name="quantity" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Min Stock Level</label>
                                                <input type="number" class="form-control" name="min_stock_level" min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Storage Location</label>
                                        <input type="text" class="form-control" name="storage_location" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_inventory" class="btn btn-primary">Add Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Equipment Tab -->
            <?php if ($active_tab == 'equipment'): ?>
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Equipment Management</h5>
                        <div>
                            <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                <i class='bx bx-plus'></i> Add Equipment
                            </button>
                            <button class="btn btn-success btn-icon" data-bs-toggle="modal" data-bs-target="#assignEquipmentModal">
                                <i class='bx bx-transfer'></i> Assign Equipment
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Equipment Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Equipment Name</th>
                                        <th>Type</th>
                                        <th>Serial No</th>
                                        <th>Assigned Unit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_equipment as $equip): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($equip['name']); ?></td>
                                        <td><?php echo htmlspecialchars($equip['type']); ?></td>
                                        <td><?php echo htmlspecialchars($equip['serial_no'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            if ($equip['assigned_unit']) {
                                                foreach ($units as $unit) {
                                                    if ($unit['id'] == $equip['assigned_unit']) {
                                                        echo htmlspecialchars($unit['unit_name']);
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo 'Not Assigned';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($equip['status'] == 'available') echo 'bg-success';
                                                elseif ($equip['status'] == 'in-use') echo 'bg-primary';
                                                else echo 'bg-warning';
                                                ?>
                                            ">
                                                <?php 
                                                if ($equip['status'] == 'available') echo 'Available';
                                                elseif ($equip['status'] == 'in-use') echo 'In Use';
                                                else echo 'Maintenance';
                                                ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEquipmentModal<?php echo $equip['id']; ?>">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <a href="index.php?tab=equipment&delete=1&table=equipment&id=<?php echo $equip['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this equipment?')">
                                                <i class='bx bx-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Equipment Modal -->
                                    <div class="modal fade" id="editEquipmentModal<?php echo $equip['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Equipment</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $equip['id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Equipment Name</label>
                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($equip['name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Type</label>
                                                            <input type="text" class="form-control" name="type" value="<?php echo htmlspecialchars($equip['type']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Serial Number</label>
                                                            <input type="text" class="form-control" name="serial_no" value="<?php echo htmlspecialchars($equip['serial_no'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Assigned Unit</label>
                                                            <select class="form-select" name="assigned_unit">
                                                                <option value="">Not Assigned</option>
                                                                <?php foreach ($units as $unit): ?>
                                                                <option value="<?php echo $unit['id']; ?>" <?php echo $equip['assigned_unit'] == $unit['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($unit['unit_name']); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_equipment" class="btn btn-primary">Update Equipment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Add Equipment Modal -->
                <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Equipment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Equipment Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Type</label>
                                        <input type="text" class="form-control" name="type" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" name="serial_no">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Assigned Unit</label>
                                        <select class="form-select" name="assigned_unit">
                                            <option value="">Not Assigned</option>
                                            <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_equipment" class="btn btn-primary">Add Equipment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Assign Equipment Modal -->
                <div class="modal fade" id="assignEquipmentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Assign Equipment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Equipment</label>
                                        <select class="form-select" name="equipment_id" required>
                                            <option value="">Select Equipment</option>
                                            <?php 
                                            foreach ($all_equipment as $equip): 
                                                if ($equip['status'] == 'available'):
                                            ?>
                                            <option value="<?php echo $equip['id']; ?>"><?php echo htmlspecialchars($equip['name'] . ' (' . $equip['type'] . ')'); ?></option>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Assign To</label>
                                        <select class="form-select" name="assigned_to" required>
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Expected Return Date</label>
                                        <input type="date" class="form-control" name="expected_return">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="assign_equipment" class="btn btn-primary">Assign Equipment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Maintenance Tab -->
            <?php if ($active_tab == 'maintenance'): ?>
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Maintenance Schedules</h5>
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class='bx bx-plus'></i> Schedule Maintenance
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Maintenance Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Type</th>
                                        <th>Schedule Type</th>
                                        <th>Next Maintenance</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['equipment_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['equipment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['schedule_type']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($schedule['next_maintenance'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($schedule['assigned_to']) {
                                                foreach ($users as $user) {
                                                    if ($user['id'] == $schedule['assigned_to']) {
                                                        echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo 'Not Assigned';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($schedule['status'] == 'completed') echo 'bg-success';
                                                elseif ($schedule['status'] == 'pending') echo 'bg-warning';
                                                else echo 'bg-danger';
                                                ?>
                                            ">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($schedule['status'] != 'completed'): ?>
                                            <a href="index.php?tab=maintenance&update_status=1&table=maintenance&id=<?php echo $schedule['id']; ?>&status=completed" class="btn btn-sm btn-success">
                                                <i class='bx bx-check'></i> Complete
                                            </a>
                                            <?php endif; ?>
                                            <a href="index.php?tab=maintenance&delete=1&table=maintenance&id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this maintenance schedule?')">
                                                <i class='bx bx-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Add Maintenance Modal -->
                <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Schedule Maintenance</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Equipment</label>
                                        <select class="form-select" name="equipment_id" required>
                                            <option value="">Select Equipment</option>
                                            <?php foreach ($all_equipment as $equip): ?>
                                            <option value="<?php echo $equip['id']; ?>"><?php echo htmlspecialchars($equip['name'] . ' (' . $equip['type'] . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Schedule Type</label>
                                        <select class="form-select" name="schedule_type" required>
                                            <option value="">Select Type</option>
                                            <option value="Routine Check">Routine Check</option>
                                            <option value="Preventive Maintenance">Preventive Maintenance</option>
                                            <option value="Corrective Maintenance">Corrective Maintenance</option>
                                            <option value="Calibration">Calibration</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Next Maintenance Date</label>
                                        <input type="date" class="form-control" name="next_maintenance" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Assigned To</label>
                                        <select class="form-select" name="assigned_to">
                                            <option value="">Not Assigned</option>
                                            <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_maintenance" class="btn btn-primary">Schedule Maintenance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assignments Tab -->
            <?php if ($active_tab == 'assignments'): ?>
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Equipment Assignments</h5>
                    </div>
                    <div class="card-body">
                        <!-- Assignments Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Type</th>
                                        <th>Assigned To</th>
                                        <th>Assigned Date</th>
                                        <th>Expected Return</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipment_assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['equipment_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['equipment_type']); ?></td>
                                        <td>
                                            <?php 
                                            if ($assignment['first_name'] && $assignment['last_name']) {
                                                echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']);
                                            } else {
                                                echo 'Unknown User';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($assignment['expected_return']) {
                                                echo date('M j, Y', strtotime($assignment['expected_return']));
                                            } else {
                                                echo 'Not Specified';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($assignment['status'] == 'returned') echo 'bg-success';
                                                else echo 'bg-primary';
                                                ?>
                                            ">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($assignment['status'] != 'returned'): ?>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#returnEquipmentModal<?php echo $assignment['id']; ?>">
                                                <i class='bx bx-check'></i> Return
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Return Equipment Modal -->
                                    <div class="modal fade" id="returnEquipmentModal<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Return Equipment</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $assignment['id']; ?>">
                                                    <div class="modal-body">
                                                        <p>You are returning: <strong><?php echo htmlspecialchars($assignment['equipment_name']); ?></strong></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Condition</label>
                                                            <select class="form-select" name="condition" required>
                                                                <option value="">Select Condition</option>
                                                                <option value="Excellent">Excellent</option>
                                                                <option value="Good">Good</option>
                                                                <option value="Fair">Fair</option>
                                                                <option value="Poor">Poor</option>
                                                                <option value="Damaged">Damaged</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Notes</label>
                                                            <textarea class="form-control" name="notes" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="return_equipment" class="btn btn-primary">Confirm Return</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Audit Logs Tab -->
            <?php if ($active_tab == 'audit' && $is_admin): ?>
                <div class="content-card fade-in">
                    <div class="card-header">
                        <h5>Audit Logs</h5>
                    </div>
                    <div class="card-body">
                        <!-- Audit Logs Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($log['action'] == 'CREATE') echo 'bg-success';
                                                elseif ($log['action'] == 'UPDATE') echo 'bg-primary';
                                                else echo 'bg-danger';
                                                ?>
                                            ">
                                                <?php echo $log['action']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $log['table_name']; ?></td>
                                        <td><?php echo $log['record_id']; ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const fireLoader = document.getElementById('fireLoader');
            fireLoader.classList.add('active');
            
            // Hide loader after 1.5 seconds
            setTimeout(() => {
                fireLoader.classList.remove('active');
            }, 1500);
            
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Filter functionality for inventory
            const categoryFilter = document.getElementById('categoryFilter');
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            const resetFilters = document.getElementById('resetFilters');
            const inventoryTable = document.getElementById('inventoryTable');
            
            if (categoryFilter && statusFilter && searchInput && resetFilters && inventoryTable) {
                const filterTable = () => {
                    const categoryValue = categoryFilter.value.toLowerCase();
                    const statusValue = statusFilter.value.toLowerCase();
                    const searchValue = searchInput.value.toLowerCase();
                    
                    const rows = inventoryTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                    
                    for (let i = 0; i < rows.length; i++) {
                        const categoryCell = rows[i].getElementsByTagName('td')[1];
                        const statusCell = rows[i].getElementsByTagName('td')[5];
                        const nameCell = rows[i].getElementsByTagName('td')[0];
                        const locationCell = rows[i].getElementsByTagName('td')[4];
                        
                        if (categoryCell && statusCell && nameCell && locationCell) {
                            const categoryText = categoryCell.textContent || categoryCell.innerText;
                            const statusText = statusCell.textContent || statusCell.innerText;
                            const nameText = nameCell.textContent || nameCell.innerText;
                            const locationText = locationCell.textContent || locationCell.innerText;
                            
                            const categoryMatch = categoryValue === '' || categoryText.toLowerCase().includes(categoryValue);
                            const statusMatch = statusValue === '' || statusText.toLowerCase().includes(statusValue);
                            const searchMatch = searchValue === '' || 
                                nameText.toLowerCase().includes(searchValue) || 
                                locationText.toLowerCase().includes(searchValue);
                            
                            if (categoryMatch && statusMatch && searchMatch) {
                                rows[i].style.display = '';
                            } else {
                                rows[i].style.display = 'none';
                            }
                        }
                    }
                };
                
                categoryFilter.addEventListener('change', filterTable);
                statusFilter.addEventListener('change', filterTable);
                searchInput.addEventListener('keyup', filterTable);
                
                resetFilters.addEventListener('click', () => {
                    categoryFilter.value = '';
                    statusFilter.value = '';
                    searchInput.value = '';
                    filterTable();
                });
            }
        });
    </script>
</body>
</html>