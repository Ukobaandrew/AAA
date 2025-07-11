<?php
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
error_reporting(E_ALL);

session_start();

// DB connection settings
$host = "localhost";
$dbname = "u740329344_rlis";
$username = "u740329344_rlis";
$password = "Rlis@7030";

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// Verify user has receptionist privileges


$page_title = "Receptionist Settings";
include 'header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile
    if (isset($_POST['update_profile'])) {
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $phone, $_SESSION['user_id']])) {
            $_SESSION['success'] = "Profile updated successfully!";
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
        } else {
            $_SESSION['error'] = "Failed to update profile.";
        }
    }

    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $_SESSION['success'] = "Password changed successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update password.";
                }
            } else {
                $_SESSION['error'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
    }

    // Update preferences
    if (isset($_POST['update_preferences'])) {
        $theme = filter_var($_POST['theme'], FILTER_SANITIZE_STRING);
        $results_per_page = (int)$_POST['results_per_page'];
        $default_barcode_prefix = filter_var($_POST['default_barcode_prefix'], FILTER_SANITIZE_STRING);
        $default_collection_point = (int)$_POST['default_collection_point'];

        $_SESSION['theme'] = $theme;
        $_SESSION['results_per_page'] = $results_per_page;

        $stmt = $pdo->prepare("REPLACE INTO user_preferences 
            (user_id, theme, results_per_page, default_barcode_prefix, default_collection_point) 
            VALUES (?, ?, ?, ?, ?)");

        if ($stmt->execute([$_SESSION['user_id'], $theme, $results_per_page, $default_barcode_prefix, $default_collection_point])) {
            $_SESSION['success'] = "Preferences updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update preferences.";
        }
    }

    // Update notification settings
    if (isset($_POST['update_notifications'])) {
        $email_new_patient = isset($_POST['email_new_patient']) ? 1 : 0;
        $email_test_results = isset($_POST['email_test_results']) ? 1 : 0;
        $email_payments = isset($_POST['email_payments']) ? 1 : 0;
        $notify_overdue_payments = isset($_POST['notify_overdue_payments']) ? 1 : 0;
        $notify_critical_results = isset($_POST['notify_critical_results']) ? 1 : 0;

        $stmt = $pdo->prepare("REPLACE INTO user_preferences 
            (user_id, email_new_patient, email_test_results, email_payments, notify_overdue_payments, notify_critical_results) 
            VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([
            $_SESSION['user_id'],
            $email_new_patient,
            $email_test_results,
            $email_payments,
            $notify_overdue_payments,
            $notify_critical_results
        ])) {
            $_SESSION['success'] = "Notification settings updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update notification settings.";
        }
    }

    header("Location: settings.php");
    exit();
}

// Fetch current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch preferences
$preferences = [
    'theme' => 'light',
    'results_per_page' => 20,
    'default_barcode_prefix' => 'PAT',
    'default_collection_point' => 1,
    'email_new_patient' => 1,
    'email_test_results' => 1,
    'email_payments' => 1,
    'notify_overdue_payments' => 1,
    'notify_critical_results' => 1,
];
$stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->rowCount() > 0) {
    $preferences = array_merge($preferences, $stmt->fetch());
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
       
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Receptionist Settings</h3>
                </div>
                <div class="card-body">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#profile">Profile</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#password">Password</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#preferences">Preferences</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#notifications">Notifications</a></li>
                    </ul>

                    <div class="tab-content">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile">
                            <form method="post">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone" class="form-control"
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" class="form-control"
                                           value="<?= ucfirst($user['role']) ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Last Login</label>
                                    <input type="text" class="form-control"
                                           value="<?= date('M d, Y H:i', strtotime($user['updated_at'])) ?>" readonly>
                                </div>

                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>

                        <!-- Password Tab -->
                        <div class="tab-pane fade" id="password">
                            <form method="post">
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>

                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>

                        <!-- Preferences Tab -->
                        <div class="tab-pane fade" id="preferences">
                            <form method="post">
                                <div class="form-group">
                                    <label>Theme</label>
                                    <select name="theme" class="form-control">
                                        <option value="light" <?= $preferences['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                                        <option value="dark" <?= $preferences['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                                        <option value="blue" <?= $preferences['theme'] === 'blue' ? 'selected' : '' ?>>Blue</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Results Per Page</label>
                                    <select name="results_per_page" class="form-control">
                                        <option value="10" <?= $preferences['results_per_page'] == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="20" <?= $preferences['results_per_page'] == 20 ? 'selected' : '' ?>>20</option>
                                        <option value="50" <?= $preferences['results_per_page'] == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $preferences['results_per_page'] == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Barcode Prefix</label>
                                    <input type="text" name="default_barcode_prefix" class="form-control"
                                           value="<?= htmlspecialchars($preferences['default_barcode_prefix']) ?>">
                                </div>

                                <div class="form-group">
                                    <label>Default Collection Point</label>
                                    <select name="default_collection_point" class="form-control">
                                        <?php
                                        $points = $pdo->query("SELECT * FROM collection_points")->fetchAll();
                                        foreach ($points as $cp): ?>
                                            <option value="<?= $cp['id'] ?>" <?= $cp['id'] == $preferences['default_collection_point'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cp['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
                            </form>
                        </div>

                        <!-- Notifications Tab -->
                        <div class="tab-pane fade" id="notifications">
                            <form method="post">
                                <h5>Email Notifications</h5>
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="email_new_patient" class="form-check-input" <?= $preferences['email_new_patient'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">New patient registrations</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="email_test_results" class="form-check-input" <?= $preferences['email_test_results'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Test results ready</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="email_payments" class="form-check-input" <?= $preferences['email_payments'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Payment receipts</label>
                                </div>

                                <h5 class="mt-4">System Notifications</h5>
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="notify_overdue_payments" class="form-check-input" <?= $preferences['notify_overdue_payments'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Overdue payments</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="notify_critical_results" class="form-check-input" <?= $preferences['notify_critical_results'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Critical test results</label>
                                </div>

                                <button type="submit" name="update_notifications" class="btn btn-primary">Save Notification Settings</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
