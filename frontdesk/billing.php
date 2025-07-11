<?php
// Database connection parameters
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



$page_title = "Billing & Payments";
include 'header.php';

// Get pending invoices
$invoices = $pdo->query("SELECT i.*, p.first_name, p.last_name, p.barcode 
                        FROM invoices i
                        JOIN patients p ON i.patient_id = p.id
                        WHERE i.status = 'unpaid'
                        ORDER BY i.created_at DESC")->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pending Invoices</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Patient</th>
                                    <th>Barcode</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= $invoice['id'] ?></td>
                                        <td>
                                            <a href="patient_profile.php?id=<?= $invoice['patient_id'] ?>">
                                                <?= htmlspecialchars($invoice['first_name'] . ' ' . htmlspecialchars($invoice['last_name'])) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($invoice['barcode']) ?></td>
                                        <td>₦<?= number_format($invoice['final_amount'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                        <td>
                                            <span class="badge text-warning">Unpaid</span>
                                        </td>
                                        <td>
                                            <a href="view_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="process_payment.php?invoice_id=<?= $invoice['id'] ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill-wave"></i> Pay
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>