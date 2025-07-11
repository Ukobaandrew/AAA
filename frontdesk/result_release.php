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

if (!isset($_GET['date'])) {
    header("Location: daily_reports.php");
    exit();
}

$date = $_GET['date'];
$filename = "Daily_Report_" . date('Y-m-d', strtotime($date)) . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// Get daily statistics
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_patients,
        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male_patients,
        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female_patients,
        (SELECT COUNT(*) FROM test_results WHERE DATE(created_at) = ?) AS total_tests,
        (SELECT COUNT(*) FROM invoices WHERE DATE(created_at) = ? AND status = 'paid') AS paid_invoices,
        (SELECT SUM(final_amount) FROM invoices WHERE DATE(created_at) = ? AND status = 'paid') AS total_revenue
    FROM patients 
    WHERE DATE(created_at) = ?
");
$stmt_stats->execute([$date, $date, $date, $date]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Handle null revenue if no paid invoices
$total_revenue = $stats['total_revenue'] ?? 0;

// Get patients for the day
$stmt_patients = $pdo->prepare("SELECT * FROM patients WHERE DATE(created_at) = ? ORDER BY created_at");
$stmt_patients->execute([$date]);
$patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

// Generate Excel content
echo "Daily Report - " . date('F j, Y', strtotime($date)) . "\n\n";
echo "Summary Statistics\n";
echo "Total Patients: " . $stats['total_patients'] . "\n";
echo "Male Patients: " . $stats['male_patients'] . "\n";
echo "Female Patients: " . $stats['female_patients'] . "\n";
echo "Total Tests: " . $stats['total_tests'] . "\n";
echo "Paid Invoices: " . $stats['paid_invoices'] . "\n";
echo "Total Revenue: ₦" . number_format($total_revenue, 2) . "\n\n";

echo "Patient Details\n";
echo "No.\tName\tBarcode\tGender\tPhone\tRegistration Time\n";

foreach ($patients as $index => $patient) {
    echo ($index + 1) . "\t";
    echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "\t";
    echo htmlspecialchars($patient['barcode']) . "\t";
    echo ucfirst($patient['gender']) . "\t";
    echo htmlspecialchars($patient['phone']) . "\t";
    echo date('H:i', strtotime($patient['created_at'])) . "\n";
}

exit();
?>
