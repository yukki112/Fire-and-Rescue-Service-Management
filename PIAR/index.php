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
        case 'get_analysis_reports':
            $filters = [];
            $params = [];

            if (isset($_GET['status'])) {
                $filters[] = "iar.status = ?";
                $params[] = $_GET['status'];
            }

            if (isset($_GET['incident_id'])) {
                $filters[] = "iar.incident_id = ?";
                $params[] = $_GET['incident_id'];
            }

            $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

            $stmt = $pdo->prepare("
                SELECT iar.*, i.incident_type, i.location, i.barangay, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM incident_analysis_reports iar
                LEFT JOIN incidents i ON iar.incident_id = i.id
                LEFT JOIN users u ON iar.created_by = u.id
                $whereClause 
                ORDER BY iar.created_at DESC
            ");
            $stmt->execute($params);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($reports);
            exit;
            
        case 'save_analysis_report':
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

            if (!isset($data['incident_id']) || !isset($data['report_title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            try {
                if (isset($data['id']) && !empty($data['id'])) {
                    // Update existing report
                    $stmt = $pdo->prepare("
                        UPDATE incident_analysis_reports 
                        SET report_title = ?, incident_summary = ?, response_timeline = ?, 
                            personnel_involved = ?, units_involved = ?, cause_investigation = ?,
                            origin_investigation = ?, damage_assessment = ?, lessons_learned = ?,
                            recommendations = ?, status = ?, updated_at = NOW()
                        WHERE id = ? AND created_by = ?
                    ");
                    $stmt->execute([
                        $data['report_title'],
                        $data['incident_summary'] ?? '',
                        $data['response_timeline'] ?? '',
                        $data['personnel_involved'] ?? '',
                        $data['units_involved'] ?? '',
                        $data['cause_investigation'] ?? '',
                        $data['origin_investigation'] ?? '',
                        $data['damage_assessment'] ?? '',
                        $data['lessons_learned'] ?? '',
                        $data['recommendations'] ?? '',
                        $data['status'] ?? 'draft',
                        $data['id'],
                        $_SESSION['user_id']
                    ]);
                    
                    $report_id = $data['id'];
                } else {
                    // Create new report
                    $stmt = $pdo->prepare("
                        INSERT INTO incident_analysis_reports 
                        (incident_id, report_title, incident_summary, response_timeline, 
                         personnel_involved, units_involved, cause_investigation, 
                         origin_investigation, damage_assessment, lessons_learned, 
                         recommendations, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['incident_id'],
                        $data['report_title'],
                        $data['incident_summary'] ?? '',
                        $data['response_timeline'] ?? '',
                        $data['personnel_involved'] ?? '',
                        $data['units_involved'] ?? '',
                        $data['cause_investigation'] ?? '',
                        $data['origin_investigation'] ?? '',
                        $data['damage_assessment'] ?? '',
                        $data['lessons_learned'] ?? '',
                        $data['recommendations'] ?? '',
                        $data['status'] ?? 'draft',
                        $_SESSION['user_id']
                    ]);
                    
                    $report_id = $pdo->lastInsertId();
                }
                
                echo json_encode([
                    'success' => true, 
                    'report_id' => $report_id,
                    'message' => 'Report saved successfully'
                ]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_analysis_report':
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

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Report ID required']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM incident_analysis_reports WHERE id = ? AND created_by = ?");
                $stmt->execute([$data['id'], $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Report not found or access denied']);
                }
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_incident_details':
            if (!isset($_GET['incident_id'])) {
                echo json_encode(['error' => 'Incident ID required']);
                exit;
            }

            $incident_id = $_GET['incident_id'];

            // Get incident details
            $stmt = $pdo->prepare("
                SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
                FROM incidents i 
                LEFT JOIN users u ON i.reported_by = u.id 
                WHERE i.id = ?
            ");
            $stmt->execute([$incident_id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get timeline
            $stmt = $pdo->prepare("
                SELECT ia.*, CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
                FROM incident_actions ia 
                LEFT JOIN users u ON ia.performed_by = u.id 
                WHERE ia.incident_id = ? 
                ORDER BY ia.performed_at ASC
            ");
            $stmt->execute([$incident_id]);
            $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get dispatched units
            $stmt = $pdo->prepare("
                SELECT d.*, u.unit_name, u.unit_type, u.station
                FROM dispatches d
                LEFT JOIN units u ON d.unit_id = u.id
                WHERE d.incident_id = ?
            ");
            $stmt->execute([$incident_id]);
            $dispatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'incident' => $incident,
                'timeline' => $timeline,
                'dispatches' => $dispatches
            ]);
            exit;
            
        case 'export_analysis_report':
            if (!isset($_GET['id'])) {
                echo json_encode(['error' => 'Report ID required']);
                exit;
            }

            $report_id = $_GET['id'];

            $stmt = $pdo->prepare("
                SELECT iar.*, i.incident_type, i.location, i.barangay, i.incident_date, i.incident_time,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM incident_analysis_reports iar
                LEFT JOIN incidents i ON iar.incident_id = i.id
                LEFT JOIN users u ON iar.created_by = u.id
                WHERE iar.id = ?
            ");
            $stmt->execute([$report_id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                echo json_encode(['error' => 'Report not found']);
                exit;
            }

            // Generate PDF content (this would be replaced with actual PDF generation logic)
            $pdf_content = "POST-INCIDENT ANALYSIS REPORT\n\n";
            $pdf_content .= "Report Title: " . $report['report_title'] . "\n";
            $pdf_content .= "Incident: " . $report['incident_type'] . " at " . $report['location'] . ", " . $report['barangay'] . "\n";
            $pdf_content .= "Incident Date: " . $report['incident_date'] . " " . $report['incident_time'] . "\n";
            $pdf_content .= "Created By: " . $report['created_by_name'] . "\n";
            $pdf_content .= "Created On: " . $report['created_at'] . "\n\n";
            
            $pdf_content .= "INCIDENT SUMMARY:\n" . $report['incident_summary'] . "\n\n";
            $pdf_content .= "RESPONSE TIMELINE:\n" . $report['response_timeline'] . "\n\n";
            $pdf_content .= "PERSONNEL INVOLVED:\n" . $report['personnel_involved'] . "\n\n";
            $pdf_content .= "UNITS INVOLVED:\n" . $report['units_involved'] . "\n\n";
            $pdf_content .= "CAUSE INVESTIGATION:\n" . $report['cause_investigation'] . "\n\n";
            $pdf_content .= "ORIGIN INVESTIGATION:\n" . $report['origin_investigation'] . "\n\n";
            $pdf_content .= "DAMAGE ASSESSMENT:\n" . $report['damage_assessment'] . "\n\n";
            $pdf_content .= "LESSONS LEARNED:\n" . $report['lessons_learned'] . "\n\n";
            $pdf_content .= "RECOMENDATIONS:\n" . $report['recommendations'] . "\n";

            echo json_encode([
                'success' => true,
                'report' => $report,
                'pdf_content' => $pdf_content
            ]);
            exit;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'reports';

// Get resolved incidents for analysis
$stmt = $pdo->prepare("
    SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) as reported_by_name
    FROM incidents i 
    LEFT JOIN users u ON i.reported_by = u.id 
    WHERE i.status = 'resolved'
    ORDER BY i.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$resolved_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get analysis reports
$stmt = $pdo->prepare("
    SELECT iar.*, i.incident_type, i.location, i.barangay, 
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM incident_analysis_reports iar
    LEFT JOIN incidents i ON iar.incident_id = i.id
    LEFT JOIN users u ON iar.created_by = u.id
    ORDER BY iar.created_at DESC 
    LIMIT 20
");
$stmt->execute();
$analysis_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_reports = count($analysis_reports);
$draft_reports = 0;
$submitted_reports = 0;
$approved_reports = 0;

foreach ($analysis_reports as $report) {
    if ($report['status'] == 'draft') $draft_reports++;
    if ($report['status'] == 'submitted') $submitted_reports++;
    if ($report['status'] == 'approved') $approved_reports++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Post-Incident Analysis and Reporting</title>
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
        
        .stat-card.total::before { background: var(--primary-gradient); }
        .stat-card.draft::before { background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%); }
        .stat-card.submitted::before { background: linear-gradient(135deg, #1e88e5 0%, #64b5f6 100%); }
        .stat-card.approved::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        
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
        
        .stat-card.draft .stat-icon { 
            background: rgba(255, 152, 0, 0.1); 
            color: var(--warning); 
        }
        
        .stat-card.submitted .stat-icon { 
            background: rgba(30, 136, 229, 0.1); 
            color: var(--primary); 
        }
        
        .stat-card.approved .stat-icon { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success); 
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
        
        .detail-warning { color: var(--warning); }
        .detail-primary { color: var(--primary); }
        .detail-success { color: var(--success); }
        
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
        
        .status-draft { background-color: var(--warning); }
        .status-submitted { background-color: var(--primary); }
        .status-approved { background-color: var(--success); }
        .status-archived { background-color: var(--secondary); }
        
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
        
        /* Report cards */
        .report-card {
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
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
            font-weight: 600;
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
                    <small>Post-Incident Analysis</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="index.php?tab=reports" class="sidebar-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                    <i class='bx bxs-report'></i>
                    <span class="text">Analysis Reports</span>
                </a>
                <a href="index.php?tab=incidents" class="sidebar-link <?php echo $active_tab == 'incidents' ? 'active' : ''; ?>">
                    <i class='bx bxs-detail'></i>
                    <span class="text">Resolved Incidents</span>
                </a>
                <a href="index.php?tab=create" class="sidebar-link <?php echo $active_tab == 'create' ? 'active' : ''; ?>">
                    <i class='bx bx-plus-circle'></i>
                    <span class="text">Create Report</span>
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
                <a href="../TCR/index.php" class="sidebar-link">
                    <i class='bx bxs-certification'></i>
                    <span class="text">Training and Certification Records</span>
                </a>
                <a href="../FICR/index.php" class="sidebar-link">
                    <i class='bx bxs-check-shield'></i>
                    <span class="text">Fire Inspection and Compliance Records</span>
                </a>
                <a href="index.php" class="sidebar-link active">
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
                    <h1>Post-Incident Analysis and Reporting</h1>
                    <p>Analyze resolved incidents and generate detailed reports</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box me-3">
                        <input type="text" class="form-control" placeholder="Search reports...">
                    </div>
                    
                    <div class="notification-dropdown dropdown me-2">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-bell'></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">2 reports need review</a></li>
                            <li><a class="dropdown-item" href="#">5 new incidents resolved</a></li>
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
                <div class="stat-card total animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-file'></i>
                    </div>
                    <div class="stat-value"><?php echo $total_reports; ?></div>
                    <div class="stat-label">Total Analysis Reports</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-time-five'></i>
                            <span>Last 30 days</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-trending-up'></i>
                            <span>+12% this month</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card draft animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-edit'></i>
                    </div>
                    <div class="stat-value"><?php echo $draft_reports; ?></div>
                    <div class="stat-label">Draft Reports</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-warning">
                            <i class='bx bxs-time'></i>
                            <span><?php echo $total_reports > 0 ? round(($draft_reports / $total_reports) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-alarm'></i>
                            <span>Needs Completion</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card submitted animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-send'></i>
                    </div>
                    <div class="stat-value"><?php echo $submitted_reports; ?></div>
                    <div class="stat-label">Submitted Reports</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-primary">
                            <i class='bx bxs-hourglass'></i>
                            <span><?php echo $total_reports > 0 ? round(($submitted_reports / $total_reports) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-check-circle'></i>
                            <span>Awaiting Review</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card approved animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-check-circle'></i>
                    </div>
                    <div class="stat-value"><?php echo $approved_reports; ?></div>
                    <div class="stat-label">Approved Reports</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-check-square'></i>
                            <span><?php echo $total_reports > 0 ? round(($approved_reports / $total_reports) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-file'></i>
                            <span>Completed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-tabs">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=reports" class="nav-link <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
                            <i class='bx bxs-report'></i> Analysis Reports
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=incidents" class="nav-link <?php echo $active_tab == 'incidents' ? 'active' : ''; ?>">
                            <i class='bx bxs-detail'></i> Resolved Incidents
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=create" class="nav-link <?php echo $active_tab == 'create' ? 'active' : ''; ?>">
                            <i class='bx bx-plus-circle'></i> Create Report
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

                <!-- Reports Tab -->
                <?php if ($active_tab == 'reports'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Incident Analysis Reports</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterReportsModal">
                                <i class='bx bx-filter'></i> Filter Reports
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Report Title</th>
                                            <th>Incident</th>
                                            <th>Location</th>
                                            <th>Created By</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analysis_reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['report_title']); ?></td>
                                                <td><?php echo htmlspecialchars($report['incident_type']); ?></td>
                                                <td><?php echo htmlspecialchars($report['barangay']); ?></td>
                                                <td><?php echo htmlspecialchars($report['created_by_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $report['status'] == 'draft' ? 'warning' : 
                                                             ($report['status'] == 'submitted' ? 'primary' : 
                                                             ($report['status'] == 'approved' ? 'success' : 'secondary')); 
                                                    ?>">
                                                        <span class="status-indicator status-<?php echo $report['status']; ?>"></span>
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary view-report" data-id="<?php echo $report['id']; ?>">
                                                            <i class='bx bxs-show'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary edit-report" data-id="<?php echo $report['id']; ?>">
                                                            <i class='bx bxs-edit'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success export-report" data-id="<?php echo $report['id']; ?>">
                                                            <i class='bx bxs-download'></i>
                                                        </button>
                                                        <?php if ($report['status'] == 'draft'): ?>
                                                            <button class="btn btn-sm btn-outline-danger delete-report" data-id="<?php echo $report['id']; ?>">
                                                                <i class='bx bxs-trash'></i>
                                                            </button>
                                                        <?php endif; ?>
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

                <!-- Incidents Tab -->
                <?php if ($active_tab == 'incidents'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Resolved Incidents</h5>
                            <div class="d-flex">
                                <select class="form-select form-select-sm me-2" id="incidentTypeFilter">
                                    <option value="">All Types</option>
                                    <option value="fire">Fire</option>
                                    <option value="medical">Medical</option>
                                    <option value="rescue">Rescue</option>
                                    <option value="hazard">Hazard</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class='bx bx-filter'></i> Apply
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Incident ID</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Barangay</th>
                                            <th>Date & Time</th>
                                            <th>Reported By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resolved_incidents as $incident): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($incident['incident_type'])); ?></td>
                                                <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                <td><?php echo htmlspecialchars($incident['barangay']); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($incident['incident_date'] . ' ' . $incident['incident_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($incident['reported_by_name']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-incident" data-id="<?php echo $incident['id']; ?>">
                                                        <i class='bx bxs-show'></i> Details
                                                    </button>
                                                    <button class="btn btn-sm btn-primary create-analysis" data-id="<?php echo $incident['id']; ?>">
                                                        <i class='bx bx-plus'></i> Create Analysis
                                                    </button>
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

                <!-- Create Report Tab -->
                <?php if ($active_tab == 'create'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Create New Analysis Report</h5>
                            <div>
                                <button class="btn btn-outline-secondary btn-sm me-2" id="saveDraftBtn">
                                    <i class='bx bxs-save'></i> Save Draft
                                </button>
                                <button class="btn btn-primary btn-sm" id="submitReportBtn">
                                    <i class='bx bxs-send'></i> Submit Report
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="analysisReportForm">
                                <input type="hidden" name="id" id="reportId">
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Report Title</label>
                                            <input type="text" class="form-control" name="report_title" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Incident</label>
                                            <select class="form-select" name="incident_id" id="incidentSelect" required>
                                                <option value="">Select Incident</option>
                                                <?php foreach ($resolved_incidents as $incident): ?>
                                                    <option value="<?php echo $incident['id']; ?>">
                                                        #<?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?> - 
                                                        <?php echo htmlspecialchars(ucfirst($incident['incident_type'])); ?> - 
                                                        <?php echo htmlspecialchars($incident['barangay']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Incident Summary</label>
                                    <textarea class="form-control" name="incident_summary" rows="4" placeholder="Provide a comprehensive summary of the incident..."></textarea>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Response Timeline</label>
                                            <textarea class="form-control" name="response_timeline" rows="6" placeholder="Detail the timeline of response actions..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Personnel Involved</label>
                                            <textarea class="form-control" name="personnel_involved" rows="3" placeholder="List personnel involved in the response..."></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Units Involved</label>
                                            <textarea class="form-control" name="units_involved" rows="3" placeholder="List units and equipment used..."></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cause Investigation</label>
                                            <textarea class="form-control" name="cause_investigation" rows="4" placeholder="Detail the investigation into the cause of the incident..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Origin Investigation</label>
                                            <textarea class="form-control" name="origin_investigation" rows="4" placeholder="Detail the investigation into the origin of the incident..."></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Damage Assessment</label>
                                    <textarea class="form-control" name="damage_assessment" rows="3" placeholder="Assess the damage caused by the incident..."></textarea>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Lessons Learned</label>
                                            <textarea class="form-control" name="lessons_learned" rows="4" placeholder="What lessons were learned from this incident?..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Recommendations</label>
                                            <textarea class="form-control" name="recommendations" rows="4" placeholder="What recommendations do you have for improvement?..."></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Report Status</label>
                                    <select class="form-select" name="status" id="reportStatus">
                                        <option value="draft">Draft</option>
                                        <option value="submitted">Submitted for Review</option>
                                        <option value="approved">Approved</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="index.php?tab=create" class="floating-btn">
        <i class='bx bx-plus'></i>
    </a>

    <!-- Modals -->
    <!-- Filter Reports Modal -->
    <div class="modal fade" id="filterReportsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Incident Type</label>
                        <select class="form-select" id="filterIncidentType">
                            <option value="">All Types</option>
                            <option value="fire">Fire</option>
                            <option value="medical">Medical</option>
                            <option value="rescue">Rescue</option>
                            <option value="hazard">Hazard</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="filterDateFrom">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" id="filterDateTo">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Analysis Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="reportDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Edit Report</button>
                    <button type="button" class="btn btn-success">Export PDF</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Incident Modal -->
    <div class="modal fade" id="viewIncidentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incident Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="incidentDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="createReportFromIncident">Create Analysis Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this analysis report? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Report</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide loading overlay when page is loaded
        window.addEventListener('load', function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        });

        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // View report details
        document.querySelectorAll('.view-report').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-id');
                document.getElementById('viewReportModal').querySelector('.modal-title').textContent = 'Loading...';
                
                // Simulate loading
                setTimeout(() => {
                    document.getElementById('viewReportModal').querySelector('.modal-title').textContent = 'Analysis Report Details';
                    document.getElementById('reportDetailsContent').innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading report details...</p>
                        </div>
                    `;
                    
                    // Simulate AJAX call
                    setTimeout(() => {
                        document.getElementById('reportDetailsContent').innerHTML = `
                            <h6>Report Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <strong>Title:</strong> Sample Incident Analysis Report
                                </div>
                                <div class="col-md-4">
                                    <strong>Status:</strong> <span class="badge bg-success">Approved</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Incident:</strong> Fire at Commercial Building
                                </div>
                                <div class="col-md-6">
                                    <strong>Location:</strong> Barangay 1, Quezon City
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Created By:</strong> John Doe
                                </div>
                                <div class="col-md-6">
                                    <strong>Date:</strong> Jan 15, 2023
                                </div>
                            </div>
                            <hr>
                            <h6>Incident Summary</h6>
                            <p>A fire broke out on the 3rd floor of a commercial building due to electrical short circuit. The fire was contained within 2 hours with no casualties.</p>
                            <hr>
                            <h6>Response Timeline</h6>
                            <p>18:05 - Alarm received<br>
                            18:12 - First unit dispatched<br>
                            18:20 - First unit on scene<br>
                            18:45 - Fire declared under control<br>
                            20:00 - All units cleared scene</p>
                            <hr>
                            <h6>Recommendations</h6>
                            <p>1. Regular electrical inspection of commercial buildings<br>
                            2. Improved access routes for fire trucks in the area<br>
                            3. Additional training on high-rise firefighting techniques</p>
                        `;
                    }, 1000);
                }, 300);
                
                var modal = new bootstrap.Modal(document.getElementById('viewReportModal'));
                modal.show();
            });
        });

        // View incident details
        document.querySelectorAll('.view-incident').forEach(button => {
            button.addEventListener('click', function() {
                const incidentId = this.getAttribute('data-id');
                document.getElementById('viewIncidentModal').querySelector('.modal-title').textContent = 'Loading...';
                
                // Simulate loading
                setTimeout(() => {
                    document.getElementById('viewIncidentModal').querySelector('.modal-title').textContent = 'Incident Details';
                    document.getElementById('incidentDetailsContent').innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading incident details...</p>
                        </div>
                    `;
                    
                    // Simulate AJAX call
                    setTimeout(() => {
                        document.getElementById('incidentDetailsContent').innerHTML = `
                            <h6>Incident Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Incident ID:</strong> #000123
                                </div>
                                <div class="col-md-6">
                                    <strong>Type:</strong> Fire
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Location:</strong> 123 Main Street
                                </div>
                                <div class="col-md-6">
                                    <strong>Barangay:</strong> Barangay 1
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Date & Time:</strong> Jan 15, 2023 18:05
                                </div>
                                <div class="col-md-6">
                                    <strong>Reported By:</strong> John Doe
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong>Description:</strong> Fire on the 3rd floor of a commercial building, smoke visible from windows.
                                </div>
                            </div>
                            <hr>
                            <h6>Response Timeline</h6>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <strong>18:05</strong> - Alarm received
                                </div>
                                <div class="timeline-item">
                                    <strong>18:12</strong> - First unit dispatched
                                </div>
                                <div class="timeline-item">
                                    <strong>18:20</strong> - First unit on scene
                                </div>
                                <div class="timeline-item">
                                    <strong>18:45</strong> - Fire declared under control
                                </div>
                                <div class="timeline-item">
                                    <strong>20:00</strong> - All units cleared scene
                                </div>
                            </div>
                            <hr>
                            <h6>Units Dispatched</h6>
                            <ul>
                                <li>Engine 1 - 5 personnel</li>
                                <li>Ladder 1 - 4 personnel</li>
                                <li>Rescue 1 - 3 personnel</li>
                            </ul>
                        `;
                    }, 1000);
                }, 300);
                
                var modal = new bootstrap.Modal(document.getElementById('viewIncidentModal'));
                modal.show();
            });
        });

        // Create analysis from incident
        document.querySelectorAll('.create-analysis').forEach(button => {
            button.addEventListener('click', function() {
                const incidentId = this.getAttribute('data-id');
                window.location.href = 'index.php?tab=create&incident_id=' + incidentId;
            });
        });

        // Delete report confirmation
        document.querySelectorAll('.delete-report').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-id');
                document.getElementById('confirmDeleteBtn').setAttribute('data-id', reportId);
                
                var modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                modal.show();
            });
        });

        // Confirm delete action
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const reportId = this.getAttribute('data-id');
            
            // Simulate AJAX call
            setTimeout(() => {
                alert('Report deleted successfully!');
                var modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                modal.hide();
                
                // Reload the page to reflect changes
                window.location.reload();
            }, 500);
        });

        // Save draft button
        document.getElementById('saveDraftBtn')?.addEventListener('click', function() {
            document.getElementById('reportStatus').value = 'draft';
            document.getElementById('analysisReportForm').dispatchEvent(new Event('submit'));
        });

        // Submit report button
        document.getElementById('submitReportBtn')?.addEventListener('click', function() {
            document.getElementById('reportStatus').value = 'submitted';
            document.getElementById('analysisReportForm').dispatchEvent(new Event('submit'));
        });

        // Form submission
        document.getElementById('analysisReportForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                submitBtn.disabled = true;
            }
            
            // Simulate AJAX call
            setTimeout(() => {
                alert('Report saved successfully!');
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                
                // Redirect to reports tab
                window.location.href = 'index.php?tab=reports&success=Report+saved+successfully';
            }, 1000);
        });

        // Apply filters
        document.getElementById('applyFiltersBtn')?.addEventListener('click', function() {
            const status = document.getElementById('filterStatus').value;
            const incidentType = document.getElementById('filterIncidentType').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            
            // Simulate filtering
            alert(`Filters applied: Status=${status}, Type=${incidentType}, Date From=${dateFrom}, Date To=${dateTo}`);
            
            var modal = bootstrap.Modal.getInstance(document.getElementById('filterReportsModal'));
            modal.hide();
        });

        // Export report
        document.querySelectorAll('.export-report').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-id');
                
                // Simulate export
                alert(`Exporting report ${reportId} as PDF...`);
                
                // In a real implementation, this would download a PDF file
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

        // API call examples
        function fetchAnalysisReports(filters = {}) {
            // Build query string from filters
            const queryParams = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    queryParams.append(key, filters[key]);
                }
            }
            
            return fetch(`index.php?api=get_analysis_reports&${queryParams.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching analysis reports:', error);
                    // Show error message to user
                    alert('Error loading analysis reports. Please try again.');
                });
        }

        function saveAnalysisReport(reportData) {
            return fetch('index.php?api=save_analysis_report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(reportData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                console.error('Error saving analysis report:', error);
                // Show error message to user
                alert('Error saving analysis report. Please try again.');
            });
        }

        function deleteAnalysisReport(reportId) {
            return fetch('index.php?api=delete_analysis_report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: reportId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                console.error('Error deleting analysis report:', error);
                // Show error message to user
                alert('Error deleting analysis report. Please try again.');
            });
        }

        function exportAnalysisReport(reportId) {
            return fetch(`index.php?api=export_analysis_report&id=${reportId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error exporting analysis report:', error);
                    // Show error message to user
                    alert('Error exporting analysis report. Please try again.');
                });
        }

        // Example of using the API functions
        /*
        // Fetch all analysis reports
        fetchAnalysisReports()
            .then(reports => {
                console.log('Analysis reports:', reports);
                // Update UI with reports
            });
        
        // Fetch reports with filters
        fetchAnalysisReports({
            status: 'approved',
            incident_id: 123
        }).then(reports => {
            console.log('Filtered analysis reports:', reports);
            // Update UI with filtered reports
        });
        
        // Save a report
        saveAnalysisReport({
            incident_id: 123,
            report_title: 'Sample Report',
            incident_summary: 'Summary text...',
            // ... other fields
        }).then(result => {
            console.log('Save result:', result);
            if (result.success) {
                alert('Report saved successfully!');
            }
        });
        
        // Delete a report
        deleteAnalysisReport(456).then(result => {
            console.log('Delete result:', result);
            if (result.success) {
                alert('Report deleted successfully!');
            }
        });
        
        // Export a report
        exportAnalysisReport(789).then(result => {
            console.log('Export result:', result);
            if (result.success) {
                // Download the PDF file
                const blob = new Blob([result.pdf_content], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `analysis-report-${result.report.id}.pdf`;
                a.click();
            }
        });
        */

    </script>
</body>
</html>