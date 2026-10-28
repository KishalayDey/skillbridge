<?php
// ============================================
// DATABASE CONNECTION
// Change these only if your MySQL login is different.
// ============================================

$db_host = "localhost";
$db_user = "root";
$db_pass = "";              // default XAMPP password is empty
$db_name = "kaajkoro_db";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Make sure data is stored and read correctly
$conn->set_charset("utf8mb4");
?>
