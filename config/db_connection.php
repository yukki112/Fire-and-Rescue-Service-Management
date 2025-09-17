<?php
// Database configuration for FRSM
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'frsm_1'); // Default XAMPP username
define('DB_PASSWORD', 'Admin123'); // Default XAMPP has no password
define('DB_NAME', 'frsm_1');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
