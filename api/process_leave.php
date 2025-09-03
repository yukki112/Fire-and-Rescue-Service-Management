<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $leave_type_id = $_POST['leave_type_id'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Validate required fields
    if (empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
        header('Location: index.php?tab=leave&error=Please fill all required fields');
        exit;
    }
    
    // Validate date range
    if (strtotime($start_date) > strtotime($end_date)) {
        header('Location: index.php?tab=leave&error=End date must be after start date');
        exit;
    }
    
    try {
        // Insert the leave request
        $stmt = $pdo->prepare("
            INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, reason, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $stmt->execute([$_SESSION['user_id'], $leave_type_id, $start_date, $end_date, $reason]);
        
        header('Location: index.php?tab=leave&success=leave_requested');
        exit;
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header('Location: index.php?tab=leave&error=Database error: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}