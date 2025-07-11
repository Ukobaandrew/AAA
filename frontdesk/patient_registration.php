<?php 
// patient_registration.php

// Database connection parameters
$host = "localhost";
$dbname = "u740329344_rlis";
$user = "u740329344_rlis";
$password = "Rlis@7030";

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with improved error handling
try {
    $mysqli = new mysqli($host, $user, $password, $dbname);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $mysqli->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Helper function for sanitizing input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize all inputs
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name  = sanitizeInput($_POST['last_name'] ?? '');
    $dob        = sanitizeInput($_POST['dob'] ?? '');
    $gender     = sanitizeInput($_POST['gender'] ?? '');
    $phone      = sanitizeInput($_POST['phone'] ?? '');
    $email      = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $barcode    = sanitizeInput($_POST['barcode'] ?? '');
    $address    = sanitizeInput($_POST['address'] ?? '');
    $selected_services = $_POST['services'] ?? [];
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || 
        empty($phone) || empty($barcode) || empty($address)) {
        die("<div class='alert alert-danger'>Please fill in all required fields.</div>");
    }
    
    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        die("<div class='alert alert-danger'>Invalid date format for Date of Birth.</div>");
    }
    
    // Determine which action was submitted
    $action = $_POST['action'] ?? '';
    
    // Start transaction for atomic operations
    $mysqli->begin_transaction();
    
    try {
        if ($action === 'register') {
            // Full registration process
            $stmt = $mysqli->prepare("INSERT INTO patients 
                (first_name, last_name, dob, gender, phone, email, barcode, address, registration_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
            if (!$stmt) {
                throw new Exception("Prepare failed (patient insert): " . $mysqli->error);
            }
            
            $stmt->bind_param("ssssssss", $first_name, $last_name, $dob, $gender, $phone, $email, $barcode, $address);
            
            if (!$stmt->execute()) {
                throw new Exception("Patient insert error: " . $stmt->error);
            }
            
            $patient_id = $stmt->insert_id;
            $stmt->close();

            $total_amount = 0.0;
            $service_details = [];
            
            // Process selected services (tests)
            foreach ($selected_services as $test_id) {
                $test_id = (int)$test_id; // Ensure integer
                
                // Retrieve price and name from tests table
                $query = "SELECT id, name, price, department_id FROM tests WHERE id = ?";
                $stmt = $mysqli->prepare($query);
                
                if (!$stmt) {
                    throw new Exception("Prepare failed (select test price): " . $mysqli->error);
                }
                
                $stmt->bind_param("i", $test_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (select test price): " . $stmt->error);
                }
                
                $stmt->bind_result($id, $name, $price, $department_id);
                
                if ($stmt->fetch()) {
                    $total_amount += $price;
                    $service_details[] = [
                        'id' => $id,
                        'name' => $name,
                        'price' => $price,
                        'department_id' => $department_id
                    ];
                }
                
                $stmt->close();

                // Create test result record with status 'pending'
                $status = 'pending';
                $result_text = '';
                $stmt = $mysqli->prepare("INSERT INTO test_results 
                    (patient_id, test_id, result, status, created_at) 
                    VALUES (?, ?, ?, ?, NOW())");
                    
                if (!$stmt) {
                    throw new Exception("Prepare failed (insert test result): " . $mysqli->error);
                }
                
                $stmt->bind_param("iiss", $patient_id, $test_id, $result_text, $status);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (insert test result): " . $stmt->error);
                }
                
                $stmt->close();
                
                // Optionally assign patient to department queue
                if ($department_id) {
                    $stmt = $mysqli->prepare("INSERT INTO department_queue 
                        (patient_id, department_id, status, created_at) 
                        VALUES (?, ?, 'pending', NOW())");
                        
                    if ($stmt) {
                        $stmt->bind_param("ii", $patient_id, $department_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // Insert an invoice record for the patient
            $discount = 0.00; // default discount value
            $final_amount = $total_amount - $discount;
            $stmt = $mysqli->prepare("INSERT INTO invoices 
                (patient_id, total_amount, discount, final_amount, invoice_date, status) 
                VALUES (?, ?, ?, ?, NOW(), 'unpaid')");
                
            if (!$stmt) {
                throw new Exception("Prepare failed (invoice insert): " . $mysqli->error);
            }
            
            $stmt->bind_param("iddd", $patient_id, $total_amount, $discount, $final_amount);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (invoice insert): " . $stmt->error);
            }
            
            $invoice_id = $stmt->insert_id;
            $stmt->close();

            // Insert invoice items
            foreach ($service_details as $service) {
                $stmt = $mysqli->prepare("INSERT INTO invoice_items 
                    (invoice_id, test_id, test_name, price, quantity) 
                    VALUES (?, ?, ?, ?, 1)");
                    
                if (!$stmt) {
                    throw new Exception("Prepare failed (invoice items): " . $mysqli->error);
                }
                
                $stmt->bind_param("iisd", $invoice_id, $service['id'], $service['name'], $service['price']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed (invoice items): " . $stmt->error);
                }
                
                $stmt->close();
            }
            
            // Generate patient ID card if needed
            generatePatientIDCard($patient_id, $first_name, $last_name, $barcode);
            
            $mysqli->commit();
            
            // Redirect to success page with patient ID
            header("Location: registration_success.php?patient_id=$patient_id&invoice_id=$invoice_id");
            exit();
            
        } elseif ($action === 'draft') {
            // Save to Draft process
            $services_json = json_encode($selected_services);
            $stmt = $mysqli->prepare("INSERT INTO patient_drafts 
                (first_name, last_name, dob, gender, phone, email, barcode, address, services, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
            if (!$stmt) {
                throw new Exception("Prepare failed (draft): " . $mysqli->error);
            }
            
            $stmt->bind_param("sssssssss", $first_name, $last_name, $dob, $gender, $phone, $email, $barcode, $address, $services_json);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed (draft): " . $stmt->error);
            }
            
            $draft_id = $stmt->insert_id;
            $stmt->close();
            
            $mysqli->commit();
            
            echo "<div class='alert alert-success'>Draft saved successfully! Draft ID: $draft_id</div>";
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
    }
}

// Function to generate patient ID card (placeholder)
function generatePatientIDCard($patient_id, $first_name, $last_name, $barcode) {
    // In a real implementation, this would generate a PDF or image
    // For now, we'll just log the action
    error_log("Generated ID card for patient $patient_id: $first_name $last_name ($barcode)");
}

// Fetch available services (tests) from the database with categories
$services = [];
$categories = [];

$result = $mysqli->query("SELECT id, name, price, department_id FROM tests ORDER BY department_id, name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $result->free();
}

// Fetch departments for categorization
$result = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
    $result->free();
}

// Fetch recent drafts if needed
$drafts = [];
$result = $mysqli->query("SELECT id, first_name, last_name, created_at FROM patient_drafts ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $drafts[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patient Registration - RLIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
  <style>
    .service-category {
        background-color: #f8f9fa;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        border-left: 4px solid #0d6efd;
    }
    .service-item {
        padding: 8px;
        border-bottom: 1px solid #eee;
    }
    .service-item:hover {
        background-color: #f1f1f1;
    }
    .total-display {
        font-size: 1.2rem;
        font-weight: bold;
        color: #0d6efd;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }
    .barcode-display {
        font-family: 'Libre Barcode 39', monospace;
        font-size: 2rem;
        text-align: center;
        margin: 10px 0;
    }
    .form-section {
        margin-bottom: 30px;
        padding: 20px;
        background-color: #fff;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    .required-field::after {
        content: " *";
        color: red;
    }
  </style>
</head>
<body>
    <?php include 'header.php'?>
    
  <div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h2 class="mb-4"><i class="bi bi-person-plus"></i> Patient Registration</h2>
            
            <form id="patientRegistrationForm" action="patient_registration.php" method="POST" autocomplete="off">
              <!-- Patient Details Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-person-vcard"></i> Personal Information</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label required-field">First Name:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label required-field">Last Name:</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="dob" class="form-label required-field">Date of Birth:</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="gender" class="form-label required-field">Gender:</label>
                            <select class="form-control" id="gender" name="gender" required>
                              <option value="">Select Gender</option>
                              <option value="male">Male</option>
                              <option value="female">Female</option>
                              <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="phone" class="form-label required-field">Phone:</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label required-field">Address:</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                </div>
              </div>
              
              <!-- Barcode Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-upc-scan"></i> Patient Identification</h4>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="barcode" class="form-label required-field">Patient Barcode/ID:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcode" name="barcode" required>
                                <button type="button" class="btn btn-primary" id="generateBarcode">
                                    <i class="bi bi-upc"></i> Generate
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="barcode-display" id="barcodePreview">[Barcode Preview]</div>
                    </div>
                </div>
              </div>
              
              <!-- Services Section -->
              <div class="form-section" id="servicesSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="bi bi-clipboard2-pulse"></i> Services & Tests</h4>
                    <button type="button" class="btn btn-outline-primary" id="toggleServicesBtn">
                        <i class="bi bi-list-check"></i> Select Services
                    </button>
                </div>
                
                <div id="servicesList" style="display:none;">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat_id => $cat_name): ?>
                            <div class="service-category">
                                <h5><?= htmlspecialchars($cat_name) ?></h5>
                                <div class="row">
                                    <?php foreach ($services as $service): ?>
                                        <?php if ($service['department_id'] == $cat_id): ?>
                                            <div class="col-md-6">
                                                <div class="service-item">
                                                    <div class="form-check">
                                                        <input class="form-check-input service-checkbox" 
                                                               type="checkbox" 
                                                               name="services[]" 
                                                               value="<?= $service['id'] ?>" 
                                                               data-price="<?= $service['price'] ?>" 
                                                               id="service_<?= $service['id'] ?>">
                                                        <label class="form-check-label" for="service_<?= $service['id'] ?>">
                                                            <?= htmlspecialchars($service['name']) ?>
                                                            <span class="text-muted">($<?= number_format($service['price'], 2) ?>)</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No service categories found.</div>
                    <?php endif; ?>
                    
                    <div class="total-display mt-3">
                        Total Amount: $<span id="totalAmount">0.00</span>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> Selected services will automatically assign the patient to the respective departments.
                    </div>
                </div>
              </div>
              
              <!-- Submit Buttons -->
              <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button type="submit" name="action" value="draft" class="btn btn-secondary me-md-2">
                    <i class="bi bi-save"></i> Save Draft
                </button>
                <button type="submit" name="action" value="register" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Complete Registration
                </button>
              </div>
            </form>
        </div>
        
        <div class="col-md-4">
            <!-- Quick Help Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-question-circle"></i> Quick Help
                </div>
                <div class="card-body">
                    <h5 class="card-title">Registration Guidelines</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">All fields marked with * are required</li>
                        <li class="list-group-item">Use the barcode generator for new patients</li>
                        <li class="list-group-item">Save as draft for incomplete registrations</li>
                        <li class="list-group-item">Double-check patient details before submission</li>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Drafts Section -->
            <?php if (!empty($drafts)): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-files"></i> Recent Drafts
                </div>
                <div class="card-body">
                    <h5 class="card-title">Uncompleted Registrations</h5>
                    <div class="list-group">
                        <?php foreach ($drafts as $draft): ?>
                            <a href="#" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($draft['first_name'] . ' ' . $draft['last_name']) ?></h6>
                                    <small><?= date('M d, Y', strtotime($draft['created_at'])) ?></small>
                                </div>
                                <small class="text-muted">Draft ID: <?= $draft['id'] ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
  
  <?php include 'footer.php'?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Function to update the total amount dynamically
    function updateTotal() {
        let checkboxes = document.querySelectorAll('.service-checkbox:checked');
        let total = 0;
        
        checkboxes.forEach(function(checkbox) {
            total += parseFloat(checkbox.getAttribute('data-price'));
        });
        
        document.getElementById('totalAmount').textContent = total.toFixed(2);
    }
    
    // Toggle services visibility
    document.getElementById('toggleServicesBtn').addEventListener('click', function() {
        const servicesList = document.getElementById('servicesList');
        const icon = this.querySelector('i');
        
        if (servicesList.style.display === 'none') {
            servicesList.style.display = 'block';
            icon.classList.remove('bi-list-check');
            icon.classList.add('bi-x-circle');
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
        } else {
            servicesList.style.display = 'none';
            icon.classList.remove('bi-x-circle');
            icon.classList.add('bi-list-check');
            this.classList.remove('btn-primary');
            this.classList.add('btn-outline-primary');
        }
    });
    
    // Generate barcode
    document.getElementById('generateBarcode').addEventListener('click', function() {
        // Generate a random 9-digit number for barcode
        const randomBarcode = Math.floor(100000000 + Math.random() * 900000000).toString();
        document.getElementById('barcode').value = randomBarcode;
        document.getElementById('barcodePreview').textContent = `*${randomBarcode}*`;
    });
    
    // Update barcode preview on input
    document.getElementById('barcode').addEventListener('input', function() {
        const barcodeValue = this.value.trim();
        if (barcodeValue) {
            document.getElementById('barcodePreview').textContent = `*${barcodeValue}*`;
        } else {
            document.getElementById('barcodePreview').textContent = '[Barcode Preview]';
        }
    });
    
    // Attach event listeners to all service checkboxes
    document.querySelectorAll('.service-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateTotal);
    });
    
    // Form validation
    document.getElementById('patientRegistrationForm').addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Validate phone number format
        const phoneField = document.getElementById('phone');
        const phoneRegex = /^[0-9]{10,15}$/;
        if (!phoneRegex.test(phoneField.value.trim())) {
            isValid = false;
            phoneField.classList.add('is-invalid');
        } else {
            phoneField.classList.remove('is-invalid');
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
        }
    });
    
    // Auto-format phone number
    document.getElementById('phone').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        this.value = value;
    });
  </script>
</body>
</html>