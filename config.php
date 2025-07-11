<?php
$host = "localhost";
$dbname = "u740329344_rlis";
$username = "u740329344_rlis";
$password = "Rlis@7030";

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// $host = "localhost";
// $dbname = "u740329344_rlis";
// $username = "u740329344_rlis";
// $password = "Rlis@7030";

// try {
//     // Create PDO connection
//     $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
//     // Set the PDO error mode to exception
//     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch (PDOException $e) {
//     // Handle the connection error
//     die("Connection failed: " . $e->getMessage());
// }

