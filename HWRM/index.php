<?php
session_start();
require_once '../config/database.php';

// Handle API requests
if (isset($_GET['api']) || isset($_POST['api']) || strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    header('Content-Type: application/json');
    
    // Extract API endpoint from URL or parameters
    $api_endpoint = '';
    if (isset($_GET['api'])) {
        $api_endpoint = $_GET['api'];
    } elseif (isset($_POST['api'])) {
        $api_endpoint = $_POST['api'];
    } elseif (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        $api_endpoint = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '.php');
    }
    
    switch ($api_endpoint) {
        case 'get_water_sources':
            $filters = [];
            $params = [];

            if (isset($_GET['barangay'])) {
                $filters[] = "barangay = ?";
                $params[] = $_GET['barangay'];
            }

            if (isset($_GET['status'])) {
                $filters[] = "status = ?";
                $params[] = $_GET['status'];
            }

            if (isset($_GET['type'])) {
                $filters[] = "source_type = ?";
                $params[] = $_GET['type'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            try {
                $stmt = $pdo->prepare("SELECT * FROM water_sources $whereClause ORDER BY name");
                $stmt->execute($params);
                $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($sources);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_source_details':
            if (!isset($_GET['id'])) {
                echo json_encode(['error' => 'Source ID required']);
                exit;
            }

            $source_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if (!$source_id) {
                echo json_encode(['error' => 'Invalid source ID']);
                exit;
            }

            try {
                // Get source details
                $stmt = $pdo->prepare("SELECT * FROM water_sources WHERE id = ?");
                $stmt->execute([$source_id]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$source) {
                    echo json_encode(['error' => 'Water source not found']);
                    exit;
                }

                // Get inspection history
                $stmt = $pdo->prepare("
                    SELECT i.*, u.first_name, u.last_name 
                    FROM water_source_inspections i 
                    LEFT JOIN users u ON i.inspected_by = u.id 
                    WHERE i.source_id = ? 
                    ORDER BY i.inspection_date DESC
                ");
                $stmt->execute([$source_id]);
                $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get maintenance history
                $stmt = $pdo->prepare("
                    SELECT m.*, u.first_name, u.last_name 
                    FROM water_source_maintenance m 
                    LEFT JOIN users u ON m.performed_by = u.id 
                    WHERE m.source_id = ? 
                    ORDER BY m.maintenance_date DESC
                ");
                $stmt->execute([$source_id]);
                $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'source' => $source,
                    'inspections' => $inspections,
                    'maintenance' => $maintenance
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'add_inspection':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }

            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON data']);
                exit;
            }

            $required_fields = ['source_id', 'inspection_date', 'condition', 'next_inspection'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO water_source_inspections 
                    (source_id, inspected_by, inspection_date, pressure, flow_rate, condition, issues_found, actions_taken, recommendations, next_inspection) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['source_id'],
                    $_SESSION['user_id'],
                    $data['inspection_date'],
                    $data['pressure'] ?? null,
                    $data['flow_rate'] ?? null,
                    $data['condition'],
                    $data['issues_found'] ?? null,
                    $data['actions_taken'] ?? null,
                    $data['recommendations'] ?? null,
                    $data['next_inspection']
                ]);

                // Update the water source's last inspection date
                $stmt = $pdo->prepare("UPDATE water_sources SET last_inspection = ?, next_inspection = ? WHERE id = ?");
                $stmt->execute([$data['inspection_date'], $data['next_inspection'], $data['source_id']]);

                $pdo->commit();
                
                echo json_encode(['success' => true, 'inspection_id' => $pdo->lastInsertId()]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'add_maintenance':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }

            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON data']);
                exit;
            }

            $required_fields = ['source_id', 'maintenance_type', 'maintenance_date', 'description'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO water_source_maintenance 
                    (source_id, maintenance_type, performed_by, maintenance_date, description, parts_used, cost, hours_spent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['source_id'],
                    $data['maintenance_type'],
                    $_SESSION['user_id'],
                    $data['maintenance_date'],
                    $data['description'],
                    $data['parts_used'] ?? null,
                    $data['cost'] ?? null,
                    $data['hours_spent'] ?? null
                ]);

                echo json_encode(['success' => true, 'maintenance_id' => $pdo->lastInsertId()]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_source_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }

            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON data']);
                exit;
            }

            if (!isset($data['source_id']) || !isset($data['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $allowed_statuses = ['active', 'inactive', 'maintenance', 'low_flow'];
            if (!in_array($data['status'], $allowed_statuses)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE water_sources SET status = ? WHERE id = ?");
                $stmt->execute([$data['status'], $data['source_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_source_stats':
            try {
                // Count by type
                $stmt = $pdo->prepare("
                    SELECT source_type, COUNT(*) as count 
                    FROM water_sources 
                    GROUP BY source_type
                ");
                $stmt->execute();
                $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count by status
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count 
                    FROM water_sources 
                    GROUP BY status
                ");
                $stmt->execute();
                $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count by barangay
                $stmt = $pdo->prepare("
                    SELECT barangay, COUNT(*) as count 
                    FROM water_sources 
                    GROUP BY barangay 
                    ORDER BY count DESC
                ");
                $stmt->execute();
                $by_barangay = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count overdue inspections
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM water_sources 
                    WHERE next_inspection < CURDATE() AND status != 'inactive'
                ");
                $stmt->execute();
                $overdue_inspections = $stmt->fetch()['count'];

                echo json_encode([
                    'by_type' => $by_type,
                    'by_status' => $by_status,
                    'by_barangay' => $by_barangay,
                    'overdue_inspections' => $overdue_inspections
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user role and permissions
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $is_admin = $user['is_admin'] ?? 0;
} catch (PDOException $e) {
    // Handle error appropriately
    $is_admin = 0;
}

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'mapping';

// Get barangays for filters
try {
    $stmt = $pdo->prepare("SELECT DISTINCT barangay FROM water_sources ORDER BY barangay");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $barangays = [];
}

// Get water sources for listing
try {
    $stmt = $pdo->prepare("SELECT * FROM water_sources ORDER BY name");
    $stmt->execute();
    $water_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $water_sources = [];
}

// Calculate stats
$total_sources = count($water_sources);
$active_sources = 0;
$maintenance_sources = 0;
$inactive_sources = 0;

foreach ($water_sources as $source) {
    if ($source['status'] == 'active') $active_sources++;
    if ($source['status'] == 'maintenance') $maintenance_sources++;
    if ($source['status'] == 'inactive') $inactive_sources++;
}

// Count overdue inspections
$today = new DateTime();
$overdue_count = 0;
foreach ($water_sources as $source) {
    if ($source['next_inspection'] && $source['status'] != 'inactive') {
        $next_inspection = new DateTime($source['next_inspection']);
        if ($next_inspection < $today) {
            $overdue_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Hydrant and Water Resource Mapping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #1e88e5;
            --primary-dark: #1565c0;
            --primary-light: #64b5f6;
            --primary-gradient: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            --secondary: #64748b;
            --accent: #fd7e14;
            --success: #28a745;
            --warning: #ff9800;
            --danger: #e53935;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-800: #334155;
            --sidebar-width: 280px;
            --header-height: 80px;
            --card-radius: 16px;
            --card-shadow: 0 10px 30px rgba(30, 136, 229, 0.1);
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
            border: 1px solid rgba(30, 136, 229, 0.1);
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
            border: 1px solid rgba(30, 136, 229, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card.sources::before { background: var(--primary-gradient); }
        .stat-card.active::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.maintenance::before { background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%); }
        .stat-card.overdue::before { background: linear-gradient(135deg, #e53935 0%, #ff6b6b 100%); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(30, 136, 229, 0.15);
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
            background: rgba(30, 136, 229, 0.1);
            color: var(--primary);
        }
        
        .stat-card.active .stat-icon { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success); 
        }
        
        .stat-card.maintenance .stat-icon { 
            background: rgba(255, 152, 0, 0.1); 
            color: var(--warning); 
        }
        
        .stat-card.overdue .stat-icon { 
            background: rgba(229, 57, 53, 0.1); 
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
        
        /* Tabs */
        .dashboard-tabs {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(30, 136, 229, 0.1);
        }
        
        .nav-tabs {
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            color: var(--secondary);
            font-weight: 500;
            transition: var(--transition);
            margin-right: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .nav-tabs .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--primary-gradient);
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link:hover {
            background: rgba(30, 136, 229, 0.05);
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            background: rgba(30, 136, 229, 0.1);
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active::before {
            width: 100%;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        /* Content Cards */
        .content-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(30, 136, 229, 0.1);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(30, 136, 229, 0.03);
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
            background: rgba(30, 136, 229, 0.03);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(30, 136, 229, 0.05);
        }
        
        /* Tables */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(30, 136, 229, 0.05);
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: rgba(30, 136, 229, 0.05);
            border-top: none;
            font-weight: 600;
            color: var(--dark);
            padding: 1rem;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Montserrat', sans-serif;
            border-bottom: 2px solid rgba(30, 136, 229, 0.1);
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid rgba(30, 136, 229, 0.05);
            font-size: 14px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(30, 136, 229, 0.03);
            transform: scale(1.01);
            transition: var(--transition);
        }
        
        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active { background-color: var(--primary); }
        .status-maintenance { background-color: var(--warning); }
        .status-inactive { background-color: var(--danger); }
        .status-low_flow { background-color: #ffd600; }
        
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
            box-shadow: 0 4px 15px rgba(30, 136, 229, 0.3);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 136, 229, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e53935 0%, #ff6b6b 100%);
            box-shadow: 0 4px 15px rgba(229, 57, 53, 0.3);
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
            box-shadow: 0 4px 15px rgba(30, 136, 229, 0.3);
        }
        
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-icon i {
            margin-right: 8px;
            font-size: 1rem;
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
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.1);
        }
        
        /* Map container */
        .map-container {
            height: 500px;
            border-radius: var(--card-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(30, 136, 229, 0.1);
        }
        
        /* Details panel */
        .details-panel {
            max-height: 600px;
            overflow-y: auto;
            background: rgba(30, 136, 229, 0.03);
            border-radius: var(--card-radius);
            padding: 1.25rem;
            border: 1px solid rgba(30, 136, 229, 0.05);
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
        }
        
        /* Source cards */
        .source-card {
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .source-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .source-hydrant { border-left-color: var(--primary); }
        .source-reservoir { border-left-color: #4caf50; }
        .source-lake { border-left-color: #2196f3; }
        .source-river { border-left-color: #3f51b5; }
        .source-well { border-left-color: #795548; }
        .source-storage_tank { border-left-color: #9e9e9e; }
        
        /* Modals */
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: rgba(30, 136, 229, 0.03);
        }
        
        .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--dark);
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
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
            
            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 13px;
                margin-right: 0.25rem;
            }
            
            .nav-tabs .nav-link i {
                margin-right: 4px;
                font-size: 1rem;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
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
            margin-right: 1rem;
            box-shadow: 0 4px 10px rgba(30, 136, 229, 0.3);
        }
        
        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: flex;
            }
        }

        /* Alert styles */
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        /* Search box */
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 50px;
            background: rgba(30, 136, 229, 0.05);
            border: 1px solid rgba(30, 136, 229, 0.1);
        }
        
        .search-box::before {
            content: '\ebee';
            font-family: 'boxicons';
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            z-index: 1;
        }
        
        /* Notification and profile buttons */
        .notification-dropdown .btn, .profile-dropdown .btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(30, 136, 229, 0.1);
            color: var(--primary);
            position: relative;
            transition: var(--transition);
        }
        
        .notification-dropdown .btn:hover, .profile-dropdown .btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .notification-dropdown .btn::after {
            display: none;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--danger);
            color: white;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Animation for cards */
        .animate-card {
            animation: cardSlideIn 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        @keyframes cardSlideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Delay animations for each card */
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        /* Floating action button */
        .floating-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 25px rgba(30, 136, 229, 0.4);
            z-index: 900;
            transition: var(--transition);
        }
        
        .floating-btn:hover {
            transform: translateY(-5px) rotate(90deg);
            box-shadow: 0 12px 30px rgba(30, 136, 229, 0.5);
        }
        
        /* Custom checkbox and radio */
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .spinner-border-lg {
            width: 3rem;
            height: 3rem;
            color: var(--primary);
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
        }
        
        .toast {
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: none;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .toast-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(30, 136, 229, 0.1);
            padding: 0.75rem 1rem;
        }
        
        .toast-body {
            background: white;
            padding: 1rem;
        }
        
        /* Custom animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border spinner-border-lg" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBmaWxsPSIjZmZmIiBkPSJNMjU2IDBDMTE0LjYgMCAwIDExNC42IDAgMjU2czExNC42IDI1NiAyNTYgMjU2czI1Ni0xMTQuNiAyNTYtMjU2UzM5Ny40IDAgMjU2IDB6TTQwMCAyODhoLTEwNHYxMDRoLTEwNFYyODhoLTEwNHYtMTA0aDEwNHYtMTA0aDEwNHYxMDRINDAwVjI4OHoiLz48L3N2Zz4=" alt="Logo">
                <div class="text">
                    Quezon City Fire Station
                    <small>Hydrant & Water Resources</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation of HWRM</div>
                <a href="index.php?tab=mapping" class="sidebar-link <?php echo $active_tab == 'mapping' ? 'active' : ''; ?>">
                    <i class='bx bxs-map'></i>
                    <span class="text">Water Resource Mapping</span>
                </a>
                <a href="index.php?tab=sources" class="sidebar-link <?php echo $active_tab == 'sources' ? 'active' : ''; ?>">
                    <i class='bx bx-water'></i>
                    <span class="text">Water Sources</span>
                </a>
                <a href="index.php?tab=inspections" class="sidebar-link <?php echo $active_tab == 'inspections' ? 'active' : ''; ?>">
                    <i class='bx bxs-check-square'></i>
                    <span class="text">Inspections</span>
                </a>
                <a href="index.php?tab=maintenance" class="sidebar-link <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">
                    <i class='bx bxs-wrench'></i>
                    <span class="text">Maintenance</span>
                </a>
                <a href="index.php?tab=reports" class="sidebar-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                    <i class='bx bxs-report'></i>
                    <span class="text">Reports</span>
                </a>
                
                <div class="sidebar-section mt-4">Other Modules</div>
                <a href="../IRD/index.php" class="sidebar-link">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
                <a href="../FSIET/index.php" class="sidebar-link">
                    <i class='bx bxs-package'></i>
                    <span class="text">Fire Station Inventory & Equipment Tracking</span>
                </a>
                <a href="index.php" class="sidebar-link active">
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
                <a href="../PIAR/index.php" class="sidebar-link">
                    <i class='bx bxs-analyse'></i>
                    <span class="text">Post-Incident Analysis and Reporting</span>
                </a>
                
                <div class="sidebar-section mt-4">Account</div>
                <a href="../profile.php" class="sidebar-link">
                    <i class='bx bxs-user'></i>
                    <span class="text">Profile</span>
                </a>
                <a href="../logout.php" class="sidebar-link">
                    <i class='bx bxs-log-out'></i>
                    <span class="text">Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class='bx bx-menu'></i>
                </button>
                
                <div class="page-title">
                    <h1>Hydrant & Water Resource Mapping</h1>
                    <p>Manage and monitor water resources for firefighting operations</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box me-3">
                        <input type="text" class="form-control" placeholder="Search water sources...">
                    </div>
                    
                    <div class="notification-dropdown dropdown me-2">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-bell'></i>
                            <span class="notification-badge"><?php echo $overdue_count; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if ($overdue_count > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=inspections"><?php echo $overdue_count; ?> overdue inspections</a></li>
                            <?php endif; ?>
                            <?php if ($maintenance_sources > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=maintenance"><?php echo $maintenance_sources; ?> sources need maintenance</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary" href="#">View all</a></li>
                        </ul>
                    </div>
                    
                    <div class="profile-dropdown dropdown">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-user-circle'></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Signed in as <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h6></li>
                            <li><a class="dropdown-item" href="../profile.php"><i class='bx bxs-user me-2'></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class='bx bxs-cog me-2'></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class='bx bxs-log-out me-2'></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card sources animate-card">
                    <div class="stat-icon">
                        <i class='bx bx-water'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_sources; ?></div>
                    <div class="stat-label">Total Water Sources</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-map'></i>
                            <span><?php echo count($barangays); ?> Barangays</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-stats'></i>
                            <span>6 Types</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card active animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-check-circle'></i>
                    </div>
                    <div class="stat-value"><?php echo $active_sources; ?></div>
                    <div class="stat-label">Active Sources</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-up-arrow'></i>
                            <span><?php echo $total_sources > 0 ? round(($active_sources / $total_sources) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-trending-up'></i>
                            <span>Operational</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card maintenance animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-wrench'></i>
                    </div>
                    <div class="stat-value"><?php echo $maintenance_sources; ?></div>
                    <div class="stat-label">Needs Maintenance</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-warning">
                            <i class='bx bxs-time-five'></i>
                            <span><?php echo $total_sources > 0 ? round(($maintenance_sources / $total_sources) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-alarm'></i>
                            <span>Attention Required</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card overdue animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-error-circle'></i>
                    </div>
                    <div class="stat-value"><?php echo $overdue_count; ?></div>
                    <div class="stat-label">Overdue Inspections</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-danger">
                            <i class='bx bxs-error'></i>
                            <span><?php echo $total_sources > 0 ? round(($overdue_count / $total_sources) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bxs-time'></i>
                            <span>Schedule Now</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-tabs">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=mapping" class="nav-link <?php echo $active_tab == 'mapping' ? 'active' : ''; ?>">
                            <i class='bx bxs-map'></i> Mapping
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=sources" class="nav-link <?php echo $active_tab == 'sources' ? 'active' : ''; ?>">
                            <i class='bx bx-water'></i> Water Sources
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=inspections" class="nav-link <?php echo $active_tab == 'inspections' ? 'active' : ''; ?>">
                            <i class='bx bxs-check-square'></i> Inspections
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=maintenance" class="nav-link <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">
                            <i class='bx bxs-wrench'></i> Maintenance
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=reports" class="nav-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                            <i class='bx bxs-report'></i> Reports
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="dashboardTabContent">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class='bx bxs-check-circle me-2'></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Mapping Tab -->
                <?php if ($active_tab == 'mapping'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Water Resource Map</h5>
                            <div class="d-flex">
                                <select class="form-select form-select-sm me-2" id="mapFilter">
                                    <option value="all">All Sources</option>
                                    <option value="hydrant">Hydrants</option>
                                    <option value="reservoir">Reservoirs</option>
                                    <option value="lake">Lakes</option>
                                    <option value="river">Rivers</option>
                                    <option value="well">Wells</option>
                                    <option value="storage_tank">Storage Tanks</option>
                                </select>
                                <select class="form-select form-select-sm me-2" id="statusFilter">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="low_flow">Low Flow</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class='bx bx-filter'></i> Apply
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="map-container" id="waterMap">
                                <!-- Map will be loaded here via JavaScript -->
                                <div class="d-flex justify-content-center align-items-center h-100">
                                    <div class="text-center">
                                        <i class='bx bx-map text-muted' style="font-size: 3rem;"></i>
                                        <p class="mt-3 text-muted">Interactive map loading...</p>
                                        <button class="btn btn-primary mt-2" id="loadMapBtn">
                                            <i class='bx bx-refresh'></i> Load Map
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-8">
                                    <div class="details-panel" id="mapDetailsPanel">
                                        <h6 class="mb-3">Water Source Details</h6>
                                        <p class="text-muted">Select a water source on the map to view details</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Legend</h6>
                                            <div class="legend-item mb-2">
                                                <span class="status-indicator status-active"></span>
                                                <span class="ms-2">Active</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <span class="status-indicator status-maintenance"></span>
                                                <span class="ms-2">Maintenance</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <span class="status-indicator status-inactive"></span>
                                                <span class="ms-2">Inactive</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <span class="status-indicator status-low_flow"></span>
                                                <span class="ms-2">Low Flow</span>
                                            </div>
                                            <hr>
                                            <div class="legend-item mb-2">
                                                <i class='bx bx-water text-primary'></i>
                                                <span class="ms-2">Hydrant</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <i class='bx bx-water text-success'></i>
                                                <span class="ms-2">Reservoir</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <i class='bx bx-water text-info'></i>
                                                <span class="ms-2">Lake/River</span>
                                            </div>
                                            <div class="legend-item mb-2">
                                                <i class='bx bx-water text-secondary'></i>
                                                <span class="ms-2">Well/Tank</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Water Sources Tab -->
                <?php if ($active_tab == 'sources'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Water Sources Management</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                                <i class='bx bx-plus'></i> Add Source
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Barangay</label>
                                            <select class="form-select" id="barangayFilter">
                                                <option value="">All Barangays</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo htmlspecialchars($barangay['barangay']); ?>"><?php echo htmlspecialchars($barangay['barangay']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Type</label>
                                            <select class="form-select" id="typeFilter">
                                                <option value="">All Types</option>
                                                <option value="hydrant">Hydrant</option>
                                                <option value="reservoir">Reservoir</option>
                                                <option value="lake">Lake</option>
                                                <option value="river">River</option>
                                                <option value="well">Well</option>
                                                <option value="storage_tank">Storage Tank</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" id="statusFilter">
                                                <option value="">All Status</option>
                                                <option value="active">Active</option>
                                                <option value="maintenance">Maintenance</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="low_flow">Low Flow</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Sort By</label>
                                            <select class="form-select" id="sortFilter">
                                                <option value="name_asc">Name (A-Z)</option>
                                                <option value="name_desc">Name (Z-A)</option>
                                                <option value="barangay_asc">Barangay</option>
                                                <option value="status">Status</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" id="waterSourcesList">
                                <?php foreach ($water_sources as $source): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card source-card source-<?php echo $source['source_type']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($source['name']); ?></h6>
                                                    <span class="badge bg-<?php 
                                                        echo $source['status'] == 'active' ? 'success' : 
                                                             ($source['status'] == 'maintenance' ? 'warning' : 
                                                             ($source['status'] == 'inactive' ? 'danger' : 'info')); 
                                                    ?>">
                                                        <span class="status-indicator status-<?php echo $source['status']; ?>"></span>
                                                        <?php echo ucfirst(str_replace('_', ' ', $source['status'])); ?>
                                                    </span>
                                                </div>
                                                <p class="card-text text-muted small mb-1">
                                                    <i class='bx bx-current-location'></i> <?php echo htmlspecialchars($source['barangay']); ?>
                                                </p>
                                                <p class="card-text text-muted small mb-2">
                                                    <i class='bx bx-category'></i> <?php echo ucfirst(str_replace('_', ' ', $source['source_type'])); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Last inspection: <?php echo $source['last_inspection'] ? date('M j, Y', strtotime($source['last_inspection'])) : 'Never'; ?>
                                                    </small>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary view-source" data-id="<?php echo $source['id']; ?>">
                                                            <i class='bx bxs-show'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary edit-source" data-id="<?php echo $source['id']; ?>">
                                                            <i class='bx bxs-edit'></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <nav aria-label="Page navigation" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Inspections Tab -->
                <?php if ($active_tab == 'inspections'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Water Source Inspections</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInspectionModal">
                                <i class='bx bx-plus'></i> Log Inspection
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class='bx bx-info-circle'></i> You have <strong><?php echo $overdue_count; ?></strong> overdue inspections that need to be scheduled.
                            </div>
                            
                            <ul class="nav nav-pills mb-3" id="inspectionsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="pill" data-bs-target="#upcoming" type="button" role="tab">Upcoming</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="overdue-tab" data-bs-toggle="pill" data-bs-target="#overdue" type="button" role="tab">Overdue</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">History</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="inspectionsTabContent">
                                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Water Source</th>
                                                    <th>Type</th>
                                                    <th>Barangay</th>
                                                    <th>Next Inspection</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($water_sources as $source): ?>
                                                    <?php if ($source['next_inspection'] && $source['status'] != 'inactive'): ?>
                                                        <?php 
                                                        $next_inspection = new DateTime($source['next_inspection']);
                                                        $today = new DateTime();
                                                        $interval = $today->diff($next_inspection);
                                                        $days_until = $interval->format('%r%a');
                                                        ?>
                                                        <?php if ($days_until >= 0): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($source['name']); ?></td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $source['source_type'])); ?></td>
                                                                <td><?php echo htmlspecialchars($source['barangay']); ?></td>
                                                                <td><?php echo date('M j, Y', strtotime($source['next_inspection'])); ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $days_until <= 7 ? 'warning' : 'info'; ?>">
                                                                        <?php echo $days_until == 0 ? 'Today' : ($days_until == 1 ? 'Tomorrow' : "in $days_until days"); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary log-inspection" data-id="<?php echo $source['id']; ?>">
                                                                        <i class='bx bxs-check-square'></i> Log Inspection
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="overdue" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Water Source</th>
                                                    <th>Type</th>
                                                    <th>Barangay</th>
                                                    <th>Last Inspection</th>
                                                    <th>Overdue By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($water_sources as $source): ?>
                                                    <?php if ($source['next_inspection'] && $source['status'] != 'inactive'): ?>
                                                        <?php 
                                                        $next_inspection = new DateTime($source['next_inspection']);
                                                        $today = new DateTime();
                                                        if ($next_inspection < $today): 
                                                            $interval = $today->diff($next_inspection);
                                                            $days_overdue = abs($interval->format('%a'));
                                                        ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($source['name']); ?></td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $source['source_type'])); ?></td>
                                                                <td><?php echo htmlspecialchars($source['barangay']); ?></td>
                                                                <td><?php echo $source['last_inspection'] ? date('M j, Y', strtotime($source['last_inspection'])) : 'Never'; ?></td>
                                                                <td>
                                                                    <span class="badge bg-danger">
                                                                        <?php echo $days_overdue == 1 ? '1 day' : "$days_overdue days"; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary log-inspection" data-id="<?php echo $source['id']; ?>">
                                                                        <i class='bx bxs-check-square'></i> Log Inspection
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="history" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Water Source</th>
                                                    <th>Inspector</th>
                                                    <th>Condition</th>
                                                    <th>Issues Found</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class='bx bx-history text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No inspection history available</p>
                                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addInspectionModal">
                                                            <i class='bx bxs-check-square'></i> Log First Inspection
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Maintenance Tab -->
                <?php if ($active_tab == 'maintenance'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Maintenance Records</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                <i class='bx bx-plus'></i> Log Maintenance
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class='bx bx-info-circle'></i> Maintenance records help track the upkeep of water sources and ensure they remain operational.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Water Source</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Performed By</th>
                                            <th>Cost</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class='bx bx-wrench text-muted' style="font-size: 3rem;"></i>
                                                <p class="mt-3 text-muted">No maintenance records available</p>
                                                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                                    <i class='bx bx-plus'></i> Log Maintenance
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reports Tab -->
                <?php if ($active_tab == 'reports'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Water Resource Reports</h5>
                            <div>
                                <button class="btn btn-primary btn-sm me-2">
                                    <i class='bx bxs-download'></i> Export PDF
                                </button>
                                <button class="btn btn-success btn-sm">
                                    <i class='bx bxs-download'></i> Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>Water Sources by Type</h5>
                                            <canvas id="sourcesByTypeChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>Water Sources by Status</h5>
                                            <canvas id="sourcesByStatusChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="text-center mb-4">Inspection Overview</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Count</th>
                                                    <th>Percentage</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><span class="badge bg-success">Up to Date</span></td>
                                                    <td><?php echo $total_sources - $overdue_count; ?></td>
                                                    <td><?php echo $total_sources > 0 ? round((($total_sources - $overdue_count) / $total_sources) * 100, 2) : 0; ?>%</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-success" 
                                                                 style="width: <?php echo $total_sources > 0 ? (($total_sources - $overdue_count) / $total_sources) * 100 : 0; ?>%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><span class="badge bg-danger">Overdue</span></td>
                                                    <td><?php echo $overdue_count; ?></td>
                                                    <td><?php echo $total_sources > 0 ? round(($overdue_count / $total_sources) * 100, 2) : 0; ?>%</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-danger" 
                                                                 style="width: <?php echo $total_sources > 0 ? ($overdue_count / $total_sources) * 100 : 0; ?>%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="#" class="floating-btn" data-bs-toggle="modal" data-bs-target="#quickActionModal">
        <i class='bx bx-plus'></i>
    </a>

    <!-- Modals -->
    <!-- Add Water Source Modal -->
    <div class="modal fade" id="addSourceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Water Source</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addSourceForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Source Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="source_type" required>
                                <option value="">Select Type</option>
                                <option value="hydrant">Hydrant</option>
                                <option value="reservoir">Reservoir</option>
                                <option value="lake">Lake</option>
                                <option value="river">River</option>
                                <option value="well">Well</option>
                                <option value="storage_tank">Storage Tank</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <input type="text" class="form-control" name="barangay" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="any" class="form-control" name="latitude" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="any" class="form-control" name="longitude" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                                <option value="low_flow">Low Flow</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity (Liters)</label>
                            <input type="number" class="form-control" name="capacity">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Flow Rate (L/min)</label>
                            <input type="number" step="any" class="form-control" name="flow_rate">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pressure (PSI)</label>
                            <input type="number" step="any" class="form-control" name="pressure">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Source</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Inspection Modal -->
    <div class="modal fade" id="addInspectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Inspection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addInspectionForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Water Source</label>
                            <select class="form-select" name="source_id" required>
                                <option value="">Select Water Source</option>
                                <?php foreach ($water_sources as $source): ?>
                                    <option value="<?php echo $source['id']; ?>"><?php echo htmlspecialchars($source['name'] . ' (' . $source['barangay'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inspection Date</label>
                            <input type="date" class="form-control" name="inspection_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Pressure (PSI)</label>
                                    <input type="number" step="any" class="form-control" name="pressure">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Flow Rate (L/min)</label>
                                    <input type="number" step="any" class="form-control" name="flow_rate">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condition</label>
                            <select class="form-select" name="condition" required>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issues Found</label>
                            <textarea class="form-control" name="issues_found" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Actions Taken</label>
                            <textarea class="form-control" name="actions_taken" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recommendations</label>
                            <textarea class="form-control" name="recommendations" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next Inspection Date</label>
                            <input type="date" class="form-control" name="next_inspection" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Log Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addMaintenanceForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Water Source</label>
                            <select class="form-select" name="source_id" required>
                                <option value="">Select Water Source</option>
                                <?php foreach ($water_sources as $source): ?>
                                    <option value="<?php echo $source['id']; ?>"><?php echo htmlspecialchars($source['name'] . ' (' . $source['barangay'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maintenance Type</label>
                            <select class="form-select" name="maintenance_type" required>
                                <option value="routine">Routine Maintenance</option>
                                <option value="repair">Repair</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="replacement">Part Replacement</option>
                                <option value="upgrade">Upgrade</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maintenance Date</label>
                            <input type="date" class="form-control" name="maintenance_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parts Used</label>
                            <textarea class="form-control" name="parts_used" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cost (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="cost">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hours Spent</label>
                                    <input type="number" step="0.5" class="form-control" name="hours_spent">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Log Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Action Modal -->
    <div class="modal fade" id="quickActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                            <i class='bx bx-water'></i> Add Water Source
                        </button>
                        <button class="btn btn-warning btn-icon" data-bs-toggle="modal" data-bs-target="#addInspectionModal">
                            <i class='bx bxs-check-square'></i> Log Inspection
                        </button>
                        <button class="btn btn-info btn-icon" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class='bx bxs-wrench'></i> Log Maintenance
                        </button>
                        <button class="btn btn-success btn-icon">
                            <i class='bx bxs-report'></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Source Details Modal -->
    <div class="modal fade" id="viewSourceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Water Source Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="sourceDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Edit Details</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Hide loading overlay when page is loaded
        window.addEventListener('load', function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        });

        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Initialize charts if on reports page
        <?php if ($active_tab == 'reports'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Sources by Type Chart
            var typeCtx = document.getElementById('sourcesByTypeChart').getContext('2d');
            
            // Count sources by type
            var typeCounts = {
                hydrant: 0,
                reservoir: 0,
                lake: 0,
                river: 0,
                well: 0,
                storage_tank: 0
            };
            
            <?php foreach ($water_sources as $source): ?>
                typeCounts['<?php echo $source['source_type']; ?>']++;
            <?php endforeach; ?>
            
            var typeChart = new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: ['Hydrants', 'Reservoirs', 'Lakes', 'Rivers', 'Wells', 'Storage Tanks'],
                    datasets: [{
                        data: [
                            typeCounts.hydrant,
                            typeCounts.reservoir,
                            typeCounts.lake,
                            typeCounts.river,
                            typeCounts.well,
                            typeCounts.storage_tank
                        ],
                        backgroundColor: [
                            '#1e88e5', '#4caf50', '#2196f3', '#3f51b5', '#795548', '#9e9e9e'
                        ],
                        borderWidth: 0
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

            // Sources by Status Chart
            var statusCtx = document.getElementById('sourcesByStatusChart').getContext('2d');
            var statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Maintenance', 'Inactive', 'Low Flow'],
                    datasets: [{
                        data: [<?php echo $active_sources; ?>, <?php echo $maintenance_sources; ?>, <?php echo $inactive_sources; ?>, <?php echo $total_sources - $active_sources - $maintenance_sources - $inactive_sources; ?>],
                        backgroundColor: ['#28a745', '#ff9800', '#e53935', '#ffd600'],
                        borderWidth: 0
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
        });
        <?php endif; ?>

        // View source details
        document.querySelectorAll('.view-source').forEach(button => {
            button.addEventListener('click', function() {
                const sourceId = this.getAttribute('data-id');
                // In a real implementation, you would fetch source details via AJAX
                document.getElementById('viewSourceModal').querySelector('.modal-title').textContent = 'Loading...';
                
                // Simulate loading
                setTimeout(() => {
                    document.getElementById('viewSourceModal').querySelector('.modal-title').textContent = 'Water Source Details';
                    document.getElementById('sourceDetailsContent').innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading source details...</p>
                        </div>
                    `;
                    
                    // Simulate AJAX call
                    setTimeout(() => {
                        document.getElementById('sourceDetailsContent').innerHTML = `
                            <h6>Source Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Name:</strong> Sample Hydrant
                                </div>
                                <div class="col-md-6">
                                    <strong>Type:</strong> Hydrant
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Barangay:</strong> Barangay 1
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong> <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Flow Rate:</strong> 500 L/min
                                </div>
                                <div class="col-md-6">
                                    <strong>Pressure:</strong> 75 PSI
                                </div>
                            </div>
                            <hr>
                            <h6>Last Inspection</h6>
                            <p class="text-muted">No inspection records found</p>
                            <hr>
                            <h6>Maintenance History</h6>
                            <p class="text-muted">No maintenance records found</p>
                        `;
                    }, 1000);
                }, 300);
                
                var modal = new bootstrap.Modal(document.getElementById('viewSourceModal'));
                modal.show();
            });
        });

        // Form submissions
        document.getElementById('addSourceForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Water source added successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addSourceModal'));
            modal.hide();
        });

        document.getElementById('addInspectionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Inspection logged successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addInspectionModal'));
            modal.hide();
        });

        document.getElementById('addMaintenanceForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Maintenance logged successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addMaintenanceModal'));
            modal.hide();
        });

        // Load map button
        document.getElementById('loadMapBtn')?.addEventListener('click', function() {
            const mapContainer = document.getElementById('waterMap');
            mapContainer.innerHTML = `
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading interactive map...</p>
                    </div>
                </div>
            `;
            
            // Simulate map loading
            setTimeout(() => {
                mapContainer.innerHTML = `
                    <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                        <div class="text-center text-muted">
                            <i class='bx bx-map' style="font-size: 4rem;"></i>
                            <p class="mt-3">Interactive map would be displayed here</p>
                            <small>Showing water sources with color-coded status indicators</small>
                        </div>
                    </div>
                `;
            }, 1500);
        });

        // Log inspection buttons
        document.querySelectorAll('.log-inspection').forEach(button => {
            button.addEventListener('click', function() {
                const sourceId = this.getAttribute('data-id');
                document.getElementById('addInspectionForm').querySelector('[name="source_id"]').value = sourceId;
                var modal = new bootstrap.Modal(document.getElementById('addInspectionModal'));
                modal.show();
            });
        });

        // Auto-hide alerts after 5 seconds
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                bootstrap.Alert.getInstance(alert)?.close();
            }, 5000);
        });

        // Add animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            var cards = document.querySelectorAll('.fade-in');
            cards.forEach(function(card, index) {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });

        // Filter functionality for water sources
        document.getElementById('barangayFilter')?.addEventListener('change', filterSources);
        document.getElementById('typeFilter')?.addEventListener('change', filterSources);
        document.getElementById('statusFilter')?.addEventListener('change', filterSources);
        document.getElementById('sortFilter')?.addEventListener('change', filterSources);

        function filterSources() {
            const barangay = document.getElementById('barangayFilter').value;
            const type = document.getElementById('typeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const sort = document.getElementById('sortFilter').value;
            
            // In a real implementation, you would make an AJAX request to the server
            // For now, we'll just show a loading state
            const sourcesList = document.getElementById('waterSourcesList');
            sourcesList.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Filtering water sources...</p>
                </div>
            `;
            
            // Simulate filtering delay
            setTimeout(() => {
                // This would be replaced with actual filtered results from the server
                sourcesList.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class='bx bx-filter-alt text-muted' style="font-size: 3rem;"></i>
                        <p class="mt-3">Filter functionality would load filtered results here</p>
                        <small>In a real implementation, this would show actual filtered water sources</small>
                    </div>
                `;
            }, 1000);
        }

        // API call examples
        function fetchWaterSources(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_water_sources&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching water sources:', error);
                    // Show error message to user
                    alert('Error loading water sources. Please try again.');
                });
        }

        function fetchSourceDetails(sourceId) {
            return fetch(`index.php?api=get_source_details&id=${sourceId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching source details:', error);
                    // Show error message to user
                    alert('Error loading source details. Please try again.');
                });
        }

        // Example of using the API functions
        /*
        // Fetch all water sources
        fetchWaterSources()
            .then(sources => {
                console.log('Water sources:', sources);
                // Update UI with sources
            });
        
        // Fetch sources with filters
        fetchWaterSources({
            barangay: 'Barangay 1',
            status: 'active',
            type: 'hydrant'
        }).then(sources => {
            console.log('Filtered water sources:', sources);
            // Update UI with filtered sources
        });
        
        // Fetch details for a specific source
        fetchSourceDetails(123)
            .then(details => {
                console.log('Source details:', details);
                // Update UI with details
            });
        */

        // Form submission handlers for API calls
        document.getElementById('addInspectionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                source_id: this.querySelector('[name="source_id"]').value,
                inspection_date: this.querySelector('[name="inspection_date"]').value,
                pressure: this.querySelector('[name="pressure"]').value,
                flow_rate: this.querySelector('[name="flow_rate"]').value,
                condition: this.querySelector('[name="condition"]').value,
                issues_found: this.querySelector('[name="issues_found"]').value,
                actions_taken: this.querySelector('[name="actions_taken"]').value,
                recommendations: this.querySelector('[name="recommendations"]').value,
                next_inspection: this.querySelector('[name="next_inspection"]').value
            };
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging...';
            submitBtn.disabled = true;
            
            fetch('index.php?api=add_inspection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Inspection logged successfully!');
                    var modal = bootstrap.Modal.getInstance(document.getElementById('addInspectionModal'));
                    modal.hide();
                    // Refresh the page or update UI as needed
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error logging inspection. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Similar form submission handlers can be added for:
        // - addSourceForm
        // - addMaintenanceForm
        // - updateSourceStatus forms

    </script>
</body>
</html>