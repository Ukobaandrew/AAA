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
    $phone2     = sanitizeInput($_POST['phone2'] ?? '');
    $email      = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $barcode    = sanitizeInput($_POST['barcode'] ?? '');
    $address    = sanitizeInput($_POST['address'] ?? '');
    $national_id = sanitizeInput($_POST['national_id'] ?? '');
    $title      = sanitizeInput($_POST['title'] ?? '');
    $race       = sanitizeInput($_POST['race'] ?? '');
    $nationality = sanitizeInput($_POST['nationality'] ?? '');
    $passport_no = sanitizeInput($_POST['passport_no'] ?? '');
    $weight     = (float)($_POST['weight'] ?? 0);
    $height     = (float)($_POST['height'] ?? 0);
    $bmi        = (float)($_POST['bmi'] ?? 0);
    $lmp_date   = sanitizeInput($_POST['lmp_date'] ?? '');
    $clinical_data = sanitizeInput($_POST['clinical_data'] ?? '');
    $internal_comment = sanitizeInput($_POST['internal_comment'] ?? '');
    $is_urgent  = isset($_POST['is_urgent']) ? 1 : 0;
    $is_fasting = isset($_POST['is_fasting']) ? 1 : 0;
    $result_delivery = $_POST['result_delivery'] ?? [];
    $selected_services = $_POST['services'] ?? [];
    
    // New customer fields
    $customer_type = sanitizeInput($_POST['customer_type'] ?? '');
    $referral_source = sanitizeInput($_POST['referral_source'] ?? '');
    
    // Collection information
  $collection_point_id = (int)($_POST['collection_point'] ?? 0);

    $collected_by = sanitizeInput($_POST['collected_by'] ?? '');
    $collection_date = sanitizeInput($_POST['collection_date'] ?? '');
    $collection_time = sanitizeInput($_POST['collection_time'] ?? '');
    $received_by = sanitizeInput($_POST['received_by'] ?? '');
    $received_date = sanitizeInput($_POST['received_date'] ?? '');
    $received_time = sanitizeInput($_POST['received_time'] ?? '');
    
    // Referral information
    $referral_organisation = sanitizeInput($_POST['referral_organisation'] ?? '');
    $referral_doctor = sanitizeInput($_POST['referral_doctor'] ?? '');
    
    // Validate required fields
    $required_fields = [
        'First Name' => $first_name,
        'Last Name' => $last_name,
        'Date of Birth' => $dob,
        'Gender' => $gender,
        'Phone' => $phone,
        'Barcode' => $barcode,
        'Address' => $address
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field => $value) {
        if (empty($value)) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        die("<div class='alert alert-danger'>Please fill in all required fields: " . implode(', ', $missing_fields) . "</div>");
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
    (first_name, last_name, dob, gender, phone, phone2, email, barcode, address, 
    national_id, title, race, nationality, passport_no, weight, height, bmi, 
    lmp_date, clinical_data, internal_comment, is_urgent, is_fasting, 
    customer_type, referral_source, collection_point_id, collected_by, collection_date, 
    collection_time, received_by, received_date, received_time, referral_organisation, 
    referral_doctor, services, result_delivery, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

if (!$stmt) {
    throw new Exception("Prepare failed (patient insert): " . $mysqli->error);
}

// Corrected bind_param format string: 27s, 3d, 4i = 34 total
// 34 variables = 34 format characters
$stmt->bind_param("ssssssssssssssdddssiiisssssssssssss", 
    $first_name, $last_name, $dob, $gender, $phone, $phone2, $email, $barcode, $address,
    $national_id, $title, $race, $nationality, $passport_no, $weight, $height, $bmi,
    $lmp_date, $clinical_data, $internal_comment, $is_urgent, $is_fasting,
    $customer_type, $referral_source, $collection_point_id, $collected_by, $collection_date,
    $collection_time, $received_by, $received_date, $received_time, $referral_organisation,
    $referral_doctor, $services_json, $result_delivery_json);

            
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
                (patient_id, total_amount, discount, final_amount, created_at, status) 
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
            $result_delivery_json = json_encode($result_delivery);
            
       $collection_point_id = (int)($_POST['collection_point'] ?? 0);
$services_json = json_encode($selected_services);
$result_delivery_json = json_encode($result_delivery);

$stmt = $mysqli->prepare("INSERT INTO patient_drafts 
    (first_name, last_name, dob, gender, phone, phone2, email, barcode, address, 
    national_id, title, race, nationality, passport_no, weight, height, bmi, 
    lmp_date, clinical_data, internal_comment, is_urgent, is_fasting, 
    customer_type, referral_source, collection_point_id, collected_by, collection_date, 
    collection_time, received_by, received_date, received_time, referral_organisation, 
    referral_doctor, services, result_delivery, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

if (!$stmt) {
    throw new Exception("Prepare failed (draft): " . $mysqli->error);
}

// 34 variables = 34 format characters
$stmt->bind_param("ssssssssssssssdddssiiisssssssssssss", 
    $first_name, $last_name, $dob, $gender, $phone, $phone2, $email, $barcode, $address,
    $national_id, $title, $race, $nationality, $passport_no, $weight, $height, $bmi,
    $lmp_date, $clinical_data, $internal_comment, $is_urgent, $is_fasting,
    $customer_type, $referral_source, $collection_point_id, $collected_by, $collection_date,
    $collection_time, $received_by, $received_date, $received_time, $referral_organisation,
    $referral_doctor, $services_json, $result_delivery_json);

            
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

// Fetch collection points
$collection_points = [];
$result = $mysqli->query("SELECT id, name FROM collection_points ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $collection_points[$row['id']] = $row['name'];
    }
    $result->free();
}

// Fetch customer types
$customer_types = [
    '' => 'Select Customer Type',
    '1' => 'INITIAL',
    '2' => 'FOLLOW-UP',
    '3' => 'REPEAT',
    '4' => 'DAY 2',
    '5' => 'DAY 7'
];

// Fetch referral sources
$referral_sources = [
    '' => 'Select Referral Source',
    '1' => 'Facebook',
    '2' => 'Dr Referral',
    '3' => 'Family/Friend Referral',
    '4' => 'Radio Jingle',
    '5' => 'Events',
    '6' => 'Web',
    '7' => 'Other Social media platforms'
];

// Fetch races
$races = [
    '' => 'Select Race',
    '1' => 'Black',
    '2' => 'Caucasian',
    '3' => 'Indian',
    '4' => 'Asian'
];

// Fetch titles
$titles = [
    '' => 'Select Title',
    '1' => 'Mr',
    '3' => 'Miss',
    '26' => 'Ms',
    '27' => 'Mrs',
    '6' => 'Dr.',
    '8' => 'Prof.'
];

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

    #referralPreview embed,
    #referralPreview img {
        border: 1px solid #dee2e6;
        box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }


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
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-right: 15px;
    }
    .checkbox-group input {
        margin-right: 5px;
    }
    .form-tabs {
        margin-bottom: 20px;
    }
    .form-tab {
        padding: 10px 20px;
        cursor: pointer;
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-bottom: none;
        margin-right: 5px;
        border-radius: 4px 4px 0 0;
    }
    .form-tab.active {
        background-color: white;
        border-bottom: 1px solid white;
        margin-bottom: -1px;
        font-weight: bold;
        color: #2c5fa8;
    }
    .tab-content {
        display: none;
        padding: 20px;
        border: 1px solid #ddd;
        border-top: none;
        background-color: white;
    }
    .tab-content.active {
        display: block;
    }
  </style>
</head>
<body>
    <?php include 'header.php'?>
    
  <div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4"><i class="bi bi-person-plus"></i> Patient Registration</h2>
            
            <form id="patientRegistrationForm" action="patients.php" method="POST" autocomplete="off">
              <!-- Personal Information Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-person-vcard"></i> Personal Information</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="first_name" class="form-label required-field">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required style="text-transform: uppercase;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="last_name" class="form-label required-field">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required style="text-transform: uppercase;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <select class="form-control" id="title" name="title">
                                <?php foreach ($titles as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="national_id" class="form-label">National ID</label>
                            <input type="text" class="form-control" id="national_id" name="national_id">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="race" class="form-label">Race</label>
                            <select class="form-control" id="race" name="race">
                                <?php foreach ($races as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="dob" class="form-label required-field">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="gender" class="form-label required-field">Gender</label>
                            <select class="form-control" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
              </div>
              
              <!-- Contact Information Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-telephone"></i> Contact Information</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="phone" class="form-label required-field">Phone 1</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="phone2" class="form-label">Phone 2</label>
                            <input type="tel" class="form-control" id="phone2" name="phone2">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="address" class="form-label required-field">Address</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="nationality" class="form-label">Nationality</label>
                            <input type="text" class="form-control" id="nationality" name="nationality">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="passport_no" class="form-label">Passport No.</label>
                            <input type="text" class="form-control" id="passport_no" name="passport_no">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="customer_type" class="form-label">Customer Type</label>
                            <select class="form-control" id="customer_type" name="customer_type">
                                <?php foreach ($customer_types as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4" id="referral_source_group">
                        <div class="mb-3">
                            <label for="referral_source" class="form-label">Referral Source</label>
                            <select class="form-control" id="referral_source" name="referral_source">
                                <?php foreach ($referral_sources as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
              </div>
              
              <!-- Barcode & Identification Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-upc-scan"></i> Barcode & Identification</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="barcode" class="form-label required-field">Barcode No</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcode" name="barcode" required>
                                <button type="button" class="btn btn-primary" id="generateBarcode">
                                    <i class="bi bi-upc"></i> Generate
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="barcode-display" id="barcodePreview">[Barcode Preview]</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label>Result Delivery Options</label>
                            <div class="d-flex flex-wrap">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="result_keep" name="result_delivery[]" value="keep">
                                    <label for="result_keep" style="font-weight: normal;">Keep for patient</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="result_send" name="result_delivery[]" value="send">
                                    <label for="result_send" style="font-weight: normal;">Send to hospital</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="result_email" name="result_delivery[]" value="email">
                                    <label for="result_email" style="font-weight: normal;">Email</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
              
              <!-- Collection Information Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-collection"></i> Collection Information</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="collection_point" class="form-label required-field">Collection Point</label>
                            <select class="form-control" id="collection_point" name="collection_point" required>
                                <?php foreach ($collection_points as $id => $name): ?>
                                    <option value="<?= $id ?>"><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="collected_by" class="form-label">Collected By</label>
                            <input type="text" class="form-control" id="collected_by" name="collected_by">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="collection_date" class="form-label">Collection Date</label>
                            <input type="date" class="form-control" id="collection_date" name="collection_date">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="collection_time" class="form-label">Collection Time</label>
                            <input type="time" class="form-control" id="collection_time" name="collection_time">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="received_by" class="form-label">Received By</label>
                            <input type="text" class="form-control" id="received_by" name="received_by">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="received_date" class="form-label">Received Date</label>
                            <input type="date" class="form-control" id="received_date" name="received_date">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="received_time" class="form-label">Received Time</label>
                            <input type="time" class="form-control" id="received_time" name="received_time">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="referral_organisation" class="form-label">Referral Organisation</label>
                            <input type="text" class="form-control" id="referral_organisation" name="referral_organisation">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="referral_doctor" class="form-label">Referral Doctor</label>
                            <input type="text" class="form-control" id="referral_doctor" name="referral_doctor">
                        </div>
                    </div>
                </div>
              </div>
              
              <!-- Medical Information Section -->
              <div class="form-section">
                <h4 class="mb-4"><i class="bi bi-heart-pulse"></i> Medical Information</h4>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="height" class="form-label">Height (cm)</label>
                            <input type="number" step="0.1" class="form-control" id="height" name="height">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="bmi" class="form-label">BMI (kg/m²)</label>
                            <input type="number" step="0.1" class="form-control" id="bmi" name="bmi" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="lmp_date" class="form-label">LMP Date</label>
                            <input type="date" class="form-control" id="lmp_date" name="lmp_date">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="clinical_data" class="form-label">Clinical Data</label>
                            <textarea class="form-control" id="clinical_data" name="clinical_data" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="internal_comment" class="form-label">Internal Commentary</label>
                            <textarea class="form-control" id="internal_comment" name="internal_comment" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label>Options</label>
                            <div class="d-flex flex-wrap">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_urgent" name="is_urgent" value="1">
                                    <label for="is_urgent" style="font-weight: normal;">Urgent</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_fasting" name="is_fasting" value="1">
                                    <label for="is_fasting" style="font-weight: normal;">Fasting</label>
                                </div>
                            </div>
                        </div>
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
                
<div id="servicesList" style="display:block;">
    <?php if (!empty($categories)): ?>
        <!-- Department Dropdown -->
        <div class="form-group mb-3">
            <label for="departmentSelect"><strong>Filter by Department (optional)</strong></label>
            <select id="departmentSelect" class="form-control">
                <option value="">-- All Departments --</option>
                <?php foreach ($categories as $cat_id => $cat_name): ?>
                    <option value="<?= $cat_id ?>"><?= htmlspecialchars($cat_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Search input with search and reset buttons -->
        <div class="input-group mb-3">
            <input type="text" id="serviceSearch" class="form-control" placeholder="Search service by any field...">
            <button class="btn btn-primary" id="searchBtn">
                <i class="bi bi-search"></i>
            </button>
            <button class="btn btn-secondary" id="resetBtn">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>

        <!-- Services output -->
        <div id="serviceCheckboxContainer" class="row text-center text-muted">
            <div class="col-12">No services to display. Start by searching.</div>
        </div>

        <!-- Total -->
        <div class="total-display mt-3">
            Total Amount: $<span id="totalAmount">0.00</span>
        </div>

        <!-- Info -->
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle"></i> Selected services will automatically assign the patient to the respective departments.
        </div>


        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <?php else: ?>
        <div class="alert alert-info">No service categories found.</div>
    <?php endif; ?>
</div>

             </div>
              <!-- Referral Attachment Section -->
<!-- Referral Attachment Section -->
<div class="form-group mt-4">
    <label for="referralFile"><strong>Attach Referral Document (Optional)</strong></label>
    <input type="file" id="referralFile" name="referral_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
    <small class="form-text text-muted">Accepted: PDF, JPG, PNG. Max: 5MB.</small>

    <!-- File Preview Area -->
    <div id="referralPreview" class="mt-3"></div>
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
    </div>
  </div>
  
  <?php include 'footer.php'?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
        <!-- Script -->
        <script>
            const allServices = <?= json_encode($services) ?>;
            const categories = <?= json_encode($categories) ?>;

            const searchInput = document.getElementById('serviceSearch');
            const searchBtn = document.getElementById('searchBtn');
            const resetBtn = document.getElementById('resetBtn');
            const departmentSelect = document.getElementById('departmentSelect');
            const container = document.getElementById('serviceCheckboxContainer');

            // Initial blank display
            container.innerHTML = '<div class="col-12 text-muted">No services to display. Start by searching.</div>';

            // Search trigger
            searchBtn.addEventListener('click', () => performSearch(searchInput.value.trim().toLowerCase()));
            resetBtn.addEventListener('click', resetSearch);
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch(searchInput.value.trim().toLowerCase());
                }
            });
            searchInput.addEventListener('input', () => {
                performSearch(searchInput.value.trim().toLowerCase());
            });

            function performSearch(keyword) {
                const selectedDeptId = departmentSelect.value;
                let results = allServices;

                // Apply search filter
                if (keyword !== '') {
                    results = results.filter(service => {
                        const values = Object.values(service).join(' ').toLowerCase();
                        return values.includes(keyword);
                    });
                }

                // Apply department filter if selected
                if (selectedDeptId !== '') {
                    results = results.filter(service => service.department_id == selectedDeptId);
                }

                renderGroupedServices(results);
            }

            function resetSearch() {
                searchInput.value = '';
                departmentSelect.value = '';
                container.innerHTML = '<div class="col-12 text-muted">No services to display. Start by searching.</div>';
                updateTotal();
            }

            function renderGroupedServices(services) {
                container.innerHTML = '';

                if (services.length === 0) {
                    container.innerHTML = '<div class="col-12"><div class="alert alert-warning">No services found.</div></div>';
                    return;
                }

                // Group by department
                const grouped = {};
                services.forEach(service => {
                    if (!grouped[service.department_id]) {
                        grouped[service.department_id] = [];
                    }
                    grouped[service.department_id].push(service);
                });

                for (const deptId in grouped) {
                    const deptName = categories[deptId] || 'Other';
                    const group = grouped[deptId];

                    container.insertAdjacentHTML('beforeend', `
                        <div class="col-12 mt-4">
                          <h5 style="color: black;"><strong>${deptName}</strong></h5>

                            <hr>
                        </div>
                    `);

                    group.forEach(service => {
                        const serviceHtml = `
                            <div class="col-md-6">
                                <div class="service-item">
                                    <div class="form-check">
                                        <input class="form-check-input service-checkbox" 
                                               type="checkbox" 
                                               name="services[]" 
                                               value="${service.id}" 
                                               data-price="${service.price}" 
                                               id="service_${service.id}">
                                        <label class="form-check-label" for="service_${service.id}">
                                            ${service.name} 
                                            <span class="text-muted">($${parseFloat(service.price).toFixed(2)})</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', serviceHtml);
                    });
                }

                updateTotal();
                attachCheckboxListeners();
            }

            function attachCheckboxListeners() {
                document.querySelectorAll('.service-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateTotal);
                });
            }

            function updateTotal() {
                let total = 0;
                document.querySelectorAll('.service-checkbox:checked').forEach(cb => {
                    total += parseFloat(cb.getAttribute('data-price'));
                });
                document.getElementById('totalAmount').textContent = total.toFixed(2);
            }
        </script>
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
    
    // Calculate BMI when weight or height changes
    function calculateBMI() {
        const weight = parseFloat(document.getElementById('weight').value) || 0;
        const height = parseFloat(document.getElementById('height').value) || 0;
        
        if (weight > 0 && height > 0) {
            const heightInMeters = height / 100;
            const bmi = weight / (heightInMeters * heightInMeters);
            document.getElementById('bmi').value = bmi.toFixed(1);
        } else {
            document.getElementById('bmi').value = '';
        }
    }
    
    document.getElementById('weight').addEventListener('input', calculateBMI);
    document.getElementById('height').addEventListener('input', calculateBMI);
    
    // Show/hide referral source based on customer type
    document.getElementById('customer_type').addEventListener('change', function() {
        const referralSourceGroup = document.getElementById('referral_source_group');
        if (this.value === '1') { // INITIAL customer type
            referralSourceGroup.style.display = 'block';
        } else {
            referralSourceGroup.style.display = 'none';
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
    
    document.getElementById('phone2').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        this.value = value;
    });
    
  </script>
  
</body>
</html>