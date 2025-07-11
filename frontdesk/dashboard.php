<?php
// dashboard.php - Front Desk Dashboard

$host = "localhost";
$dbname = "u740329344_rlis";
$username = "u740329344_rlis";
$password = "Rlis@7030";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get counts for dashboard stats
$patient_count = 0;
$appointment_count = 0;
$draft_count = 0;
$pending_payments = 0;

// Total patients
$result = $conn->query("SELECT COUNT(*) as count FROM patients");
if ($result) {
    $patient_count = $result->fetch_assoc()['count'];
}

// Today's appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
if ($result) {
    $appointment_count = $result->fetch_assoc()['count'];
}

// Drafts
$result = $conn->query("SELECT COUNT(*) as count FROM patient_drafts");
if ($result) {
    $draft_count = $result->fetch_assoc()['count'];
}

// Pending payments
$result = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'unpaid'");
if ($result) {
    $pending_payments = $result->fetch_assoc()['count'];
}

// Recent appointments
$recent_appointments = [];
$result = $conn->query("
    SELECT a.*, p.first_name, p.last_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.appointment_date >= NOW()
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
if ($result) {
    $recent_appointments = $result->fetch_all(MYSQLI_ASSOC);
}

// Recent patients
$recent_patients = [];
$result = $conn->query("
    SELECT * FROM patients 
    ORDER BY created_at DESC 
    LIMIT 5
");
if ($result) {
    $recent_patients = $result->fetch_all(MYSQLI_ASSOC);
}

// Recent activity (simplified example)
$recent_activity = [
    ['action' => 'New patient registered', 'details' => 'John Doe (MRN: 123456)', 'time' => '10 mins ago'],
    ['action' => 'Appointment scheduled', 'details' => 'Jane Smith for Blood Test', 'time' => '25 mins ago'],
    ['action' => 'Draft saved', 'details' => 'Patient registration form', 'time' => '1 hour ago'],
    ['action' => 'System update', 'details' => 'RLIS v2.1 installed', 'time' => 'Yesterday']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Front Desk - RLIS Reception Dashboard</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Custom CSS -->
  <style>
    :root {
      --primary-color: #3498db;
      --secondary-color: #2c3e50;
      --accent-color: #e74c3c;
      --light-color: #ecf0f1;
      --dark-color: #34495e;
      --success-color: #2ecc71;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f7fa;
      color: #333;
    }
    
    .sidebar {
      background-color: var(--secondary-color);
      color: white;
      height: 100vh;
      position: fixed;
      padding-top: 20px;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      width: 270px;
    }
    
    .sidebar .nav-link {
      color: rgba(255,255,255,0.8);
      margin-bottom: 5px;
      border-radius: 5px;
      padding: 10px 15px;
    }
    
    .sidebar .nav-link:hover, .sidebar .nav-link.active {
      background-color: rgba(255,255,255,0.1);
      color: white;
    }
    
    .sidebar .nav-link i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }
    
    .main-content {
      margin-left: 270px;
      padding: 0 20px;
    }
    
    .dashboard-header {
      background-color: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-card {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      transition: transform 0.3s ease;
      border-left: 4px solid var(--primary-color);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
    }
    
    .stat-card i {
      font-size: 2rem;
      color: var(--primary-color);
      margin-bottom: 15px;
    }
    
    .stat-card h3 {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .stat-card p {
      color: #666;
      margin-bottom: 0;
    }
    
    .quick-actions {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .quick-action-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 15px;
      border-radius: 8px;
      background-color: var(--light-color);
      color: var(--dark-color);
      text-align: center;
      transition: all 0.3s ease;
      height: 100%;
    }
    
    .quick-action-btn:hover {
      background-color: var(--primary-color);
      color: white;
      transform: translateY(-3px);
    }
    
    .quick-action-btn i {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    
    .recent-activity {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .activity-item {
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }
    
    .activity-item:last-child {
      border-bottom: none;
    }
    
    .activity-time {
      font-size: 0.8rem;
      color: #999;
    }
    
    .user-profile {
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 5px;
      background-color: rgba(255,255,255,0.1);
      margin-top: 20px;
    }
    
    .user-profile img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-right: 10px;
    }
    
    .user-profile-info {
      line-height: 1.2;
    }
    
    .user-profile-info .user-name {
      font-weight: 600;
    }
    
    .user-profile-info .user-role {
      font-size: 0.8rem;
      opacity: 0.8;
    }
    
    .badge-status {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .badge-scheduled { background-color: #d1ecf1; color: #0c5460; }
    .badge-completed { background-color: #d4edda; color: #155724; }
    .badge-cancelled { background-color: #f8d7da; color: #721c24; }
    
    @media (max-width: 992px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
      
      .main-content {
        margin-left: 0;
      }
    }
  </style>
<?php

// Get counts for sidebar badges
$draft_count = 0;
$appointment_count = 0;
$pending_payments = 0;

// Check if user is logged in (example - adapt to your auth system)
$logged_in = isset($_SESSION['user_id']);
$user_name = $logged_in ? $_SESSION['user_name'] : 'Front Desk User';
$user_role = $logged_in ? $_SESSION['user_role'] : 'Receptionist';

// Get draft count
$result = $conn->query("SELECT COUNT(*) as count FROM patient_drafts");
if ($result) {
    $draft_count = $result->fetch_assoc()['count'];
}

// Get today's appointment count
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = '$today'");
if ($result) {
    $appointment_count = $result->fetch_assoc()['count'];
}

// Get pending payments count
$result = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'unpaid'");
if ($result) {
    $pending_payments = $result->fetch_assoc()['count'];
}
?>

  <style>
    :root {
      --primary-color: #3498db;
      --secondary-color: #2c3e50;
      --accent-color: #e74c3c;
      --light-color: #ecf0f1;
      --dark-color: #34495e;
      --success-color: #2ecc71;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #121212;
      color: white;
      min-height: 100vh;
      padding-top: 70px; /* Space for fixed header */
    }
    
    h5, p {
      color: white;
    }
    
    /* Main Navigation */
    .main-nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: #1f2a2e;
      color: white;
      padding: 1px 15px;
      z-index: 1040;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .main-nav .nav-left {
      display: flex;
      align-items: center;
    }
    
    .main-nav .nav-right {
      display: flex;
      align-items: center;
    }
    
    .main-nav .menu-toggle {
      background: none;
      border: none;
      color: white;
      font-size: 1.5rem;
      padding: 5px;
      margin-right: 15px;
    }
    
    .main-nav .nav-links {
      display: flex;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    
    .main-nav .nav-links li {
      margin-right: 15px;
    }
    
    .main-nav .nav-links a {
      color: white;
      text-decoration: none;
      padding: 5px 10px;
      border-radius: 5px;
      transition: all 0.3s;
    }
    
    .main-nav .nav-links a:hover {
      background: rgba(255,255,255,0.1);
    }
    
    /* Theme Toggle */
    .theme-toggle {
      cursor: pointer;
      margin-right: 15px;
      display: flex;
      align-items: center;
      color: white;
    }
    
    .theme-toggle i {
      margin-right: 5px;
    }
    
    /* Notification Badge */
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background-color: #ff4757;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
    }
    
    /* Sidebar */
    .sidebar {
      width: 280px;
      height: 100vh;
      position: fixed;
      top: 0;
      left: -270px;
      background: #1c1c1c;
      color: white;
      transition: all 0.3s ease;
      z-index: 1050;
      box-shadow: 5px 0 15px rgba(0,0,0,0.1);
      padding-top: 0px; /* Account for main nav height */
    }
    
    .sidebar.show {
      left: 0;
    }
    
    .sidebar-header {
      padding: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .sidebar-close-btn {
      background: none;
      border: none;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
    }
    
    .sidebar .nav-link {
      color: rgba(255,255,255,0.8);
      margin-bottom: 5px;
      border-radius: 5px;
      padding: 12px 20px;
      border-left: 3px solid transparent;
      transition: all 0.2s;
    }
    
    .sidebar .nav-link:hover, 
    .sidebar .nav-link.active {
      background: rgba(255,255,255,0.1);
      border-left: 3px solid gold;
      color: gold;
    }
    
    .sidebar .nav-link i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
      transition: transform 0.2s;
    }
    
    .sidebar .nav-link:hover i {
      transform: scale(1.1);
    }
    
    /* Sidebar Overlay */
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0,0,0,0.5);
      z-index: 1040;
      display: none;
    }
    
    /* Main Content */
    .main-content {
      padding: 20px;
      transition: all 0.3s ease;
      margin-top: 70px; /* Account for main nav height */
     color:#0d6efd;
    }
    
    .page-header {
      background-color: rgba(255,255,255,0.1);
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .content-card {
      background-color: rgba(255,255,255,0.1);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    /* User Profile */
    .user-profile {
      display: flex;
      align-items: center;
      padding: 15px;
      border-radius: 5px;
      background-color: rgba(255,255,255,0.1);
      margin-top: 20px;
      margin-bottom: 20px;
    }
    
    .user-profile img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-right: 10px;
    }
    
    .user-profile-info {
      line-height: 1.2;
    }
    
    .user-profile-info .user-name {
      font-weight: 600;
    }
    
    .user-profile-info .user-role {
      font-size: 0.8rem;
      opacity: 0.8;
    }
    
    /* Dropdown Menu */
    .dropdown-menu {
      background: linear-gradient(135deg, #1a1a40, #4a148c);
      border: 1px solid rgba(255,255,255,0.1);
    }
    
    .dropdown-item {
      color: white;
    }
    
    .dropdown-item:hover {
      background: rgba(255,255,255,0.1);
    }
    
    /* Desktop styles */
    @media (min-width: 992px) {
      body {
        padding-top: 0;
        padding-left: 270px;
      }
      
      .main-nav {
        left: 270px;
        right: 0;
        width: auto;
      }
      
      .sidebar {
        left: 0;
      }
      
      .sidebar-close-btn {
        display: none;
      }
      
      .sidebar-overlay {
        display: none !important;
      }
      
      .main-content {
        padding: 30px;
        margin-top: 0;
        margin-left: 0;
      }
    }
    
    /* Light theme styles */
    body.light-theme {
      background: white;
      color: #333;
    }
    
    body.light-theme .main-nav {
      background: #ccc !important;
    }
    
    body.light-theme .sidebar {
      background: white !important;
      color: #333;
    }
    
    body.light-theme .sidebar .nav-link {
      color: #333;
    }
    
    body.light-theme .sidebar .nav-link:hover,
    body.light-theme .sidebar .nav-link.active {
      background: rgba(0,0,0,0.05);
      color: #333;
    }
    
    body.light-theme .sidebar-header {
      border-bottom: 1px solid rgba(0,0,0,0.1);
      background: #ccc !important;
    }
    
    body.light-theme .theme-toggle {
      color: #333;
    }
    
    body.light-theme .page-header,
    body.light-theme .content-card {
      background: white;
      color: #333;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    body.light-theme h5,
    body.light-theme p {
      color: #333;
    }
    
    body.light-theme .dropdown-menu {
      background: white;
    }
    
    body.light-theme .dropdown-item {
      color: #333;
    }
  </style>
</head>
<body>
  <!-- Main Navigation -->
  <nav class="main-nav">
    <div class="nav-left">
      <button class="menu-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
      </button>
   
    </div>
    
    <div class="nav-right">
      <div class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
      </div>
      
      <div class="dropdown">
        <button class="btn btn-outline-light dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown">
          EN
        </button>
        <ul class="dropdown-menu" aria-labelledby="languageDropdown">
          <li><a class="dropdown-item" href="#">English</a></li>
          <li><a class="dropdown-item" href="#">Spanish</a></li>
          <li><a class="dropdown-item" href="#">French</a></li>
        </ul>
      </div>
      
      <div class="ms-3 position-relative">
        <a href="#" class="text-white position-relative" data-bs-toggle="modal" data-bs-target="#notificationModal">
          <i class="fas fa-bell"></i>
          <span class="notification-badge">3</span>
        </a>
      </div>
      
      <div class="dropdown ms-3">
        <button class="btn btn-outline-light dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown">
          <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user_name) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
          <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
  
  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <h3><i class="fas fa-clinic-medical"></i> RLIS</h3>
        <p class="text-info mb-0">Front Desk Module</p>
      </div>
      <button class="sidebar-close-btn d-lg-none" id="sidebarClose">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <ul class="nav flex-column px-3">
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="#">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/patients.php' ? 'active' : ''; ?>" href="frontdesk/patients.php">
          <i class="fas fa-user-plus"></i> Patient Registration
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/patient_search.php' ? 'active' : ''; ?>" href="frontdesk/patient_search.php">
          <i class="fas fa-search"></i> Patient Search
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/drafts.php' ? 'active' : ''; ?>" href="frontdesk/drafts.php">
          <i class="fas fa-file-alt"></i> Drafts
          <span class="badge bg-danger float-end"><?= $draft_count ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/patients_assignment.php' ? 'active' : ''; ?>" href="frontdesk/patients_assignment.php">
          <i class="fas fa-tasks"></i> Patient Assignment
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/appointments.php' ? 'active' : ''; ?>" href="frontdesk/appointment.php">
          <i class="fas fa-calendar-check"></i> Appointments
          <span class="badge bg-primary float-end"><?= $appointment_count ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/outstanding_results.php' ? 'active' : ''; ?>" href="frontdesk/outstanding_results.php">
          <i class="fas fa-tasks"></i> Outstanding Results
          <span class="badge bg-warning float-end"><?= $pending_payments ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/billing.php' ? 'active' : ''; ?>" href="frontdesk/billing.php">
          <i class="fas fa-money-bill-wave"></i> Billing
          <span class="badge bg-warning float-end"><?= $pending_payments ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/daily_reports.php' ? 'active' : ''; ?>" href="frontdesk/daily_reports.php">
          <i class="fas fa-calendar-day"></i> Daily Report
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frontdesk/settings.php' ? 'active' : ''; ?>" href="frontdesk/settings.php">
          <i class="fas fa-cog"></i> Settings
        </a>
      </li>
    </ul>
    
    <div class="user-profile">
      <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=random" alt="User">
      <div class="user-profile-info">
        <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
        <div class="user-role"><?= htmlspecialchars($user_role) ?></div>
      </div>
    </div>
  </div>
  
  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <!-- Page Header -->
    <div class="page-header">
      <h1>
        <?php if (isset($page_icon)): ?>
          <i class="<?= $page_icon ?> me-2"></i>
        <?php endif; ?>
        <?= isset($page_title) ? $page_title : 'Dashboard' ?>
      </h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="../dashboard.php"><i class="fas fa-home"></i> Home</a></li>
          <li class="breadcrumb-item active" aria-current="page"><?= isset($page_title) ? $page_title : 'Dashboard' ?></li>
        </ol>
      </nav>
    </div>

    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-bell me-2"></i> Notifications</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="notification-item">
              <div class="d-flex justify-content-between">
                <strong>New Patient Registered</strong>
                <small>10 min ago</small>
              </div>
              <p>John Doe has completed registration</p>
            </div>
            <div class="notification-item">
              <div class="d-flex justify-content-between">
                <strong>Appointment Reminder</strong>
                <small>1 hour ago</small>
              </div>
              <p>Jane Smith has an appointment today at 2:00 PM</p>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-sm btn-primary">Mark all as read</button>
          </div>
        </div>
      </div>
    </div>

   
  
      <!-- Main Content -->
      <div class="col-md-9 col-lg-10 main-content">
        <div class="dashboard-header">
          <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
          <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
        </div>
        
        <div class="row">
          <!-- Stats Cards -->
          <div class="col-md-3">
            <div class="stat-card">
              <i class="fas fa-users"></i>
              <h3><?= number_format($patient_count) ?></h3>
              <p>Total Patients</p>
            </div>
          </div>
          
          <div class="col-md-3">
            <div class="stat-card">
              <i class="fas fa-calendar-day"></i>
              <h3><?= number_format($appointment_count) ?></h3>
              <p>Today's Appointments</p>
            </div>
          </div>
          
          <div class="col-md-3">
            <div class="stat-card">
              <i class="fas fa-file-alt"></i>
              <h3><?= number_format($draft_count) ?></h3>
              <p>Pending Drafts</p>
            </div>
          </div>
          
          <div class="col-md-3">
            <div class="stat-card">
              <i class="fas fa-money-bill-wave"></i>
              <h3><?= number_format($pending_payments) ?></h3>
              <p>Pending Payments</p>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-8">
            <!-- Quick Actions -->
            <div class="quick-actions">
              <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
              <hr>
              <div class="row">
                <div class="col-md-3 col-6 mb-3">
                  <a href="frontdesk/patients.php" class="quick-action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>New Patient</span>
                  </a>
                </div>
                <div class="col-md-3 col-6 mb-3">
                  <a href="frontdesk/patient_search.php" class="quick-action-btn">
                    <i class="fas fa-search"></i>
                    <span>Find Patient</span>
                  </a>
                </div>
                <div class="col-md-3 col-6 mb-3">
                  <a href="frontdesk/appointment.php?action=create" class="quick-action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    <span>New Appointment</span>
                  </a>
                </div>
                <div class="col-md-3 col-6 mb-3">
                  <a href="frontdesk/drafts.php" class="quick-action-btn">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>View Drafts</span>
                  </a>
                </div>
              </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
              <h4><i class="fas fa-history"></i> Recent Activity</h4>
              <hr>
              <?php foreach($recent_activity as $activity): ?>
              <div class="activity-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <strong><?= $activity['action'] ?></strong>
                    <p class="mb-0"><?= $activity['details'] ?></p>
                  </div>
                  <div class="activity-time"><?= $activity['time'] ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          
          <div class="col-md-4">
            <!-- Upcoming Appointments -->
            <div class="recent-activity">
              <h4><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h4>
              <hr>
              <?php if(!empty($recent_appointments)): ?>
                <?php foreach($recent_appointments as $appt): ?>
                <div class="activity-item">
                  <div class="d-flex justify-content-between">
                    <div>
                      <strong><?= date('h:i A', strtotime($appt['appointment_time'])) ?></strong>
                      <p class="mb-0"><?= htmlspecialchars($appt['first_name'] . ' ' . htmlspecialchars($appt['last_name'])) ?></p>
                    </div>
                    <div>
                      <span class="badge-status badge-<?= 
                        $appt['status'] === 'completed' ? 'completed' : 
                        ($appt['status'] === 'cancelled' ? 'cancelled' : 'scheduled') 
                      ?>">
                        <?= ucfirst($appt['status']) ?>
                      </span>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                <a href="frontdesk/appointments.php" class="btn btn-outline-primary btn-sm mt-2 w-100">
                  View All Appointments
                </a>
              <?php else: ?>
                <p class="text-muted">No upcoming appointments</p>
              <?php endif; ?>
            </div>
            
            <!-- Recent Patients -->
            <div class="recent-activity mt-4">
              <h4><i class="fas fa-user-clock"></i> Recent Patients</h4>
              <hr>
              <?php if(!empty($recent_patients)): ?>
                <?php foreach($recent_patients as $patient): ?>
                <div class="activity-item">
                  <div class="d-flex justify-content-between">
                    <div>
                      <strong><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></strong>
                      <p class="mb-0"><?= date('M d, Y', strtotime($patient['created_at'])) ?></p>
                    </div>
                    <div>
                      <a href="frontdesk/patient_details.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary">
                        View
                      </a>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                <a href="frontdesk/patient_search.php" class="btn btn-outline-primary btn-sm mt-2 w-100">
                  View All Patients
                </a>
              <?php else: ?>
                <p class="text-muted">No recent patients</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Custom JS -->
  <script>
    // Simple animation for stat cards on page load
    document.addEventListener('DOMContentLoaded', function() {
      const statCards = document.querySelectorAll('.stat-card');
      statCards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, index * 100);
      });
    });
  </script>
    <script>
      // Sidebar toggle functionality
      document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        // Toggle sidebar
        sidebarToggle.addEventListener('click', function() {
          sidebar.classList.add('show');
          sidebarOverlay.style.display = 'block';
          document.body.style.overflow = 'hidden';
        });
        
        // Close sidebar
        sidebarClose.addEventListener('click', function() {
          sidebar.classList.remove('show');
          sidebarOverlay.style.display = 'none';
          document.body.style.overflow = 'auto';
        });
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', function() {
          sidebar.classList.remove('show');
          sidebarOverlay.style.display = 'none';
          document.body.style.overflow = 'auto';
        });
        
        // Close sidebar when clicking a nav link (mobile only)
        if (window.innerWidth < 992) {
          document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function() {
              sidebar.classList.remove('show');
              sidebarOverlay.style.display = 'none';
              document.body.style.overflow = 'auto';
            });
          });
        }
        
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Check for saved theme preference or use system preference
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'light' || (!currentTheme && !prefersDarkScheme.matches)) {
          document.body.classList.add('light-theme');
          updateToggleIcon(true);
        }
        
        themeToggle.addEventListener('click', function() {
          const isLight = document.body.classList.toggle('light-theme');
          updateToggleIcon(isLight);
          
          // Save the preference to localStorage
          localStorage.setItem('theme', isLight ? 'light' : 'dark');
        });
        
        function updateToggleIcon(isLight) {
          const icon = themeToggle.querySelector('i');
          
          if (isLight) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
          } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
          }
        }
      });
    </script>
</body>
</html>