<?php
// patient_registration.php

// Database connection parameters
$host = 'localhost';
$user = 'u740329344_rlis';
$password = 'Rlis@7030';
$dbname = 'u740329344_rlis';

// Create connection
$mysqli = new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve patient details from POST
    $first_name = $_POST['first_name'] ?? '';
    $last_name  = $_POST['last_name'] ?? '';
    $dob        = $_POST['dob'] ?? '';
    $gender     = $_POST['gender'] ?? '';
    $phone      = $_POST['phone'] ?? '';
    $email      = $_POST['email'] ?? '';
    $barcode    = $_POST['barcode'] ?? '';
    $address    = $_POST['address'] ?? '';

    // Insert patient record
    $stmt = $mysqli->prepare("INSERT INTO patients (first_name, last_name, dob, gender, phone, email, barcode, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $first_name, $last_name, $dob, $gender, $phone, $email, $barcode, $address);
    $stmt->execute();
    $patient_id = $stmt->insert_id;
    $stmt->close();

    // Process selected services (tests)
    $selected_services = $_POST['services'] ?? [];
    $total_amount = 0.0;

    // For each selected service, fetch its price and insert a test_results record
    foreach ($selected_services as $test_id) {
        // Retrieve price from tests table
        $query = "SELECT price FROM tests WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $stmt->bind_result($price);
        if ($stmt->fetch()) {
            $total_amount += $price;
        }
        $stmt->close();

        // Create test result record with status 'pending'
        $status = 'pending';
        $result = '';
        $stmt = $mysqli->prepare("INSERT INTO test_results (patient_id, test_id, result, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $patient_id, $test_id, $result, $status);
        $stmt->execute();
        $stmt->close();
    }

    // Insert an invoice record for the patient
    $discount = 0.00; // default discount value
    $final_amount = $total_amount - $discount;
    $stmt = $mysqli->prepare("INSERT INTO invoices (patient_id, total_amount, discount, final_amount) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iddd", $patient_id, $total_amount, $discount, $final_amount);
    $stmt->execute();
    $invoice_id = $stmt->insert_id;
    $stmt->close();

    // Success message (could also redirect to another page)
    echo "<div class='alert alert-success'>Patient registered successfully! Invoice ID: $invoice_id</div>";
}

// Fetch available services (tests) from the database
$services = [];
$result = $mysqli->query("SELECT id, name, price FROM tests");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patient Registration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="styles.css" rel="stylesheet">
  <script>
    // Function to update the total amount dynamically
    function updateTotal() {
      let checkboxes = document.querySelectorAll('input[name="services[]"]:checked');
      let total = 0;
      checkboxes.forEach(function(checkbox) {
          total += parseFloat(checkbox.getAttribute('data-price'));
      });
      document.getElementById('totalAmount').textContent = total.toFixed(2);
    }
  </script>
</head>
<body>
  <div class="container mt-5">
    <h2 class="text-center mb-4">Patient Registration</h2>
    <form action="patient_registration.php" method="POST">
      <!-- Patient Details Section -->
      <div class="mb-3">
        <label for="first_name" class="form-label">First Name:</label>
        <input type="text" class="form-control" id="first_name" name="first_name" required>
      </div>
      <div class="mb-3">
        <label for="last_name" class="form-label">Last Name:</label>
        <input type="text" class="form-control" id="last_name" name="last_name" required>
      </div>
      <div class="mb-3">
        <label for="dob" class="form-label">Date of Birth:</label>
        <input type="date" class="form-control" id="dob" name="dob" required>
      </div>
      <div class="mb-3">
        <label for="gender" class="form-label">Gender:</label>
        <select class="form-control" id="gender" name="gender" required>
          <option value="">Select Gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="phone" class="form-label">Phone:</label>
        <input type="text" class="form-control" id="phone" name="phone" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email:</label>
        <input type="email" class="form-control" id="email" name="email">
      </div>
      <div class="mb-3">
        <label for="barcode" class="form-label">Patient Barcode:</label>
        <input type="text" class="form-control" id="barcode" name="barcode" required>
      </div>
      <div class="mb-3">
        <label for="address" class="form-label">Address:</label>
        <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
      </div>
      
      <!-- Services Section -->
      <h4 class="mt-4">Select Services</h4>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Select</th>
            <th>Service Name</th>
            <th>Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($services as $service): ?>
            <tr>
              <td>
                <input type="checkbox" name="services[]" value="<?= $service['id'] ?>" data-price="<?= $service['price'] ?>" onclick="updateTotal()">
              </td>
              <td><?= htmlspecialchars($service['name']) ?></td>
              <td>$<?= number_format($service['price'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="mb-3">
        <strong>Total Amount: $<span id="totalAmount">0.00</span></strong>
      </div>
      <div class="mb-3">
        <p class="text-muted">Note: Selected services will automatically assign the patient to the respective units based on the service's department.</p>
      </div>
      
      <div class="text-center">
        <button type="submit" class="btn btn-primary">Register Patient</button>
      </div>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
