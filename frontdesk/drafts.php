<?php
// drafts.php - Patient Drafts Management

// Database connection
$host = 'localhost';
$dbname = 'u740329344_rlis';
$username = 'u740329344_rlis';
$password = 'Rlis@7030';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize variables
$draftData = [
    'id'         => '',
    'first_name' => '',
    'last_name'  => '',
    'dob'        => '',
    'gender'     => '',
    'phone'      => '',
    'email'      => '',
    'barcode'    => '',
    'address'    => '',
    'services'   => []
];
$registrationMsg = '';
$success = false;
$showRegistrationForm = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register') {
        try {
            // Begin transaction
            $conn->beginTransaction();

            // Insert patient
            $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, dob, gender, phone, email, barcode, address) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['dob'],
                $_POST['gender'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['barcode'],
                $_POST['address']
            ]);
            $patient_id = $conn->lastInsertId();

            // Process services and calculate total
            $total_amount = 0;
            $selected_services = $_POST['services'] ?? [];
            
            foreach ($selected_services as $test_id) {
                // Get test price
                $stmt = $conn->prepare("SELECT price FROM tests WHERE id = ?");
                $stmt->execute([$test_id]);
                $price = $stmt->fetchColumn();
                $total_amount += $price;

                // Create test result
                $stmt = $conn->prepare("INSERT INTO test_results (patient_id, test_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$patient_id, $test_id]);
            }

            // Create invoice
            $stmt = $conn->prepare("INSERT INTO invoices (patient_id, total_amount, discount, final_amount, status) 
                                   VALUES (?, ?, 0, ?, 'unpaid')");
            $stmt->execute([$patient_id, $total_amount, $total_amount]);

            // Delete draft if exists
            if (!empty($_POST['draft_id'])) {
                $stmt = $conn->prepare("DELETE FROM patient_drafts WHERE id = ?");
                $stmt->execute([$_POST['draft_id']]);
            }

            $conn->commit();
            $registrationMsg = "Patient registered successfully!";
            $success = true;
            
            // Reset form
            $draftData = [
                'id'         => '',
                'first_name' => '',
                'last_name'  => '',
                'dob'        => '',
                'gender'     => '',
                'phone'      => '',
                'email'      => '',
                'barcode'    => '',
                'address'    => '',
                'services'   => []
            ];
            
        } catch(PDOException $e) {
            $conn->rollBack();
            $registrationMsg = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_draft') {
        try {
            $stmt = $conn->prepare("DELETE FROM patient_drafts WHERE id = ?");
            $stmt->execute([$_POST['draft_id']]);
            $registrationMsg = "Draft deleted successfully!";
            $success = true;
        } catch(PDOException $e) {
            $registrationMsg = "Error deleting draft: " . $e->getMessage();
        }
    }
}

// Search functionality
$searchQuery = $_GET['search'] ?? '';
$query = "SELECT * FROM patient_drafts";
$params = [];

if (!empty($searchQuery)) {
    $query .= " WHERE first_name LIKE ? OR last_name LIKE ? OR barcode LIKE ?";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}

$query .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load draft if specified
if (isset($_GET['draft_id']) && !empty($_GET['draft_id'])) {
    $stmt = $conn->prepare("SELECT * FROM patient_drafts WHERE id = ?");
    $stmt->execute([$_GET['draft_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $draftData = $row;
        $draftData['services'] = json_decode($row['services'], true) ?: [];
        $showRegistrationForm = true;
    }
}

// Get all available services
$services = [];
$stmt = $conn->query("SELECT id, name, price FROM tests ORDER BY name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page variables for header
$page_title = "Patient Drafts";
$page_icon = "fas fa-file-alt";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> | RLIS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .draft-card {
      transition: all 0.3s ease;
      border-left: 4px solid #3498db;
    }
    .draft-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .registration-container {
      display: <?= $showRegistrationForm ? 'block' : 'none' ?>;
    }
    .empty-state {
      padding: 3rem;
      text-align: center;
      background-color: #f8f9fa;
      border-radius: 10px;
    }
    .service-item {
      cursor: pointer;
    }
    .service-item:hover {
      background-color: #f8f9fa;
    }
    .total-display {
      font-size: 1.2rem;
      font-weight: bold;
      color: #2c3e50;
    }
  </style>
</head>
<body>
  <?php include 'header.php' ?>
  
  <div class="main-content">
    <div class="container-fluid py-4">
      <?php if (!empty($registrationMsg)): ?>
        <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show">
          <?= $registrationMsg ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      
      <div class="row mb-4">
        <div class="col-md-8">
          <h2><i class="<?= $page_icon ?> me-2"></i><?= $page_title ?></h2>
        </div>
        <div class="col-md-4">
          <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control" placeholder="Search drafts..." 
                   value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="btn btn-primary ms-2">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>
      </div>
      
      <div class="row">
        <div class="col-md-12">
          <div class="card mb-4">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">Saved Drafts (<?= count($drafts) ?>)</h5>
            </div>
            <div class="card-body">
              <?php if (!empty($drafts)): ?>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Barcode</th>
                        <th>Date Saved</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($drafts as $draft): ?>
                        <tr class="draft-card">
                          <td><?= $draft['id'] ?></td>
                          <td><?= htmlspecialchars($draft['first_name'] . ' ' . $draft['last_name']) ?></td>
                          <td><?= htmlspecialchars($draft['barcode']) ?></td>
                          <td><?= date('M d, Y h:i A', strtotime($draft['created_at'])) ?></td>
                          <td>
                            <a href="drafts.php?draft_id=<?= $draft['id'] ?>" class="btn btn-sm btn-success">
                              <i class="fas fa-edit me-1"></i> Continue
                            </a>
                            <form method="POST" style="display:inline;">
                              <input type="hidden" name="draft_id" value="<?= $draft['id'] ?>">
                              <input type="hidden" name="action" value="delete_draft">
                              <button type="submit" class="btn btn-sm btn-danger" 
                                      onclick="return confirm('Are you sure you want to delete this draft?')">
                                <i class="fas fa-trash me-1"></i> Delete
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="fas fa-info-circle me-2"></i> No drafts found.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Registration Form Container (Hidden by default) -->
      <div class="registration-container" id="registrationContainer">
        <div class="row">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                  <?= !empty($draftData['id']) ? 
                    "Continue Registration (Draft #" . $draftData['id'] . ")" : 
                    "New Patient Registration" ?>
                </h5>
                <button class="btn btn-sm btn-light" onclick="hideRegistrationForm()">
                  <i class="fas fa-times"></i> Close
                </button>
              </div>
              <div class="card-body">
                <form method="POST" id="registrationForm">
                  <input type="hidden" name="draft_id" value="<?= $draftData['id'] ?>">
                  
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label class="form-label">First Name *</label>
                      <input type="text" class="form-control" name="first_name" required 
                             value="<?= htmlspecialchars($draftData['first_name']) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Last Name *</label>
                      <input type="text" class="form-control" name="last_name" required 
                             value="<?= htmlspecialchars($draftData['last_name']) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Date of Birth *</label>
                      <input type="date" class="form-control" name="dob" required 
                             value="<?= htmlspecialchars($draftData['dob']) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Gender *</label>
                      <select class="form-select" name="gender" required>
                        <option value="">Select...</option>
                        <option value="male" <?= $draftData['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $draftData['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $draftData['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Phone *</label>
                      <input type="tel" class="form-control" name="phone" required 
                             value="<?= htmlspecialchars($draftData['phone']) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Barcode *</label>
                      <input type="text" class="form-control" name="barcode" required 
                             value="<?= htmlspecialchars($draftData['barcode']) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Email</label>
                      <input type="email" class="form-control" name="email" 
                             value="<?= htmlspecialchars($draftData['email']) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Address *</label>
                      <input type="text" class="form-control" name="address" required 
                             value="<?= htmlspecialchars($draftData['address']) ?>">
                    </div>
                  </div>
                  
                  <h5 class="mb-3">Select Services</h5>
                  <div class="row">
                    <?php foreach ($services as $service): ?>
                      <div class="col-md-4 mb-3">
                        <div class="card service-item">
                          <div class="card-body">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="services[]" 
                                     value="<?= $service['id'] ?>" id="service_<?= $service['id'] ?>"
                                     data-price="<?= $service['price'] ?>"
                                     <?= in_array($service['id'], $draftData['services']) ? 'checked' : '' ?>
                                     onchange="updateTotal()">
                              <label class="form-check-label" for="service_<?= $service['id'] ?>">
                                <?= htmlspecialchars($service['name']) ?>
                              </label>
                            </div>
                            <div class="text-end text-muted">
                              $<?= number_format($service['price'], 2) ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  
                  <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="total-display">
                      Total: $<span id="totalAmount">0.00</span>
                    </div>
                    <button type="submit" name="action" value="register" class="btn btn-primary btn-lg">
                      <i class="fas fa-save me-2"></i> Register Patient
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Empty State (Shown when no draft is selected) -->
      <?php if (!$showRegistrationForm): ?>
        <div class="empty-state">
          <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
          <h4>No Draft Selected</h4>
          <p class="text-muted">Select a draft from the list above to continue registration</p>
          <p class="text-muted">or <a href="patient_registration.php">create a new patient registration</a></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <?php include 'footer.php' ?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Calculate and update total amount
    function updateTotal() {
      let checkboxes = document.querySelectorAll('input[name="services[]"]:checked');
      let total = 0;
      checkboxes.forEach(function(checkbox) {
        total += parseFloat(checkbox.getAttribute('data-price'));
      });
      document.getElementById('totalAmount').textContent = total.toFixed(2);
    }
    
    // Hide registration form
    function hideRegistrationForm() {
      document.getElementById('registrationContainer').style.display = 'none';
      window.history.pushState({}, document.title, window.location.pathname);
    }
    
    // Initialize total on page load if form is visible
    document.addEventListener('DOMContentLoaded', function() {
      if (document.getElementById('registrationContainer').style.display === 'block') {
        updateTotal();
      }
    });
  </script>
</body>
</html>