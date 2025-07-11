<?php
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

;

if (!isset($_GET['id'])) {
    header("Location: billing.php");
    exit();
}

$invoice_id = (int)$_GET['id'];

// Fetch invoice
$stmt = $pdo->prepare("SELECT i.*, p.first_name, p.last_name, p.barcode 
                        FROM invoices i
                        JOIN patients p ON i.patient_id = p.id
                        WHERE i.id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: billing.php");
    exit();
}

// Fetch invoice items
$stmt_items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt_items->execute([$invoice_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Fetch payments
$stmt_payments = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date");
$stmt_payments->execute([$invoice_id]);
$payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Invoice #" . $invoice['id'];
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Invoice Details</h3>
                    <div class="card-tools">
                        <a href="print_invoice.php?id=<?= $invoice_id ?>" class="btn btn-sm btn-primary" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4>Patient Information</h4>
                            <p>
                                <strong>Name:</strong> <?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?><br>
                                <strong>Barcode:</strong> <?= htmlspecialchars($invoice['barcode']) ?><br>
                                <strong>Invoice Date:</strong> <?= date('M d, Y', strtotime($invoice['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-right">
                            <h4>Invoice #<?= $invoice['id'] ?></h4>
                            <p>
                                <strong>Status:</strong> 
                                <span class="badge badge-<?= $invoice['status'] == 'paid' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($invoice['status']) ?>
                                </span><br>
                                <strong>Total Amount:</strong> ₦<?= number_format($invoice['final_amount'], 2) ?>
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Test Name</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= $item['id'] ?></td>
                                        <td><?= htmlspecialchars($item['test_name']) ?></td>
                                        <td>₦<?= number_format($item['price'], 2) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>₦<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                    <td>₦<?= number_format($invoice['final_amount'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($payments)): ?>
                        <h4 class="mt-4">Payment History</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                            <td>₦<?= number_format($payment['amount_paid'], 2) ?></td>
                                            <td><?= ucfirst($payment['payment_method']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $payment['status'] == 'completed' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($payment['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <?php if ($invoice['status'] !== 'paid'): ?>
                        <a href="process_payment.php?invoice_id=<?= $invoice_id ?>" class="btn btn-success">
                            <i class="fas fa-money-bill-wave"></i> Record Payment
                        </a>
                    <?php endif; ?>
                    <a href="billing.php" class="btn btn-default">Back to Billing</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
