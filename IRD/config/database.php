<?php
$host = 'localhost:3307';
$dbname = 'ird';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $pdo->exec("SET time_zone = '+08:00'"); // Set to Philippine timezone
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli-server' || $_SERVER['SERVER_NAME'] === 'localhost') {
        die("Database connection error: " . $e->getMessage());
    } else {
        die("Database connection error. Please try again later.");
    }
}

function checkDatabaseHealth() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Database health check failed: " . $e->getMessage());
        return false;
    }
}
?>
