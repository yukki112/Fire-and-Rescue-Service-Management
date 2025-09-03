<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'registry';

// Get establishments for dropdowns
$stmt = $pdo->prepare("SELECT id, name FROM establishments ORDER BY name");
$stmt->execute();
$establishments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inspection checklists
$stmt = $pdo->prepare("SELECT id, name FROM inspection_checklists WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for assignment
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent inspections
$stmt = $pdo->prepare("
    SELECT ir.*, e.name as establishment_name, u.first_name, u.last_name 
    FROM inspection_results ir 
    LEFT JOIN establishments e ON ir.establishment_id = e.id 
    LEFT JOIN users u ON ir.inspector_id = u.id 
    ORDER BY ir.inspection_date DESC 
    LIMIT 10
");
$stmt->execute();
$recent_inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent clearances
$stmt = $pdo->prepare("
    SELECT c.*, e.name as establishment_name, u.first_name, u.last_name 
    FROM clearances c 
    LEFT JOIN establishments e ON c.establishment_id = e.id 
    LEFT JOIN users u ON c.issued_by = u.id 
    ORDER BY c.issue_date DESC 
    LIMIT 10
");
$stmt->execute();
$recent_clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$total_inspections = count($recent_inspections);
$compliant_count = 0;
$non_compliant_count = 0;
$scheduled_count = 0;

foreach ($recent_inspections as $inspection) {
    if ($inspection['compliance_status'] == 'compliant') $compliant_count++;
    if ($inspection['compliance_status'] == 'non_compliant') $non_compliant_count++;
    if ($inspection['status'] == 'scheduled') $scheduled_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City - Fire Inspection and Compliance Records</title>
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
        
        .stat-card.scheduled::before { background: var(--primary-gradient); }
        .stat-card.compliant::before { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.non_compliant::before { background: linear-gradient(135deg, #e53935 0%, #ff6b6b 100%); }
        .stat-card.clearances::before { background: linear-gradient(135deg, #06d6a0 0%, #0d47a1 100%); }
        
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
        
        .stat-card.compliant .stat-icon { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success); 
        }
        
        .stat-card.non_compliant .stat-icon { 
            background: rgba(229, 57, 53, 0.1); 
            color: var(--danger); 
        }
        
        .stat-card.clearances .stat-icon { 
            background: rgba(6, 214, 160, 0.1); 
            color: var(--compliant-green); 
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
        
        .status-compliant { background-color: var(--compliant-green); }
        .status-non_compliant { background-color: var(--non-compliant-red); }
        .status-partial_compliant { background-color: var(--pending-yellow); }
        .status-scheduled { background-color: var(--inspection-blue); }
        .status-in_progress { background-color: var(--pending-yellow); }
        .status-completed { background-color: var(--compliant-green); }
        .status-cancelled { background-color: var(--non-compliant-red); }
        
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
        
        /* Inspection cards */
        .inspection-card {
            border-left: 4px solid var(--inspection-blue);
            transition: all 0.3s ease;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .inspection-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .rating-excellent { border-left-color: var(--compliant-green); }
        .rating-good { border-left-color: #28a745; }
        .rating-fair { border-left-color: var(--pending-yellow); }
        .rating-poor { border-left-color: var(--violation-orange); }
        .rating-critical { border-left-color: var(--non-compliant-red); }
        
        /* Checklist items */
        .checklist-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .checklist-item:last-child {
            border-bottom: none;
        }
        
        /* Violation severity */
        .severity-minor { color: #6c757d; }
        .severity-major { color: #fd7e14; }
        .severity-critical { color: #dc3545; }
        
        /* Inspection buttons */
        .btn-inspection {
            background: var(--inspection-blue);
            border-color: var(--inspection-blue);
            color: white;
        }
        
        .btn-inspection:hover {
            background: #14213d;
            border-color: #14213d;
            color: white;
        }
        
        .btn-compliance {
            background: var(--compliant-green);
            border-color: var(--compliant-green);
            color: white;
        }
        
        .btn-compliance:hover {
            background: #05b38a;
            border-color: #05b38a;
            color: white;
        }
        
        .btn-violation {
            background: var(--violation-orange);
            border-color: var(--violation-orange);
            color: white;
        }
        
        .btn-violation:hover {
            background: #e04000;
            border-color: #e04000;
            color: white;
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
                <img src="../img/FRSM.png" alt="Logo">
                <div class="text">
                    Fire and Rescue Services Management
                    <small>(ADMIN)</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="../dashboard.html" class="sidebar-link">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
                
                <div class="sidebar-section mt-4">Modules</div>
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
                <a href="index.php" class="sidebar-link active">
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
                    <h1>Fire Inspection and Compliance Records</h1>
                    <p>Manage fire safety inspections, violations, and compliance tracking</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box me-3">
                        <input type="text" class="form-control" placeholder="Search establishments...">
                    </div>
                    
                    <div class="notification-dropdown dropdown me-2">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bxs-bell'></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">2 inspections scheduled today</a></li>
                            <li><a class="dropdown-item" href="#">1 compliance deadline approaching</a></li>
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
                <div class="stat-card scheduled animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-calendar'></i>
                    </div>
                    <div class="stat-value"><?php echo $scheduled_count; ?></div>
                    <div class="stat-label">Scheduled Inspections</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-time-five'></i>
                            <span>This Week: 2</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-stats'></i>
                            <span>This Month: 8</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card compliant animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-check-circle'></i>
                    </div>
                    <div class="stat-value"><?php echo $compliant_count; ?></div>
                    <div class="stat-label">Compliant Establishments</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-success">
                            <i class='bx bxs-up-arrow'></i>
                            <span>+12% from last month</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-trending-up'></i>
                            <span>Good Compliance</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card non_compliant animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-error-circle'></i>
                    </div>
                    <div class="stat-value"><?php echo $non_compliant_count; ?></div>
                    <div class="stat-label">Non-Compliant</div>
                    <div class="stat-details">
                        <div class="stat-detail detail-warning">
                            <i class='bx bxs-time-five'></i>
                            <span>5 with deadlines</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bx-alarm'></i>
                            <span>Attention Required</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card clearances animate-card">
                    <div class="stat-icon">
                        <i class='bx bxs-certificate'></i>
                    </div>
                    <div class="stat-value"><?php echo count($recent_clearances); ?></div>
                    <div class="stat-label">Clearances Issued</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <i class='bx bxs-calendar'></i>
                            <span>This Month: 15</span>
                        </div>
                        <div class="stat-detail">
                            <i class='bx bxs-time'></i>
                            <span>Renewals: 7</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-tabs">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=registry" class="nav-link <?php echo $active_tab == 'registry' ? 'active' : ''; ?>">
                            <i class='bx bxs-building'></i> Establishment Registry
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=inspections" class="nav-link <?php echo $active_tab == 'inspections' ? 'active' : ''; ?>">
                            <i class='bx bxs-check-shield'></i> Inspections
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=violations" class="nav-link <?php echo $active_tab == 'violations' ? 'active' : ''; ?>">
                            <i class='bx bxs-error'></i> Violations
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="index.php?tab=clearances" class="nav-link <?php echo $active_tab == 'clearances' ? 'active' : ''; ?>">
                            <i class='bx bxs-certificate'></i> Clearances
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

                <!-- Establishment Registry Tab -->
                <?php if ($active_tab == 'registry'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Establishment Registry</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEstablishmentModal">
                                <i class='bx bx-plus'></i> Add Establishment
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Business Type</label>
                                            <select class="form-select" id="businessTypeFilter">
                                                <option value="">All Types</option>
                                                <option value="restaurant">Restaurant</option>
                                                <option value="retail">Retail</option>
                                                <option value="office">Office</option>
                                                <option value="industrial">Industrial</option>
                                                <option value="residential">Residential</option>
                                                <option value="educational">Educational</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Compliance Status</label>
                                            <select class="form-select" id="complianceFilter">
                                                <option value="">All Status</option>
                                                <option value="compliant">Compliant</option>
                                                <option value="non_compliant">Non-Compliant</option>
                                                <option value="partial_compliant">Partial Compliance</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Barangay</label>
                                            <select class="form-select" id="barangayFilter">
                                                <option value="">All Barangays</option>
                                                <option value="barangay1">Barangay 1</option>
                                                <option value="barangay2">Barangay 2</option>
                                                <option value="barangay3">Barangay 3</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Sort By</label>
                                            <select class="form-select" id="sortFilter">
                                                <option value="name_asc">Name (A-Z)</option>
                                                <option value="name_desc">Name (Z-A)</option>
                                                <option value="recent">Most Recent</option>
                                                <option value="compliance">Compliance Status</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Establishment</th>
                                            <th>Business Type</th>
                                            <th>Address</th>
                                            <th>Last Inspection</th>
                                            <th>Compliance Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ABC Restaurant</td>
                                            <td>Restaurant</td>
                                            <td>123 Main St, Barangay 1</td>
                                            <td>Oct 15, 2023</td>
                                            <td><span class="badge bg-success"><span class="status-indicator status-compliant"></span> Compliant</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>XYZ Retail Store</td>
                                            <td>Retail</td>
                                            <td>456 Oak St, Barangay 2</td>
                                            <td>Sep 28, 2023</td>
                                            <td><span class="badge bg-danger"><span class="status-indicator status-non_compliant"></span> Non-Compliant</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>City Office Building</td>
                                            <td>Office</td>
                                            <td>789 Pine St, Barangay 3</td>
                                            <td>Oct 5, 2023</td>
                                            <td><span class="badge bg-warning"><span class="status-indicator status-partial_compliant"></span> Partial Compliance</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Industrial Solutions Inc.</td>
                                            <td>Industrial</td>
                                            <td>101 Factory Rd, Barangay 4</td>
                                            <td>Aug 20, 2023</td>
                                            <td><span class="badge bg-success"><span class="status-indicator status-compliant"></span> Compliant</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Metro Apartments</td>
                                            <td>Residential</td>
                                            <td>202 Residence Ave, Barangay 1</td>
                                            <td>Sep 10, 2023</td>
                                            <td><span class="badge bg-danger"><span class="status-indicator status-non_compliant"></span> Non-Compliant</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
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

                <!-- Inspections Tab -->
                <?php if ($active_tab == 'inspections'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Fire Safety Inspections</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleInspectionModal">
                                <i class='bx bx-plus'></i> Schedule Inspection
                            </button>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills mb-3" id="inspectionsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="scheduled-tab" data-bs-toggle="pill" data-bs-target="#scheduled" type="button" role="tab">Scheduled</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed" type="button" role="tab">Completed</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="overdue-tab" data-bs-toggle="pill" data-bs-target="#overdue" type="button" role="tab">Overdue</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="inspectionsTabContent">
                                <div class="tab-pane fade show active" id="scheduled" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Establishment</th>
                                                    <th>Scheduled Date</th>
                                                    <th>Inspector</th>
                                                    <th>Checklist</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>ABC Restaurant</td>
                                                    <td>Oct 25, 2023</td>
                                                    <td>Inspector Garcia</td>
                                                    <td>Restaurant Checklist</td>
                                                    <td><span class="badge bg-primary"><span class="status-indicator status-scheduled"></span> Scheduled</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary">Start Inspection</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Reschedule</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>XYZ Retail Store</td>
                                                    <td>Oct 26, 2023</td>
                                                    <td>Inspector Reyes</td>
                                                    <td>Retail Checklist</td>
                                                    <td><span class="badge bg-primary"><span class="status-indicator status-scheduled"></span> Scheduled</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary">Start Inspection</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Reschedule</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>City Office Building</td>
                                                    <td>Oct 27, 2023</td>
                                                    <td>Inspector Santos</td>
                                                    <td>Office Checklist</td>
                                                    <td><span class="badge bg-primary"><span class="status-indicator status-scheduled"></span> Scheduled</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary">Start Inspection</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Reschedule</button>
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
                                                    <th>Establishment</th>
                                                    <th>Inspection Date</th>
                                                    <th>Inspector</th>
                                                    <th>Rating</th>
                                                    <th>Compliance</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Industrial Solutions Inc.</td>
                                                    <td>Oct 20, 2023</td>
                                                    <td>Inspector Garcia</td>
                                                    <td><span class="badge bg-success">Excellent</span></td>
                                                    <td><span class="badge bg-success">Compliant</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">View Report</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Metro Apartments</td>
                                                    <td>Oct 18, 2023</td>
                                                    <td>Inspector Reyes</td>
                                                    <td><span class="badge bg-danger">Poor</span></td>
                                                    <td><span class="badge bg-danger">Non-Compliant</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">View Report</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Community Center</td>
                                                    <td>Oct 15, 2023</td>
                                                    <td>Inspector Santos</td>
                                                    <td><span class="badge bg-warning">Fair</span></td>
                                                    <td><span class="badge bg-warning">Partial Compliance</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary">View Report</button>
                                                        <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="overdue" role="tabpanel">
                                    <div class="alert alert-warning">
                                        <i class='bx bx-info-circle'></i> You have 2 overdue inspections that need to be scheduled.
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Establishment</th>
                                                    <th>Original Date</th>
                                                    <th>Days Overdue</th>
                                                    <th>Priority</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Downtown Mall</td>
                                                    <td>Oct 5, 2023</td>
                                                    <td><span class="badge bg-danger">20 days</span></td>
                                                    <td><span class="badge bg-danger">High</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary">Schedule Now</button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Tech Park Building</td>
                                                    <td>Oct 10, 2023</td>
                                                    <td><span class="badge bg-warning">15 days</span></td>
                                                    <td><span class="badge bg-warning">Medium</span></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary">Schedule Now</button>
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

                <!-- Violations Tab -->
                <?php if ($active_tab == 'violations'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Fire Safety Violations</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addViolationModal">
                                <i class='bx bx-plus'></i> Add Violation
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class='bx bx-info-circle'></i> Track and manage fire safety violations and corrective actions.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Establishment</th>
                                            <th>Violation</th>
                                            <th>Severity</th>
                                            <th>Date Reported</th>
                                            <th>Corrective Action</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>XYZ Retail Store</td>
                                            <td>Blocked fire exit</td>
                                            <td><span class="severity-critical">Critical</span></td>
                                            <td>Sep 28, 2023</td>
                                            <td>Clear obstruction immediately</td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-success">Mark Resolved</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Metro Apartments</td>
                                            <td>Expired fire extinguishers</td>
                                            <td><span class="severity-major">Major</span></td>
                                            <td>Sep 10, 2023</td>
                                            <td>Replace all extinguishers</td>
                                            <td><span class="badge bg-success">Resolved</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Edit</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>City Office Building</td>
                                            <td>Faulty smoke detectors</td>
                                            <td><span class="severity-major">Major</span></td>
                                            <td>Oct 5, 2023</td>
                                            <td>Repair or replace detectors</td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-success">Mark Resolved</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Community Center</td>
                                            <td>No emergency lighting</td>
                                            <td><span class="severity-minor">Minor</span></td>
                                            <td>Oct 15, 2023</td>
                                            <td>Install emergency lights</td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-success">Mark Resolved</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Downtown Mall</td>
                                            <td>Inadequate fire alarm system</td>
                                            <td><span class="severity-critical">Critical</span></td>
                                            <td>Oct 5, 2023</td>
                                            <td>Upgrade alarm system</td>
                                            <td><span class="badge bg-danger">Overdue</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-warning">Extend Deadline</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Clearances Tab -->
                <?php if ($active_tab == 'clearances'): ?>
                    <div class="content-card fade-in">
                        <div class="card-header">
                            <h5>Fire Safety Clearances</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#issueClearanceModal">
                                <i class='bx bx-plus'></i> Issue Clearance
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success">
                                <i class='bx bx-info-circle'></i> Fire Safety Clearances certify that establishments comply with fire safety standards.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Establishment</th>
                                            <th>Clearance #</th>
                                            <th>Issue Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>Issued By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ABC Restaurant</td>
                                            <td>FSC-2023-00125</td>
                                            <td>Oct 15, 2023</td>
                                            <td>Oct 15, 2024</td>
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td>Inspector Garcia</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Renew</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Industrial Solutions Inc.</td>
                                            <td>FSC-2023-00126</td>
                                            <td>Oct 20, 2023</td>
                                            <td>Oct 20, 2024</td>
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td>Inspector Garcia</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Renew</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Community Center</td>
                                            <td>FSC-2023-00127</td>
                                            <td>Oct 15, 2023</td>
                                            <td>Oct 15, 2024</td>
                                            <td><span class="badge bg-warning">Expiring Soon</span></td>
                                            <td>Inspector Santos</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Renew</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Tech Park Building</td>
                                            <td>FSC-2022-00987</td>
                                            <td>Nov 10, 2022</td>
                                            <td>Nov 10, 2023</td>
                                            <td><span class="badge bg-danger">Expired</span></td>
                                            <td>Inspector Reyes</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-warning">Notify</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Metro Apartments</td>
                                            <td>FSC-2022-00988</td>
                                            <td>Dec 5, 2022</td>
                                            <td>Dec 5, 2023</td>
                                            <td><span class="badge bg-warning">Expiring Soon</span></td>
                                            <td>Inspector Reyes</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                                <button class="btn btn-sm btn-outline-secondary">Renew</button>
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
                            <h5>Inspection and Compliance Reports</h5>
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
                                            <h5>Compliance Status</h5>
                                            <canvas id="complianceChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5>Violations by Type</h5>
                                            <canvas id="violationsChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="text-center mb-4">Monthly Inspection Overview</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Inspections</th>
                                                    <th>Compliant</th>
                                                    <th>Non-Compliant</th>
                                                    <th>Clearances Issued</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>October 2023</td>
                                                    <td>25</td>
                                                    <td>18</td>
                                                    <td>7</td>
                                                    <td>15</td>
                                                </tr>
                                                <tr>
                                                    <td>September 2023</td>
                                                    <td>32</td>
                                                    <td>22</td>
                                                    <td>10</td>
                                                    <td>18</td>
                                                </tr>
                                                <tr>
                                                    <td>August 2023</td>
                                                    <td>28</td>
                                                    <td>20</td>
                                                    <td>8</td>
                                                    <td>16</td>
                                                </tr>
                                                <tr>
                                                    <td>July 2023</td>
                                                    <td>30</td>
                                                    <td>19</td>
                                                    <td>11</td>
                                                    <td>14</td>
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
    <!-- Add Establishment Modal -->
    <div class="modal fade" id="addEstablishmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Establishment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addEstablishmentForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Establishment Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Business Type</label>
                            <select class="form-select" name="business_type" required>
                                <option value="">Select Type</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="retail">Retail</option>
                                <option value="office">Office</option>
                                <option value="industrial">Industrial</option>
                                <option value="residential">Residential</option>
                                <option value="educational">Educational</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay" required>
                                <option value="">Select Barangay</option>
                                <option value="barangay1">Barangay 1</option>
                                <option value="barangay2">Barangay 2</option>
                                <option value="barangay3">Barangay 3</option>
                                <option value="barangay4">Barangay 4</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Owner/Manager Name</label>
                            <input type="text" class="form-control" name="owner_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Establishment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Inspection Modal -->
    <div class="modal fade" id="scheduleInspectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Inspection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="scheduleInspectionForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Establishment</label>
                            <select class="form-select" name="establishment_id" required>
                                <option value="">Select Establishment</option>
                                <?php foreach ($establishments as $establishment): ?>
                                    <option value="<?php echo $establishment['id']; ?>"><?php echo htmlspecialchars($establishment['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inspection Date</label>
                            <input type="date" class="form-control" name="inspection_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Checklist</label>
                            <select class="form-select" name="checklist_id" required>
                                <option value="">Select Checklist</option>
                                <?php foreach ($checklists as $checklist): ?>
                                    <option value="<?php echo $checklist['id']; ?>"><?php echo htmlspecialchars($checklist['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Inspector</label>
                            <select class="form-select" name="inspector_id" required>
                                <option value="">Select Inspector</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Violation Modal -->
    <div class="modal fade" id="addViolationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Violation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addViolationForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Establishment</label>
                            <select class="form-select" name="establishment_id" required>
                                <option value="">Select Establishment</option>
                                <?php foreach ($establishments as $establishment): ?>
                                    <option value="<?php echo $establishment['id']; ?>"><?php echo htmlspecialchars($establishment['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Violation Type</label>
                            <select class="form-select" name="violation_type" required>
                                <option value="">Select Violation Type</option>
                                <option value="blocked_exit">Blocked Fire Exit</option>
                                <option value="expired_extinguisher">Expired Fire Extinguisher</option>
                                <option value="faulty_alarm">Faulty Fire Alarm</option>
                                <option value="no_emergency_lighting">No Emergency Lighting</option>
                                <option value="storage_violation">Improper Storage</option>
                                <option value="electrical_violation">Electrical Hazard</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="severity" required>
                                <option value="minor">Minor</option>
                                <option value="major">Major</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Corrective Action</label>
                            <textarea class="form-control" name="corrective_action" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="date" class="form-control" name="deadline" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Violation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Issue Clearance Modal -->
    <div class="modal fade" id="issueClearanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Fire Safety Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="issueClearanceForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Establishment</label>
                            <select class="form-select" name="establishment_id" required>
                                <option value="">Select Establishment</option>
                                <?php foreach ($establishments as $establishment): ?>
                                    <option value="<?php echo $establishment['id']; ?>"><?php echo htmlspecialchars($establishment['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clearance Number</label>
                            <input type="text" class="form-control" name="clearance_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issue Date</label>
                            <input type="date" class="form-control" name="issue_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inspection Reference</label>
                            <select class="form-select" name="inspection_id">
                                <option value="">Select Inspection (Optional)</option>
                                <?php foreach ($recent_inspections as $inspection): ?>
                                    <option value="<?php echo $inspection['id']; ?>"><?php echo htmlspecialchars($inspection['establishment_name'] . ' - ' . $inspection['inspection_date']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Issue Clearance</button>
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
                        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addEstablishmentModal">
                            <i class='bx bxs-building'></i> Add Establishment
                        </button>
                        <button class="btn btn-warning btn-icon" data-bs-toggle="modal" data-bs-target="#scheduleInspectionModal">
                            <i class='bx bxs-check-shield'></i> Schedule Inspection
                        </button>
                        <button class="btn btn-danger btn-icon" data-bs-toggle="modal" data-bs-target="#addViolationModal">
                            <i class='bx bxs-error'></i> Add Violation
                        </button>
                        <button class="btn btn-success btn-icon" data-bs-toggle="modal" data-bs-target="#issueClearanceModal">
                            <i class='bx bxs-certificate'></i> Issue Clearance
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

        // Initialize charts if on reports page
        <?php if ($active_tab == 'reports'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Compliance Status Chart
            var complianceCtx = document.getElementById('complianceChart').getContext('2d');
            var complianceChart = new Chart(complianceCtx, {
                type: 'pie',
                data: {
                    labels: ['Compliant', 'Non-Compliant', 'Partial Compliance'],
                    datasets: [{
                        data: [<?php echo $compliant_count; ?>, <?php echo $non_compliant_count; ?>, <?php echo $total_inspections - $compliant_count - $non_compliant_count; ?>],
                        backgroundColor: ['#06d6a0', '#e63946', '#ffd166'],
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

            // Violations by Type Chart
            var violationsCtx = document.getElementById('violationsChart').getContext('2d');
            var violationsChart = new Chart(violationsCtx, {
                type: 'bar',
                data: {
                    labels: ['Blocked Exits', 'Expired Extinguishers', 'Faulty Alarms', 'No Emergency Lighting', 'Electrical Hazards'],
                    datasets: [{
                        label: 'Number of Violations',
                        data: [12, 8, 5, 7, 4],
                        backgroundColor: '#1d3557',
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        <?php endif; ?>

        // Form submissions
        document.getElementById('addEstablishmentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Establishment added successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addEstablishmentModal'));
            modal.hide();
        });

        document.getElementById('scheduleInspectionForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Inspection scheduled successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('scheduleInspectionModal'));
            modal.hide();
        });

        document.getElementById('addViolationForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Violation added successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('addViolationModal'));
            modal.hide();
        });

        document.getElementById('issueClearanceForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Clearance issued successfully!');
            var modal = bootstrap.Modal.getInstance(document.getElementById('issueClearanceModal'));
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
    </script>
</body>
</html>