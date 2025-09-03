<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $employee_id = $_POST['employee_id'] ?? '';
    $shift_type_id = $_POST['shift_type_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validate required fields
    if (empty($employee_id) || empty($shift_type_id) || empty($date)) {
        header('Location: index.php?tab=assignment&error=Please fill all required fields');
        exit;
    }
    
    try {
        // Insert the shift assignment
        $stmt = $pdo->prepare("
            INSERT INTO shift_schedules (employee_id, shift_type_id, date, notes, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'scheduled', ?, NOW(), NOW())
        ");
        
        $stmt->execute([$employee_id, $shift_type_id, $date, $notes, $_SESSION['user_id']]);
        
        header('Location: index.php?tab=assignment&success=shift_assigned');
        exit;
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header('Location: index.php?tab=assignment&error=Database error: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}