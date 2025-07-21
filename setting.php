<?php
include 'connection/db.php';
session_start();
$errors = [];
$success = '';
$loggedInUserId = 1;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    if (!isset($loggedInUserId)) {
        $errors[] = "You must be logged in to change your password.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password)) {
            $errors[] = "Current password is required.";
        }
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        }
        if (empty($confirm_password)) {
            $errors[] = "Confirm new password is required.";
        }
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long.";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "New password and confirm password do not match.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $loggedInUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_new_password, $loggedInUserId);

                if ($update_stmt->execute()) {
  
                session_unset(); 
                session_destroy(); 
                header("Location: index.php?password_changed=1");
                exit();
            }    else {
                    $errors[] = "Failed to update password: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $errors[] = "Incorrect current password.";
            }
            $stmt->close();
        }
    }
}

// Check if redirected with success message
if (isset($_GET['password_success']) && $_GET['password_success'] == 1) {
    $success = "Password changed successfully!";
}

$users = [];
$query = "SELECT id, first_name, last_name, email, gender, phone_number, role FROM users ORDER BY first_name";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - User & Password Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        :root {
            --sidebar-bg: #2c3e50;
            --sidebar-active: #3498db;
            --sidebar-hover: #34495e;
            --text-color: #ecf0f1;
            --primary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .admin-sidebar {
            background-color: var(--sidebar-bg);
            min-height: 100vh;
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .nav-item {
            margin-bottom: 2px;
        }
        .nav-link {
            color: var(--text-color);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: var(--sidebar-hover);
            color: white;
        }
        .nav-link i {
            width: 24px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.35rem;
            overflow: hidden;
        }
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
        .table tbody tr:nth-of-type(even) {
            background-color: #f2f2f2;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        .badge {
            font-size: 0.85em;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }
        .gender-badge {
            text-transform: capitalize;
        }
        .male-badge {
            background-color: #3498db;
        }
        .female-badge {
            background-color: #e83e8c;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            border-radius: 0.35rem;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>

<?php include 'navbar.php' ?>
<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid p-3 main-content">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-2"></div>
            <div class="col-lg-6">
                <div class="card shadow-sm p-4">
                    <h5 class="mb-3 text-primary"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    <form method="POST" action="">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-1"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>

</body>
</html>
