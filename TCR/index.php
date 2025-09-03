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
        case 'get_training_stats':
            // Count training statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_courses,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
                    (SELECT COUNT(*) FROM training_sessions WHERE status = 'scheduled') as scheduled_sessions,
                    (SELECT COUNT(*) FROM training_sessions WHERE status = 'ongoing') as ongoing_sessions
                FROM training_courses
            ");
            $stmt->execute();
            $training_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Count certifications
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_certifications,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_certifications,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_certifications,
                    SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked_certifications
                FROM certifications
            ");
            $stmt->execute();
            $certification_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Count upcoming renewals (within 30 days)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as upcoming_renewals 
                FROM certifications 
                WHERE status = 'active' AND expiry_date <= CURDATE() + INTERVAL 30 DAY
            ");
            $stmt->execute();
            $upcoming_renewals = $stmt->fetch()['upcoming_renewals'];

            echo json_encode([
                'training' => $training_stats,
                'certifications' => $certification_stats,
                'upcoming_renewals' => $upcoming_renewals
            ]);
            exit;
            
        case 'get_training_courses':
            $filters = [];
            $params = [];

            if (isset($_GET['category'])) {
                $filters[] = "category = ?";
                $params[] = $_GET['category'];
            }

            if (isset($_GET['status'])) {
                $filters[] = "status = ?";
                $params[] = $_GET['status'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            $stmt = $pdo->prepare("
                SELECT * 
                FROM training_courses 
                $whereClause 
                ORDER BY course_name
            ");
            $stmt->execute($params);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($courses);
            exit;
            
        case 'get_training_sessions':
            $filters = [];
            $params = [];

            if (isset($_GET['status'])) {
                $filters[] = "ts.status = ?";
                $params[] = $_GET['status'];
            }

            if (isset($_GET['course_id'])) {
                $filters[] = "ts.course_id = ?";
                $params[] = $_GET['course_id'];
            }

            if (isset($_GET['start_date'])) {
                $filters[] = "ts.start_date >= ?";
                $params[] = $_GET['start_date'];
            }

            if (isset($_GET['end_date'])) {
                $filters[] = "ts.end_date <= ?";
                $params[] = $_GET['end_date'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            $stmt = $pdo->prepare("
                SELECT ts.*, tc.course_name, tc.course_code,
                       u.first_name as creator_first, u.last_name as creator_last
                FROM training_sessions ts
                LEFT JOIN training_courses tc ON ts.course_id = tc.id
                LEFT JOIN users u ON ts.created_by = u.id
                $whereClause
                ORDER BY ts.start_date ASC, ts.start_time ASC
            ");
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($sessions);
            exit;
            
        case 'add_training_course':
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

            if (!isset($data['course_code']) || !isset($data['course_name']) || !isset($data['category']) || !isset($data['duration_hours'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                
                // Check if course code already exists
                $stmt = $pdo->prepare("SELECT id FROM training_courses WHERE course_code = ?");
                $stmt->execute([$data['course_code']]);
                if ($stmt->fetch()) {
                    throw new Exception("Course code already exists");
                }
                
                // Insert new course
                $stmt = $pdo->prepare("
                    INSERT INTO training_courses 
                    (course_code, course_name, description, duration_hours, validity_months, category, prerequisites, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['course_code'],
                    $data['course_name'],
                    $data['description'] ?? null,
                    $data['duration_hours'],
                    $data['validity_months'] ?? null,
                    $data['category'],
                    $data['prerequisites'] ?? null,
                    $data['status'] ?? 'active'
                ]);
                
                $courseId = $pdo->lastInsertId();
                
                // Log audit action
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    'add_training_course',
                    'training_courses',
                    $courseId,
                    json_encode([
                        'course_code' => $data['course_code'],
                        'course_name' => $data['course_name'],
                        'category' => $data['category']
                    ]),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'course_id' => $courseId,
                    'message' => 'Training course added successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'schedule_training_session':
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

            if (!isset($data['course_id']) || !isset($data['session_code']) || !isset($data['title']) || 
                !isset($data['start_date']) || !isset($data['end_date']) || !isset($data['start_time']) || 
                !isset($data['end_time']) || !isset($data['location']) || !isset($data['instructor']) || 
                !isset($data['max_participants'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                
                // Check if session code already exists
                $stmt = $pdo->prepare("SELECT id FROM training_sessions WHERE session_code = ?");
                $stmt->execute([$data['session_code']]);
                if ($stmt->fetch()) {
                    throw new Exception("Session code already exists");
                }
                
                // Insert new session
                $stmt = $pdo->prepare("
                    INSERT INTO training_sessions 
                    (course_id, session_code, title, description, start_date, end_date, start_time, end_time, location, instructor, max_participants, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['course_id'],
                    $data['session_code'],
                    $data['title'],
                    $data['description'] ?? null,
                    $data['start_date'],
                    $data['end_date'],
                    $data['start_time'],
                    $data['end_time'],
                    $data['location'],
                    $data['instructor'],
                    $data['max_participants'],
                    $data['status'] ?? 'scheduled',
                    $_SESSION['user_id']
                ]);
                
                $sessionId = $pdo->lastInsertId();
                
                // Log audit action
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    'schedule_training_session',
                    'training_sessions',
                    $sessionId,
                    json_encode([
                        'session_code' => $data['session_code'],
                        'title' => $data['title'],
                        'start_date' => $data['start_date']
                    ]),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'session_id' => $sessionId,
                    'message' => 'Training session scheduled successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'enroll_participant':
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

            if (!isset($data['session_id']) || !isset($data['user_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                
                // Check if participant is already enrolled
                $stmt = $pdo->prepare("SELECT id FROM training_enrollments WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$data['session_id'], $data['user_id']]);
                if ($stmt->fetch()) {
                    throw new Exception("Participant is already enrolled in this session");
                }
                
                // Check session capacity
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as enrolled_count, max_participants 
                    FROM training_enrollments te
                    JOIN training_sessions ts ON te.session_id = ts.id
                    WHERE te.session_id = ?
                ");
                $stmt->execute([$data['session_id']]);
                $session = $stmt->fetch();
                
                if ($session && $session['enrolled_count'] >= $session['max_participants']) {
                    throw new Exception("Session has reached maximum capacity");
                }
                
                // Enroll participant
                $stmt = $pdo->prepare("
                    INSERT INTO training_enrollments 
                    (session_id, user_id, enrollment_date, status) 
                    VALUES (?, ?, CURDATE(), 'registered')
                ");
                $stmt->execute([$data['session_id'], $data['user_id']]);
                
                $enrollmentId = $pdo->lastInsertId();
                
                // Log audit action
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    'enroll_participant',
                    'training_enrollments',
                    $enrollmentId,
                    json_encode([
                        'session_id' => $data['session_id'],
                        'user_id' => $data['user_id']
                    ]),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'enrollment_id' => $enrollmentId,
                    'message' => 'Participant enrolled successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_enrollment_status':
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

            if (!isset($data['enrollment_id']) || !isset($data['status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                
                // Get current enrollment details
                $stmt = $pdo->prepare("SELECT * FROM training_enrollments WHERE id = ?");
                $stmt->execute([$data['enrollment_id']]);
                $enrollment = $stmt->fetch();
                
                if (!$enrollment) {
                    throw new Exception("Enrollment not found");
                }
                
                // Update enrollment status
                $stmt = $pdo->prepare("
                    UPDATE training_enrollments 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$data['status'], $data['enrollment_id']]);
                
                // If status is completed and course has validity, issue certificate
                if ($data['status'] === 'completed') {
                    $stmt = $pdo->prepare("
                        SELECT tc.validity_months 
                        FROM training_courses tc
                        JOIN training_sessions ts ON tc.id = ts.course_id
                        JOIN training_enrollments te ON ts.id = te.session_id
                        WHERE te.id = ?
                    ");
                    $stmt->execute([$data['enrollment_id']]);
                    $course = $stmt->fetch();
                    
                    if ($course && $course['validity_months']) {
                        $issueDate = date('Y-m-d');
                        $expiryDate = date('Y-m-d', strtotime("+{$course['validity_months']} months"));
                        
                        // Generate certification number
                        $certNumber = 'CERT-' . strtoupper(uniqid());
                        
                        $stmt = $pdo->prepare("
                            UPDATE training_enrollments 
                            SET certificate_issued = 1, certificate_issue_date = ?, certificate_expiry_date = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$issueDate, $expiryDate, $data['enrollment_id']]);
                        
                        // Also add to certifications table
                        $stmt = $pdo->prepare("
                            INSERT INTO certifications 
                            (user_id, course_id, certification_number, issue_date, expiry_date, status, issuing_authority) 
                            SELECT te.user_id, ts.course_id, ?, ?, ?, 'active', 'Quezon City Fire Department'
                            FROM training_enrollments te
                            JOIN training_sessions ts ON te.session_id = ts.id
                            WHERE te.id = ?
                        ");
                        $stmt->execute([$certNumber, $issueDate, $expiryDate, $data['enrollment_id']]);
                    }
                }
                
                // Log audit action
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    'update_enrollment_status',
                    'training_enrollments',
                    $data['enrollment_id'],
                    json_encode(['status' => $enrollment['status']]),
                    json_encode(['status' => $data['status']]),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Enrollment status updated successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_certifications':
            $filters = [];
            $params = [];

            if (isset($_GET['user_id'])) {
                $filters[] = "c.user_id = ?";
                $params[] = $_GET['user_id'];
            }

            if (isset($_GET['status'])) {
                $filters[] = "c.status = ?";
                $params[] = $_GET['status'];
            }

            if (isset($_GET['expiring_soon'])) {
                $filters[] = "c.expiry_date <= CURDATE() + INTERVAL 30 DAY AND c.status = 'active'";
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            $stmt = $pdo->prepare("
                SELECT c.*, tc.course_name, tc.course_code,
                       u.first_name, u.last_name
                FROM certifications c
                LEFT JOIN training_courses tc ON c.course_id = tc.id
                LEFT JOIN users u ON c.user_id = u.id
                $whereClause
                ORDER BY c.expiry_date ASC
            ");
            $stmt->execute($params);
            $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($certifications);
            exit;
            
        case 'get_audit_logs':
            $filters = [];
            $params = [];

            if (isset($_GET['user_id'])) {
                $filters[] = "al.user_id = ?";
                $params[] = $_GET['user_id'];
            }

            if (isset($_GET['action'])) {
                $filters[] = "al.action = ?";
                $params[] = $_GET['action'];
            }

            if (isset($_GET['start_date'])) {
                $filters[] = "DATE(al.created_at) >= ?";
                $params[] = $_GET['start_date'];
            }

            if (isset($_GET['end_date'])) {
                $filters[] = "DATE(al.created_at) <= ?";
                $params[] = $_GET['end_date'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";
            $limit = isset($_GET['limit']) ? "LIMIT " . intval($_GET['limit']) : "LIMIT 100";

            $stmt = $pdo->prepare("
                SELECT al.*, u.first_name, u.last_name 
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                $whereClause
                ORDER BY al.created_at DESC
                $limit
            ");
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($logs);
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
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profiles';

// Get all users for enrollment
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training courses
$stmt = $pdo->prepare("SELECT * FROM training_courses ORDER BY course_name");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique course categories
$stmt = $pdo->prepare("SELECT DISTINCT category FROM training_courses ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate stats
$total_courses = count($courses);
$active_courses = 0;
foreach ($courses as $course) {
    if ($course['status'] == 'active') $active_courses++;
}

// Get session counts
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM training_sessions GROUP BY status");
$stmt->execute();
$session_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$scheduled_sessions = 0;
$ongoing_sessions = 0;
foreach ($session_counts as $count) {
    if ($count['status'] == 'scheduled') $scheduled_sessions = $count['count'];
    if ($count['status'] == 'ongoing') $ongoing_sessions = $count['count'];
}

// Get certification counts
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM certifications GROUP BY status");
$stmt->execute();
$cert_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_certifications = 0;
$active_certifications = 0;
$expired_certifications = 0;
$revoked_certifications = 0;

foreach ($cert_counts as $count) {
    $total_certifications += $count['count'];
    if ($count['status'] == 'active') $active_certifications = $count['count'];
    if ($count['status'] == 'expired') $expired_certifications = $count['count'];
    if ($count['status'] == 'revoked') $revoked_certifications = $count['count'];
}

// Count upcoming renewals (within 30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as upcoming_renewals 
    FROM certifications 
    WHERE status = 'active' AND expiry_date <= CURDATE() + INTERVAL 30 DAY
");
$stmt->execute();
$upcoming_renewals = $stmt->fetch()['upcoming_renewals'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Training and Certification Records</title>
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
        
        .stat-card.courses::before { background: var(--primary-gradient); }
        .stat-card.sessions::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.certifications::before { background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%); }
        .stat-card.renewals::before { background: linear-gradient(135deg, #e53935 0%, #ff6b6b 100%); }
        
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
        
        .stat-card.sessions .stat-icon { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success); 
        }
        
        .stat-card.certifications .stat-icon { 
            background: rgba(255, 152, 0, 0.1); 
            color: var(--warning); 
        }
        
        .stat-card.renewals .stat-icon { 
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
        
        .status-active { background-color: var(--success); }
        .status-scheduled { background-color: var(--primary); }
        .status-ongoing { background-color: var(--warning); }
        .status-completed { background-color: var(--success); }
        .status-cancelled { background-color: var(--danger); }
        .status-expired { background-color: var(--danger); }
        .status-revoked { background-color: var(--secondary); }
        
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
                    <small>Training & Certification Records</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation of TCR</div>
                <a href="index.php?tab=profiles" class="sidebar-link <?php echo $active_tab == 'profiles' ? 'active' : ''; ?>">
                    <i class='bx bxs-user-detail'></i>
                    <span class="text">Training Profiles</span>
                </a>
                <a href="index.php?tab=courses" class="sidebar-link <?php echo $active_tab == 'courses' ? 'active' : ''; ?>">
                    <i class='bx bxs-book'></i>
                    <span class="text">Training Courses</span>
                </a>
                <a href="index.php?tab=sessions" class="sidebar-link <?php echo $active_tab == 'sessions' ? 'active' : ''; ?>">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Training Sessions</span>
                </a>
                <a href="index.php?tab=certifications" class="sidebar-link <?php echo $active_tab == 'certifications' ? 'active' : ''; ?>">
                    <i class='bx bxs-certificate'></i>
                    <span class="text">Certifications</span>
                </a>
                <a href="index.php?tab=reports" class="sidebar-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                    <i class='bx bxs-report'></i>
                    <span class="text">Reports & Analytics</span>
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
                <a href="../PSS/index.php" class="sidebar-link">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Personnel Shift Scheduling</span>
                </a>
                <a href="index.php" class="sidebar-link active">
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
                    <h1>Training & Certification Records</h1>
                    <p>Manage training programs, certifications, and personnel qualifications</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box me-3">
                        <input type="text" class="form-control" placeholder="Search courses, personnel...">
                    </div>
                    
                    <div class="notification-dropdown dropdown me-2">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-bell'></i>
                            <span class="notification-badge"><?php echo $upcoming_renewals; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php if ($upcoming_renewals > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=certifications"><?php echo $upcoming_renewals; ?> certifications expiring soon</a></li>
                            <?php endif; ?>
                            <?php if ($scheduled_sessions > 0): ?>
                                <li><a class="dropdown-item" href="index.php?tab=sessions"><?php echo $scheduled_sessions; ?> upcoming training sessions</a></li>
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
                <div class="stat-card courses animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-book'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_courses; ?></div>
                    <div class="stat-label">Training Courses</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-check-circle'></i>
                            <span><?php echo $active_courses; ?> Active</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-category'></i>
                            <span><?php echo count($categories); ?> Categories</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card sessions animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-calendar'></i>
                    </div>
                    <div class="stat-value"><?php echo $scheduled_sessions + $ongoing_sessions; ?></div>
                    <div class="stat-label">Active Sessions</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-time-five'></i>
                            <span><?php echo $scheduled_sessions; ?> Scheduled</span>
                        </div>
                        <div class="stat-detail detail-warning">
                            <i class='bx bx-trending-up'></i>
                            <span><?php echo $ongoing_sessions; ?> Ongoing</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card certifications animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-certificate'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_certifications; ?></div>
                    <div class="stat-label">Certifications</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-check-circle'></i>
                            <span><?php echo $active_certifications; ?> Active</span>
                        </div>
                        <div class="stat-detail detail-danger">
                            <i class='bx bxs-error-circle'></i>
                            <span><?php echo $expired_certifications; ?> Expired</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card renewals animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-time'></i>
                    </div>
                    <div class="stat-value"><?php echo $upcoming_renewals; ?></div>
                    <div class="stat-label">Upcoming Renewals</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-danger">
                            <i class='bx bxs-error'></i>
                            <span>Within 30 Days</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-alarm'></i>
                            <span>Attention Required</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-tabs">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=profiles" class="nav-link <?php echo $active_tab == 'profiles' ? 'active' : ''; ?>">
                            <i class='bx bxs-user-detail'></i> Training Profiles
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=courses" class="nav-link <?php echo $active_tab == 'courses' ? 'active' : ''; ?>">
                            <i class='bx bxs-book'></i> Training Courses
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=sessions" class="nav-link <?php echo $active_tab == 'sessions' ? 'active' : ''; ?>">
                            <i class='bx bxs-calendar'></i> Training Sessions
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=certifications" class="nav-link <?php echo $active_tab == 'certifications' ? 'active' : ''; ?>">
                            <i class='bx bxs-certificate'></i> Certifications
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=reports" class="nav-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                            <i class='bx bxs-report'></i> Reports & Analytics
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

                <!-- Training Profiles Tab -->
                <?php if ($active_tab == 'profiles'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Personnel Training Profiles</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProfileModal">
                                <i class='bx bx-plus'></i> Add Profile
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Search Personnel</label>
                                            <input type="text" class="form-control" placeholder="Search by name...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <select class="form-select">
                                                <option value="">All Departments</option>
                                                <option value="operations">Operations</option>
                                                <option value="prevention">Prevention</option>
                                                <option value="investigation">Investigation</option>
                                                <option value="administration">Administration</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Rank/Position</label>
                                            <select class="form-select">
                                                <option value="">All Ranks</option>
                                                <option value="firefighter">Firefighter</option>
                                                <option value="engineer">Engineer</option>
                                                <option value="lieutenant">Lieutenant</option>
                                                <option value="captain">Captain</option>
                                                <option value="battalion_chief">Battalion Chief</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Personnel</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Completed Courses</th>
                                            <th>Active Certifications</th>
                                            <th>Training Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px;">
                                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                            <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>Firefighter</td>
                                                <td>Operations</td>
                                                <td>
                                                    <span class="badge bg-primary rounded-pill">5</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success rounded-pill">3</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">Compliant</span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary view-profile" data-id="<?php echo $user['id']; ?>">
                                                            <i class='bx bxs-show'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary edit-profile" data-id="<?php echo $user['id']; ?>">
                                                            <i class='bx bxs-edit'></i>
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

                <!-- Training Courses Tab -->
                <?php if ($active_tab == 'courses'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Training Courses Management</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class='bx bx-plus'></i> Add Course
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" id="categoryFilter">
                                                <option value="">All Categories</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" id="statusFilter">
                                                <option value="">All Status</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Duration</label>
                                            <select class="form-select" id="durationFilter">
                                                <option value="">Any Duration</option>
                                                <option value="1-8">1-8 hours</option>
                                                <option value="9-16">9-16 hours</option>
                                                <option value="17-24">17-24 hours</option>
                                                <option value="25+">25+ hours</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Sort By</label>
                                            <select class="form-select" id="sortFilter">
                                                <option value="name_asc">Name (A-Z)</option>
                                                <option value="name_desc">Name (Z-A)</option>
                                                <option value="duration_asc">Duration (Low-High)</option>
                                                <option value="duration_desc">Duration (High-Low)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" id="coursesList">
                                <?php foreach ($courses as $course): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                    <span class="badge bg-<?php echo $course['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <span class="status-indicator status-<?php echo $course['status']; ?>"></span>
                                                        <?php echo ucfirst($course['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="card-text text-muted small mb-1">
                                                    <strong>Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?>
                                                </p>
                                                <p class="card-text text-muted small mb-1">
                                                    <strong>Category:</strong> <?php echo htmlspecialchars($course['category']); ?>
                                                </p>
                                                <p class="card-text text-muted small mb-2">
                                                    <strong>Duration:</strong> <?php echo $course['duration_hours']; ?> hours
                                                    <?php if ($course['validity_months']): ?>
                                                        | <strong>Validity:</strong> <?php echo $course['validity_months']; ?> months
                                                    <?php endif; ?>
                                                </p>
                                                <?php if ($course['prerequisites']): ?>
                                                    <p class="card-text text-muted small mb-2">
                                                        <strong>Prerequisites:</strong> <?php echo htmlspecialchars($course['prerequisites']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <?php 
                                                        $stmt = $pdo->prepare("SELECT COUNT(*) as session_count FROM training_sessions WHERE course_id = ?");
                                                        $stmt->execute([$course['id']]);
                                                        $session_count = $stmt->fetch()['session_count'];
                                                        ?>
                                                        <?php echo $session_count; ?> session(s)
                                                    </small>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary view-course" data-id="<?php echo $course['id']; ?>">
                                                            <i class='bx bxs-show'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary edit-course" data-id="<?php echo $course['id']; ?>">
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

                <!-- Training Sessions Tab -->
                <?php if ($active_tab == 'sessions'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Training Sessions</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                                <i class='bx bx-plus'></i> Schedule Session
                            </button>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills mb-3" id="sessionsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="pill" data-bs-target="#upcoming" type="button" role="tab">Upcoming</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="ongoing-tab" data-bs-toggle="pill" data-bs-target="#ongoing" type="button" role="tab">Ongoing</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed" type="button" role="tab">Completed</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="sessionsTabContent">
                                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Session Code</th>
                                                    <th>Course</th>
                                                    <th>Date & Time</th>
                                                    <th>Location</th>
                                                    <th>Instructor</th>
                                                    <th>Participants</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class='bx bx-calendar text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No upcoming training sessions</p>
                                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                                                            <i class='bx bx-plus'></i> Schedule Session
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="ongoing" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Session Code</th>
                                                    <th>Course</th>
                                                    <th>Date & Time</th>
                                                    <th>Location</th>
                                                    <th>Instructor</th>
                                                    <th>Participants</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class='bx bx-time-five text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No ongoing training sessions</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="completed" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Session Code</th>
                                                    <th>Course</th>
                                                    <th>Date & Time</th>
                                                    <th>Location</th>
                                                    <th>Instructor</th>
                                                    <th>Participants</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class='bx bx-check-circle text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No completed training sessions</p>
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

                <!-- Certifications Tab -->
                <?php if ($active_tab == 'certifications'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Certification Records</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                <i class='bx bx-plus'></i> Add Certification
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class='bx bx-info-circle'></i> You have <strong><?php echo $upcoming_renewals; ?></strong> certifications expiring within the next 30 days.
                            </div>
                            
                            <ul class="nav nav-pills mb-3" id="certificationsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="active-tab" data-bs-toggle="pill" data-bs-target="#active-certs" type="button" role="tab">Active</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="expiring-tab" data-bs-toggle="pill" data-bs-target="#expiring-certs" type="button" role="tab">Expiring Soon</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="expired-tab" data-bs-toggle="pill" data-bs-target="#expired-certs" type="button" role="tab">Expired</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="all-tab" data-bs-toggle="pill" data-bs-target="#all-certs" type="button" role="tab">All Certifications</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="certificationsTabContent">
                                <div class="tab-pane fade show active" id="active-certs" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Personnel</th>
                                                    <th>Certification</th>
                                                    <th>Issue Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class='bx bx-certification text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No active certifications found</p>
                                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                                            <i class='bx bx-plus'></i> Add Certification
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="expiring-certs" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Personnel</th>
                                                    <th>Certification</th>
                                                    <th>Issue Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Days Remaining</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class='bx bx-time text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No certifications expiring soon</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="expired-certs" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Personnel</th>
                                                    <th>Certification</th>
                                                    <th>Issue Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Days Expired</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class='bx bx-error-circle text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No expired certifications found</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="all-certs" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Personnel</th>
                                                    <th>Certification</th>
                                                    <th>Issue Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class='bx bx-certification text-muted' style="font-size: 3rem;"></i>
                                                        <p class="mt-3 text-muted">No certification records found</p>
                                                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                                                            <i class='bx bx-plus'></i> Add Certification
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

                <!-- Reports Tab -->
                <?php if ($active_tab == 'reports'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Training & Certification Reports</h5>
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
                                            <h5>Training Courses by Category</h5>
                                            <canvas id="coursesByCategoryChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>Certifications by Status</h5>
                                            <canvas id="certsByStatusChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="text-center mb-4">Training Compliance Overview</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Department</th>
                                                    <th>Total Personnel</th>
                                                    <th>Fully Compliant</th>
                                                    <th>Partially Compliant</th>
                                                    <th>Non-Compliant</th>
                                                    <th>Compliance Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Operations</td>
                                                    <td>45</td>
                                                    <td>38</td>
                                                    <td>5</td>
                                                    <td>2</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-success" style="width: 84.4%"></div>
                                                        </div>
                                                        <small>84.4%</small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Prevention</td>
                                                    <td>12</td>
                                                    <td>10</td>
                                                    <td>2</td>
                                                    <td>0</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-success" style="width: 83.3%"></div>
                                                        </div>
                                                        <small>83.3%</small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Investigation</td>
                                                    <td>8</td>
                                                    <td>7</td>
                                                    <td>1</td>
                                                    <td>0</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-success" style="width: 87.5%"></div>
                                                        </div>
                                                        <small>87.5%</small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Administration</td>
                                                    <td>15</td>
                                                    <td>12</td>
                                                    <td>3</td>
                                                    <td>0</td>
                                                    <td>
                                                        <div class="progress" style="height: 10px;">
                                                            <div class="progress-bar bg-success" style="width: 80%"></div>
                                                        </div>
                                                        <small>80%</small>
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
    <!-- Add Training Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Training Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addCourseForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course Code</label>
                            <input type="text" class="form-control" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="fire_suppression">Fire Suppression</option>
                                <option value="rescue_operations">Rescue Operations</option>
                                <option value="hazmat">Hazmat Response</option>
                                <option value="ems">Emergency Medical Services</option>
                                <option value="technical_rescue">Technical Rescue</option>
                                <option value="leadership">Leadership & Management</option>
                                <option value="safety">Safety & Prevention</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (Hours)</label>
                            <input type="number" class="form-control" name="duration_hours" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Validity Period (Months)</label>
                            <input type="number" class="form-control" name="validity_months" min="0">
                            <div class="form-text">Leave blank or set to 0 if certification is not required</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prerequisites</label>
                            <input type="text" class="form-control" name="prerequisites">
                            <div class="form-text">Comma-separated list of prerequisite course codes</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Training Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Training Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addSessionForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <?php if ($course['status'] == 'active'): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Session Code</label>
                            <input type="text" class="form-control" name="session_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Session Title</label>
                            <input type="text" class="form-control" name="title" required>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Instructor</label>
                            <input type="text" class="form-control" name="instructor" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maximum Participants</label>
                            <input type="number" class="form-control" name="max_participants" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Certification Modal -->
    <div class="modal fade" id="addCertificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Certification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addCertificationForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Personnel</label>
                            <select class="form-select" name="user_id" required>
                                <option value="">Select Personnel</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Certification Number</label>
                            <input type="text" class="form-control" name="certification_number">
                            <div class="form-text">Leave blank to auto-generate</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Issue Date</label>
                                    <input type="date" class="form-control" name="issue_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" name="expiry_date">
                                    <div class="form-text">Leave blank if certification doesn't expire</div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issuing Authority</label>
                            <input type="text" class="form-control" name="issuing_authority" value="Quezon City Fire Department">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="revoked">Revoked</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Certification</button>
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
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class='bx bxs-book'></i> Add Training Course
                        </button>
                        <button class="btn btn-warning btn-icon" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                            <i class='bx bxs-calendar'></i> Schedule Session
                        </button>
                        <button class="btn btn-info btn-icon" data-bs-toggle="modal" data-bs-target="#addCertificationModal">
                            <i class='bx bxs-certificate'></i> Add Certification
                        </button>
                        <button class="btn btn-success btn-icon">
                            <i class='bx bxs-report'></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Course Details Modal -->
    <div class="modal fade" id="viewCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Course Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="courseDetailsContent">
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
            // Courses by Category Chart
            var categoryCtx = document.getElementById('coursesByCategoryChart').getContext('2d');
            
            // Count courses by category
            var categoryCounts = {};
            <?php foreach ($courses as $course): ?>
                if (categoryCounts['<?php echo $course['category']; ?>']) {
                    categoryCounts['<?php echo $course['category']; ?>']++;
                } else {
                    categoryCounts['<?php echo $course['category']; ?>'] = 1;
                }
            <?php endforeach; ?>
            
            var categoryLabels = Object.keys(categoryCounts);
            var categoryData = Object.values(categoryCounts);
            
            var categoryChart = new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: [
                            '#1e88e5', '#4caf50', '#2196f3', '#3f51b5', '#795548', '#9e9e9e', '#ff9800'
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

            // Certifications by Status Chart
            var statusCtx = document.getElementById('certsByStatusChart').getContext('2d');
            var statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Expired', 'Revoked'],
                    datasets: [{
                        data: [<?php echo $active_certifications; ?>, <?php echo $expired_certifications; ?>, <?php echo $revoked_certifications; ?>],
                        backgroundColor: ['#28a745', '#e53935', '#64748b'],
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

        // View course details
        document.querySelectorAll('.view-course').forEach(button => {
            button.addEventListener('click', function() {
                const courseId = this.getAttribute('data-id');
                document.getElementById('viewCourseModal').querySelector('.modal-title').textContent = 'Loading...';
                
                // Simulate loading
                setTimeout(() => {
                    document.getElementById('viewCourseModal').querySelector('.modal-title').textContent = 'Course Details';
                    document.getElementById('courseDetailsContent').innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading course details...</p>
                        </div>
                    `;
                    
                    // Simulate AJAX call
                    setTimeout(() => {
                        document.getElementById('courseDetailsContent').innerHTML = `
                            <h6>Course Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Course Code:</strong> FFS-101
                                </div>
                                <div class="col-md-6">
                                    <strong>Course Name:</strong> Firefighter Safety and Survival
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Category:</strong> Fire Suppression
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong> <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Duration:</strong> 16 hours
                                </div>
                                <div class="col-md-6">
                                    <strong>Validity:</strong> 24 months
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Prerequisites:</strong> BLS-CPR, Hazmat Awareness
                            </div>
                            <hr>
                            <h6>Course Description</h6>
                            <p>This course provides firefighters with the knowledge and skills to operate safely and effectively in various emergency situations, with emphasis on personal safety, situational awareness, and survival techniques.</p>
                            <hr>
                            <h6>Upcoming Sessions</h6>
                            <p class="text-muted">No upcoming sessions scheduled</p>
                        `;
                    }, 1000);
                }, 300);
                
                var modal = new bootstrap.Modal(document.getElementById('viewCourseModal'));
                modal.show();
            });
        });

        // Form submissions
        document.getElementById('addCourseForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Training course added successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addCourseModal'));
            modal.hide();
        });

        document.getElementById('addSessionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Training session scheduled successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addSessionModal'));
            modal.hide();
        });

        document.getElementById('addCertificationForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real implementation, you would submit the form via AJAX
            alert('Certification added successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addCertificationModal'));
            modal.hide();
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

        // Filter functionality for courses
        document.getElementById('categoryFilter')?.addEventListener('change', filterCourses);
        document.getElementById('statusFilter')?.addEventListener('change', filterCourses);
        document.getElementById('durationFilter')?.addEventListener('change', filterCourses);
        document.getElementById('sortFilter')?.addEventListener('change', filterCourses);

        function filterCourses() {
            const category = document.getElementById('categoryFilter').value;
            const status = document.getElementById('statusFilter').value;
            const duration = document.getElementById('durationFilter').value;
            const sort = document.getElementById('sortFilter').value;
            
            // In a real implementation, you would make an AJAX request to the server
            // For now, we'll just show a loading state
            const coursesList = document.getElementById('coursesList');
            coursesList.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Filtering courses...</p>
                </div>
            `;
            
            // Simulate filtering delay
            setTimeout(() => {
                // This would be replaced with actual filtered results from the server
                coursesList.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class='bx bx-filter-alt text-muted' style="font-size: 3rem;"></i>
                        <p class="mt-3">Filter functionality would load filtered results here</p>
                        <small>In a real implementation, this would show actual filtered courses</small>
                    </div>
                `;
            }, 1000);
        }

        // API call examples
        function fetchTrainingStats() {
            return fetch(`index.php?api=get_training_stats`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching training stats:', error);
                    // Show error message to user
                    alert('Error loading training statistics. Please try again.');
                });
        }

        function fetchTrainingCourses(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_training_courses&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching training courses:', error);
                    // Show error message to user
                    alert('Error loading training courses. Please try again.');
                });
        }

        function fetchTrainingSessions(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_training_sessions&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching training sessions:', error);
                    // Show error message to user
                    alert('Error loading training sessions. Please try again.');
                });
        }

        function fetchCertifications(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_certifications&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching certifications:', error);
                    // Show error message to user
                    alert('Error loading certifications. Please try again.');
                });
        }

        // Example of using the API functions
        /*
        // Fetch training statistics
        fetchTrainingStats()
            .then(stats => {
                console.log('Training stats:', stats);
                // Update UI with stats
            });
        
        // Fetch courses with filters
        fetchTrainingCourses({
            category: 'fire_suppression',
            status: 'active'
        }).then(courses => {
            console.log('Filtered courses:', courses);
            // Update UI with filtered courses
        });
        
        // Fetch sessions with filters
        fetchTrainingSessions({
            status: 'scheduled',
            start_date: '2023-01-01'
        }).then(sessions => {
            console.log('Filtered sessions:', sessions);
            // Update UI with filtered sessions
        });
        
        // Fetch certifications with filters
        fetchCertifications({
            status: 'active',
            expiring_soon: true
        }).then(certifications => {
            console.log('Filtered certifications:', certifications);
            // Update UI with filtered certifications
        });
        */

        // Form submission handlers for API calls
        document.getElementById('addCourseForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                course_code: this.querySelector('[name="course_code"]').value,
                course_name: this.querySelector('[name="course_name"]').value,
                category: this.querySelector('[name="category"]').value,
                duration_hours: this.querySelector('[name="duration_hours"]').value,
                validity_months: this.querySelector('[name="validity_months"]').value,
                prerequisites: this.querySelector('[name="prerequisites"]').value,
                description: this.querySelector('[name="description"]').value,
                status: this.querySelector('[name="status"]').value
            };
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
            submitBtn.disabled = true;
            
            fetch('index.php?api=add_training_course', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Training course added successfully!');
                    var modal = bootstrap.Modal.getInstance(document.getElementById('addCourseModal'));
                    modal.hide();
                    // Refresh the page or update UI as needed
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding training course. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Similar form submission handlers can be added for:
        // - addSessionForm
        // - addCertificationForm
        // - enrollment forms

    </script>
</body>
</html>