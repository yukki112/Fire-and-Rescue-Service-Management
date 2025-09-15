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

// Set active tab and module for sidebar highlighting
$active_tab = 'modules';
$active_module = 'ird';
$active_submodule = 'reporting';

// Get user info
try {
    $user = $dbManager->fetch("frsm", "SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    // Get incidents for reporting
    $incidents = $dbManager->fetchAll("ird", "SELECT * FROM incidents ORDER BY created_at DESC");
    
    // Get report types
    $report_types = [
        'incident_summary' => 'Incident Summary Report',
        'resource_utilization' => 'Resource Utilization Report',
        'response_time' => 'Response Time Analysis',
        'trend_analysis' => 'Trend Analysis Report'
    ];
    
    // Get barangays for filtering
    $barangays = $dbManager->fetchAll("ird", "SELECT DISTINCT barangay FROM incidents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
    
    // Get incident types for filtering
    $incident_types = $dbManager->fetchAll("ird", "SELECT DISTINCT incident_type FROM incidents WHERE incident_type IS NOT NULL AND incident_type != '' ORDER BY incident_type");
    
    // Get existing reports
    $reports = $dbManager->fetchAll("ird", "
        SELECT r.*, u.first_name, u.last_name 
        FROM reports r 
        LEFT JOIN frsm.users u ON r.generated_by = u.id 
        ORDER BY r.created_at DESC
    ");
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $user = ['first_name' => 'User'];
    $incidents = [];
    $report_types = [];
    $barangays = [];
    $incident_types = [];
    $reports = [];
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_report'])) {
            // Generate report
            $query = "INSERT INTO reports (report_type, title, description, start_date, end_date, barangay_filter, incident_type_filter, format, generated_by, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $params = [
                $_POST['report_type'],
                $_POST['title'],
                $_POST['description'],
                $_POST['start_date'],
                $_POST['end_date'],
                !empty($_POST['barangay_filter']) ? $_POST['barangay_filter'] : null,
                !empty($_POST['incident_type_filter']) ? $_POST['incident_type_filter'] : null,
                $_POST['format'],
                $_SESSION['user_id']
            ];
            $dbManager->query("ird", $query, $params);
            
            // Get the inserted report ID
            $report_id = $dbManager->getConnection("ird")->lastInsertId();
            
            // Generate the actual report based on type
            $report_data = generateReportData($_POST['report_type'], $_POST);
            
            // Store report data in session for download/view
            $_SESSION['report_data'] = $report_data;
            $_SESSION['report_format'] = $_POST['format'];
            $_SESSION['report_title'] = $_POST['title'];
            $_SESSION['report_id'] = $report_id;
            
            $_SESSION['success_message'] = "Report generated successfully!";
            header("Location: reporting.php?action=generated&id=" . $report_id);
            exit;
        }
        
        if (isset($_POST['download_report'])) {
            // Handle report download
            $format = $_POST['format'];
            $title = $_POST['title'];
            $data = $_SESSION['report_data'] ?? [];
            
            if ($format === 'pdf') {
                // Generate PDF using TCPDF (if available) or simple HTML
                if (class_exists('TCPDF')) {
                    generateTCPDFReport($data, $title);
                } else {
                    generateSimplePDF($data, $title);
                }
                exit;
            } elseif ($format === 'excel') {
                // Set headers for Excel download
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="' . $title . '.xls"');
                
                // Generate Excel content
                echo generateExcelContent($data, $title);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
    }
}

// Function to generate report data based on type
function generateReportData($report_type, $filters) {
    global $dbManager;
    
    $data = [];
    $start_date = $filters['start_date'];
    $end_date = $filters['end_date'];
    $barangay = $filters['barangay_filter'] ?? null;
    $incident_type = $filters['incident_type_filter'] ?? null;
    
    switch ($report_type) {
        case 'incident_summary':
            $query = "SELECT * FROM incidents WHERE incident_date BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            
            if ($barangay) {
                $query .= " AND barangay = ?";
                $params[] = $barangay;
            }
            
            if ($incident_type) {
                $query .= " AND incident_type = ?";
                $params[] = $incident_type;
            }
            
            $query .= " ORDER BY incident_date, incident_time";
            $data = $dbManager->fetchAll("ird", $query, $params);
            break;
            
        case 'resource_utilization':
            $query = "SELECT u.unit_name, u.unit_type, u.status, COUNT(d.id) as dispatch_count 
                     FROM units u 
                     LEFT JOIN dispatches d ON u.id = d.unit_id AND d.dispatched_at BETWEEN ? AND ?
                     GROUP BY u.id";
            $data = $dbManager->fetchAll("ird", $query, [$start_date, $end_date]);
            break;
            
        case 'response_time':
            $query = "SELECT i.id, i.incident_type, i.barangay, d.dispatched_at, d.arrived_at,
                     TIMESTAMPDIFF(MINUTE, d.dispatched_at, d.arrived_at) as response_time_minutes
                     FROM incidents i 
                     JOIN dispatches d ON i.id = d.incident_id 
                     WHERE d.dispatched_at BETWEEN ? AND ? AND d.arrived_at IS NOT NULL";
            $params = [$start_date, $end_date];
            
            if ($barangay) {
                $query .= " AND i.barangay = ?";
                $params[] = $barangay;
            }
            
            if ($incident_type) {
                $query .= " AND i.incident_type = ?";
                $params[] = $incident_type;
            }
            
            $data = $dbManager->fetchAll("ird", $query, $params);
            break;
            
        case 'trend_analysis':
            $query = "SELECT incident_type, barangay, COUNT(*) as count, 
                     AVG(injuries) as avg_injuries, AVG(fatalities) as avg_fatalities
                     FROM incidents 
                     WHERE incident_date BETWEEN ? AND ?
                     GROUP BY incident_type, barangay";
            $params = [$start_date, $end_date];
            
            if ($barangay) {
                $query .= " HAVING barangay = ?";
                $params[] = $barangay;
            }
            
            $data = $dbManager->fetchAll("ird", $query, $params);
            break;
    }
    
    return $data;
}

// Function to generate PDF using TCPDF
function generateTCPDFReport($data, $title) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Quezon City FRSM');
    $pdf->SetAuthor('Quezon City FRSM');
    $pdf->SetTitle($title);
    $pdf->SetSubject('Incident Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(10);
    
    if (!empty($data)) {
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Get column headers
        $headers = array_keys($data[0]);
        
        // Create table header
        foreach ($headers as $header) {
            $pdf->Cell(40, 7, $header, 1);
        }
        $pdf->Ln();
        
        // Create table rows
        $pdf->SetFont('helvetica', '', 8);
        foreach ($data as $row) {
            foreach ($row as $value) {
                $pdf->Cell(40, 6, $value, 1);
            }
            $pdf->Ln();
        }
    } else {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'No data available for this report', 0, 1, 'C');
    }
    
    // Output PDF
    $pdf->Output($title . '.pdf', 'D');
}

// Function to generate simple PDF (fallback if TCPDF not available)
function generateSimplePDF($data, $title) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
    
    // Simple PDF generation using FPDF-like approach
    $pdf_content = "%PDF-1.4\n";
    $pdf_content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $pdf_content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $pdf_content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
    $pdf_content .= "4 0 obj\n<< /Length 100 >>\nstream\nBT /F1 12 Tf 72 720 Td (" . $title . ") Tj ET\n";
    
    $y = 700;
    if (!empty($data)) {
        // Headers
        $headers = array_keys($data[0]);
        $pdf_content .= "BT /F1 10 Tf 72 " . $y . " Td (" . implode("   |   ", $headers) . ") Tj ET\n";
        $y -= 20;
        
        // Data rows
        foreach ($data as $row) {
            $row_text = implode(" | ", $row);
            $pdf_content .= "BT /F1 8 Tf 72 " . $y . " Td (" . $row_text . ") Tj ET\n";
            $y -= 15;
            if ($y < 100) break;
        }
    } else {
        $pdf_content .= "BT /F1 10 Tf 72 650 Td (No data available) Tj ET\n";
    }
    
    $pdf_content .= "endstream\nendobj\n";
    $pdf_content .= "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000234 00000 n \n";
    $pdf_content .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . strlen($pdf_content) . "\n%%EOF";
    
    echo $pdf_content;
}

// Function to generate Excel content
function generateExcelContent($data, $title) {
    $content = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">";
    $content .= "<head><meta charset=\"UTF-8\"><title>" . $title . "</title></head>";
    $content .= "<body>";
    $content .= "<h1>" . $title . "</h1>";
    $content .= "<table border=\"1\">";
    
    if (!empty($data)) {
        // Header row
        $content .= "<tr>";
        foreach (array_keys($data[0]) as $key) {
            $content .= "<th>" . htmlspecialchars($key) . "</th>";
        }
        $content .= "</tr>";
        
        // Data rows
        foreach ($data as $row) {
            $content .= "<tr>";
            foreach ($row as $value) {
                $content .= "<td>" . htmlspecialchars($value) . "</td>";
            }
            $content .= "</tr>";
        }
    } else {
        $content .= "<tr><td colspan=\"10\">No data available</td></tr>";
    }
    
    $content .= "</table>";
    $content .= "</body></html>";
    
    return $content;
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Check if we have a recently generated report
$recent_report_id = $_GET['id'] ?? null;
$is_generated_view = isset($_GET['action']) && $_GET['action'] === 'generated';
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
        .reporting-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.4rem;
        }
        .card-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .report-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .report-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .report-type {
            font-size: 0.8rem;
            padding: 0.3em 0.6em;
            border-radius: 4px;
            background-color: #e9ecef;
            color: #495057;
        }
        .report-content {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .report-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .filter-badge {
            font-size: 0.7rem;
            padding: 0.2em 0.4em;
            margin-right: 5px;
        }
        .highlight-new {
            background-color: #f8fff8;
            border-left: 4px solid #28a745;
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
                <a class="sidebar-link dropdown-toggle active" data-bs-toggle="collapse" href="#irdMenu" role="button">
                    <i class='bx bxs-report'></i>
                    <span class="text">Incident Response Dispatch</span>
                </a>
            
                <div class="sidebar-dropdown collapse show" id="irdMenu">
                   
                    <a href="../incident_intake/ii.php" class="sidebar-dropdown-link">
                        <i class='bx bx-plus-medical'></i>
                        <span>Incident Intake</span>
                    </a>
                    <a href="../incident_location_mapping/ilm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-map'></i>
                        <span>Incident Location Mapping</span>
                    </a>
                    <a href="../unit_assignment/ua.php" class="sidebar-dropdown-link">
                        <i class='bx bx-group'></i>
                        <span>Unit Assignment</span>
                    </a>
                    <a href="../communication/comm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-message-rounded'></i>
                        <span>Communication</span>
                    </a>
                    <a href="../status_monitoring/sm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i>
                        <span>Status Monitoring</span>
                    </a>
                    <a href="../reporting/report.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-file'></i>
                        <span>Reporting</span>
                    </a>
                </div>
                
                  <!-- Fire Station Inventory & Equipment Tracking -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#fsietMenu" role="button">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Inventory & Equipment</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="fsietMenu">
                    <a href="../../FSIET/inventory_management/im.php" class="sidebar-dropdown-link">
                        <i class='bx bx-package'></i>
                        <span>Inventory Management</span>
                    </a>
                    <a href="../../equipment_tracking/et.php" class="sidebar-dropdown-link">
                        <i class='bx bx-wrench'></i>
                        <span>Equipment Location Tracking</span>
                    </a>
                    <a href="../../FSIET/maintenance_inspection_scheduler/mis.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Maintenance & Inspection Scheduler</span>
                    </a>
                     <a href="../../FSIET/repair_management/rm.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Repair & Out-of-Service Management</span>
                    </a>
                    <a href="../../FSIET/inventory_reports_auditlogs/iral.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i>
                        <span>Inventory Reports & Audit Logs</span>
                    </a>
                    
                </div>
                
                
                  <!-- Hydrant and Water Resource Mapping -->
                <a class="sidebar-link dropdown-toggle" data-bs-toggle="collapse" href="#hwrmMenu" role="button">
                    <i class='bx bx-water'></i>
                    <span class="text">Hydrant & Water Resources</span>
                </a>
                <div class="sidebar-dropdown collapse show" id="hwrmMenu">
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
                            <i class='bx bx-bell'></i>
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
                    <h1>Reporting</h1>
                    <p>Generate and manage incident reports and analytics.</p>
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
            
            <!-- Reporting Content -->
            <div class="dashboard-content">
                <!-- Report Generator Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="reporting-container animate-fade-in">
                            <form method="POST" action="">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class='bx bx-file'></i>
                                        Generate New Report
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Report Type <span class="text-danger">*</span></label>
                                                <select class="form-select" name="report_type" required>
                                                    <option value="">Select a report type...</option>
                                                    <?php foreach ($report_types as $key => $type): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == $key) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Report Title <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="title" required 
                                                       placeholder="Enter report title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2" 
                                                  placeholder="Enter report description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="start_date" required
                                                       value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d', strtotime('-1 month')); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="end_date" required
                                                       value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Barangay Filter</label>
                                                <select class="form-select" name="barangay_filter">
                                                    <option value="">All Barangays</option>
                                                    <?php foreach ($barangays as $barangay): ?>
                                                        <option value="<?php echo htmlspecialchars($barangay['barangay']); ?>" <?php echo (isset($_POST['barangay_filter']) && $_POST['barangay_filter'] == $barangay['barangay']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($barangay['barangay']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Incident Type Filter</label>
                                                <select class="form-select" name="incident_type_filter">
                                                    <option value="">All Types</option>
                                                    <?php foreach ($incident_types as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type['incident_type']); ?>" <?php echo (isset($_POST['incident_type_filter']) && $_POST['incident_type_filter'] == $type['incident_type']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type['incident_type']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Output Format <span class="text-danger">*</span></label>
                                                <select class="form-select" name="format" required>
                                                    <option value="pdf" <?php echo (isset($_POST['format']) && $_POST['format'] == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                                                    <option value="excel" <?php echo (isset($_POST['format']) && $_POST['format'] == 'excel') ? 'selected' : ''; ?>>Excel</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="generate_report" class="btn btn-primary">
                                            <i class='bx bx-plus'></i> Generate Report
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Report History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card animate-fade-in">
                            <div class="card-header">
                                <h5>Report History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($reports) > 0): ?>
                                    <?php foreach ($reports as $report): ?>
                                    <div class="report-card <?php echo ($is_generated_view && $report['id'] == $recent_report_id) ? 'highlight-new' : ''; ?>">
                                        <div class="report-header">
                                            <div>
                                                <strong><?php echo htmlspecialchars($report['title']); ?></strong>
                                                <span class="report-type ms-2">
                                                    <?php echo isset($report_types[$report['report_type']]) ? $report_types[$report['report_type']] : $report['report_type']; ?>
                                                </span>
                                            </div>
                                            <span class="badge bg-info">
                                                <?php echo strtoupper($report['format']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="report-content">
                                            <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                        </div>
                                        
                                        <div class="report-meta">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    Generated by: <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                                    <?php if ($report['barangay_filter']): ?>
                                                        <span class="badge bg-secondary filter-badge">Barangay: <?php echo htmlspecialchars($report['barangay_filter']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($report['incident_type_filter']): ?>
                                                        <span class="badge bg-secondary filter-badge">Type: <?php echo htmlspecialchars($report['incident_type_filter']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php echo date('M j, Y H:i', strtotime($report['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($is_generated_view && $report['id'] == $recent_report_id && isset($_SESSION['report_data'])): ?>
                                        <div class="mt-3">
                                            <form method="POST" action="">
                                                <input type="hidden" name="format" value="<?php echo $report['format']; ?>">
                                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($report['title']); ?>">
                                                <button type="submit" name="download_report" class="btn btn-sm btn-success">
                                                    <i class='bx bx-download'></i> Download Report
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">No reports found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toasts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const toasts = document.querySelectorAll('.toast');
                toasts.forEach(toast => {
                    toast.classList.remove('animate-slide-in');
                    toast.classList.add('animate-slide-out');
                    setTimeout(() => toast.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>