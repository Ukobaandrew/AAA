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

if (!isset($_GET['invoice_id'])) {
    header("Location: billing.php");
    exit();
}

$invoice_id = (int)$_GET['invoice_id'];
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: billing.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $transaction_ref = htmlspecialchars(trim($_POST['transaction_ref']));

    if ($amount <= 0) {
        $_SESSION['error'] = "Invalid payment amount";
        header("Location: process_payment.php?invoice_id=$invoice_id");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Insert payment record
        $stmt = $pdo->prepare("INSERT INTO payments 
            (invoice_id, patient_id, amount_paid, payment_method, transaction_id, status, payment_date) 
            VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->execute([
            $invoice_id, 
            $invoice['patient_id'], 
            $amount, 
            $payment_method, 
            $transaction_ref
        ]);

        // Calculate total paid
        $stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments 
            WHERE invoice_id = ? AND status = 'completed'");
        $stmt->execute([$invoice_id]);
        $total_paid = $stmt->fetchColumn();

        if ($total_paid >= $invoice['final_amount']) {
            $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
            $stmt->execute([$invoice_id]);
        }

        $pdo->commit();

        $_SESSION['success'] = "Payment recorded successfully!";
        header("Location: view_invoice.php?id=$invoice_id");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Payment failed: " . $e->getMessage();
        header("Location: process_payment.php?invoice_id=$invoice_id");
        exit();
    }
}

$page_title = "Process Payment for Invoice #" . $invoice['id'];
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Record Payment</h3>
                </div>
                <form method="post">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Invoice Amount</label>
                            <input type="text" class="form-control" 
                                   value="₦<?= number_format($invoice['final_amount'], 2) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Amount Paid*</label>
                            <input type="number" name="amount" class="form-control" 
                                   step="0.01" min="0.01" max="<?= $invoice['final_amount'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method*</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                            </select>
                        </div>
                      
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Payment
                        </button>
                        <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-default">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
