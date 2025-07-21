<?php

include 'connection/db.php';
session_start();
$errors = [];
$success = '';

if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);
    $level = trim($_POST['level']);
    $teacher_id = (int)$_POST['teacher_id'];

    if (empty($class_name)) {
        $errors[] = "Class name is required.";
    }
    if (empty($level)) {
        $errors[] = "Class level is required.";
    }
    if ($teacher_id <= 0) {
        $errors[] = "A valid teacher must be selected.";
    }

    if (empty($errors)) {
        $check_class = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND level = ?");
        $check_class->bind_param("ss", $class_name, $level);
        $check_class->execute();
        $check_class->store_result();

        if ($check_class->num_rows > 0) {
            $errors[] = "This class name already exists for this level.";
        }
        $check_class->close();
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO classes (class_name, level, teacher_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $class_name, $level, $teacher_id); // "ssi" for string, string, integer

        if ($stmt->execute()) {
            $_SESSION['message'] = "Class added successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $errors[] = "Failed to add class: " . $conn->error;
        }
        $stmt->close();
    }

    // Set session messages for display
    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['message_type'] = 'danger';
    }
}

// Handle deleting a class
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_class'])) {
    $class_id = (int)$_POST['class_id'];

    if ($class_id > 0) {
        $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Class deleted successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['message'] = "Failed to delete class: " . $conn->error;
            $_SESSION['message_type'] = 'danger';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
    } else {
        $_SESSION['message'] = "Invalid class ID for deletion.";
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}


// Fetch teachers for the dropdown
$teachers = [];
$teachers_query = "SELECT id, first_name, last_name FROM users WHERE role = 'teacher' ORDER BY first_name";
$teachers_result = $conn->query($teachers_query);

if ($teachers_result && $teachers_result->num_rows > 0) {
    while ($row = $teachers_result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

// Fetch all classes to display in table
$classes = [];
$query = "SELECT c.id, c.class_name, c.level, u.first_name, u.last_name
          FROM classes c
          JOIN users u ON c.teacher_id = u.id
          ORDER BY c.level, c.class_name";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}
$conn->close(); // Close database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Class Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
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
        /* Adjust Select2 styles to match Bootstrap 5 */
        .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px); /* Bootstrap's default input height */
            padding: 0.375rem 0.75rem; /* Bootstrap's default input padding */
            border: 1px solid #ced4da; /* Bootstrap's default input border */
            border-radius: 0.25rem; 
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem + 2px); /* Match height */
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: inherit; 
        }
        .select2-dropdown {
            border: 1px solid #ced4da; 
            border-radius: 0.25rem; 
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); 
        }
        .select2-search input {
            border: 1px solid #ced4da !important; 
            border-radius: 0.25rem !important;
            padding: 0.375rem 0.75rem !important;
        }
    </style>
</head>
<body>

        <?php include 'navbar.php'?>

<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="main-content-wrapper">
        <div class="content-card mb-4">
            <div class="content-header">
                <h2>Add New Class</h2>
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

            <form action="" method="post">
                <div class="mb-3">
                    <label for="class_name" class="form-label">Class Name:</label>
                    <input type="text" class="form-control" id="class_name" name="class_name" placeholder="Example: Standard One, Form I" required>
                </div>

                <div class="mb-4">
                    <label for="level" class="form-label">Level:</label>
                    <select class="form-select" id="level" name="level" required>
                        <option value="">Select Level</option>
                        <option value="Primary">Primary</option>
                        <option value="Secondary">Secondary</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="teacher_id" class="form-label">Assign Teacher:</label>
                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php
                        if (!empty($teachers)) {
                            foreach ($teachers as $teacher) {
                                echo '<option value="' . htmlspecialchars($teacher['id']) . '">' . htmlspecialchars($teacher['first_name']) . ' ' . htmlspecialchars($teacher['last_name']) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No teachers found</option>';
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" name="add_class" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-plus-circle me-2"></i>Add Class
                </button>
            </form>
        </div>

        <div class="content-card">
            <div class="content-header">
                <h2>Existing Classes</h2>
            </div>

            <?php if (!empty($classes)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Class Name</th>
                            <th>Level</th>
                            <th>Assigned Teacher</th>
                            <th>Actions</th> </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?= $rowNum++ ?></td>
                                <td><?= htmlspecialchars($class['class_name']) ?></td>
                                <td>
                                    <span class="level-badge <?= strtolower($class['level']) ?>-badge">
                                        <?= htmlspecialchars($class['level']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($class['first_name']) ?> <?= htmlspecialchars($class['last_name']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal" data-class-id="<?= htmlspecialchars($class['id']) ?>" data-class-name="<?= htmlspecialchars($class['class_name']) ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    No classes registered yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete the class "<strong id="modalClassName"></strong>"? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form id="deleteClassForm" method="post" action="">
          <input type="hidden" name="class_id" id="modalClassId">
          <button type="submit" name="delete_class" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.hide();
                } else {
                    alert.remove();
                }
            });
        }, 5000);
        $('#teacher_id').select2({
            placeholder: "Search and select a teacher",
            allowClear: true 
        });
        var deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
        deleteConfirmationModal.addEventListener('show.bs.modal', function (event) {
          
            var button = event.relatedTarget;
            var classId = button.getAttribute('data-class-id');
            var className = button.getAttribute('data-class-name');

            var modalClassIdInput = deleteConfirmationModal.querySelector('#modalClassId');
            var modalClassNameStrong = deleteConfirmationModal.querySelector('#modalClassName');

            modalClassIdInput.value = classId;
            modalClassNameStrong.textContent = className;
        });
    });
</script>

</body>
</html>