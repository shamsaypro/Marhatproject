<?php
// Start output buffering to prevent header errors
ob_start();


include 'connection/db.php';
session_start();
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}


$errors = [];
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subject'])) {
    // Sanitize and fetch form inputs
    $subject_name = trim($_POST['subject_name']);
    $class_id = trim($_POST['class_id']);
    // Teacher is now assumed to be the class teacher, fetched via JS
    $teacher_id = !empty($_POST['teacher_id']) ? trim($_POST['teacher_id']) : NULL;

    // Validate inputs
    if (empty($subject_name)) {
        $errors[] = "Subject name is required.";
    }
    if (empty($class_id)) {
        $errors[] = "Class is required.";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        // Prepare the SQL statement
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, class_id, teacher_id) VALUES (?, ?, ?)");

        if ($teacher_id === NULL) {
            $stmt->bind_param("sii", $subject_name, $class_id, $teacher_id);
        } else {
            $stmt->bind_param("sii", $subject_name, $class_id, $teacher_id);
        }

        if ($stmt->execute()) {
            $success = "Subject added successfully!";
            // Redirect to refresh page and clear form data
            $_SESSION['message'] = $success;
            $_SESSION['message_type'] = 'success';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $errors[] = "Failed to add subject: " . $conn->error;
        }
        $stmt->close();
    }

    // If there were errors, store them in session for display
    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['message_type'] = 'danger';
    }
}

// Handle delete subject request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $subject_id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Subject deleted successfully!";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Failed to delete subject: " . $conn->error;
        $_SESSION['message_type'] = 'danger';
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch success/error messages from session if redirected
if (isset($_SESSION['message'])) {
    // Messages will be displayed and then unset below
}

// Fetch all classes for the dropdown and for grouping subjects
$classes_data = [];
$query_classes = "SELECT id, class_name, level, teacher_id FROM classes ORDER BY level, class_name"; // Assuming teacher_id in classes table
$result_classes = $conn->query($query_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
}

// Fetch all teachers for the dropdown (assuming 'users' table with role 'teacher')
$teachers_data = [];
$query_teachers = "SELECT id, first_name, last_name FROM users WHERE role = 'teacher' ORDER BY first_name, last_name";
$result_teachers = $conn->query($query_teachers);
if ($result_teachers && $result_teachers->num_rows > 0) {
    while ($row = $result_teachers->fetch_assoc()) {
        $teachers_data[] = $row;
    }
}

// Fetch all subjects and organize them by class
$subjects_by_class = [];
$query_subjects = "
    SELECT
        sub.id,
        sub.subject_name,
        sub.class_id,
        c.class_name,
        c.level,
        CONCAT(u.first_name, ' ', u.last_name) AS class_teacher_name
    FROM
        subjects sub
    JOIN
        classes c ON sub.class_id = c.id
    LEFT JOIN
        users u ON c.teacher_id = u.id AND u.role = 'teacher' -- Join with users for class teacher
    ORDER BY
        c.level, c.class_name, sub.subject_name
";
$result_subjects = $conn->query($query_subjects);

if ($result_subjects && $result_subjects->num_rows > 0) {
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects_by_class[$row['class_id']][] = $row;
    }
}
$conn->close(); // Close database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Subject Management</title>
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
            left: 0; /* Ensure it starts from the very left */
            width: 100%;
            z-index: 1030;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); /* Add a subtle shadow */
        }

        .d-flex {
            height: calc(100% - 56px); /* Full height minus navbar height (assuming 56px default) */
            margin-top: 56px; /* Offset for fixed navbar */
            display: flex; /* Ensure flex behavior */
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
            margin-top: 20px; /* Adjusted from 30px to give more space from class title */
            margin-bottom: 40px; /* Space between tables for different classes */
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

        .class-section-title {
            background-color: #e9ecef;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 600;
            color: #34495e;
            border-left: 5px solid var(--primary-color);
        }

        /* Adjust modal width for a slightly wider layout if needed */
        #addSubjectModal .modal-dialog {
            max-width: 650px; /* Adjust as needed for two columns */
        }
    </style>
</head>
<body>

       <?php include 'navbar.php'?>


<div class="d-flex">
    <?php include 'sidebar.php'; // Include the sidebar ?>

    <div class="main-content-wrapper">
        <div class="content-card mb-4">
            <div class="content-header d-flex justify-content-between align-items-center">
                <h2>Subject Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-book me-2"></i>Add New Subject
                </button>
            </div>

            <div class="alert-container">
                <?php
                // Display session messages and then clear them
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
                    <h2>Existing Subjects by Class</h2>
                </div>

                <?php if (!empty($classes_data)): ?>
                    <?php foreach ($classes_data as $class): ?>
                        <div class="class-section-title">
                            <?= htmlspecialchars($class['class_name']) ?> (Level <?= htmlspecialchars($class['level']) ?>)
                            <?php
                            // Find the class teacher's name from teachers_data
                            $class_teacher_name = 'N/A';
                            foreach ($teachers_data as $teacher) {
                                if ($teacher['id'] == $class['teacher_id']) {
                                    $class_teacher_name = htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']);
                                    break;
                                }
                            }
                            ?>
                            <span class="float-end text-muted" style="font-size: 0.9em;">Class Teacher: <?= $class_teacher_name ?></span>
                        </div>

                        <?php if (isset($subjects_by_class[$class['id']]) && !empty($subjects_by_class[$class['id']])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-custom">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Subject Name</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rowNum = 1; ?>
                                        <?php foreach ($subjects_by_class[$class['id']] as $subject): ?>
                                            <tr>
                                                <td><?= $rowNum++ ?></td>
                                                <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                <td>
                                                    <a href="edit_subject.php?id=<?= $subject['id'] ?>" class="btn btn-sm btn-info me-2" title="Edit Subject">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="?action=delete&id=<?= $subject['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this subject?');" title="Delete Subject">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center mt-3" role="alert">
                                No subjects registered for this class yet.
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        No classes registered yet. Please add classes first.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectModalLabel">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="class_id" class="form-label">Select Class</label>
                        <select name="class_id" id="class_id" class="form-select" required onchange="fetchClassTeacher(this.value)">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes_data as $class): ?>
                                <option value="<?= $class['id'] ?>"><?= $class['class_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="teacher_id" class="form-label">Assigned Class Teacher</label>
                        <select name="teacher_id" id="teacher_id" class="form-select" disabled>
                            <option value="">-- Select a Class to see Teacher --</option>
                        </select>
                        <small class="form-text text-muted">The teacher assigned to the selected class will be auto-filled.</small>
                    </div>

                    <button type="submit" name="add_subject" class="btn btn-primary">Add Subject</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) {
                    bsAlert.hide();
                } else {
                    // If instance not found, manually remove (for alerts created dynamically or before BS initializes)
                    alert.remove();
                }
            });
        }, 5000);
    });

    function fetchClassTeacher(classId) {
        const teacherSelect = document.getElementById("teacher_id");
        teacherSelect.innerHTML = `<option value="">-- Fetching Teacher --</option>`; // Temporary message
        teacherSelect.disabled = true; // Disable until fetch is complete

        if (classId === "") {
            teacherSelect.innerHTML = `<option value="">-- Select a Class to see Teacher --</option>`;
            return;
        }

        // Send an AJAX request to get the teacher for the selected class
        fetch(`get_class_teacher.php?class_id=${classId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                teacherSelect.innerHTML = ""; // Clear existing options

                if (data.teacher_id && data.teacher_name) {
                    const option = document.createElement("option");
                    option.value = data.teacher_id;
                    option.textContent = data.teacher_name;
                    option.selected = true; // Select the fetched teacher
                    teacherSelect.appendChild(option);
                } else {
                    const option = document.createElement("option");
                    option.value = "";
                    option.textContent = "-- No Teacher Assigned to this Class --";
                    teacherSelect.appendChild(option);
                }
                teacherSelect.disabled = false; // Re-enable after fetch
            })
            .catch(error => {
                console.error("Error fetching class teacher:", error);
                teacherSelect.innerHTML = `<option value="">-- Error fetching teacher --</option>`;
                teacherSelect.disabled = false;
            });
    }
</script>

</body>
</html>