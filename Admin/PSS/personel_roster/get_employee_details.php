<?php
session_start();
require_once 'config/database_manager.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$employee_id = intval($_GET['id']);

try {
    $employee = $dbManager->fetch("frsm", "SELECT * FROM employees WHERE id = ?", [$employee_id]);
    
    if (!$employee) {
        echo '<div class="alert alert-warning">Employee not found.</div>';
        exit;
    }
    
    echo '
    <div class="row">
        <div class="col-md-4 text-center">
            <img src="https://ui-avatars.com/api/?name=' . urlencode($employee['first_name'] . ' ' . $employee['last_name']) . '&background=0D8ABC&color=fff&size=128&rounded=true&bold=true" 
                 alt="' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '" 
                 class="img-fluid rounded-circle mb-3">
            <h4>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</h4>
            <p class="text-muted">' . htmlspecialchars($employee['employee_id']) . '</p>
            <span class="badge bg-' . ($employee['is_active'] ? 'success' : 'secondary') . '">
                ' . ($employee['is_active'] ? 'Active' : 'Inactive') . '
            </span>
        </div>
        <div class="col-md-8">
            <h5>Personal Information</h5>
            <table class="table table-sm">
                <tr>
                    <th width="30%">First Name</th>
                    <td>' . htmlspecialchars($employee['first_name']) . '</td>
                </tr>
                <tr>
                    <th>Last Name</th>
                    <td>' . htmlspecialchars($employee['last_name']) . '</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>' . htmlspecialchars($employee['email']) . '</td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td>' . (isset($employee['phone']) && !empty($employee['phone']) ? htmlspecialchars($employee['phone']) : 'N/A') . '</td>
                </tr>
            </table>
            
            <h5>Employment Details</h5>
            <table class="table table-sm">
                <tr>
                    <th width="30%">Department</th>
                    <td>' . htmlspecialchars($employee['department']) . '</td>
                </tr>
                <tr>
                    <th>Position</th>
                    <td>' . htmlspecialchars($employee['position']) . '</td>
                </tr>
                <tr>
                    <th>Hire Date</th>
                    <td>' . (isset($employee['hire_date']) && !empty($employee['hire_date']) ? htmlspecialchars($employee['hire_date']) : 'N/A') . '</td>
                </tr>
            </table>
        </div>
    </div>';
    
} catch (Exception $e) {
    error_log("Employee details error: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading employee details. Please try again.</div>';
}
?>