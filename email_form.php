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
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Handle form submission (User registration part - unchanged)
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

// Fetch all users to display in table (unchanged, though not directly used for parents filter)
$users = [];
$query_users = "SELECT first_name, last_name, email,gender, phone_number, gender FROM users";
$result_users = $conn->query($query_users);

if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all classes for the filter dropdown
$classes = [];
$query_classes = "SELECT id, class_name, level FROM classes ORDER BY level, class_name";
$result_classes = $conn->query($query_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Fetch parents based on selected class
$parents = [];
$sql_parents = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email 
                FROM users u 
                JOIN students s ON u.id = s.parent_id 
                WHERE u.role = 'parent'";

if ($selected_class_id > 0) {
    $sql_parents .= " AND s.class_id = ?";
    $stmt_parents = $conn->prepare($sql_parents);
    $stmt_parents->bind_param("i", $selected_class_id);
    $stmt_parents->execute();
    $result_parents = $stmt_parents->get_result();
} else {
    // If no class selected, fetch all parents
    $sql_parents = "SELECT id, first_name, last_name, email FROM users WHERE role = 'parent'";
    $result_parents = $conn->query($sql_parents);
}

if ($result_parents && $result_parents->num_rows > 0) {
    while ($row = $result_parents->fetch_assoc()) {
        $parents[] = $row;
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
            --navbar-height: 56px; /* Define navbar height for calculations */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5; /* Light grey background for the whole page */
            overflow-x: hidden;
            padding-top: var(--navbar-height); /* Space for fixed navbar */
        }

        /* Styles for the main navigation bar (assuming it's loaded from navbar.php and has a .navbar class or similar) */
        /* IMPORTANT: You need to ensure the main <nav> element in 'navbar.php' has the class 'fixed-top' */
        /* For example: <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top"> ... </nav> */
        .navbar { 
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030; /* Bootstrap's default z-index for fixed-top */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed;
            top: var(--navbar-height); /* Position below the fixed navbar */
            left: 0; /* Ensure it sticks to the left */
            height: calc(100vh - var(--navbar-height)); /* Fill remaining vertical space */
            overflow-y: auto; /* Enable scrolling for sidebar content if it overflows */
            transition: all 0.3s;
            z-index: 1020; /* Lower than navbar but higher than main content */
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
            padding: 20px;
            transition: all 0.3s;
            flex-grow: 1; /* Allow content to take up remaining space */
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative; /* On small screens, sidebar should not be fixed */
                top: auto; /* Remove fixed top */
                overflow-y: visible; /* Remove scrolling overflow */
            }
            body {
                padding-top: 0; /* Remove padding for smaller screens if navbar is not fixed */
            }
            .navbar {
                position: relative; /* On small screens, navbar should not be fixed */
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

        /* --- Custom Styles for Main Content --- */
        .content-card {
            background-color: #ffffff; /* White background for the card */
            padding: 30px;
            border-radius: 10px; /* More rounded corners */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); /* Stronger, softer shadow */
            margin-bottom: 30px; /* Space below the card */
            border: 1px solid #e0e0e0; /* Light border */
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
            font-weight: normal; /* Override bold from previous example */
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

        /* Responsive adjustments for the main content column */
        .main-content-col {
            flex-basis: 100%; /* Default to full width on small screens */
            max-width: 100%;
        }

        @media (min-width: 992px) { /* Adjust for larger screens (lg breakpoint) */
            .main-content-col {
                flex-basis: calc(100% - 280px); /* Full width minus sidebar width */
                max-width: calc(100% - 280px);
                margin-left: 280px; /* Push content to the right of the fixed sidebar */
            }
        }
    </style>
</head>
<body>

        <?php include 'navbar.php'?>

<div class="d-flex">
    <?php include 'sidebar.php'; ?>
      
    <div class="main-content-col p-4"> 
        <div class="content-card">
            <div class="content-header">
                <h2>Send a Meeting Message(Document) to All Parents</h2>
            </div>
            
            <div class="alert-container">
                <?php 
                // Display session messages
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

            <form action="" method="GET" class="mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="class_filter" class="form-label">Filter by Class:</label>
                        <select class="form-select" id="class_filter" name="class_id">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= ($class['id'] == $selected_class_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['class_name']) ?> (<?= htmlspecialchars($class['level']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-info">Apply Filter</button>
                    </div>
                </div>
            </form>

            <form action="send_email_all.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Filtered Parents (<span id="parentCount"><?= count($parents) ?></span>)</label>
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
                            echo '<p class="text-muted">No parents found for the selected filter.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="document" class="form-label">Choose the document (PDF au Word):</label>
                    <input type="file" name="document" id="document" class="form-control" accept=".pdf,.doc,.docx" required>
                    <div class="form-text text-muted">Aina za faili zinazokubalika: PDF, DOC, DOCX.</div>
                </div>
                
                <button type="submit" name="send_email_all" class="btn btn-primary w-100 py-2">
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
                var bsAlert = bootstrap.Alert.getInstance(alert); // Use getInstance for Bootstrap 5
                if (bsAlert) {
                    bsAlert.hide(); // Use hide() for Bootstrap 5 alerts
                }
            });
        }, 5000);
    });
</script>

</body>
</html>