<?php
// Database connection
$host = "localhost";
$dbname = "u740329344_rlis";
$user = "u740329344_rlis";
$password = "Rlis@7030";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Validate patient ID
if (!isset($_GET['id'])) {
    header("Location: patient_search.php");
    exit();
}

$patient_id = (int)$_GET['id'];

// Fetch patient details
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: patient_search.php");
    exit();
}

// Fetch recent tests
$stmt = $pdo->prepare("
    SELECT t.*, tr.status, tr.created_at 
    FROM test_results tr
    JOIN tests t ON tr.test_id = t.id
    WHERE tr.patient_id = ?
    ORDER BY tr.created_at DESC 
    LIMIT 10
");
$stmt->execute([$patient_id]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Patient Profile: " . htmlspecialchars($patient['first_name']) . " " . htmlspecialchars($patient['last_name']);
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Patient Details -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Patient Details</h3>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="barcode-display">*<?= htmlspecialchars($patient['barcode']) ?>*</div>
                    </div>
                    <table class="table table-bordered">
                        <tr>
                            <th>Name</th>
                            <td><?= htmlspecialchars($patient['first_name']) . ' ' . htmlspecialchars($patient['last_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?= ucfirst(htmlspecialchars($patient['gender'])) ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?= date('M d, Y', strtotime($patient['dob'])) ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?= htmlspecialchars($patient['phone']) ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?= htmlspecialchars($patient['email']) ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?= htmlspecialchars($patient['address']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer">
                    <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="new_test.php?patient_id=<?= $patient['id'] ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> New Test
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Tests -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Tests</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Test</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tests)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No tests found for this patient.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tests as $test): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($test['name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $test['status'] == 'pending' ? 'warning' : 
                                                    ($test['status'] == 'completed' ? 'success' : 'secondary') 
                                                ?>">
                                                    <?= ucfirst(htmlspecialchars($test['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view_result.php?test_id=<?= $test['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
