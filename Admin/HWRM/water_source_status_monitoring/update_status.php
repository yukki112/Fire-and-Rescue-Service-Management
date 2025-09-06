<?php
session_start();
require_once 'config/database_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source_id = $_POST['source_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($source_id) || empty($status)) {
        $_SESSION['error_message'] = "Source ID and status are required.";
        header('Location: wssm.php');
        exit;
    }
    
    try {
        // Update the water source status
        $dbManager->query("hwrm", 
            "UPDATE water_sources SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), updated_at = NOW() WHERE id = ?",
            [$status, "Status changed to " . strtoupper($status) . " on " . date('Y-m-d H:i:s') . ": " . $notes, $source_id]
        );
        
        // Log the status change
        $dbManager->query("hwrm",
            "INSERT INTO water_source_status_log (source_id, old_status, new_status, changed_by, notes) 
             VALUES (?, (SELECT status FROM water_sources WHERE id = ?), ?, ?, ?)",
            [$source_id, $source_id, $status, $_SESSION['user_id'], $notes]
        );
        
        $_SESSION['success_message'] = "Water source status updated successfully.";
    } catch (Exception $e) {
        error_log("Error updating status: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update status. Please try again.";
    }
    
    header('Location: wssm.php');
    exit;
} else {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: wssm.php');
    exit;
}