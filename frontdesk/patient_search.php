<?php
// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Session management for security and flash messages
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u740329344_rlis');
define('DB_PASS', 'Rlis@7030');
define('DB_NAME', 'u740329344_rlis');

// Create database connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("System error. Please try again later.");
}

// Helper function for sanitizing input
function sanitizeInput($data, $conn) {
    return htmlspecialchars($conn->real_escape_string(trim($data)));
}

// Handle patient update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    try {
        // Validate and sanitize all inputs
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("Invalid patient ID");
        }
        
        $required_fields = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'dob' => 'Date of birth',
            'gender' => 'Gender',
            'phone' => 'Phone number',
            'barcode' => 'Barcode',
            'address' => 'Address'
        ];
        
        $data = [];
        foreach ($required_fields as $field => $name) {
            if (empty($_POST[$field])) {
                throw new Exception("$name is required");
            }
            $data[$field] = sanitizeInput($_POST[$field], $conn);
        }
        
        // Optional field
        $data['email'] = !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : null;
        if ($_POST['email'] && !$data['email']) {
            throw new Exception("Invalid email format");
        }
        
        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $data['dob'])) {
            throw new Exception("Invalid date format");
        }
        
        // Validate gender
        if (!in_array($data['gender'], ['male', 'female', 'other'])) {
            throw new Exception("Invalid gender selection");
        }
        
        // Prepare and execute update statement
        $stmt = $conn->prepare("UPDATE patients SET 
            first_name = ?, 
            last_name = ?, 
            dob = ?, 
            gender = ?, 
            phone = ?, 
            email = ?, 
            barcode = ?, 
            address = ? 
            WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param(
            "ssssssssi",
            $data['first_name'],
            $data['last_name'],
            $data['dob'],
            $data['gender'],
            $data['phone'],
            $data['email'],
            $data['barcode'],
            $data['address'],
            $id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Patient record updated successfully.";
        } else {
            throw new Exception("Error updating record: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit_id=" . $id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// If an edit is requested, load the patient data
$edit_patient = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result_edit = $stmt->get_result();
        
        if ($result_edit->num_rows > 0) {
            $edit_patient = $result_edit->fetch_assoc();
        }
        $stmt->close();
    }
}

// Process search query if submitted
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_patient'])) {
    $search_term = !empty($_POST['search_term']) ? sanitizeInput($_POST['search_term'], $conn) : '';
    
    if (!empty($search_term)) {
        $search_term = "%$search_term%";
        $stmt = $conn->prepare("SELECT * FROM patients 
                               WHERE first_name LIKE ? 
                                  OR last_name LIKE ? 
                                  OR barcode LIKE ? 
                                  OR email LIKE ?");
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $result_search = $stmt->get_result();
        
        if ($result_search->num_rows > 0) {
            while ($row = $result_search->fetch_assoc()) {
                $search_results[] = $row;
            }
        }
        $stmt->close();
    }
}

// Get recently registered patients (last 10 registered)
$recent_patients = [];
$result_recent = $conn->query("SELECT * FROM patients ORDER BY id DESC LIMIT 10");
if ($result_recent->num_rows > 0) {
    while ($row = $result_recent->fetch_assoc()) {
        $recent_patients[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Patient Search & Edit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'?>
    
<div class="container mt-4">
  <h2 class="mb-4">Patient Search & Registration Details</h2>
  
  <!-- Display flash messages -->
  <?php if(isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
          <?php echo $_SESSION['success_message']; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>
  
  <?php if(isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show">
          <?php echo $_SESSION['error_message']; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>
  
  <!-- Recently Registered Patients -->
  <?php if (!empty($recent_patients)): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-info text-white">
        <h4 class="mb-0">Recently Registered Patients</h4>
      </div>
      <br>
       <!-- Search Form -->
  <form method="POST" class="row g-3 mb-4">
    <div class="col-md-10">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search_term" class="form-control" placeholder="Search by name, barcode, or email" required
               value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
      </div>
    </div>
    <div class="col-md-2">
      <button type="submit" name="search_patient" class="btn btn-primary w-100">
        <i class="bi bi-search me-1"></i> Search
      </button>
    </div>
  </form>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Date of Birth</th>
                <th>Contact</th>
                <th>Barcode</th>
                <th>Registered</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_patients as $patient): 
                $registered_date = !empty($patient['created_at']) ? date('M d, Y h:i A', strtotime($patient['created_at'])) : 'N/A';
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($patient['id']); ?></td>
                  <td>
                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    <br><small class="text-muted"><?php echo ucfirst(htmlspecialchars($patient['gender'])); ?></small>
                  </td>
                  <td><?php echo htmlspecialchars($patient['dob']); ?></td>
                  <td>
                    <?php echo htmlspecialchars($patient['phone']); ?>
                    <?php if (!empty($patient['email'])): ?>
                      <br><small class="text-muted"><?php echo htmlspecialchars($patient['email']); ?></small>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge bg-secondary"><?php echo htmlspecialchars($patient['barcode']); ?></span></td>
                  <td><?php echo $registered_date; ?></td>
                  <td>
                    <a href="?edit_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-warning">
                      <i class="bi bi-pencil"></i> Edit
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
  
  <!-- Search Form -->
  <!--<form method="POST" class="row g-3 mb-4">-->
  
  <!--  </div>-->
  <!--  <div class="col-md-2">-->
  <!--    <button type="submit" name="search_patient" class="btn btn-primary w-100">-->
  <!--      <i class="bi bi-search me-1"></i> Search-->
  <!--    </button>-->
  <!--  </div>-->
  <!--</form>-->
  
  <!-- Search Results -->
  <?php if (!empty($search_results)): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Search Results</h4>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Date of Birth</th>
                <th>Contact</th>
                <th>Barcode</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($search_results as $patient): ?>
                <tr>
                  <td><?php echo htmlspecialchars($patient['id']); ?></td>
                  <td>
                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    <br><small class="text-muted"><?php echo ucfirst(htmlspecialchars($patient['gender'])); ?></small>
                  </td>
                  <td><?php echo htmlspecialchars($patient['dob']); ?></td>
                  <td>
                    <?php echo htmlspecialchars($patient['phone']); ?>
                    <?php if (!empty($patient['email'])): ?>
                      <br><small class="text-muted"><?php echo htmlspecialchars($patient['email']); ?></small>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge bg-secondary"><?php echo htmlspecialchars($patient['barcode']); ?></span></td>
                  <td>
                    <a href="?edit_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-warning">
                      <i class="bi bi-pencil"></i> Edit
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_patient'])): ?>
    <div class="alert alert-info">
      No records found matching your search criteria.
    </div>
  <?php endif; ?>
  
  <!-- Edit Patient Form -->
  <?php if($edit_patient): ?>
    <div class="card shadow-sm mt-5">
      <div class="card-header bg-success text-white">
        <h4 class="mb-0">Edit Patient Details</h4>
      </div>
      <div class="card-body">
        <form method="POST" id="patientForm">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_patient['id']); ?>">
          
          <div class="row g-3">
            <div class="col-md-6">
              <label for="first_name" class="form-label">First Name</label>
              <input type="text" id="first_name" name="first_name" class="form-control" 
                     value="<?php echo htmlspecialchars($edit_patient['first_name']); ?>" required>
            </div>
            
            <div class="col-md-6">
              <label for="last_name" class="form-label">Last Name</label>
              <input type="text" id="last_name" name="last_name" class="form-control" 
                     value="<?php echo htmlspecialchars($edit_patient['last_name']); ?>" required>
            </div>
            
            <div class="col-md-4">
              <label for="dob" class="form-label">Date of Birth</label>
              <input type="date" id="dob" name="dob" class="form-control" 
                     value="<?php echo htmlspecialchars($edit_patient['dob']); ?>" required>
            </div>
            
            <div class="col-md-4">
              <label for="gender" class="form-label">Gender</label>
              <select id="gender" name="gender" class="form-select" required>
                <option value="male" <?php echo ($edit_patient['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo ($edit_patient['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo ($edit_patient['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            
            <div class="col-md-4">
              <label for="phone" class="form-label">Phone</label>
              <input type="tel" id="phone" name="phone" class="form-control" 
                     value="<?php echo htmlspecialchars($edit_patient['phone']); ?>" required>
            </div>
            
            <div class="col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email" class="form-control" 
                     value="<?php echo htmlspecialchars($edit_patient['email']); ?>">
            </div>
            
            <div class="col-md-6">
              <label for="barcode" class="form-label">Barcode</label>
              <div class="input-group">
                <input type="text" id="barcode" name="barcode" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_patient['barcode']); ?>" required>
                <button class="btn btn-outline-secondary" type="button" id="generateBarcode">
                  <i class="bi bi-upc-scan"></i> Generate
                </button>
              </div>
            </div>
            
            <div class="col-12">
              <label for="address" class="form-label">Address</label>
              <textarea id="address" name="address" class="form-control" rows="3" required><?php 
                echo htmlspecialchars($edit_patient['address']); 
              ?></textarea>
            </div>
            
            <div class="col-12 mt-4">
              <button type="submit" name="update_patient" class="btn btn-success px-4">
                <i class="bi bi-check-circle"></i> Update Patient
              </button>
              <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary ms-2">
                Cancel
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Generate a simple barcode
  document.getElementById('generateBarcode')?.addEventListener('click', function() {
    const randomBarcode = 'BC' + Math.floor(100000 + Math.random() * 900000);
    document.getElementById('barcode').value = randomBarcode;
  });
  
  // Form validation
  document.getElementById('patientForm')?.addEventListener('submit', function(e) {
    const phone = document.getElementById('phone').value;
    if (!/^[\d\s\-+]+$/.test(phone)) {
      alert('Please enter a valid phone number');
      e.preventDefault();
    }
  });
</script>
</body>
</html>