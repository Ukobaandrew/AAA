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

// Handle date input safely
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-7 days'));
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $from_date) || !DateTime::createFromFormat('Y-m-d', $to_date)) {
    $from_date = date('Y-m-d', strtotime('-7 days'));
    $to_date = date('Y-m-d');
}

// Ensure from_date is not after to_date
if (strtotime($from_date) > strtotime($to_date)) {
    $temp = $from_date;
    $from_date = $to_date;
    $to_date = $temp;
}

$page_title = "Report from " . date('M d, Y', strtotime($from_date)) . " to " . date('M d, Y', strtotime($to_date));
include 'header.php';

// Get statistics for the date range
$stmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_patients,
    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male_patients,
    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female_patients,
    (SELECT COUNT(*) FROM test_results WHERE DATE(created_at) BETWEEN ? AND ?) AS total_tests,
    (SELECT COUNT(*) FROM invoices WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'paid') AS paid_invoices,
    (SELECT SUM(final_amount) FROM invoices WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'paid') AS total_revenue
    FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$from_date, $to_date, $from_date, $to_date, $from_date, $to_date, $from_date, $to_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle null total revenue if no payment found
$totalRevenue = $stats['total_revenue'] ?? 0;

// Get recent patients
$patientsStmt = $pdo->prepare("SELECT * FROM patients WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 10");
$patientsStmt->execute([$from_date, $to_date]);
$patients = $patientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get data for charts - Daily data within the date range
$chartStmt = $pdo->prepare("SELECT 
    DATE(created_at) AS date,
    COUNT(*) AS patients,
    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male,
    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female,
    (SELECT COUNT(*) FROM test_results WHERE DATE(created_at) = DATE(p.created_at)) AS tests,
    (SELECT SUM(final_amount) FROM invoices WHERE DATE(created_at) = DATE(p.created_at) AND status = 'paid') AS revenue
    FROM patients p
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)");
$chartStmt->execute([$from_date, $to_date]);
$chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JavaScript
$chartDates = [];
$chartPatients = [];
$chartMale = [];
$chartFemale = [];
$chartTests = [];
$chartRevenue = [];

foreach ($chartData as $row) {
    $chartDates[] = date('M j', strtotime($row['date']));
    $chartPatients[] = $row['patients'];
    $chartMale[] = $row['male'];
    $chartFemale[] = $row['female'];
    $chartTests[] = $row['tests'];
    $chartRevenue[] = $row['revenue'] ?? 0;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Date Range Report</h3>
                    <div class="card-tools">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-2">
                                <label for="from_date" class="mr-2">From</label>
                                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                            </div>
                            <div class="form-group mr-2">
                                <label for="to_date" class="mr-2">To</label>
                                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Go</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Patients</span>
                                    <span class="info-box-number"><?= $stats['total_patients'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-male"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Male Patients</span>
                                    <span class="info-box-number"><?= $stats['male_patients'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-pink"><i class="fas fa-female"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Female Patients</span>
                                    <span class="info-box-number"><?= $stats['female_patients'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-vial"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Tests</span>
                                    <span class="info-box-number"><?= $stats['total_tests'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Patient Trends</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="patientsChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Gender Distribution</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="genderChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Test Volume</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="testsChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Revenue</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Financial Summary</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="callout callout-info">
                                                <h5>Paid Invoices</h5>
                                                <p><?= $stats['paid_invoices'] ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="callout callout-success">
                                                <h5>Total Revenue</h5>
                                                <p>₦<?= number_format($totalRevenue, 2) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Recent Patients</h3>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="products-list product-list-in-card pl-2 pr-2">
                                        <?php foreach ($patients as $patient): ?>
                                            <li class="item">
                                                <div class="product-img">
                                                    <i class="fas fa-user fa-2x"></i>
                                                </div>
                                                <div class="product-info">
                                                    <a href="patient_profile.php?id=<?= $patient['id'] ?>" class="product-title">
                                                        <?= htmlspecialchars($patient['first_name']) . ' ' . htmlspecialchars($patient['last_name']) ?>
                                                        <span class="badge badge-primary float-right">
                                                            <?= date('h:i A', strtotime($patient['created_at'])) ?>
                                                        </span>
                                                    </a>
                                                    <span class="product-description">
                                                        <?= htmlspecialchars($patient['phone']) ?> | 
                                                        <?= htmlspecialchars($patient['barcode']) ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <a href="export_daily_report.php?from_date=<?= htmlspecialchars($from_date) ?>&to_date=<?= htmlspecialchars($to_date) ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Prepare data for charts
const chartDates = <?= json_encode($chartDates) ?>;
const chartPatients = <?= json_encode($chartPatients) ?>;
const chartMale = <?= json_encode($chartMale) ?>;
const chartFemale = <?= json_encode($chartFemale) ?>;
const chartTests = <?= json_encode($chartTests) ?>;
const chartRevenue = <?= json_encode($chartRevenue) ?>;

// Patients Trend Chart
const patientsCtx = document.getElementById('patientsChart').getContext('2d');
const patientsChart = new Chart(patientsCtx, {
    type: 'line',
    data: {
        labels: chartDates,
        datasets: [
            {
                label: 'Total Patients',
                data: chartPatients,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                borderWidth: 2,
                fill: true
            },
            {
                label: 'Male Patients',
                data: chartMale,
                borderColor: 'rgba(0, 123, 255, 1)',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true
            },
            {
                label: 'Female Patients',
                data: chartFemale,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                borderWidth: 2,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Gender Distribution Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
const genderChart = new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?= $stats['male_patients'] ?>, <?= $stats['female_patients'] ?>],
            backgroundColor: [
                'rgba(0, 123, 255, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ],
            borderColor: [
                'rgba(0, 123, 255, 1)',
                'rgba(255, 99, 132, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Gender Distribution from <?= date('M j, Y', strtotime($from_date)) ?> to <?= date('M j, Y', strtotime($to_date)) ?>'
            }
        }
    }
});

// Tests Volume Chart
const testsCtx = document.getElementById('testsChart').getContext('2d');
const testsChart = new Chart(testsCtx, {
    type: 'bar',
    data: {
        labels: chartDates,
        datasets: [{
            label: 'Tests Performed',
            data: chartTests,
            backgroundColor: 'rgba(40, 167, 69, 0.7)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: chartDates,
        datasets: [{
            label: 'Daily Revenue (₦)',
            data: chartRevenue,
            backgroundColor: 'rgba(108, 117, 125, 0.7)',
            borderColor: 'rgba(108, 117, 125, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '₦' + context.raw.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include 'footer.php'; ?>