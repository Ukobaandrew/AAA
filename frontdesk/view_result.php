<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection
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


require_once 'header.php';

// Get result ID
$result_id = isset($_GET['result_id']) ? (int)$_GET['result_id'] : 0;

if (!$result_id) {
    echo "<div class='alert alert-danger'>Invalid result ID.</div>";
    require_once 'footer.php';
    exit;
}

// Fetch result details
$sql = "SELECT tr.*, p.first_name, p.last_name, p.barcode, p.phone, p.gender,
               t.name AS test_name, d.name AS department_name, u.name AS technician_name
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.id
        JOIN tests t ON tr.test_id = t.id
        JOIN departments d ON t.department_id = d.id
        LEFT JOIN users u ON tr.technician_id = u.id
        WHERE tr.id = :result_id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['result_id' => $result_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    echo "<div class='alert alert-warning'>Result not found.</div>";
    require_once 'footer.php';
    exit;
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Test Result Details</h4>
        </div>
        <div class="card-body">
            <h5>Patient Information</h5>
            <p><strong>Name:</strong> <?= htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) ?></p>
            <p><strong>Barcode:</strong> <?= htmlspecialchars($result['barcode']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($result['phone']) ?></p>
            <p><strong>Gender:</strong> <?= htmlspecialchars($result['gender']) ?></p>

            <hr>
            <h5>Test Information</h5>
            <p><strong>Test:</strong> <?= htmlspecialchars($result['test_name']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($result['department_name']) ?></p>
            <p><strong>Status:</strong> <span class="badge badge-<?= $result['status'] === 'pending' ? 'warning' : 'success' ?>"><?= ucfirst($result['status']) ?></span></p>
            <p><strong>Date Ordered:</strong> <?= date('M d, Y h:i A', strtotime($result['created_at'])) ?></p>
            <p><strong>Technician:</strong> <?= htmlspecialchars($result['technician_name'] ?? 'N/A') ?></p>

            <?php if (!empty($result['result_data'])): ?>
                <hr>
                <h5>Result Data</h5>
                <pre><?= htmlspecialchars($result['result_data']) ?></pre>
            <?php else: ?>
                <div class="alert alert-info">Result details are not available yet.</div>
            <?php endif; ?>

            <a href="outstanding_results.php" class="btn btn-secondary mt-3">&laquo; Back to Outstanding Results</a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
