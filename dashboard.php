<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'config.php';

// Redirect to login page if not logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

// Role-based dashboard redirection
$role = $_SESSION['user_role'];
$role_paths = [
    'admin' => 'admin/dashboard.php',
    'doctor' => 'doctor/dashboard.php',
    'lab_technician' => 'laboratory/dashboard.php',
    'patient' => 'patient/dashboard.php',
    'receptionist' => 'frontdesk/dashboard.php',
    'accountant' => 'accounts/dashboard.php',
    'radiologist' => 'radiology/dashboard.php',
    'marketer' => 'marketer/dashboard.php',
    'inventory' => 'inventory/dashboard.php',
    'result_collection' => 'result_collection/dashboard.php',
    'media' => 'media/dashboard.php',
    'ict' => 'ict/dashboard.php',
    'ultrasound' => 'ultrasound/dashboard.php'
];

// Include the correct dashboard file based on role
if (array_key_exists($role, $role_paths)) {
    include $role_paths[$role];
} else {
    // Redirect to login if the role is not recognized
    header("Location: login.php");
    exit();
}
?>
<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<style>
header{
    top:2px;
    position:absolute;
    width:100%;
}
</style>
<div class="container mt-3 bg-primary">
    <header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>
    <div class="mt-4">
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
        <p>Your role: <strong><?php echo htmlspecialchars($_SESSION['user_role']); ?></strong></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->
