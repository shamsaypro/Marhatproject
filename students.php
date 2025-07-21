<?php
include 'connection/db.php';
session_start();

if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
$errors = [];
$success = '';

// Handle form submission for adding a student
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    // Sanitize and fetch form inputs
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $class_id = trim($_POST['class_id']);
    $parent_id = trim($_POST['parent_id']);
    $gender = trim($_POST['gender']);
    $dob = trim($_POST['dob']); // Date of Birth

    // Validate inputs
    if (empty($firstname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lastname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($class_id)) {
        $errors[] = "Class is required.";
    }
    if (empty($parent_id)) {
        $errors[] = "Parent is required.";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    }
    if (empty($dob)) {
        $errors[] = "Date of Birth is required.";
    } elseif (!strtotime($dob)) {
        $errors[] = "Invalid Date of Birth format.";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class_id, parent_id, gender, dob) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiss", $firstname, $lastname, $class_id, $parent_id, $gender, $dob);

        if ($stmt->execute()) {
            $success = "Student added successfully!";
            // Redirect to refresh page and clear form data
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $errors[] = "Failed to add student: " . $conn->error;
        }
        $stmt->close();
    }

    // Set session messages for display
    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['message_type'] = 'danger';
    } elseif (!empty($success)) {
        $_SESSION['message'] = $success;
        $_SESSION['message_type'] = 'success';
    }
}

// Handle GET request for success message after redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $_SESSION['message'] = "Student added successfully!";
    $_SESSION['message_type'] = 'success';
    // Clear the GET parameter to prevent re-display on refresh
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?')); // Remove query string
    exit();
}

// Initialize filter variables
$gender_filter = isset($_GET['gender_filter']) ? trim($_GET['gender_filter']) : '';
$class_filter = isset($_GET['class_filter']) ? trim($_GET['class_filter']) : '';

// Fetch all classes for the dropdown and filter
$classes_data = [];
$query_classes = "SELECT id, class_name, level FROM classes ORDER BY level, class_name";
$result_classes = $conn->query($query_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
}

// Fetch all parents for the dropdown (assuming 'users' table with role 'parent')
$parents_data = [];
$query_parents = "SELECT id, first_name, last_name FROM users WHERE role = 'parent' ORDER BY first_name, last_name";
$result_parents = $conn->query($query_parents);
if ($result_parents && $result_parents->num_rows > 0) {
    while ($row = $result_parents->fetch_assoc()) {
        $parents_data[] = $row;
    }
}

// Fetch all students to display in table (with class name and parent name) with filters
$students = [];
$query_students = "
    SELECT
        s.id,
        s.first_name,
        s.last_name,
        c.class_name,
        c.level,
        CONCAT(u.first_name, ' ', u.last_name) AS parent_name,
        s.gender,
        s.dob
    FROM
        students s
    JOIN
        classes c ON s.class_id = c.id
    JOIN
        users u ON s.parent_id = u.id
";

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($gender_filter)) {
    $where_clauses[] = "s.gender = ?";
    $params[] = $gender_filter;
    $param_types .= 's';
}

if (!empty($class_filter)) {
    $where_clauses[] = "s.class_id = ?";
    $params[] = $class_filter;
    $param_types .= 'i';
}

if (!empty($where_clauses)) {
    $query_students .= " WHERE " . implode(" AND ", $where_clauses);
}

$query_students .= " ORDER BY s.first_name";

// Prepare and execute the statement for fetching students
if (!empty($where_clauses)) {
    $stmt_students = $conn->prepare($query_students);
    if ($stmt_students) {
        $stmt_students->bind_param($param_types, ...$params);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();
    } else {
        $errors[] = "Failed to prepare student query: " . $conn->error;
    }
} else {
    $result_students = $conn->query($query_students);
}

if (isset($result_students) && $result_students->num_rows > 0) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
}
$conn->close(); // Close database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Student Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        /* Essential for fixed layout */
        html, body {
            height: 100%; /* Make sure html and body take full height */
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevent global scroll */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .d-flex {
            height: calc(100% - 56px); /* Full height minus navbar height */
            margin-top: 56px; /* Offset for fixed navbar */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px; /* Fixed width for sidebar */
            flex-shrink: 0; /* Prevent sidebar from shrinking */
            overflow-y: auto; /* Enable scroll for sidebar if content is long */
            transition: all 0.3s;
            z-index: 1020;
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

        /* --- Main Content Area --- */
        .main-content-wrapper {
            flex-grow: 1; /* Allow content to take remaining space */
            overflow-y: auto; /* Enable vertical scrolling for content */
            padding: 20px; /* General padding for the scrollable content */
            transition: all 0.3s;
        }

        @media (max-width: 991.98px) { /* Adjust for smaller screens (lg breakpoint) */
            .navbar {
                position: relative;
            }

            .d-flex {
                height: auto; /* Allow height to be determined by content */
                margin-top: 0;
                flex-direction: column; /* Stack sidebar and content vertically */
            }

            .admin-sidebar {
                width: 100%; /* Full width on small screens */
                height: auto;
                position: relative; /* Not fixed */
                overflow-y: visible; /* Disable scroll for sidebar */
            }

            .main-content-wrapper {
                flex-grow: 1;
                overflow-y: visible; /* Disable scroll for content on small screens */
                padding: 10px; /* Adjust padding for smaller screens */
            }
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

        .form-control {
            border-radius: 5px;
            padding: 10px 15px;
        }

        .form-select {
            border-radius: 5px;
            padding: 10px 15px;
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

        /* Table specific styles */
        .table-responsive {
            margin-top: 30px;
        }

        .table-custom thead {
            background-color: #007bff;
            color: white;
        }

        .table-custom th, .table-custom td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table-custom tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .table-custom tbody tr:hover {
            background-color: #e2f3ff;
        }

        .table-custom {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .level-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }

        .primary-badge {
            background-color: #28a745;
        }

        .secondary-badge {
            background-color: #17a2b8;
        }

        .gender-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }
        .male-badge {
            background-color: #007bff; /* Blue */
        }
        .female-badge {
            background-color: #e83e8c; /* Pink */
        }

        /* Adjust modal width for a slightly wider layout if needed */
        #addStudentModal .modal-dialog {
            max-width: 650px; /* Adjust as needed for two columns */
        }
        
        /* Style for Select2 dropdown */
        .select2-container--default .select2-selection--single {
            height: 45px;
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 23px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'?>

<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="main-content-wrapper">
        <div class="content-card mb-4">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h2>Student Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
                </button>
            </div>

            <div class="alert-container">
                <?php
                if (isset($_SESSION['message'])):
                    $alertClass = ($_SESSION['message_type'] == 'success') ? 'alert-success' : 'alert-danger';
                ?>
                    <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
                        <?= $_SESSION['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                endif;
                ?>
            </div>

            <div class="content-card">
                <div class="content-header">
                    <h2>Existing Students</h2>
                </div>

                <form action="" method="GET" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="gender_filter" class="form-label">Filter by Gender:</label>
                            <select class="form-select" id="gender_filter" name="gender_filter">
                                <option value="">All Genders</option>
                                <option value="Male" <?= ($gender_filter == 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($gender_filter == 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="class_filter" class="form-label">Filter by Class:</label>
                            <select class="form-select" id="class_filter" name="class_filter">
                                <option value="">All Classes</option>
                                <?php foreach ($classes_data as $class): ?>
                                    <option value="<?= htmlspecialchars($class['id']) ?>" <?= ($class_filter == $class['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?> (<?= htmlspecialchars($class['level']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($students)): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-custom">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Class</th>
                                <th>Parent</th>
                                <th>Gender</th>
                                <th>DOB</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php $rowNum = 1; ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $rowNum++ ?></td>
                                    <td><?= htmlspecialchars($student['first_name']) ?></td>
                                    <td><?= htmlspecialchars($student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['class_name']) ?> (<?= htmlspecialchars($student['level']) ?>)</td>
                                    <td><?= htmlspecialchars($student['parent_name']) ?></td>
                                    <td>
                                        <span class="gender-badge <?= strtolower($student['gender']) ?>-badge">
                                            <?= htmlspecialchars($student['gender']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($student['dob']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        No students found matching your criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="firstname" class="form-label">First Name:</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" placeholder="Enter student's first name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="lastname" class="form-label">Last Name:</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Enter student's last name" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="class_id" class="form-label">Class:</label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes_data as $class): ?>
                                <option value="<?= htmlspecialchars($class['id']) ?>">
                                    <?= htmlspecialchars($class['class_name']) ?> (<?= htmlspecialchars($class['level']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="parent_id" class="form-label">Parent:</label>
                        <select class="form-select select2-parent" id="parent_id" name="parent_id" required>
                            <option value="">Select Parent</option>
                            <?php foreach ($parents_data as $parent): ?>
                                <option value="<?= htmlspecialchars($parent['id']) ?>">
                                    <?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gender" class="form-label">Gender:</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="dob" class="form-label">Date of Birth:</label>
                        <input type="date" class="form-control" id="dob" name="dob" required>
                    </div>
                </div>

                <button type="submit" name="add_student" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-user-plus me-2"></i>Add Student
                </button>
            </form>
        </div>
    </div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
<!-- Add jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
        
        // Initialize Select2 for parent dropdown
        $('.select2-parent').select2({
            placeholder: "Search for a parent...",
            allowClear: true,
            width: '100%',
            dropdownParent: $('#addStudentModal')
        });
    });
</script>

</body>
</html>