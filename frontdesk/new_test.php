<?php
session_start();

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

if (!isset($_GET['patient_id'])) {
    header("Location: search_patient.php");
    exit();
}

$patient_id = (int)$_GET['patient_id'];
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: search_patient.php");
    exit();
}

// Fetch departments, tests, and collection points
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tests = $pdo->query("SELECT id, name, price, department_id FROM tests ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$collection_points = $pdo->query("SELECT id, name FROM collection_points WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_tests = $_POST['tests'] ?? [];
    $collection_point_id = (int)$_POST['collection_point_id'];
    $payment_method = $_POST['payment_method'];
    $clinical_notes = htmlspecialchars(trim($_POST['clinical_notes']));

    if (empty($selected_tests)) {
        $_SESSION['error'] = "Please select at least one test";
        header("Location: new_test.php?patient_id=$patient_id");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Calculate total price
        $invoice_total = 0;
        foreach ($selected_tests as $test_id) {
            $stmt = $pdo->prepare("SELECT price FROM tests WHERE id = ?");
            $stmt->execute([$test_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            $invoice_total += $test['price'];
        }

        // Insert invoice
        $stmt = $pdo->prepare("INSERT INTO invoices 
            (patient_id, total_amount, discount, final_amount, status, created_at) 
            VALUES (?, ?, 0, ?, 'unpaid', NOW())");
        $stmt->execute([$patient_id, $invoice_total, $invoice_total]);
        $invoice_id = $pdo->lastInsertId();

        // Add invoice items and create test results
        foreach ($selected_tests as $test_id) {
            $stmt = $pdo->prepare("SELECT name, price FROM tests WHERE id = ?");
            $stmt->execute([$test_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("INSERT INTO invoice_items 
                (invoice_id, test_id, test_name, price, quantity) 
                VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$invoice_id, $test_id, $test['name'], $test['price']]);

            $stmt = $pdo->prepare("INSERT INTO test_results 
                (patient_id, test_id, status, clinical_notes, created_at) 
                VALUES (?, ?, 'pending', ?, NOW())");
            $stmt->execute([$patient_id, $test_id, $clinical_notes]);
        }

        // Payment (skip if insurance)
        if ($payment_method !== 'insurance') {
            $stmt = $pdo->prepare("INSERT INTO payments 
                (invoice_id, patient_id, amount_paid, payment_method, status, payment_date) 
                VALUES (?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([$invoice_id, $patient_id, $invoice_total, $payment_method]);

            $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoice_id]);
        }

        $pdo->commit();
        $_SESSION['success'] = "Test order created successfully!";
        header("Location: patient_profile.php?id=$patient_id");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to create test order: " . $e->getMessage();
        header("Location: new_test.php?patient_id=$patient_id");
        exit();
    }
}

$page_title = "New Test Order for " . $patient['first_name'] . " " . $patient['last_name'];
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">New Test Order</h3>
                </div>
                <form method="post">
                    <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h4>Patient: <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
                                <p>Barcode: <?= htmlspecialchars($patient['barcode']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Collection Point*</label>
                                    <select name="collection_point_id" class="form-control" required>
                                        <option value="">Select Collection Point</option>
                                        <?php foreach ($collection_points as $cp): ?>
                                            <option value="<?= $cp['id'] ?>"><?= htmlspecialchars($cp['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Clinical Notes</label>
                            <textarea name="clinical_notes" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Select Tests*</label>
                            <div class="row">
                                <?php foreach ($tests as $test): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tests[]" value="<?= $test['id'] ?>" id="test<?= $test['id'] ?>">
                                            <label class="form-check-label" for="test<?= $test['id'] ?>">
                                                <?= htmlspecialchars($test['name']) ?> (₦<?= number_format($test['price'], 2) ?>)
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Payment Method*</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="cash">Cash</option>
                                <option value="pos">POS</option>
                                <option value="transfer">Bank Transfer</option>
                                <option value="insurance">Insurance</option>
                            </select>
                        </div>

                        <div class="form-group text-right">
                            <button type="submit" class="btn btn-primary">Submit Test Order</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
