<?php
// edit_subject.php
ob_start();
include 'connection/db.php'; // Ensure this path is correct relative to edit_subject.php
session_start();

$errors = [];
$success = '';
$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$subject_data = null;

// Fetch subject data if editing
if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT id, subject_name, class_id, teacher_id FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $subject_data = $result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Subject not found.";
        $_SESSION['message_type'] = 'danger';
        header("Location: subjects.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['message'] = "Invalid subject ID provided for editing.";
    $_SESSION['message_type'] = 'danger';
    header("Location: subjects.php");
    exit();
}

// Handle form submission for updating a subject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $class_id = trim($_POST['class_id']);
    // The teacher_id here should be the ID from the selected class's assigned teacher.
    // We fetch it based on the selected class_id to ensure consistency.
    $assigned_teacher_id = NULL; // Default to NULL

    // Fetch the teacher_id from the classes table based on the selected class_id
    if (!empty($class_id)) {
        $stmt_class_teacher = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
        $stmt_class_teacher->bind_param("i", $class_id);
        $stmt_class_teacher->execute();
        $result_class_teacher = $stmt_class_teacher->get_result();
        if ($result_class_teacher->num_rows > 0) {
            $class_teacher_row = $result_class_teacher->fetch_assoc();
            $assigned_teacher_id = $class_teacher_row['teacher_id'];
        }
        $stmt_class_teacher->close();
    }


    if (empty($subject_name)) {
        $errors[] = "Subject name is required.";
    }
    if (empty($class_id)) {
        $errors[] = "Class is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, class_id = ?, teacher_id = ? WHERE id = ?");
        // Use $assigned_teacher_id which was fetched from the classes table
        $stmt->bind_param("siii", $subject_name, $class_id, $assigned_teacher_id, $subject_id);

        if ($stmt->execute()) {
            $success = "Subject updated successfully!";
            $_SESSION['message'] = $success;
            $_SESSION['message_type'] = 'success';
            header("Location: subjects.php"); // Redirect back to the main list
            exit();
        } else {
            $errors[] = "Failed to update subject: " . $conn->error;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['message_type'] = 'danger';
    }
}

// Fetch all classes for dropdown
$classes_data = [];
$query_classes = "SELECT id, class_name, level, teacher_id FROM classes ORDER BY level, class_name";
$result_classes = $conn->query($query_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
}

// Fetch all teachers for dropdown (used for displaying teacher names)
$teachers_data = [];
$query_teachers = "SELECT id, first_name, last_name FROM users WHERE role = 'teacher' ORDER BY first_name, last_name";
$result_teachers = $conn->query($query_teachers);
if ($result_teachers && $result_teachers->num_rows > 0) {
    while ($row = $result_teachers->fetch_assoc()) {
        $teachers_data[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Edit Subject</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; }
        .container { margin-top: 50px; }
        .card { border-radius: 10px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
        .card-header { background-color: #007bff; color: white; border-radius: 10px 10px 0 0; padding: 15px 20px;}
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .alert-container { margin-top: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <h3 style="color: white;font-family: Times-New-Roman">RESULT MANAGEMENT SYSTEM</h3>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Edit Subject: <?= htmlspecialchars($subject_data['subject_name']) ?></h2>
                </div>
                <div class="card-body">
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

                    <form method="POST">
                        <div class="mb-3">
                            <label for="subject_name" class="form-label">Subject Name</label>
                            <input type="text" name="subject_name" class="form-control" value="<?= htmlspecialchars($subject_data['subject_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="class_id" class="form-label">Select Class</label>
                            <select name="class_id" id="class_id" class="form-select" required onchange="fetchClassTeacher(this.value)">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes_data as $class): ?>
                                    <option value="<?= $class['id'] ?>" <?= ($class['id'] == $subject_data['class_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Assigned Class Teacher</label>
                            <select name="teacher_id" id="teacher_id" class="form-select" disabled>
                                <?php
                                // Initial population of the teacher dropdown based on the currently selected class
                                $selected_class_teacher_name = '-- No Teacher Assigned to this Class --';
                                $selected_class_teacher_id = '';

                                // Find the teacher_id for the currently selected class in subject_data
                                $current_subject_class_id = $subject_data['class_id'];
                                foreach ($classes_data as $class) {
                                    if ($class['id'] == $current_subject_class_id) {
                                        // Now find the teacher's name using this teacher_id from teachers_data
                                        foreach ($teachers_data as $teacher) {
                                            if ($teacher['id'] == $class['teacher_id']) {
                                                $selected_class_teacher_id = $teacher['id'];
                                                $selected_class_teacher_name = htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']);
                                                break; // Found the teacher for this class
                                            }
                                        }
                                        break; // Found the class
                                    }
                                }
                                ?>
                                <option value="<?= $selected_class_teacher_id ?>" <?= ($selected_class_teacher_id != '') ? 'selected' : '' ?>>
                                    <?= $selected_class_teacher_name ?>
                                </option>
                            </select>
                            <small class="form-text text-muted">The teacher assigned to the selected class will be auto-filled.</small>
                        </div>

                        <button type="submit" name="update_subject" class="btn btn-primary">Update Subject</button>
                        <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // This function is duplicated from subject_management.php. Consider putting it in a shared JS file.
    function fetchClassTeacher(classId) {
        const teacherSelect = document.getElementById("teacher_id");
        teacherSelect.innerHTML = `<option value="">-- Fetching Teacher --</option>`;
        teacherSelect.disabled = true;

        if (classId === "") {
            teacherSelect.innerHTML = `<option value="">-- Select a Class to see Teacher --</option>`;
            return;
        }

        fetch(`get_class_teacher.php?class_id=${classId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                teacherSelect.innerHTML = "";
                if (data.teacher_id && data.teacher_name) {
                    const option = document.createElement("option");
                    option.value = data.teacher_id;
                    option.textContent = data.teacher_name;
                    option.selected = true;
                    teacherSelect.appendChild(option);
                } else {
                    const option = document.createElement("option");
                    option.value = "";
                    option.textContent = "-- No Teacher Assigned to this Class --";
                    teacherSelect.appendChild(option);
                }
                teacherSelect.disabled = false;
            })
            .catch(error => {
                console.error("Error fetching class teacher:", error);
                teacherSelect.innerHTML = `<option value="">-- Error fetching teacher --</option>`;
                teacherSelect.disabled = false;
            });
    }

    // Call fetchClassTeacher on page load if a class is already selected (for existing subject)
    document.addEventListener('DOMContentLoaded', function() {
        const classIdInput = document.getElementById('class_id');
        if (classIdInput.value) {
            fetchClassTeacher(classIdInput.value);
        }
    });
</script>

</body>
</html>