<?php
session_start();

// Include the Database Manager
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'pss';
$active_submodule = 'notifications_and_alert';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get all active employees
    $employees = $dbManager->fetchAll("frsm", "SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    
    // Get notification types
    $notification_types = $dbManager->fetchAll("pss", "SELECT * FROM notification_types ORDER BY name");
    
    // Get notifications for the logged-in user
    $notifications = $dbManager->fetchAll("pss", 
        "SELECT n.*, nt.name as notification_type_name, nt.icon, nt.color 
         FROM notifications n 
         JOIN notification_types nt ON n.notification_type_id = nt.id 
         WHERE n.user_id = ? 
         ORDER BY n.created_at DESC", 
        [$_SESSION['user_id']]
    );
    
    // For admins, get all notifications
    if ($user['is_admin'] == 1) {
        $all_notifications = $dbManager->fetchAll("pss", 
            "SELECT n.*, nt.name as notification_type_name, nt.icon, nt.color,
                    u.first_name, u.last_name, u.username
             FROM notifications n 
             JOIN notification_types nt ON n.notification_type_id = nt.id 
             JOIN frsm.users u ON n.user_id = u.id 
             ORDER BY n.created_at DESC"
        );
    }
    
    // Mark notifications as read when page loads
    if (!empty($notifications)) {
        $unread_ids = [];
        foreach ($notifications as $notification) {
            if ($notification['is_read'] == 0) {
                $unread_ids[] = $notification['id'];
            }
        }
        
        if (!empty($unread_ids)) {
            $placeholders = implode(',', array_fill(0, count($unread_ids), '?'));
            $dbManager->query("pss", 
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id IN ($placeholders)",
                $unread_ids
            );
        }
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $employees = [];
    $notification_types = [];
    $notifications = [];
    $all_notifications = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        // Send new notification
        $user_id = $_POST['user_id'];
        $notification_type_id = $_POST['notification_type_id'];
        $title = $_POST['title'];
        $message = $_POST['message'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';
        
        try {
            // Insert new notification
            $dbManager->query("pss", 
                "INSERT INTO notifications (user_id, notification_type_id, title, message, priority) 
                 VALUES (?, ?, ?, ?, ?)",
                [$user_id, $notification_type_id, $title, $message, $priority]
            );
            
            $_SESSION['success_message'] = "Notification sent successfully!";
        } catch (Exception $e) {
            error_log("Notification submission error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to send notification. Please try again.";
        }
        
        header("Location: naa.php");
        exit;
    }
    
    if (isset($_POST['clear_notifications'])) {
        // Clear all notifications for user
        try {
            $dbManager->query("pss", 
                "DELETE FROM notifications WHERE user_id = ?",
                [$_SESSION['user_id']]
            );
            
            $_SESSION['success_message'] = "All notifications cleared successfully!";
        } catch (Exception $e) {
            error_log("Notification clear error: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to clear notifications. Please try again.";
        }
        
        header("Location: naa.php");
        exit;
    }
    
    if (isset($_POST['delete_notification'])) {
        // Delete specific notification (admin only)
        if ($user['is_admin'] == 1) {
            $notification_id = $_POST['notification_id'];
            
            try {
                $dbManager->query("pss", 
                    "DELETE FROM notifications WHERE id = ?",
                    [$notification_id]
                );
                
                $_SESSION['success_message'] = "Notification deleted successfully!";
            } catch (Exception $e) {
                error_log("Notification deletion error: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to delete notification. Please try again.";
            }
        }
        
        header("Location: naa.php");
        exit;
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
    <style>
        .notification-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .notification-card {
            border-left: 4px solid #007bff;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .notification-card.unread {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
        }
        .notification-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-body {
            padding: 15px;
        }
        .notification-badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .action-btn {
            padding: 5px 10px;
            margin: 0 2px;
        }
        .priority-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
        .notification-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="img/frsmse.png" alt="QC Logo">
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
                  
                </div>
                

                <!-- Personnel Shift Scheduling -->
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#pssMenu" role="button">
                    <i class='bx bx-calendar-event'></i>
                    <span class="text">Shift Scheduling</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="pssMenu">
                    <a href="../shift_calendar_management/scm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i>
                        <span>Shift Calendar Management</span>
                    </a>
                    <a href="../personel_roster/pr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Personnel Roster</span>
                    </a>
                    <a href="../shift_assignment/sa.php" class="sidebar-dropdown-link">
                          <i class='bx bx-task'></i>
                        <span>Shift Assignment</span>
                    </a>
                      <a href="../leave_and_absence_management/laam.php" class="sidebar-dropdown-link">
                          <i class='bx bx-user-x'></i>
                        <span>Leave and Absence Management</span>
                    </a>
                      <a href="../notifications_and_alert/naa.php" class="sidebar-dropdown-link active">
                           <i class='bx bx-bell'></i>
                        <span>Notifications and Alerts</span>
                    </a>
                     <a href="../reporting_and_logs/ral.php" class="sidebar-dropdown-link">
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
                    <h1>Notifications and Alerts</h1>
                    <p>Manage and view all system notifications and alerts for the Quezon City Fire and Rescue Department.</p>
                </div>
                
                <div class="header-actions">
                    <div class="btn-group">
                        <a href="../dashboard.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back'></i> Back to Dashboard
                        </a>
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
            
            <!-- Notifications Content -->
            <div class="dashboard-content">
                <!-- Send Notification Form (Admin Only) -->
                <?php if ($user['is_admin'] == 1): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="notification-container animate-fade-in">
                            <h5 class="mb-3">Send Notification</h5>
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="user_id" class="form-label">Recipient</label>
                                            <select class="form-select" id="user_id" name="user_id" required>
                                                <option value="">Select User</option>
                                                <?php 
                                                $users = $dbManager->fetchAll("frsm", "SELECT * FROM users ORDER BY first_name, last_name");
                                                foreach ($users as $usr): ?>
                                                <option value="<?php echo $usr['id']; ?>">
                                                    <?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name'] . ' (' . $usr['username'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="notification_type_id" class="form-label">Notification Type</label>
                                            <select class="form-select" id="notification_type_id" name="notification_type_id" required>
                                                <option value="">Select Type</option>
                                                <?php foreach ($notification_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>" data-color="<?php echo $type['color']; ?>" data-icon="<?php echo $type['icon']; ?>">
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority" required>
                                                <option value="low">Low</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="high">High</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="mb-3 w-100">
                                            <button type="submit" name="send_notification" class="btn btn-primary w-100">Send Notification</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="title" name="title" required placeholder="Enter notification title">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message</label>
                                            <textarea class="form-control" id="message" name="message" rows="2" placeholder="Enter notification message" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Notifications Tabs -->
                <div class="row">
                    <div class="col-12">
                        <div class="notification-container animate-fade-in">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">My Notifications</h5>
                                <?php if (count($notifications) > 0): ?>
                                <form method="POST" action="">
                                    <button type="submit" name="clear_notifications" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to clear all notifications?')">
                                        <i class='bx bx-trash'></i> Clear All
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (count($notifications) > 0): ?>
                                <div class="notification-list">
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-card <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                                        <div class="notification-header">
                                            <div class="d-flex align-items-center">
                                                <i class='bx <?php echo $notification['icon']; ?> notification-icon' style="color: <?php echo $notification['color']; ?>"></i>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="priority-badge bg-<?php 
                                                    switch($notification['priority']) {
                                                        case 'low': echo 'info'; break;
                                                        case 'medium': echo 'primary'; break;
                                                        case 'high': echo 'warning'; break;
                                                        case 'urgent': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($notification['priority']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="notification-body">
                                            <p class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-bell-off'></i>
                                    <h5>No notifications found</h5>
                                    <p>You don't have any notifications at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- All Notifications Tab (Admin Only) -->
                <?php if ($user['is_admin'] == 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="notification-container animate-fade-in">
                            <h5 class="mb-3">All Notifications</h5>
                            
                            <?php if (count($all_notifications) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Recipient</th>
                                                <th>Type</th>
                                                <th>Title</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Sent On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_notifications as $notification): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                                        <br><small class="text-muted"><?php echo $notification['username']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge" style="background-color: <?php echo $notification['color']; ?>;">
                                                            <?php echo $notification['notification_type_name']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($notification['title']); ?></td>
                                                    <td>
                                                        <span class="priority-badge bg-<?php 
                                                            switch($notification['priority']) {
                                                                case 'low': echo 'info'; break;
                                                                case 'medium': echo 'primary'; break;
                                                                case 'high': echo 'warning'; break;
                                                                case 'urgent': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($notification['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($notification['is_read'] == 1): ?>
                                                            <span class="badge bg-success">Read</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Unread</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                                    <td>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger action-btn" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                                <i class='bx bx-trash'></i> Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state mt-3">
                                    <i class='bx bx-bell-off'></i>
                                    <h5>No notifications found</h5>
                                    <p>There are no notifications in the system.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toasts after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-fade-out');
                setTimeout(() => toast.remove(), 1000);
            });
        }, 5000);
    </script>
</body>
</html>