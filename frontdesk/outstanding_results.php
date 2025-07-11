<?php
// outstanding_results.php

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

// Authentication check


// Set page title
$page_title = "Dashboard";

// Include header
include 'header.php';

// Pagination configuration
$results_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the base query
$query = "SELECT tr.id, tr.patient_id, tr.test_id, tr.status, tr.created_at, 
                 p.first_name, p.last_name, p.barcode, p.phone,
                 t.name AS test_name, t.department_id,
                 d.name AS department_name,
                 u.name AS technician_name
          FROM test_results tr
          JOIN patients p ON tr.patient_id = p.id
          JOIN tests t ON tr.test_id = t.id
          JOIN departments d ON t.department_id = d.id
          LEFT JOIN users u ON tr.technician_id = u.id
          WHERE tr.status IN ('pending', 'verified')";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search OR p.barcode LIKE :search)";
}

// Add department filter
if ($department_id > 0) {
    $query .= " AND t.department_id = :department_id";
}

// Add status filter
if (in_array($status_filter, ['pending', 'verified'])) {
    $query .= " AND tr.status = :status_filter";
}

// Add date range filter
if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(tr.created_at) BETWEEN :date_from AND :date_to";
}

// Complete the query
$query .= " ORDER BY tr.created_at DESC
            LIMIT :start_from, :results_per_page";

// Prepare and execute the query
$stmt = $pdo->prepare($query);

// Bind parameters
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
}

if ($department_id > 0) {
    $stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
}

if (in_array($status_filter, ['pending', 'verified'])) {
    $stmt->bindParam(':status_filter', $status_filter, PDO::PARAM_STR);
}

if (!empty($date_from) && !empty($date_to)) {
    $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    $stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
}

$stmt->bindParam(':start_from', $start_from, PDO::PARAM_INT);
$stmt->bindParam(':results_per_page', $results_per_page, PDO::PARAM_INT);
$stmt->execute();

// Get results
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total results for pagination
$count_query = "SELECT COUNT(*) AS total 
                FROM test_results tr
                JOIN patients p ON tr.patient_id = p.id
                JOIN tests t ON tr.test_id = t.id
                WHERE tr.status IN ('pending', 'verified')";

if (!empty($search)) {
    $count_query .= " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR p.barcode LIKE '%$search%')";
}

if ($department_id > 0) {
    $count_query .= " AND t.department_id = $department_id";
}

if (in_array($status_filter, ['pending', 'verified'])) {
    $count_query .= " AND tr.status = '$status_filter'";
}

if (!empty($date_from) && !empty($date_to)) {
    $count_query .= " AND DATE(tr.created_at) BETWEEN '$date_from' AND '$date_to'";
}

$total_results = $pdo->query($count_query)->fetchColumn();
$total_pages = ceil($total_results / $results_per_page);

// Get departments for filter dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Outstanding Test Results</h3>
                    <div class="card-tools">
                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#exportModal">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Form -->
                    <form method="get" action="outstanding_results.php" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search patient..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <select name="department_id" class="form-control">
                                        <option value="0">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= $department_id == $dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <select name="status" class="form-control">
                                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="verified" <?= $status_filter == 'verified' ? 'selected' : '' ?>>Verified</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Patient</th>
                                    <th>Barcode</th>
                                    <th>Test</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Date Ordered</th>
                                    <th>Technician</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No outstanding results found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="patient_profile.php?id=<?= $row['patient_id'] ?>">
                                                    <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($row['barcode']) ?></td>
                                            <td><?= htmlspecialchars($row['test_name']) ?></td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $row['status'] == 'pending' ? 'warning' : 'success' ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($row['technician_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view_result.php?result_id=<?= $row['id'] ?>" 
                                                       class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'doctor'): ?>
                                                        <a href="release_result.php?result_id=<?= $row['id'] ?>" 
                                                           class="btn btn-sm btn-success" title="Release Result">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'lab_technician'): ?>
                                                        <a href="edit_result.php?result_id=<?= $row['id'] ?>" 
                                                           class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&department_id=<?= $department_id ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" 
                                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&department_id=<?= $department_id ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&department_id=<?= $department_id ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Outstanding Results</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="export_outstanding_results.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Format</label>
                        <select name="export_format" class="form-control">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Include Columns</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="patient" checked>
                            <label class="form-check-label">Patient Name</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="barcode" checked>
                            <label class="form-check-label">Barcode</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="test" checked>
                            <label class="form-check-label">Test Name</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="department" checked>
                            <label class="form-check-label">Department</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="status" checked>
                            <label class="form-check-label">Status</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="columns[]" value="date" checked>
                            <label class="form-check-label">Date Ordered</label>
                        </div>
                    </div>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="department_id" value="<?= $department_id ?>">
                    <input type="hidden" name="status" value="<?= $status_filter ?>">
                    <input type="hidden" name="date_from" value="<?= $date_from ?>">
                    <input type="hidden" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';
?>