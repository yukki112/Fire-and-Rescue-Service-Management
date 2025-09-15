<?php
session_start();

// Include the Database Manager
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin
$is_admin = false;
try {
    $query = "SELECT is_admin FROM frsm.users WHERE id = ?";
    $user_data = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']]);
    $is_admin = ($user_data['is_admin'] == 1);
} catch (Exception $e) {
    error_log("Fetch user admin status error: " . $e->getMessage());
}

// Set active tab and module for sidebar highlighting
$active_tab = 'system';
$active_module = 'settings';
$active_submodule = '';

// Initialize variables
$error_message = '';
$success_message = '';
$user_data = [];

// Fetch current user data
try {
    $query = "SELECT * FROM frsm.users WHERE id = ?";
    $user_data = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Fetch user data error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update Profile Information
        if (isset($_POST['update_profile'])) {
            $query = "UPDATE frsm.users 
                     SET first_name = ?, last_name = ?, middle_name = ?, email = ?, username = ?
                     WHERE id = ?";
            
            $params = [
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['middle_name'],
                $_POST['email'],
                $_POST['username'],
                $_SESSION['user_id']
            ];
            
            $dbManager->query("frsm", $query, $params);
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $query = "SELECT * FROM frsm.users WHERE id = ?";
            $user_data = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']]);
            
        } elseif (isset($_POST['change_password'])) {
            // Change Password
            // Verify current password
            $query = "SELECT password_hash FROM frsm.users WHERE id = ?";
            $current_hash = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']])['password_hash'];
            
            if (!password_verify($_POST['current_password'], $current_hash)) {
                $error_message = "Current password is incorrect.";
            } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                $error_message = "New passwords do not match.";
            } else {
                // Update password
                $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $query = "UPDATE frsm.users SET password_hash = ? WHERE id = ?";
                $dbManager->query("frsm", $query, [$new_hash, $_SESSION['user_id']]);
                $success_message = "Password changed successfully!";
            }
        } elseif (isset($_POST['update_preferences'])) {
            // Update System Preferences
            $query = "UPDATE frsm.users SET preferences = ? WHERE id = ?";
            $preferences = json_encode([
                'language' => $_POST['language'],
                'timezone' => $_POST['timezone'],
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'sound_alerts' => isset($_POST['sound_alerts']) ? 1 : 0
            ]);
            
            $dbManager->query("frsm", $query, [$preferences, $_SESSION['user_id']]);
            $success_message = "Preferences updated successfully!";
        } elseif (isset($_POST['upload_avatar']) && !empty($_FILES['avatar']['name'])) {
            // Handle avatar upload
            $target_dir = "uploads/avatars/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $imageFileType = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
            $target_file = $target_dir . "user_" . $_SESSION['user_id'] . "." . $imageFileType;
            
            // Check if image file is an actual image
            $check = getimagesize($_FILES["avatar"]["tmp_name"]);
            if ($check === false) {
                $error_message = "File is not an image.";
            } elseif ($_FILES["avatar"]["size"] > 500000) {
                $error_message = "Sorry, your file is too large.";
            } elseif (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
                $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                    // Update user record with avatar path
                    $query = "UPDATE frsm.users SET avatar_path = ? WHERE id = ?";
                    $dbManager->query("frsm", $query, [$target_file, $_SESSION['user_id']]);
                    $success_message = "Avatar updated successfully!";
                    
                    // Refresh user data
                    $query = "SELECT * FROM frsm.users WHERE id = ?";
                    $user_data = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']]);
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Settings update error: " . $e->getMessage());
        $error_message = "An error occurred while updating your settings. Please try again.";
    }
}

// Display success/error messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get user preferences
$preferences = [
    'language' => 'English',
    'timezone' => 'Asia/Manila (UTC+8)',
    'email_notifications' => 1,
    'sound_alerts' => 1
];

try {
    $query = "SELECT preferences FROM frsm.users WHERE id = ?";
    $prefs_data = $dbManager->fetch("frsm", $query, [$_SESSION['user_id']]);
    if (!empty($prefs_data['preferences'])) {
        $user_prefs = json_decode($prefs_data['preferences'], true);
        if ($user_prefs) {
            $preferences = array_merge($preferences, $user_prefs);
        }
    }
} catch (Exception $e) {
    error_log("Fetch preferences error: " . $e->getMessage());
}

// Get system statistics for admin
$system_stats = [];
if ($is_admin) {
    try {
        // User count
        $query = "SELECT COUNT(*) as count FROM frsm.users";
        $user_count = $dbManager->fetch("frsm", $query)['count'];
        
        // Employee count
        $query = "SELECT COUNT(*) as count FROM frsm.employees";
        $employee_count = $dbManager->fetch("frsm", $query)['count'];
        
        // Active sessions
        $query = "SELECT COUNT(*) as count FROM frsm.auth_tokens WHERE expires_at > NOW()";
        $active_sessions = $dbManager->fetch("frsm", $query)['count'];
        
        // Recent activities
        $query = "SELECT COUNT(*) as count FROM frsm.audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $recent_activities = $dbManager->fetch("frsm", $query)['count'];
        
        $system_stats = [
            'users' => $user_count,
            'employees' => $employee_count,
            'sessions' => $active_sessions,
            'activities' => $recent_activities
        ];
    } catch (Exception $e) {
        error_log("Fetch system stats error: " . $e->getMessage());
    }
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 100%;
        }
        
        .settings-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section:last-child {
            border-bottom: none;
        }
        
        .settings-section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #0d6efd;
            padding-bottom: 10px;
            border-bottom: 2px solid #0d6efd;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 20px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-avatar:hover .avatar-overlay {
            opacity: 1;
        }
        
        .avatar-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-card .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .admin-section {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .admin-section h5 {
            color: #0d6efd;
            margin-bottom: 15px;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                height: 100vh;
                overflow-y: auto;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: #0d6efd;
                color: white;
                border: none;
                border-radius: 5px;
                padding: 8px 12px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .header-actions {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .settings-section {
                padding: 15px;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
        }
        
        /* Card hover effects */
        .dashboard-card:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .strength-weak {
            background-color: #dc3545;
            width: 25%;
        }
        
        .strength-medium {
            background-color: #fd7e14;
            width: 50%;
        }
        
        .strength-strong {
            background-color: #198754;
            width: 75%;
        }
        
        .strength-very-strong {
            background-color: #0d6efd;
            width: 100%;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuButton">
        <i class='bx bx-menu'></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
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
                        <span>Inspection Location Mapping</span>
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
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse" id="hwrmMenu">
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
                    <a href="../../HWRM/access_and_permissions/ap.php" class="sidebar-dropdown-link">
                    <i class='bx bx-lock-alt'></i>
    <span> Access and Permissions</span>
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
                           <i class='bx bx-file'></i>
        <span>Reporting & Logs</span>
                    </a>
                    
                </div>
                
               <!-- Training and Certification Records -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#tcrMenu" role="button">
                    <i class='bx bx-certification'></i>
                    <span class="text">Training and Certification <br>Records</span>
                </a>
                <div class="sidebar-dropdown collapse" id="tcrMenu">
                    <a href="../personnel_training_profile/ptr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-book-reader'></i>
                        <span>Personnel Training Profiles</span>
                    </a>
                    <a href="../../TCR/training_course_management/tcm.php" class="sidebar-dropdown-link">
                       <i class='bx bx-chalkboard'></i>
        <span>Training Course Management</span>
                    </a>
                    <a href="../../TCR/training_calendar_and_scheduling/tcas.php" class="sidebar-dropdown-link">
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
                     <a href="../../TCR/evaluation_and_assessment_records/eaar.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i>
        <span>Evaluation and Assessment Records</span>
                    </a>
                    <a href="../../TCR/reporting_and_auditlogs/ral.php" class="sidebar-dropdown-link">
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
                     <a href="../../FICR/ reporting_and_analytics/raa.php" class="sidebar-dropdown-link">
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
                    <a href="../incident_summary_documentation/isd.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i>
                        <span>Incident Summary Documentation</span>
                    </a>
                    <a href="../response_timeline_tracking/rtt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i>
                        <span>Response Timeline Tracking</span>
                    </a>
                     <a href="../personnel_and_unit_involvement/paui.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Personnel and Unit Involvement</span>
                    </a>
                     <a href="../cause_and_origin_investigation/caoi.php" class="sidebar-dropdown-link">
                       <i class='bx bx-search-alt'></i>
                        <span>Cause and Origin Investigation</span>
                    </a>
                       <a href="../damage_assessment/da.php" class="sidebar-dropdown-link">
                      <i class='bx bx-building-house'></i>
                        <span>Damage Assessment</span>
                    </a>
                       <a href="../action_review_and_lessons_learned/arall.php" class="sidebar-dropdown-link">
                     <i class='bx bx-refresh'></i>
                        <span>Action Review and Lessons Learned</span>
                    </a>
                     <a href="../report_generation_and_archiving/rgaa.php" class="sidebar-dropdown-link">
                     <i class='bx bx-archive'></i>
                        <span>Report Generation and Archiving</span>
                    </a>
                </div>
                
                   <div class="sidebar-section">System</div>
                
                <a href="settings.php" class="sidebar-link active">
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
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Settings</h1>
                    <p>Manage your account settings and preferences</p>
                </div>
                <?php if ($is_admin): ?>
                <div class="badge bg-danger">Administrator</div>
                <?php endif; ?>
            </div>
            
            <!-- Messages -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle'></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class='bx bx-check-circle'></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Admin Dashboard Section -->
            <?php if ($is_admin): ?>
            <div class="dashboard-card">
                <h3 class="settings-section-title">Administrator Dashboard</h3>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="number"><?php echo $system_stats['users'] ?? 0; ?></div>
                            <div class="label">System Users</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="number"><?php echo $system_stats['employees'] ?? 0; ?></div>
                            <div class="label">Employees</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="number"><?php echo $system_stats['sessions'] ?? 0; ?></div>
                            <div class="label">Active Sessions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="number"><?php echo $system_stats['activities'] ?? 0; ?></div>
                            <div class="label">Recent Activities</div>
                        </div>
                    </div>
                </div>
                
                <div class="admin-section">
                    <h5>Quick Actions</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="user_management.php" class="btn btn-outline-primary">Manage Users</a>
                        <a href="system_logs.php" class="btn btn-outline-primary">View System Logs</a>
                        <a href="backup_restore.php" class="btn btn-outline-primary">Backup & Restore</a>
                        <a href="system_config.php" class="btn btn-outline-primary">System Configuration</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Settings Content -->
            <div class="dashboard-card">
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">Password</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">Preferences</button>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">Admin</button>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Profile Information</h3>
                            
                            <div class="text-center mb-4">
                                <div class="profile-avatar" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                    <?php if (!empty($user_data['avatar_path'])): ?>
                                        <img src="<?php echo $user_data['avatar_path']; ?>" alt="Profile Avatar">
                                    <?php else: ?>
                                        <?php 
                                        $initials = '';
                                        if (!empty($user_data['first_name'])) $initials .= substr($user_data['first_name'], 0, 1);
                                        if (!empty($user_data['last_name'])) $initials .= substr($user_data['last_name'], 0, 1);
                                        echo strtoupper($initials);
                                        ?>
                                    <?php endif; ?>
                                    <div class="avatar-overlay">
                                        <i class='bx bx-camera'></i>
                                    </div>
                                </div>
                                <p class="text-muted">Click on avatar to change</p>
                            </div>
                            
                            <form method="POST" action="settings.php">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">First Name</label>
                                            <input type="text" class="form-control" name="first_name" 
                                                   value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" name="middle_name" 
                                                   value="<?php echo htmlspecialchars($user_data['middle_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" class="form-control" name="last_name" 
                                                   value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Tab -->
                    <div class="tab-pane fade" id="password" role="tabpanel">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Change Password</h3>
                            
                            <form method="POST" action="settings.php">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <small class="form-text text-muted">Use at least 8 characters with a mix of letters, numbers, and symbols.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences" role="tabpanel">
                        <div class="settings-section">
                            <h3 class="settings-section-title">System Preferences</h3>
                            
                            <form method="POST" action="settings.php">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Language</label>
                                            <select class="form-select" name="language">
                                                <option value="English" <?php echo ($preferences['language'] == 'English') ? 'selected' : ''; ?>>English</option>
                                                <option value="Filipino" <?php echo ($preferences['language'] == 'Filipino') ? 'selected' : ''; ?>>Filipino</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Time Zone</label>
                                            <select class="form-select" name="timezone">
                                                <option value="Asia/Manila (UTC+8)" <?php echo ($preferences['timezone'] == 'Asia/Manila (UTC+8)') ? 'selected' : ''; ?>>Asia/Manila (UTC+8)</option>
                                                <option value="UTC+0" <?php echo ($preferences['timezone'] == 'UTC+0') ? 'selected' : ''; ?>>UTC+0</option>
                                                <option value="UTC-5" <?php echo ($preferences['timezone'] == 'UTC-5') ? 'selected' : ''; ?>>UTC-5</option>
                                                <option value="UTC-8" <?php echo ($preferences['timezone'] == 'UTC-8') ? 'selected' : ''; ?>>UTC-8</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notificationsSwitch" name="email_notifications" <?php echo ($preferences['email_notifications'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificationsSwitch">Enable Email Notifications</label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="soundSwitch" name="sound_alerts" <?php echo ($preferences['sound_alerts'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="soundSwitch">Enable Sound Alerts</label>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Admin Tab (only for admins) -->
                    <?php if ($is_admin): ?>
                    <div class="tab-pane fade" id="admin" role="tabpanel">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Administrator Settings</h3>
                            
                            <div class="admin-section">
                                <h5>System Configuration</h5>
                                <form>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">System Name</label>
                                                <input type="text" class="form-control" value="Fire and Rescue Service Management">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">System Version</label>
                                                <input type="text" class="form-control" value="v2.1.0" disabled>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">System Maintenance Mode</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="maintenanceSwitch">
                                            <label class="form-check-label" for="maintenanceSwitch">Enable Maintenance Mode</label>
                                        </div>
                                        <small class="form-text text-muted">When enabled, only administrators can access the system.</small>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary">Save Configuration</button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="admin-section">
                                <h5>Database Management</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary">Backup Database</button>
                                    <button class="btn btn-outline-primary">Optimize Database</button>
                                    <button class="btn btn-outline-danger">Clear Cache</button>
                                </div>
                            </div>
                            
                            <div class="admin-section">
                                <h5>User Management</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="user_management.php" class="btn btn-outline-primary">View All Users</a>
                                    <a href="user_roles.php" class="btn btn-outline-primary">Manage Roles</a>
                                    <a href="audit_logs.php" class="btn btn-outline-primary">View Audit Logs</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Avatar Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="settings.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="avatar" class="form-label">Select an image</label>
                            <input class="form-control" type="file" id="avatar" name="avatar" accept="image/*">
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="upload_avatar" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Password strength indicator
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            // Reset strength bar
            strengthBar.className = 'password-strength';
            
            if (password.length === 0) {
                return;
            }
            
            // Calculate password strength
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            
            // Contains both lower and uppercase characters
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
            
            // Contains numbers
            if (password.match(/([0-9])/)) strength += 1;
            
            // Contains special characters
            if (password.match(/([!,@,#,$,%,^,&,*,?,_,~])/)) strength += 1;
            
            // Update strength bar
            switch(strength) {
                case 0:
                case 1:
                    strengthBar.classList.add('strength-weak');
                    break;
                case 2:
                    strengthBar.classList.add('strength-medium');
                    break;
                case 3:
                    strengthBar.classList.add('strength-strong');
                    break;
                case 4:
                    strengthBar.classList.add('strength-very-strong');
                    break;
            }
        });
        
        // Initialize tabs
        const triggerTabList = document.querySelectorAll('#settingsTabs button')
        triggerTabList.forEach(triggerEl => {
            new bootstrap.Tab(triggerEl)
        })
    </script>
</body>
</html>