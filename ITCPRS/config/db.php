<?php
/**
 * config/db.php
 * Database connection using MySQLi.
 * Edit the credentials below to match your environment.
 */

$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "itcprs_db";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone to Philippine Standard Time (UTC+8)
mysqli_query($conn, "SET time_zone = '+08:00'");