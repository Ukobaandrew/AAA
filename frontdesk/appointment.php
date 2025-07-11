<?php
session_start();

// Include your DB connection details
include('db_config.php');

// Connect to the database
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get POST data
    $barcode = trim($_POST['barcode']);
    $assigned_to = intval($_POST['assigned_to']);
    $appointment_date = trim($_POST['appointment_date']);
    $scanned_image = '';

    // Process scanned image upload if exists
    if (isset($_FILES['scanned_image']) && $_FILES['scanned_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        // Ensure the uploads directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($_FILES['scanned_image']['name']);
        if (move_uploaded_file($_FILES['scanned_image']['tmp_name'], $target_file)) {
            $scanned_image = $target_file;
        }
    }
    
    // Look up the patient by barcode
    $stmt = $conn->prepare("SELECT id FROM patients WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Patient found – get their id
        $patient = $result->fetch_assoc();
        $patient_id = $patient['id'];
        
        // Insert appointment record
        $stmt2 = $conn->prepare("INSERT INTO appointments (patient_id, assigned_to, appointment_date, scanned_image) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("iiss", $patient_id, $assigned_to, $appointment_date, $scanned_image);
        if ($stmt2->execute()) {
            $message = "Appointment scheduled successfully.";
        } else {
            $message = "Error scheduling appointment: " . $conn->error;
        }
        $stmt2->close();
    } else {
        $message = "Patient not found. Please register the patient first.";
    }
    $stmt->close();
}

// Fetch available doctors and radiologists
$users_sql = "SELECT id, name, role FROM users WHERE role IN ('doctor','radiologist') AND status = 'active'";
$users_result = $conn->query($users_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Assignment - Front Desk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
     <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'?>
<div class="container mt-5">
    <h2 class="mb-4">Appointment Assignment</h2>
    <?php if (isset($message)) { echo '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>'; } ?>
    <form action="appointment.php" method="POST" enctype="multipart/form-data">
        <!-- Patient Barcode Input -->
        <div class="mb-3">
            <label for="barcode" class="form-label">Patient Barcode</label>
            <input type="text" name="barcode" id="barcode" class="form-control" required>
        </div>
        <!-- Assign to Doctor/Radiologist -->
        <div class="mb-3">
            <label for="assigned_to" class="form-label">Assign to (Doctor/Radiologist)</label>
            <select name="assigned_to" id="assigned_to" class="form-select" required>
                <option value="">Select Doctor/Radiologist</option>
                <?php while($user = $users_result->fetch_assoc()){ ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['role']) . ')'; ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <!-- Appointment Date and Time -->
        <div class="mb-3">
            <label for="appointment_date" class="form-label">Appointment Date &amp; Time</label>
            <input type="datetime-local" name="appointment_date" id="appointment_date" class="form-control" required>
        </div>
        <!-- Optional Scanned Image Upload -->
        <div class="mb-3">
            <label for="scanned_image" class="form-label">Upload Scanned Image (Optional)</label>
            <input type="file" name="scanned_image" id="scanned_image" class="form-control">
        </div>
        <!-- Submit Button -->
        <div class="text-center">
            <button type="submit" class="btn btn-primary">Assign Appointment</button>
        </div>
    </form>
</div>
<?php include 'footer.php'?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
