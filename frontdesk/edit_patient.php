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

if (!isset($_GET['id'])) {
    header("Location: search_patient.php");
    exit();
}

$patient_id = (int)$_GET['id'];
$patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?")->execute([$patient_id])->fetch();

if (!$patient) {
    header("Location: search_patient.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $phone = htmlspecialchars(trim($_POST['phone']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $address = htmlspecialchars(trim($_POST['address']));
    $national_id = htmlspecialchars(trim($_POST['national_id']));

    $stmt = $pdo->prepare("UPDATE patients SET 
                          first_name = ?, last_name = ?, dob = ?, gender = ?,
                          phone = ?, email = ?, address = ?, national_id = ?
                          WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $email, $address, $national_id, $patient_id]);

    $_SESSION['success'] = "Patient updated successfully!";
    header("Location: patient_profile.php?id=$patient_id");
    exit();
}

$page_title = "Edit Patient: " . $patient['first_name'] . " " . $patient['last_name'];
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Patient Details</h3>
                </div>
                <form method="post">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name*</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($patient['first_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name*</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($patient['last_name']) ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date of Birth*</label>
                                    <input type="date" name="dob" class="form-control" 
                                           value="<?= $patient['dob'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Gender*</label>
                                    <select name="gender" class="form-control" required>
                                        <option value="male" <?= $patient['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $patient['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= $patient['gender'] == 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>National ID</label>
                                    <input type="text" name="national_id" class="form-control" 
                                           value="<?= htmlspecialchars($patient['national_id'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone*</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($patient['phone']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="patient_profile.php?id=<?= $patient_id ?>" class="btn btn-default">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>