<?php
// registration_success.php

// Connect to DB
$host = "localhost";
$dbname = "u740329344_rlis";
$user = "u740329344_rlis";
$password = "Rlis@7030";

$mysqli = new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// Get IDs from URL
$patient_id = intval($_GET['patient_id'] ?? 0);
$invoice_id = intval($_GET['invoice_id'] ?? 0);

if (!$patient_id || !$invoice_id) {
    die("<div class='alert alert-danger'>Invalid access.</div>");
}

// Fetch patient info
$patient = $mysqli->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

// Fetch invoice info
$invoice = $mysqli->query("SELECT * FROM invoices WHERE id = $invoice_id")->fetch_assoc();

// Fetch invoice items
$items = [];
$result = $mysqli->query("SELECT * FROM invoice_items WHERE invoice_id = $invoice_id");
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .print-btn { margin-top: 20px; }
    </style>
</head>
<body class="bg-light">
   <?php include('header.php')

?>
<div class="container mt-4">
    <div class="card p-4">
        <h2 class="text-success text-center">🎉 Registration Successful!</h2>
        <p class="text-center mb-4">Patient has been registered successfully. Below are the details:</p>

        <!-- Patient Info -->
        <div class="mb-4">
            <h5>🧍 Patient Information</h5>
            <ul class="list-group">
                <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></li>
                <li class="list-group-item"><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></li>
                <li class="list-group-item"><strong>Date of Birth:</strong> <?= htmlspecialchars($patient['dob']) ?></li>
                <li class="list-group-item"><strong>Phone:</strong> <?= htmlspecialchars($patient['phone']) ?></li>
                <li class="list-group-item"><strong>Barcode:</strong> <?= htmlspecialchars($patient['barcode']) ?></li>
            </ul>
        </div>

        <!-- Invoice Info -->
        <div class="mb-4">
            <h5>🧾 Invoice Summary</h5>
            <ul class="list-group">
                <li class="list-group-item"><strong>Total Amount:</strong> ₦<?= number_format($invoice['total_amount'], 2) ?></li>
                <li class="list-group-item"><strong>Discount:</strong> ₦<?= number_format($invoice['discount'], 2) ?></li>
                <li class="list-group-item"><strong>Final Amount:</strong> ₦<?= number_format($invoice['final_amount'], 2) ?></li>
                <li class="list-group-item"><strong>Status:</strong> <?= ucfirst($invoice['status']) ?></li>
                <li class="list-group-item"><strong>Date:</strong> <?= date('F j, Y h:i A', strtotime($invoice['invoice_date'])) ?></li>
            </ul>
        </div>

        <!-- Services -->
        <div class="mb-4">
            <h5>🧪 Selected Services</h5>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Price (₦)</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['test_name']) ?></td>
                        <td><?= number_format($item['price'], 2) ?></td>
                        <td><?= $item['quantity'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="text-center">
            <button class="btn btn-outline-primary print-btn" onclick="window.print()">🖨️ Print Summary</button>
            <a href="dashboard.php" class="btn btn-success ms-3">🏠 Return to Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
