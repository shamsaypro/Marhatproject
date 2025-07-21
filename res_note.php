<?php
// Database connection
include 'connection/db.php';

session_start();

if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
$errors = [];
$success = '';

// Handle form submission for user registration (existing code, not modified for filter)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) {
    // Sanitize and fetch form inputs
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $mobile     = trim($_POST['mobile']);
    $gender     = trim($_POST['gender']);
    $password   = $_POST['password'];
    $role       = trim($_POST['role']);
    
    // Validate inputs
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($mobile)) $errors[] = "Mobile number is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($role)) $errors[] = "Role is required";
    
    // Check if email already exists
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    
    if ($check_email->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email,gender, phone_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $first_name, $last_name, $email, $gender, $mobile,$hashed_password, $role);
        
        if ($stmt->execute()) {
            $success = "User registered successfully!";
            // Refresh the page to show new user in table
            header("Refresh:2");
        } else {
            $errors[] = "Error: " . $conn->error;
        }
    }
}

// Fetch all users to display in table (existing code, not modified for filter)
$users = [];
$query = "SELECT first_name, last_name, email,gender, phone_number, gender FROM users";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the selected level filter
$selected_level = isset($_GET['level_filter']) ? $_GET['level_filter'] : 'all';

// Fetch parents based on the level filter
$parents = [];
if ($selected_level == 'all') {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email
            FROM users u
            WHERE u.role = 'parent'";
} else {
    // Filter by 'level' from the 'classes' table
    $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
            FROM users u
            JOIN students s ON u.id = s.parent_id
            JOIN classes c ON s.class_id = c.id
            WHERE u.role = 'parent' AND c.level = ?";
}

$stmt = $conn->prepare($sql);
if ($selected_level != 'all') {
    $stmt->bind_param("s", $selected_level);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parents[] = $row;
    }
} else {
    // Debugging information
    error_log("No parents found for filter: " . $selected_level);
    error_log("SQL Query: " . $sql);
    if ($selected_level != 'all') {
        error_log("Level: " . $selected_level);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Result Management System</title>
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
            background-color: #f0f2f5;
            overflow-x: hidden;
            padding-top: 56px; /* Height of navbar */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            width: 280px;
            position: fixed;
            top: 56px; /* Height of navbar */
            bottom: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
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
            flex-grow: 1;
            height: calc(100vh - 56px); /* Viewport height minus navbar height */
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }

            .main-content {
                margin-left: 0;
                height: auto;
            }
        }
        
        .table-responsive {
            margin-top: 20px;
        }
        
        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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

        .content-card {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .content-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .content-header h2 {
            font-weight: 600;
            color: #333;
        }

        .form-check-label {
            font-weight: normal;
            color: #555;
        }

        .form-control-file, .form-control {
            border-radius: 5px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .alert-container {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .main-content-col {
            flex-basis: 100%;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .main-content-col {
                flex-basis: calc(100% - 280px);
                max-width: calc(100% - 280px);
                margin-left: 280px;
            }
        }
        
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .filter-section label {
            font-weight: 600;
            margin-right: 10px;
        }
        
        .filter-section select {
            width: 200px;
            display: inline-block;
        }

        /* Fixed navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }
    </style>
</head>
<body>

        <?php include 'navbar.php'?>


<div class="d-flex">
    <?php include 'sidebar.php'; ?>
      
    <div class="main-content-col p-4"> <div class="content-card">
            <div class="content-header">
                <h2>Send a Results Message to Parents</h2>
            </div>
            
            <div class="alert-container">
                <?php
                if (isset($_SESSION['message'])):
                    $alertClass = ($_SESSION['message_type'] == 'success') ? 'alert-success' : 'alert-warning';
                    if ($_SESSION['message_type'] == 'danger') {
                        $alertClass = 'alert-danger';
                    }
                ?>
                    <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
                        <?= $_SESSION['message'] ?>
                        <?php if (isset($_SESSION['error_details']) && !empty($_SESSION['error_details'])): ?>
                            <br><strong>Maelezo ya Makosa:</strong>
                            <ul>
                                <?php foreach ($_SESSION['error_details'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    unset($_SESSION['error_details']);
                endif;
                ?>
            </div>
            
            <div class="filter-section mb-4">
                <form method="get" action="" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <label for="level_filter" class="form-label">Filter by Student Level:</label>
                        <select name="level_filter" id="level_filter" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $selected_level == 'all' ? 'selected' : '' ?>>All Parents</option>
                            <option value="Primary" <?= $selected_level == 'Primary' ? 'selected' : '' ?>>Parents of Primary Students</option>
                            <option value="Secondary" <?= $selected_level == 'Secondary' ? 'selected' : '' ?>>Parents of Secondary Students</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <span class="badge bg-primary">
                            <?= count($parents) ?> Parents Found
                        </span>
                    </div>
                </form>
            </div>

            <form action="send_mail1.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="level_filter" value="<?= $selected_level ?>">
                
                <div class="mb-3">
                    <label class="form-label">Selected Parents (<span id="parentCount"><?= count($parents) ?></span>)</label>
                    <div class="form-control" style="height: 180px; overflow-y: scroll; border: 1px solid #ced4da; padding: 10px;">
                        <?php
                        if (!empty($parents)) {
                            foreach ($parents as $parent):
                        ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="parent_ids[]"
                                        value="<?= $parent['id'] ?>" id="parent_<?= $parent['id'] ?>" checked>
                                    <label class="form-check-label" for="parent_<?= $parent['id'] ?>">
                                        <?= htmlspecialchars($parent['first_name']) ?>
                                        <?= htmlspecialchars($parent['last_name']) ?>
                                        (<?= htmlspecialchars($parent['email']) ?>)
                                    </label>
                                </div>
                        <?php
                            endforeach;
                        } else {
                            echo '<p class="text-muted">No parents found matching your criteria.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                
                <button type="submit" name="send_email1" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-paper-plane me-2"></i>Send
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
<script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.hide();
                }
            });
        }, 5000);
        
        // Update parent count when checkboxes are changed
        const checkboxes = document.querySelectorAll('input[name="parent_ids[]"]');
        const parentCount = document.getElementById('parentCount');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('input[name="parent_ids[]"]:checked').length;
                parentCount.textContent = checkedCount;
            });
        });
    });
</script>

</body>
</html>