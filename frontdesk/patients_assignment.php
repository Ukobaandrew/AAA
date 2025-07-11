<?php
// patients_assignment.php

// Database connection settings – update these with your credentials.
$servername = "localhost";
$username = "u740329344_rlis";
$password = "Rlis@7030";
$dbname = "u740329344_rlis";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process assignment if form submitted
if (isset($_POST['assign'])) {
    $test_result_id = intval($_POST['test_result_id']);
    $doctor_id = intval($_POST['doctor_id']);

    // Update the test_results table: assign the selected doctor to the test result.
    $stmt = $conn->prepare("UPDATE test_results SET doctor_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $doctor_id, $test_result_id);

    if ($stmt->execute()) {
        $message = "Patient assignment updated successfully!";
    } else {
        $message = "Error updating assignment: " . $conn->error;
    }
    $stmt->close();
}

// Query scanned patients (test_results) that are not yet assigned (doctor_id is NULL)
$sql = "SELECT tr.id AS tr_id, tr.created_at AS upload_time, tr.status, 
               p.first_name, p.last_name, p.barcode,
               t.name AS test_name
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.id
        JOIN tests t ON tr.test_id = t.id
        WHERE tr.doctor_id IS NULL";
$result = $conn->query($sql);

// Query list of doctors/radiologists for the dropdown (users with role 'doctor' or 'radiologist')
$doctors_sql = "SELECT id, name FROM users WHERE role IN ('doctor', 'radiologist')";
$doctors_result = $conn->query($doctors_sql);
$doctors = [];
if ($doctors_result->num_rows > 0) {
    while ($row = $doctors_result->fetch_assoc()) {
        $doctors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patients Assignment</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="styles.css" rel="stylesheet">
  <style>
    /* Optional: additional custom styling */
    .dropdown-toggle::after {
      margin-left: .255em;
    }
  </style>
</head>
<body>
    <?php include 'header.php'?>
<div class="container mt-4">
    
  <h2 class="mb-4">Patients Assignment</h2>
  <?php if(isset($message)) { echo "<div class='alert alert-info'>{$message}</div>"; } ?>
  <table class="table table-bordered table-striped">
    <thead class="table-light">
      <tr>
        <th scope="col"><input type="checkbox" id="selectAll"></th>
        <th scope="col">Patients Details</th>
        <th scope="col">Study Details</th>
        <th scope="col">Clients Details</th>
        <th scope="col">Provider/Doctors Details</th>
        <th scope="col">Report Details</th>
        <th scope="col">Actions</th>
        <th scope="col">Alerts</th>
      </tr>
    </thead>
    <tbody>
      <?php if($result && $result->num_rows > 0) { 
              while($row = $result->fetch_assoc()) { 
                  $patientName = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                  $barcode = htmlspecialchars($row['barcode']);
                  $studyDetails = htmlspecialchars($row['test_name']);
                  $uploadTime = htmlspecialchars($row['upload_time']);
      ?>
      <tr>
        <td>
          <input type="checkbox" class="rowCheckbox" value="<?php echo $row['tr_id']; ?>">
        </td>
        <td>
          <strong><?php echo $patientName; ?></strong><br>
          Barcode: <?php echo $barcode; ?>
        </td>
        <td>
          <strong><?php echo $studyDetails; ?></strong><br>
          Uploaded: <?php echo $uploadTime; ?>
        </td>
        <td>
          <!-- Clients Details placeholder; add details as needed -->
          N/A
        </td>
        <td>
          <!-- Form to assign a doctor to this test result -->
          <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <input type="hidden" name="test_result_id" value="<?php echo $row['tr_id']; ?>">
            <div class="input-group">
              <select name="doctor_id" class="form-select" required>
                <option value="">Select Doctor</option>
                <?php foreach($doctors as $doc) { ?>
                  <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['name']); ?></option>
                <?php } ?>
              </select>
              <button type="submit" name="assign" class="btn btn-primary">Assign</button>
            </div>
          </form>
        </td>
        <td>
          <!-- Report Details: show upload time and placeholders for additional details -->
          <small>Uploaded: <?php echo $uploadTime; ?></small><br>
          <small>Assigned: N/A</small><br>
          <button class="btn btn-secondary btn-sm mt-1">Report</button>
        </td>
        <td>
          <!-- Actions Dropdown -->
          <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
              Actions
            </button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#">TAT</a></li>
              <li><a class="dropdown-item" href="#">Share Study</a></li>
              <li><a class="dropdown-item" href="#">Edit Patient</a></li>
              <li><a class="dropdown-item" href="#">Close Study</a></li>
              <li><a class="dropdown-item" href="#">Report History</a></li>
            </ul>
          </div>
        </td>
        <td>
          <!-- Alerts: placeholder for any doctor-reported issues -->
          <span class="badge bg-warning text-dark">No Alerts</span>
        </td>
      </tr>
      <?php } 
            } else { ?>
      <tr>
        <td colspan="8" class="text-center">No scanned patients awaiting assignment.</td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php include 'footer.php'?>
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript for "Select All" checkbox functionality
document.getElementById('selectAll').addEventListener('click', function() {
  let checkboxes = document.querySelectorAll('.rowCheckbox');
  checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});
</script>
</body>
</html>
<?php
// Close the database connection
$conn->close();
?>
