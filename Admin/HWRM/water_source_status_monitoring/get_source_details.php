<?php
session_start();
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized access.";
    exit;
}

// Check if source ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo "Source ID is required.";
    exit;
}

$source_id = $_GET['id'];

try {
    // Get water source details
    $source = $dbManager->fetch("hwrm", 
        "SELECT * FROM water_sources WHERE id = ?", 
        [$source_id]
    );
    
    if (!$source) {
        http_response_code(404);
        echo "Water source not found.";
        exit;
    }
    
    // Get latest inspection
    $latest_inspection = $dbManager->fetch("hwrm",
        "SELECT * FROM water_source_inspections 
         WHERE source_id = ? 
         ORDER BY inspection_date DESC 
         LIMIT 1",
        [$source_id]
    );
    
    // Get maintenance history
    $maintenance_history = $dbManager->fetchAll("hwrm",
        "SELECT * FROM water_source_maintenance 
         WHERE source_id = ? 
         ORDER BY maintenance_date DESC 
         LIMIT 5",
        [$source_id]
    );
    
    // Get status history
    $status_history = $dbManager->fetchAll("hwrm",
        "SELECT * FROM water_source_status_log 
         WHERE source_id = ? 
         ORDER BY changed_at DESC 
         LIMIT 10",
        [$source_id]
    );
    
    // Format the response
    echo '<div class="source-details">';
    echo '<h5>' . htmlspecialchars($source['name']) . ' (' . htmlspecialchars($source['source_id']) . ')</h5>';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h6>Basic Information</h6>';
    echo '<table class="table table-sm">';
    echo '<tr><th>Type:</th><td>' . ucfirst(str_replace('_', ' ', $source['source_type'])) . '</td></tr>';
    echo '<tr><th>Location:</th><td>' . htmlspecialchars($source['location']) . '</td></tr>';
    echo '<tr><th>Barangay:</th><td>' . htmlspecialchars($source['barangay']) . '</td></tr>';
    echo '<tr><th>Coordinates:</th><td>' . htmlspecialchars($source['latitude']) . ', ' . htmlspecialchars($source['longitude']) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<h6>Status Information</h6>';
    echo '<table class="table table-sm">';
    echo '<tr><th>Current Status:</th><td><span class="badge status-badge status-' . $source['status'] . '">' . ucfirst($source['status']) . '</span></td></tr>';
    echo '<tr><th>Pressure:</th><td>' . ($source['pressure'] ? htmlspecialchars($source['pressure']) . ' PSI' : 'N/A') . '</td></tr>';
    echo '<tr><th>Flow Rate:</th><td>' . ($source['flow_rate'] ? htmlspecialchars($source['flow_rate']) . ' L/min' : 'N/A') . '</td></tr>';
    echo '<tr><th>Capacity:</th><td>' . ($source['capacity'] ? htmlspecialchars($source['capacity']) . ' L' : 'N/A') . '</td></tr>';
    echo '<tr><th>Last Inspection:</th><td>' . ($source['last_inspection'] ? date('M j, Y', strtotime($source['last_inspection'])) : 'Never') . '</td></tr>';
    echo '<tr><th>Next Inspection:</th><td>' . ($source['next_inspection'] ? date('M j, Y', strtotime($source['next_inspection'])) : 'Not scheduled') . '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    
    if (!empty($source['notes'])) {
        echo '<div class="mb-3">';
        echo '<h6>Notes</h6>';
        echo '<div class="card card-body p-2">' . nl2br(htmlspecialchars($source['notes'])) . '</div>';
        echo '</div>';
    }
    
    if ($latest_inspection) {
        echo '<div class="mb-3">';
        echo '<h6>Latest Inspection</h6>';
        echo '<table class="table table-sm">';
        echo '<tr><th>Date:</th><td>' . date('M j, Y', strtotime($latest_inspection['inspection_date'])) . '</td></tr>';
        echo '<tr><th>Condition:</th><td><span class="badge condition-badge condition-' . $latest_inspection['condition'] . '">' . ucfirst($latest_inspection['condition']) . '</span></td></tr>';
        echo '<tr><th>Pressure:</th><td>' . ($latest_inspection['pressure'] ? htmlspecialchars($latest_inspection['pressure']) . ' PSI' : 'N/A') . '</td></tr>';
        echo '<tr><th>Flow Rate:</th><td>' . ($latest_inspection['flow_rate'] ? htmlspecialchars($latest_inspection['flow_rate']) . ' L/min' : 'N/A') . '</td></tr>';
        if (!empty($latest_inspection['issues_found'])) {
            echo '<tr><th>Issues Found:</th><td>' . nl2br(htmlspecialchars($latest_inspection['issues_found'])) . '</td></tr>';
        }
        if (!empty($latest_inspection['recommendations'])) {
            echo '<tr><th>Recommendations:</th><td>' . nl2br(htmlspecialchars($latest_inspection['recommendations'])) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    if (!empty($maintenance_history)) {
        echo '<div class="mb-3">';
        echo '<h6>Recent Maintenance</h6>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Parts Used</th></tr></thead>';
        echo '<tbody>';
        foreach ($maintenance_history as $maintenance) {
            echo '<tr>';
            echo '<td>' . date('M j, Y', strtotime($maintenance['maintenance_date'])) . '</td>';
            echo '<td>' . ucfirst($maintenance['maintenance_type']) . '</td>';
            echo '<td>' . htmlspecialchars(substr($maintenance['description'], 0, 50)) . (strlen($maintenance['description']) > 50 ? '...' : '') . '</td>';
            echo '<td>' . (!empty($maintenance['parts_used']) ? htmlspecialchars(substr($maintenance['parts_used'], 0, 30)) . (strlen($maintenance['parts_used']) > 30 ? '...' : '') : 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    
} catch (Exception $e) {
    error_log("Error fetching source details: " . $e->getMessage());
    http_response_code(500);
    echo "Error loading details. Please try again.";
    exit;
}