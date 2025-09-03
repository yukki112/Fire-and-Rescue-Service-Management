<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
        case 'get_schedules':
            $filters = [];
            $params = [];

            if (isset($_GET['month'])) {
                $filters[] = "DATE_FORMAT(ss.date, '%Y-%m') = ?";
                $params[] = $_GET['month'];
            }

            if (isset($_GET['employee_id'])) {
                $filters[] = "ss.employee_id = ?";
                $params[] = $_GET['employee_id'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            try {
                $stmt = $pdo->prepare("
                    SELECT ss.*, e.first_name, e.last_name, st.name as shift_name, st.start_time, st.end_time, st.color
                    FROM shift_schedules ss
                    JOIN employees e ON ss.employee_id = e.id
                    JOIN shift_types st ON ss.shift_type_id = st.id
                    $whereClause 
                    ORDER BY ss.date, e.first_name
                ");
                $stmt->execute($params);
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($schedules);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_employee_schedule':
            if (!isset($_GET['employee_id'])) {
                echo json_encode(['error' => 'Employee ID required']);
                exit;
            }

            $employee_id = filter_var($_GET['employee_id'], FILTER_VALIDATE_INT);
            if (!$employee_id) {
                echo json_encode(['error' => 'Invalid employee ID']);
                exit;
            }

            try {
                // Get employee details
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$employee) {
                    echo json_encode(['error' => 'Employee not found']);
                    exit;
                }

                // Get schedule for the next 30 days
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+30 days'));
                
                $stmt = $pdo->prepare("
                    SELECT ss.*, st.name as shift_name, st.start_time, st.end_time, st.color
                    FROM shift_schedules ss
                    JOIN shift_types st ON ss.shift_type_id = st.id
                    WHERE ss.employee_id = ? AND ss.date BETWEEN ? AND ?
                    ORDER BY ss.date
                ");
                $stmt->execute([$employee_id, $start_date, $end_date]);
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get leave requests
                $stmt = $pdo->prepare("
                    SELECT lr.*, lt.name as leave_type
                    FROM leave_requests lr
                    JOIN leave_types lt ON lr.leave_type_id = lt.id
                    WHERE lr.employee_id = ? AND lr.status = 'approved'
                    ORDER BY lr.start_date
                ");
                $stmt->execute([$employee_id]);
                $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'employee' => $employee,
                    'schedules' => $schedules,
                    'leaves' => $leaves
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'assign_shift':
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

            $required_fields = ['employee_id', 'shift_type_id', 'date'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            try {
                $pdo->beginTransaction();
                
                // Check if shift already exists for this employee on this date
                $stmt = $pdo->prepare("SELECT id FROM shift_schedules WHERE employee_id = ? AND date = ?");
                $stmt->execute([$data['employee_id'], $data['date']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing shift
                    $stmt = $pdo->prepare("
                        UPDATE shift_schedules 
                        SET shift_type_id = ?, notes = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $data['shift_type_id'],
                        $data['notes'] ?? null,
                        $existing['id']
                    ]);
                } else {
                    // Insert new shift
                    $stmt = $pdo->prepare("
                        INSERT INTO shift_schedules 
                        (employee_id, shift_type_id, date, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['employee_id'],
                        $data['shift_type_id'],
                        $data['date'],
                        $data['notes'] ?? null,
                        $_SESSION['user_id']
                    ]);
                }

                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Shift assigned successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'request_leave':
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

            $required_fields = ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'reason'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO leave_requests 
                    (employee_id, leave_type_id, start_date, end_date, reason, requested_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['employee_id'],
                    $data['leave_type_id'],
                    $data['start_date'],
                    $data['end_date'],
                    $data['reason'],
                    $_SESSION['user_id']
                ]);

                echo json_encode(['success' => true, 'leave_id' => $pdo->lastInsertId()]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'process_request':
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

            if (!isset($data['request_id']) || !isset($data['type']) || !isset($data['action'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $allowed_actions = ['approve', 'reject'];
            if (!in_array($data['action'], $allowed_actions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                exit;
            }

            try {
                if ($data['type'] === 'leave') {
                    $status = $data['action'] === 'approve' ? 'approved' : 'rejected';
                    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $_SESSION['user_id'], $data['request_id']]);
                } elseif ($data['type'] === 'swap') {
                    $status = $data['action'] === 'approve' ? 'approved' : 'rejected';
                    $stmt = $pdo->prepare("UPDATE shift_swaps SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $_SESSION['user_id'], $data['request_id']]);
                    
                    // If approved, swap the shifts
                    if ($data['action'] === 'approve') {
                        // Get swap request details
                        $stmt = $pdo->prepare("
                            SELECT ss.shift_schedule_id, ss.original_employee_id, ss.requested_employee_id
                            FROM shift_swaps ss
                            WHERE ss.id = ?
                        ");
                        $stmt->execute([$data['request_id']]);
                        $swap = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($swap) {
                            // Update the shift schedule with the new employee
                            $stmt = $pdo->prepare("
                                UPDATE shift_schedules 
                                SET employee_id = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$swap['requested_employee_id'], $swap['shift_schedule_id']]);
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Request processed successfully']);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_schedule_stats':
            try {
                // Count by shift type
                $stmt = $pdo->prepare("
                    SELECT st.name, COUNT(ss.id) as count 
                    FROM shift_schedules ss
                    JOIN shift_types st ON ss.shift_type_id = st.id
                    WHERE ss.date >= CURDATE()
                    GROUP BY st.name
                ");
                $stmt->execute();
                $by_shift = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count by status
                $stmt = $pdo->prepare("
                    SELECT status, COUNT(*) as count 
                    FROM leave_requests 
                    GROUP BY status
                ");
                $stmt->execute();
                $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count by department
                $stmt = $pdo->prepare("
                    SELECT e.department, COUNT(ss.id) as count 
                    FROM shift_schedules ss
                    JOIN employees e ON ss.employee_id = e.id
                    WHERE ss.date >= CURDATE()
                    GROUP BY e.department 
                    ORDER BY count DESC
                ");
                $stmt->execute();
                $by_department = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Count pending requests
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM leave_requests 
                    WHERE status = 'pending'
                ");
                $stmt->execute();
                $pending_leave = $stmt->fetch()['count'];
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM shift_swaps 
                    WHERE status = 'pending'
                ");
                $stmt->execute();
                $pending_swap = $stmt->fetch()['count'];

                echo json_encode([
                    'by_shift' => $by_shift,
                    'by_status' => $by_status,
                    'by_department' => $by_department,
                    'pending_leave' => $pending_leave,
                    'pending_swap' => $pending_swap
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
$active_tab = $_GET['tab'] ?? 'calendar';

// Get current date for calendar
$current_date = date('Y-m-d');
$current_month = date('Y-m');

// Get all employees
$stmt = $pdo->prepare("SELECT * FROM employees ORDER BY first_name, last_name");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all shift types
$stmt = $pdo->prepare("SELECT * FROM shift_types ORDER BY start_time");
$stmt->execute();
$shift_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave types
$stmt = $pdo->prepare("SELECT * FROM leave_types ORDER BY name");
$stmt->execute();
$leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current month's schedule
$stmt = $pdo->prepare("
    SELECT ss.*, e.first_name, e.last_name, st.name as shift_name, st.start_time, st.end_time, st.color
    FROM shift_schedules ss
    JOIN employees e ON ss.employee_id = e.id
    JOIN shift_types st ON ss.shift_type_id = st.id
    WHERE DATE_FORMAT(ss.date, '%Y-%m') = ?
    ORDER BY ss.date, e.first_name
");
$stmt->execute([$current_month]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'pending'
    ORDER BY lr.created_at DESC
");
$stmt->execute();
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get shift swap requests
$stmt = $pdo->prepare("
    SELECT ss.*, e1.first_name as orig_first_name, e1.last_name as orig_last_name, 
           e2.first_name as req_first_name, e2.last_name as req_last_name,
           s.date as shift_date, st.name as shift_name
    FROM shift_swaps ss
    JOIN employees e1 ON ss.original_employee_id = e1.id
    JOIN employees e2 ON ss.requested_employee_id = e2.id
    JOIN shift_schedules s ON ss.shift_schedule_id = s.id
    JOIN shift_types st ON s.shift_type_id = st.id
    WHERE ss.status = 'pending'
    ORDER BY ss.created_at DESC
");
$stmt->execute();
$swap_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_employees = count($employees);
$total_schedules = count($schedules);
$pending_leave_count = count($pending_requests);
$pending_swap_count = count($swap_requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Personnel Shift Scheduling</title>
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
        
            
            --shift-day: #007bff;
            --shift-night: #6f42c1;
            --shift-graveyard: #343a40;
            --shift-swing: #fd7e14;
            --leave-approved: #28a745;
            --leave-pending: #ffc107;
            --leave-rejected: #dc3545;
            --absence: #6c757d;
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
        
        .stat-card.employees::before { background: var(--primary-gradient); }
        .stat-card.schedules::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.pending::before { background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%); }
        .stat-card.swaps::before { background: linear-gradient(135deg, #e53935 0%, #ff6b6b 100%); }
        
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
        
        .stat-card.schedules .stat-icon { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success); 
        }
        
        .stat-card.pending .stat-icon { 
            background: rgba(255, 152, 0, 0.1); 
            color: var(--warning); 
        }
        
        .stat-card.swaps .stat-icon { 
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
        
        /* Calendar styling */
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .calendar-header {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: bold;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .calendar-day {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            min-height: 120px;
            background-color: white;
        }
        
        .calendar-day.other-month {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .calendar-day.today {
            border: 2px solid var(--shift-day);
            background-color: #e8f4ff;
        }
        
        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .shift-item {
            font-size: 0.8rem;
            padding: 3px 5px;
            margin-bottom: 3px;
            border-radius: 3px;
            color: white;
            cursor: pointer;
        }
        
        /* Shift type indicators */
        .shift-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .shift-day { background-color: var(--shift-day); }
        .shift-night { background-color: var(--shift-night); }
        .shift-graveyard { background-color: var(--shift-graveyard); }
        .shift-swing { background-color: var(--shift-swing); }
        
        /* Leave status indicators */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-approved { background-color: var(--leave-approved); }
        .status-pending { background-color: var(--leave-pending); }
        .status-rejected { background-color: var(--leave-rejected); }
        
        /* Roster table styling */
        .roster-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .roster-table th,
        .roster-table td {
            padding: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .roster-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .roster-table .employee-name {
            text-align: left;
            font-weight: bold;
        }
        
        /* Leave request cards */
        .leave-card {
            border-left: 4px solid var(--leave-pending);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .leave-card.approved { border-left-color: var(--leave-approved); }
        .leave-card.rejected { border-left-color: var(--leave-rejected); }
        
        .leave-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Notification badges */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
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
                    <small>Personnel Shift Scheduling</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation of PSS</div>
                <a href="index.php?tab=calendar" class="sidebar-link <?php echo $active_tab == 'calendar' ? 'active' : ''; ?>">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Shift Calendar</span>
                </a>
                <a href="index.php?tab=roster" class="sidebar-link <?php echo $active_tab == 'roster' ? 'active' : ''; ?>">
                    <i class='bx bxs-user-detail'></i>
                    <span class="text">Shift Roster</span>
                </a>
                <a href="index.php?tab=requests" class="sidebar-link <?php echo $active_tab == 'requests' ? 'active' : ''; ?>">
                    <i class='bx bxs-time-five'></i>
                    <span class="text">Leave Requests</span>
                </a>
                <a href="index.php?tab=swaps" class="sidebar-link <?php echo $active_tab == 'swaps' ? 'active' : ''; ?>">
                    <i class='bx bxs-transfer'></i>
                    <span class="text">Shift Swaps</span>
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
                <a href="../HWRM/index.php" class="sidebar-link">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant and Water Resource Mapping</span>
                </a>
                <a href="index.php" class="sidebar-link active">
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
                    <h1>Personnel Shift Scheduling</h1>
                    <p>Manage and monitor personnel shifts and schedules</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box me-3">
                        <input type="text" class="form-control" placeholder="Search employees...">
                    </div>
                    
                    <div class="notification-dropdown dropdown me-2">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-bell'></i>
                            <?php if ($pending_leave_count > 0 || $pending_swap_count > 0): ?>
                                <span class="notification-badge"><?php echo $pending_leave_count + $pending_swap_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if ($pending_leave_count > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=requests"><?php echo $pending_leave_count; ?> pending leave requests</a></li>
                            <?php endif; ?>
                            <?php if ($pending_swap_count > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=swaps"><?php echo $pending_swap_count; ?> pending shift swaps</a></li>
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
                <div class="stat-card employees animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-user'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_employees; ?></div>
                    <div class="stat-label">Total Employees</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-group'></i>
                            <span><?php echo count(array_unique(array_column($employees, 'department'))); ?> Departments</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-stats'></i>
                            <span><?php echo count($shift_types); ?> Shift Types</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card schedules animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-calendar'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_schedules; ?></div>
                    <div class="stat-label">Scheduled Shifts</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-up-arrow'></i>
                            <span>This Month</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-trending-up'></i>
                            <span>Active</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card pending animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-time-five'></i>
                    </div>
                    <div class="stat-value"><?php echo $pending_leave_count; ?></div>
                    <div class="stat-label">Pending Leave Requests</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-warning">
                            <i class='bx bxs-time-five'></i>
                            <span>Needs Review</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-alarm'></i>
                            <span>Attention Required</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card swaps animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-transfer'></i>
                    </div>
                    <div class="stat-value"><?php echo $pending_swap_count; ?></div>
                    <div class="stat-label">Pending Shift Swaps</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-danger">
                            <i class='bx bxs-error'></i>
                            <span>Needs Approval</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bxs-time'></i>
                            <span>Review Now</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-tabs">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=calendar" class="nav-link <?php echo $active_tab == 'calendar' ? 'active' : ''; ?>">
                            <i class='bx bxs-calendar'></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=roster" class="nav-link <?php echo $active_tab == 'roster' ? 'active' : ''; ?>">
                            <i class='bx bxs-user-detail'></i> Roster
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=requests" class="nav-link <?php echo $active_tab == 'requests' ? 'active' : ''; ?>">
                            <i class='bx bxs-time-five'></i> Leave Requests
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=swaps" class="nav-link <?php echo $active_tab == 'swaps' ? 'active' : ''; ?>">
                            <i class='bx bxs-transfer'></i> Shift Swaps
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

                <!-- Calendar Tab -->
                <?php if ($active_tab == 'calendar'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Shift Calendar - <?php echo date('F Y'); ?></h5>
                            <div class="d-flex">
                                <select class="form-select form-select-sm me-2" id="monthFilter">
                                    <?php
                                    $months = [];
                                    for ($i = -3; $i <= 3; $i++) {
                                        $time = strtotime("$i months");
                                        $value = date('Y-m', $time);
                                        $label = date('F Y', $time);
                                        $selected = ($value == $current_month) ? 'selected' : '';
                                        echo "<option value='$value' $selected>$label</option>";
                                    }
                                    ?>
                                </select>
                                <select class="form-select form-select-sm me-2" id="departmentFilter">
                                    <option value="all">All Departments</option>
                                    <?php
                                    $departments = array_unique(array_column($employees, 'department'));
                                    foreach ($departments as $dept):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" id="refreshCalendar">
                                    <i class='bx bx-refresh'></i> Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <div>Sun</div>
                                    <div>Mon</div>
                                    <div>Tue</div>
                                    <div>Wed</div>
                                    <div>Thu</div>
                                    <div>Fri</div>
                                    <div>Sat</div>
                                </div>
                                <div class="calendar" id="shiftCalendar">
                                    <!-- Calendar will be generated by JavaScript -->
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Shift Legend</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($shift_types as $shift): ?>
                                        <div class="d-flex align-items-center">
                                            <span class="shift-indicator" style="background-color: <?php echo $shift['color']; ?>"></span>
                                            <span class="ms-2"><?php echo $shift['name']; ?> (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Roster Tab -->
                <?php if ($active_tab == 'roster'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Shift Roster</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignShiftModal">
                                <i class='bx bx-plus'></i> Assign Shift
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Date Range</label>
                                            <select class="form-select" id="dateRangeFilter">
                                                <option value="this_week">This Week</option>
                                                <option value="next_week">Next Week</option>
                                                <option value="this_month" selected>This Month</option>
                                                <option value="next_month">Next Month</option>
                                                <option value="custom">Custom Range</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <select class="form-select" id="rosterDeptFilter">
                                                <option value="all">All Departments</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Shift Type</label>
                                            <select class="form-select" id="shiftTypeFilter">
                                                <option value="all">All Shifts</option>
                                                <?php foreach ($shift_types as $shift): ?>
                                                    <option value="<?php echo $shift['id']; ?>"><?php echo $shift['name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Sort By</label>
                                            <select class="form-select" id="sortRosterFilter">
                                                <option value="name_asc">Name (A-Z)</option>
                                                <option value="name_desc">Name (Z-A)</option>
                                                <option value="department">Department</option>
                                                <option value="date">Date</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Date</th>
                                            <th>Shift</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?></td>
                                                <td><?php 
                                                    $employee_dept = '';
                                                    foreach ($employees as $emp) {
                                                        if ($emp['id'] == $schedule['employee_id']) {
                                                            $employee_dept = $emp['department'];
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($employee_dept);
                                                ?></td>
                                                <td><?php echo date('M j, Y', strtotime($schedule['date'])); ?></td>
                                                <td>
                                                    <span class="shift-indicator" style="background-color: <?php echo $schedule['color']; ?>"></span>
                                                    <?php echo $schedule['shift_name']; ?>
                                                </td>
                                                <td><?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Scheduled</span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary edit-shift" data-id="<?php echo $schedule['id']; ?>">
                                                            <i class='bx bxs-edit'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-shift" data-id="<?php echo $schedule['id']; ?>">
                                                            <i class='bx bxs-trash'></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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

                <!-- Leave Requests Tab -->
                <?php if ($active_tab == 'requests'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Leave Requests</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestLeaveModal">
                                <i class='bx bx-plus'></i> Request Leave
                            </button>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills mb-3" id="leaveTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button" role="tab">Pending (<?php echo $pending_leave_count; ?>)</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="approved-tab" data-bs-toggle="pill" data-bs-target="#approved" type="button" role="tab">Approved</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="rejected-tab" data-bs-toggle="pill" data-bs-target="#rejected" type="button" role="tab">Rejected</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="leaveTabContent">
                                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                                    <?php if (count($pending_requests) > 0): ?>
                                        <?php foreach ($pending_requests as $request): ?>
                                            <div class="card leave-card mb-3">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="card-title"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h6>
                                                            <p class="card-text mb-1">
                                                                <strong><?php echo $request['leave_type']; ?></strong>: 
                                                                <?php echo date('M j, Y', strtotime($request['start_date'])); ?> to <?php echo date('M j, Y', strtotime($request['end_date'])); ?>
                                                            </p>
                                                            <p class="card-text text-muted mb-0"><?php echo htmlspecialchars($request['reason']); ?></p>
                                                        </div>
                                                        <div class="action-buttons">
                                                            <?php if ($is_admin): ?>
                                                                <button class="btn btn-sm btn-success approve-request" data-type="leave" data-id="<?php echo $request['id']; ?>">
                                                                    <i class='bx bx-check'></i> Approve
                                                                </button>
                                                                <button class="btn btn-sm btn-danger reject-request" data-type="leave" data-id="<?php echo $request['id']; ?>">
                                                                    <i class='bx bx-x'></i> Reject
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Pending Review</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class='bx bx-time text-muted' style="font-size: 3rem;"></i>
                                            <p class="mt-3 text-muted">No pending leave requests</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="tab-pane fade" id="approved" role="tabpanel">
                                    <div class="text-center py-4">
                                        <i class='bx bx-check-circle text-muted' style="font-size: 3rem;"></i>
                                        <p class="mt-3 text-muted">No approved leave requests</p>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="rejected" role="tabpanel">
                                    <div class="text-center py-4">
                                        <i class='bx bx-x-circle text-muted' style="font-size: 3rem;"></i>
                                        <p class="mt-3 text-muted">No rejected leave requests</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Shift Swaps Tab -->
                <?php if ($active_tab == 'swaps'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Shift Swap Requests</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestSwapModal">
                                <i class='bx bx-plus'></i> Request Swap
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (count($swap_requests) > 0): ?>
                                <?php foreach ($swap_requests as $request): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title">Shift Swap Request</h6>
                                                    <p class="card-text mb-1">
                                                        <strong><?php echo htmlspecialchars($request['orig_first_name'] . ' ' . $request['orig_last_name']); ?></strong> wants to swap with 
                                                        <strong><?php echo htmlspecialchars($request['req_first_name'] . ' ' . $request['req_last_name']); ?></strong>
                                                    </p>
                                                    <p class="card-text mb-1">
                                                        <strong>Shift:</strong> <?php echo $request['shift_name']; ?> on <?php echo date('M j, Y', strtotime($request['shift_date'])); ?>
                                                    </p>
                                                    <p class="card-text text-muted mb-0">
                                                        <strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?>
                                                    </p>
                                                </div>
                                                <div class="action-buttons">
                                                    <?php if ($is_admin): ?>
                                                        <button class="btn btn-sm btn-success approve-request" data-type="swap" data-id="<?php echo $request['id']; ?>">
                                                            <i class='bx bx-check'></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger reject-request" data-type="swap" data-id="<?php echo $request['id']; ?>">
                                                            <i class='bx bx-x'></i> Reject
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending Review</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class='bx bx-transfer text-muted' style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">No pending shift swap requests</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reports Tab -->
                <?php if ($active_tab == 'reports'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Shift Scheduling Reports</h5>
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
                                            <h5>Shifts by Type</h5>
                                            <canvas id="shiftsByTypeChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>Leave Request Status</h5>
                                            <canvas id="leaveStatusChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="text-center mb-4">Schedule Coverage</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Department</th>
                                                    <th>Scheduled</th>
                                                    <th>On Leave</th>
                                                    <th>Coverage</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Firefighting</td>
                                                    <td>15</td>
                                                    <td>2</td>
                                                    <td>86.7%</td>
                                                    <td><span class="badge bg-success">Adequate</span></td>
                                                </tr>
                                                <tr>
                                                    <td>EMS</td>
                                                    <td>8</td>
                                                    <td>1</td>
                                                    <td>87.5%</td>
                                                    <td><span class="badge bg-success">Adequate</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Administration</td>
                                                    <td>5</td>
                                                    <td>2</td>
                                                    <td>60%</td>
                                                    <td><span class="badge bg-warning">Low</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Logistics</td>
                                                    <td>6</td>
                                                    <td>0</td>
                                                    <td>100%</td>
                                                    <td><span class="badge bg-success">Full</span></td>
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
    <!-- Assign Shift Modal -->
    <div class="modal fade" id="assignShiftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assignShiftForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['department'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shift Type</label>
                            <select class="form-select" name="shift_type_id" required>
                                <option value="">Select Shift Type</option>
                                <?php foreach ($shift_types as $shift): ?>
                                    <option value="<?php echo $shift['id']; ?>" data-color="<?php echo $shift['color']; ?>"><?php echo $shift['name']; ?> (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Leave Modal -->
    <div class="modal fade" id="requestLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="requestLeaveForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['department'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Leave Type</label>
                            <select class="form-select" name="leave_type_id" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach ($leave_types as $leave): ?>
                                    <option value="<?php echo $leave['id']; ?>"><?php echo $leave['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Swap Modal -->
    <div class="modal fade" id="requestSwapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Shift Swap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="requestSwapForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Your Shift</label>
                            <select class="form-select" name="shift_schedule_id" required>
                                <option value="">Select Your Shift</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>"><?php echo date('M j, Y', strtotime($schedule['date'])); ?> - <?php echo $schedule['shift_name']; ?> (<?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Swap With</label>
                            <select class="form-select" name="requested_employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['department'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
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
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#assignShiftModal">
                            <i class='bx bxs-calendar'></i> Assign Shift
                        </button>
                        <button class="btn btn-warning btn-icon" data-bs-toggle="modal" data-bs-target="#requestLeaveModal">
                            <i class='bx bxs-time'></i> Request Leave
                        </button>
                        <button class="btn btn-info btn-icon" data-bs-toggle="modal" data-bs-target="#requestSwapModal">
                            <i class='bx bxs-transfer'></i> Request Swap
                        </button>
                        <button class="btn btn-success btn-icon">
                            <i class='bx bxs-report'></i> Generate Report
                        </button>
                    </div>
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

        // Initialize calendar if on calendar page
        <?php if ($active_tab == 'calendar'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            generateCalendar('<?php echo $current_month; ?>');
        });
        
        function generateCalendar(month) {
            const calendarEl = document.getElementById('shiftCalendar');
            calendarEl.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Loading calendar...</p></div>';
            
            // Simulate API call to get schedules for the month
            setTimeout(() => {
                // This would be replaced with actual data from the server
                const year = month.split('-')[0];
                const monthNum = parseInt(month.split('-')[1]);
                
                // Get first day of month and number of days
                const firstDay = new Date(year, monthNum - 1, 1);
                const lastDay = new Date(year, monthNum, 0);
                const daysInMonth = lastDay.getDate();
                const startingDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
                
                // Generate calendar HTML
                let calendarHTML = '';
                
                // Add empty cells for days before the first day of the month
                for (let i = 0; i < startingDay; i++) {
                    const prevDate = new Date(year, monthNum - 1, -i);
                    calendarHTML += `<div class="calendar-day other-month">
                        <div class="day-number">${prevDate.getDate()}</div>
                    </div>`;
                }
                
                // Add cells for each day of the month
                for (let i = 1; i <= daysInMonth; i++) {
                    const dateStr = `${year}-${monthNum.toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                    const isToday = dateStr === '<?php echo $current_date; ?>';
                    
                    calendarHTML += `<div class="calendar-day ${isToday ? 'today' : ''}" data-date="${dateStr}">
                        <div class="day-number">${i}</div>
                        <div class="shifts-container">
                            <!-- Shifts would be populated here based on actual data -->
                        </div>
                    </div>`;
                }
                
                // Add empty cells for days after the last day of the month
                const totalCells = 42; // 6 rows x 7 columns
                const remainingCells = totalCells - (startingDay + daysInMonth);
                
                for (let i = 1; i <= remainingCells; i++) {
                    const nextDate = new Date(year, monthNum, i);
                    calendarHTML += `<div class="calendar-day other-month">
                        <div class="day-number">${i}</div>
                    </div>`;
                }
                
                calendarEl.innerHTML = calendarHTML;
                
                // Add sample shifts for demonstration
                const sampleShifts = [
                    { date: '<?php echo date('Y-m-d'); ?>', name: 'Day Shift', color: '#007bff', employees: 3 },
                    { date: '<?php echo date('Y-m-d', strtotime('+1 day')); ?>', name: 'Night Shift', color: '#6f42c1', employees: 2 },
                    { date: '<?php echo date('Y-m-d', strtotime('+3 days')); ?>', name: 'Swing Shift', color: '#fd7e14', employees: 4 },
                    { date: '<?php echo date('Y-m-d', strtotime('+5 days')); ?>', name: 'Graveyard', color: '#343a40', employees: 2 },
                ];
                
                sampleShifts.forEach(shift => {
                    const dayEl = calendarEl.querySelector(`[data-date="${shift.date}"]`);
                    if (dayEl) {
                        dayEl.querySelector('.shifts-container').innerHTML = `
                            <div class="shift-item" style="background-color: ${shift.color}">
                                ${shift.name} (${shift.employees})
                            </div>
                        `;
                    }
                });
            }, 1000);
        }
        
        // Month filter change
        document.getElementById('monthFilter').addEventListener('change', function() {
            generateCalendar(this.value);
        });
        
        // Refresh calendar button
        document.getElementById('refreshCalendar').addEventListener('click', function() {
            generateCalendar(document.getElementById('monthFilter').value);
        });
        <?php endif; ?>

        // Initialize charts if on reports page
        <?php if ($active_tab == 'reports'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Shifts by Type Chart
            const shiftsCtx = document.getElementById('shiftsByTypeChart').getContext('2d');
            const shiftsChart = new Chart(shiftsCtx, {
                type: 'pie',
                data: {
                    labels: ['Day Shift', 'Night Shift', 'Swing Shift', 'Graveyard'],
                    datasets: [{
                        data: [45, 30, 15, 10],
                        backgroundColor: ['#007bff', '#6f42c1', '#fd7e14', '#343a40'],
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

            // Leave Status Chart
            const leaveCtx = document.getElementById('leaveStatusChart').getContext('2d');
            const leaveChart = new Chart(leaveCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [60, 25, 15],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
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

        // Form submissions
        document.getElementById('assignShiftForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Shift assigned successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('assignShiftModal'));
            modal.hide();
        });

        document.getElementById('requestLeaveForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Leave request submitted successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestLeaveModal'));
            modal.hide();
        });

        document.getElementById('requestSwapForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Shift swap request submitted successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('requestSwapModal'));
            modal.hide();
        });

        // Approve/Reject request buttons
        document.querySelectorAll('.approve-request, .reject-request').forEach(button => {
            button.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const id = this.getAttribute('data-id');
                const action = this.classList.contains('approve-request') ? 'approve' : 'reject';
                
                // In a real implementation, you would make an API call
                alert(`${action === 'approve' ? 'Approving' : 'Rejecting'} ${type} request ${id}`);
                
                // Simulate UI update
                const card = this.closest('.card');
                card.classList.add('fade');
                setTimeout(() => {
                    card.remove();
                }, 500);
            });
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                bootstrap.Alert.getInstance(alert)?.close();
            }, 5000);
        });

        // Add animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.fade-in');
            cards.forEach(function(card, index) {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });

        // API call examples
        function fetchSchedules(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_schedules&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching schedules:', error);
                    // Show error message to user
                    alert('Error loading schedules. Please try again.');
                });
        }

        function fetchEmployeeSchedule(employeeId) {
            return fetch(`index.php?api=get_employee_schedule&employee_id=${employeeId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching employee schedule:', error);
                    // Show error message to user
                    alert('Error loading employee schedule. Please try again.');
                });
        }

        // Example of using the API functions
        /*
        // Fetch all schedules for current month
        fetchSchedules({ month: '2023-11' })
            .then(schedules => {
                console.log('Schedules:', schedules);
                // Update UI with schedules
            });
        
        // Fetch employee schedule
        fetchEmployeeSchedule(123)
            .then(schedule => {
                console.log('Employee schedule:', schedule);
                // Update UI with employee schedule
            });
        */

        // Form submission handlers for API calls
        document.getElementById('assignShiftForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                employee_id: this.querySelector('[name="employee_id"]').value,
                shift_type_id: this.querySelector('[name="shift_type_id"]').value,
                date: this.querySelector('[name="date"]').value,
                notes: this.querySelector('[name="notes"]').value
            };
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Assigning...';
            submitBtn.disabled = true;
            
            fetch('index.php?api=assign_shift', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Shift assigned successfully!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('assignShiftModal'));
                    modal.hide();
                    // Refresh the page or update UI as needed
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error assigning shift. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Similar form submission handlers can be added for:
        // - requestLeaveForm
        // - requestSwapForm
        // - approve/reject requests

    </script>
</body>
</html>