<?php
// db_config.php
// Database configuration file




$host = "localhost";
$dbname = "u740329344_rlis";
$username = "u740329344_rlis";
$password = "Rlis@7030";

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}