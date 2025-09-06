<?php
session_start();
require_once 'config/database_manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $source_type = $_POST['source_type'] ?? '';
        $source_id = $_POST['source_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $barangay = $_POST['barangay'] ?? '';
        $status = $_POST['status'] ?? '';
        $latitude = $_POST['latitude'] ?? 0;
        $longitude = $_POST['longitude'] ?? 0;
        $capacity = $_POST['capacity'] ?? null;
        $pressure = $_POST['pressure'] ?? null;
        $flow_rate = $_POST['flow_rate'] ?? null;
        $last_inspection = $_POST['last_inspection'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        // Insert into database using query() method instead of execute()
        $dbManager->query("hwrm", 
            "INSERT INTO water_sources (source_type, source_id, name, location, barangay, status, latitude, longitude, capacity, pressure, flow_rate, last_inspection, notes) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$source_type, $source_id, $name, $location, $barangay, $status, $latitude, $longitude, $capacity, $pressure, $flow_rate, $last_inspection, $notes]
        );
        
        // Redirect back to water source database
        header('Location: wsd.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Error adding water source: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to add water source. Please try again.";
        header('Location: wsd.php');
        exit;
    }
} else {
    header('Location: wsd.php');
    exit;
}