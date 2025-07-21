<?php
session_start(); // IMPORTANT: Start session at the beginning of the file


include 'connection/db.php';
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'teacher') {
    header("Location: index.php"); 
    exit(); 
}

$current_teacher_user_id = $_SESSION['user_data']['id']; 
$teacher_first_name = $_SESSION['user_data']['first_name'];
$teacher_last_name = $_SESSION['user_data']['last_name'];

$errors = [];
$success = '';

function insertNotification($conn, $userId, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("is", $userId, $message);
        if (!$stmt->execute()) {
            error_log("Failed to insert notification for user $userId: " . $stmt->error);
            return false;
        }
        $stmt->close();
        return true;
    } else {
        error_log("Failed to prepare notification insert statement: " . $conn->error);
        return false;
    }
}


if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_mark') {
    header('Content-Type: application/json'); 

    $student_id = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
    $subject_id = filter_var($_POST['subject_id'], FILTER_VALIDATE_INT);
    $marks = filter_var($_POST['marks'], FILTER_VALIDATE_INT);
    $term = trim($_POST['term']);
    $year = filter_var($_POST['year'], FILTER_VALIDATE_INT);
    $class_id = filter_var($_POST['class_id'], FILTER_VALIDATE_INT); 

    if ($student_id === false || $subject_id === false || $marks === false || $marks < 0 || $marks > 100 || empty($term) || $year === false || $class_id === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing data for marks entry. Please check all fields.']);
        $conn->close();
        exit();
    }

    $conn->begin_transaction(); 

    try {
        $class_grade_systems_for_calc = [];
        $stmt_grade_rules = $conn->prepare("SELECT grade_name, min_marks, max_marks FROM grade_systems WHERE class_id = ? ORDER BY min_marks DESC");
        $stmt_grade_rules->bind_param("i", $class_id);
        $stmt_grade_rules->execute();
        $result_grade_rules = $stmt_grade_rules->get_result();
        while ($row = $result_grade_rules->fetch_assoc()) {
            $class_grade_systems_for_calc[] = $row;
        }
        $stmt_grade_rules->close();

        // Check if grade system exists for this class
        if (empty($class_grade_systems_for_calc)) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'No grade system defined for the selected class. Cannot calculate grade.']);
            $conn->close();
            exit();
        }

        // 2. Calculate grade based on fetched grade systems
        $grade = 'N/A';
        foreach ($class_grade_systems_for_calc as $grade_rule) {
            if ($marks >= $grade_rule['min_marks'] && $marks <= $grade_rule['max_marks']) {
                $grade = $grade_rule['grade_name'];
                break; // Found the grade, exit loop
            }
        }
        
        
        $subject_name = 'Unknown Subject';
        $stmt_subject_name = $conn->prepare("SELECT subject_name FROM subjects WHERE id = ?");
        $stmt_subject_name->bind_param("i", $subject_id);
        $stmt_subject_name->execute();
        $subject_name_result = $stmt_subject_name->get_result();
        if ($row = $subject_name_result->fetch_assoc()) {
            $subject_name = $row['subject_name'];
        }
        $stmt_subject_name->close();
        $stmt_check = $conn->prepare("SELECT id FROM results WHERE student_id = ? AND subject_id = ? AND term = ? AND year = ?");
        $stmt_check->bind_param("iisi", $student_id, $subject_id, $term, $year);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Update existing result
            $stmt_update = $conn->prepare("UPDATE results SET marks = ?, grade = ?, teacher_id = ?, created_at = CURRENT_TIMESTAMP WHERE student_id = ? AND subject_id = ? AND term = ? AND year = ?");
            // Note: teacher_id is included in update as it's a foreign key and might need to be set
            $stmt_update->bind_param("sisiiisi", $marks, $grade, $current_teacher_user_id, $student_id, $subject_id, $term, $year);
            if (!$stmt_update->execute()) {
                throw new Exception("Failed to update result: " . $stmt_update->error);
            }
            $stmt_update->close();
            $action_message = "updated";
        } else {
            // Insert new result
            $stmt_insert = $conn->prepare("INSERT INTO results (student_id, subject_id, term, year, marks, grade, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("iisissi", $student_id, $subject_id, $term, $year, $marks, $grade, $current_teacher_user_id);
            if (!$stmt_insert->execute()) {
                throw new Exception("Failed to insert result: " . $stmt_insert->error);
            }
            $stmt_insert->close();
            $action_message = "recorded";
        }

        $conn->commit(); // Commit the transaction if all operations were successful

        // Optional: Insert notification for the teacher
        $notification_message = "Marks for " . htmlspecialchars($subject_name) . " for a student has been **" . $action_message . "** (Term: " . htmlspecialchars($term) . ", Year: " . htmlspecialchars($year) . ").";
        insertNotification($conn, $current_teacher_user_id, $notification_message);
        
        // Return success response with the calculated grade
        echo json_encode(['status' => 'success', 'message' => 'Marks ' . $action_message . ' successfully!', 'grade' => $grade]);

    } catch (Exception $e) {
        $conn->rollback(); // Rollback if any error occurred during the transaction
        error_log("Error saving marks: " . $e->getMessage()); // Log the error for debugging
        echo json_encode(['status' => 'error', 'message' => 'Failed to save marks: ' . $e->getMessage()]);
    }

    $conn->close();
    exit(); // Exit after AJAX response
}

// --- Main Page Load Logic ---

// Fetch classes that the current teacher teaches any subject in
$classes_data = [];
$query_classes = "
    SELECT DISTINCT c.id, c.class_name, c.level
    FROM classes c
    JOIN subjects s ON c.id = s.class_id
    WHERE s.teacher_id = ?
    ORDER BY c.level, c.class_name
";
$stmt_classes = $conn->prepare($query_classes);
$stmt_classes->bind_param("i", $current_teacher_user_id);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
while ($row = $result_classes->fetch_assoc()) {
    $classes_data[] = $row;
}
$stmt_classes->close();

// Fetch subjects taught by the current teacher (user_id), including their class_id
$teacher_subjects = [];
$stmt_teacher_subjects = $conn->prepare("SELECT id, subject_name, class_id FROM subjects WHERE teacher_id = ? ORDER BY subject_name");
$stmt_teacher_subjects->bind_param("i", $current_teacher_user_id);
$stmt_teacher_subjects->execute();
$result_teacher_subjects = $stmt_teacher_subjects->get_result();
while($row = $result_teacher_subjects->fetch_assoc()) {
    $teacher_subjects[] = $row;
}
$stmt_teacher_subjects->close();

// Fetch ALL grade systems to be passed to JavaScript for client-side calculation
$all_grade_systems = [];
$stmt_all_grades = $conn->prepare("SELECT class_id, grade_name, min_marks, max_marks FROM grade_systems ORDER BY class_id, min_marks DESC");
$stmt_all_grades->execute();
$result_all_grades = $stmt_all_grades->get_result();
while ($row = $result_all_grades->fetch_assoc()) {
    $all_grade_systems[] = $row;
}
$stmt_all_grades->close();

// Fixed terms and years for dropdowns
$terms = ['Term 1', 'Term 2']; // Add more terms if needed
$current_year_for_dropdown = date('Y');
$years = range($current_year_for_dropdown - 5, $current_year_for_dropdown + 2);

// Default selected values (for initial display or after form submission)
$selected_class_id = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_VALIDATE_INT) : null;
$selected_term = isset($_GET['term']) ? trim($_GET['term']) : null;
$selected_year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;
$selected_subject_id = isset($_GET['subject_id']) ? filter_var($_GET['subject_id'], FILTER_VALIDATE_INT) : null;

// Fetch data based on selected class, term, year, and subject
$students_in_class = [];
$subjects_to_display = []; // Will contain only one subject based on filter
$existing_results = [];

if ($selected_class_id && $selected_term && $selected_year && $selected_subject_id) {
    // 1. Fetch students in the selected class
    $stmt_students = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name");
    $stmt_students->bind_param("i", $selected_class_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    while ($row = $result_students->fetch_assoc()) {
        $students_in_class[] = $row;
    }
    $stmt_students->close();

    // 2. Fetch the specific subject for the selected class and THIS TEACHER
    // This ensures the teacher is authorized to enter marks for this subject in this class.
    $subject_query = "SELECT id, subject_name FROM subjects WHERE class_id = ? AND teacher_id = ? AND id = ? ORDER BY subject_name";
    $stmt_subjects = $conn->prepare($subject_query);
    $stmt_subjects->bind_param("iii", $selected_class_id, $current_teacher_user_id, $selected_subject_id);
    $stmt_subjects->execute();
    $result_subjects = $stmt_subjects->get_result();
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects_to_display[] = $row;
    }
    $stmt_subjects->close();

    // 3. Fetch existing results for these students, the selected subject, term, and year
    if (!empty($students_in_class) && !empty($subjects_to_display)) {
        $student_ids = array_column($students_in_class, 'id');
        $subject_id_to_fetch = $subjects_to_display[0]['id']; // Only one subject ID now

        if (!empty($student_ids)) {
            $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));

            // Prepare types for bind_param: 'i' for each student_id, then 'isi' for subject_id, term, year
            $types = str_repeat('i', count($student_ids)) . 'isi'; 
            $params = array_merge($student_ids, [$subject_id_to_fetch, $selected_term, $selected_year]);

            $query_results_fetch = "
                SELECT
                    student_id,
                    subject_id,
                    marks,
                    grade
                FROM
                    results
                WHERE
                    student_id IN ($placeholders_students)
                    AND subject_id = ?
                    AND term = ?
                    AND year = ?
                ";

            $stmt_results_fetch = $conn->prepare($query_results_fetch);
            $stmt_results_fetch->bind_param($types, ...$params);
            $stmt_results_fetch->execute();
            $result_fetch = $stmt_results_fetch->get_result();
            while ($row = $result_fetch->fetch_assoc()) {
                $existing_results[$row['student_id']][$row['subject_id']] = [
                    'marks' => $row['marks'],
                    'grade' => $row['grade']
                ];
            }
            $stmt_results_fetch->close();

            $class_name_for_notification = $classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name'];
            $subject_name_for_notification = $subjects_to_display[0]['subject_name'];
            $notification_message_load = "The teacher ".$teacher_first_name ." ".$teacher_last_name." record marks in a class of " . htmlspecialchars($class_name_for_notification) . " and Subject " . htmlspecialchars($subject_name_for_notification) . " for Term " . htmlspecialchars($selected_term) . ", " . htmlspecialchars($selected_year);
            insertNotification($conn, $current_teacher_user_id, $notification_message_load);
         
        }
    }
}
if ($conn && $conn->ping()) {
    $conn->close(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Teacher Page - Enter Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
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
            --navbar-height: 60px; /* Define a variable for navbar height */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden; /* Prevent horizontal body scrolling */
        }

        /* Fixed Navbar at the top */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030; /* Higher than sidebar for full coverage */
            background-color: #ffffff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            height: var(--navbar-height); /* Set navbar height */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed;
            top: var(--navbar-height); /* Position below the fixed navbar */
            bottom: 0; /* Extend to bottom of viewport */
            overflow-y: auto; /* Allow sidebar content to scroll if needed */
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
            margin-left: 280px; /* Space for the sidebar */
            padding: 20px;
            padding-top: calc(var(--navbar-height) + 20px); /* Adjust for fixed navbar height + some top padding */
            transition: all 0.3s;
            overflow-y: auto; /* Allow content to scroll */
            min-height: 100vh; /* Ensure it takes at least full viewport height */
            box-sizing: border-box; /* Include padding in height calculation */
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative; /* Allow sidebar to flow normally on small screens */
                top: 0; /* Reset top for small screens */
                overflow-y: visible; /* Reset scroll for smaller screens */
            }

            .main-content {
                margin-left: 0;
                padding-top: 20px; /* Reset padding-top as navbar won't be fixed relative to content */
            }

            .navbar {
                position: relative; /* Allow navbar to flow normally on small screens */
                width: 100%;
                margin-left: 0;
                margin-bottom: 0;
            }
        }

        /* Specific styles for marks entry */
        .filter-form {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .table-responsive {
            margin-top: 20px;
        }

        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .table thead th {
            background-color: #f2f2f2; /* Light gray for table header */
            color: #333;
        }

        .marks-input {
            width: 80px; /* Smaller width for marks input */
            text-align: center;
        }

        .grade-display {
            font-weight: bold;
            text-align: center;
        }
        
        /* Toast Container for notifications */
        #toastContainer {
            position: fixed;
            top: calc(var(--navbar-height) + 10px); /* Below navbar with some margin */
            right: 20px;
            z-index: 1100; /* Above modals */
        }
        .toast {
            min-width: 250px;
        }

    </style>
</head>
<body>
<?php include 'navbar.php'?>

<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid p-3 main-content">
        <div class="header">
            <h1>Welcome Mr./Mrs. <?php echo htmlspecialchars($teacher_first_name . ' ' . $teacher_last_name); ?>!</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

        <div class="card p-4 mb-4">
            <h4 class="card-title mb-4">Select Result Criteria</h4>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label for="class_id">Class:</label>
                    <select name="class_id" id="class_id" class="form-select" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes_data as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select name="subject_id" id="subject_id" class="form-select" required>
                        <option value="">-- Select Subject --</option>
                        <?php
                        if ($selected_class_id) {
                            $initial_filtered_subjects = array_filter($teacher_subjects, function($subject) use ($selected_class_id) {
                                return $subject['class_id'] == $selected_class_id;
                            });
                            foreach ($initial_filtered_subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject['id']); ?>"
                                    <?php echo ($selected_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach;
                        } else {
                            // If no class selected, prompt user to select class first
                            echo '<option value="">-- Select Class First --</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="term">Term:</label>
                    <select name="term" id="term" class="form-select" required>
                        <option value="">-- Select Term --</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"
                                <?php echo ($selected_term == $t) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year">Year:</label>
                    <select name="year" id="year" class="form-select" required>
                        <option value="">-- Select Year --</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo htmlspecialchars($y); ?>"
                                <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-center align-items-end">
                    <button type="submit" class="btn btn-primary w-50">Load Students</button>
                </div>
            </form>
        </div>

        <?php
        // Logic to display table or no data message
        if ($selected_class_id && $selected_term && $selected_year && $selected_subject_id): ?>
            <?php if (empty($students_in_class)): ?>
                <div class="alert alert-info text-center" role="alert">
                    No students registered in this class at the moment.
                </div>
            <?php elseif (empty($subjects_to_display)): ?>
                <div class="alert alert-warning text-center" role="alert">
                    The subject you selected is not taught by you in this class or does not exist. Please select a valid subject.
                </div>

            <?php else: ?>
                <div class="card p-4">
                    <h4 class="card-title mb-4">Enter Marks for Class: <span class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> - Subject: <span class="text-primary"><?php echo htmlspecialchars($subjects_to_display[0]['subject_name']); ?></span> (<small><?php echo htmlspecialchars($selected_term); ?>, <?php echo htmlspecialchars($selected_year); ?></small>)</h4>
                    
                    <form id="marksEntryForm"> 
                        <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">
                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                        <input type="hidden" name="class_id_selected" value="<?php echo htmlspecialchars($selected_class_id); ?>"> <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="marksTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Marks (<?php echo htmlspecialchars($subjects_to_display[0]['subject_name']); ?>)</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($students_in_class)): ?>
                                        <?php foreach ($students_in_class as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <?php
                                                $current_subject = $subjects_to_display[0];
                                                // Get existing marks and grade if available
                                                $current_marks = $existing_results[$student['id']][$current_subject['id']]['marks'] ?? '';
                                                $current_grade = $existing_results[$student['id']][$current_subject['id']]['grade'] ?? '';
                                                ?>
                                                <td>
                                                    <input type="number"
                                                        name="marks_<?php echo htmlspecialchars($student['id']); ?>_<?php echo htmlspecialchars($current_subject['id']); ?>"
                                                        value="<?php echo htmlspecialchars($current_marks); ?>"
                                                        min="0" max="100" class="form-control marks-input"
                                                        data-student-id="<?php echo htmlspecialchars($student['id']); ?>"
                                                        data-subject-id="<?php echo htmlspecialchars($current_subject['id']); ?>"
                                                        data-class-id="<?php echo htmlspecialchars($selected_class_id); ?>"
                                                        oninput="calculateGradeDisplay(this)">
                                                </td>
                                                <td class="grade-display" id="grade-<?php echo htmlspecialchars($student['id']); ?>-<?php echo htmlspecialchars($current_subject['id']); ?>">
                                                    <?php echo htmlspecialchars($current_grade); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No students found for the selected class and criteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Please select a **Class**, **Subject**, **Term**, and **Year** from the form above to start entering marks.
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Store grade systems fetched from PHP globally accessible
    // This data is used for real-time client-side grade calculation
    const allGradeSystems = <?php echo json_encode($all_grade_systems); ?>;
    let currentClassGradeRules = []; // Will be populated dynamically based on selected class

    /**
     * Calculates the grade based on the marks and the current class's grade rules.
     * @param {number} marks The marks to calculate the grade for.
     * @returns {string} The calculated grade name (e.g., 'A', 'B', 'C') or 'N/A'.
     */
    function calculateGradeFromRules(marks) {
        // Ensure marks are valid numbers within the range
        if (isNaN(marks) || marks < 0 || marks > 100) {
            return 'N/A';
        }

        // Iterate through the grade rules for the currently selected class
        for (const rule of currentClassGradeRules) {
            // Check if marks fall within the current rule's range
            if (marks >= rule.min_marks && marks <= rule.max_marks) {
                return rule.grade_name; // Return the grade name if a match is found
            }
        }
        return 'N/A'; // Return 'N/A' if no rule matches
    }

    /**
     * Displays a Bootstrap Toast notification.
     * @param {string} type 'success' or 'danger' for styling the toast.
     * @param {string} message The message to display in the toast.
     */
    function showToast(type, message) {
        const toastContainer = $('#toastContainer');
        const toastClass = type === 'success' ? 'bg-success' : 'bg-danger'; // Apply Bootstrap background class
        
        // Create the toast HTML dynamically
        const toastHtml = `
            <div class="toast align-items-center text-white ${toastClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.append(toastHtml); // Add the toast to the container
        const newToast = toastContainer.children().last(); // Get the newly added toast
        const bsToast = new bootstrap.Toast(newToast, { delay: 3000 }); // Initialize Bootstrap Toast with a delay
        bsToast.show(); 
        newToast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    }


    $(document).ready(function() {
        // Function to update currentClassGradeRules when the selected class changes
        function updateClassGradeRules() {
            const selectedClassId = $('#class_id').val(); // Get the currently selected class ID
            if (selectedClassId) {
                // Filter all grade systems to get only those for the selected class
                currentClassGradeRules = allGradeSystems.filter(rule => rule.class_id == selectedClassId);
            } else {
                currentClassGradeRules = []; // Clear if no class is selected
            }
            // console.log("Current class grade rules:", currentClassGradeRules); // For debugging
        }

        // Initial call to set grade rules on page load if a class is already selected
        updateClassGradeRules();

        // Event listener for class_id dropdown change
        $('#class_id').on('change', function() {
            const selectedClassId = $(this).val();
            const subjectSelect = $('#subject_id');
            subjectSelect.empty(); // Clear existing subjects

            if (selectedClassId) {
                // Populate subject dropdown based on the selected class
                const filteredSubjects = <?php echo json_encode($teacher_subjects); ?>.filter(subject => subject.class_id == selectedClassId);
                if (filteredSubjects.length > 0) {
                    subjectSelect.append('<option value="">-- Select Subject --</option>');
                    filteredSubjects.forEach(subject => {
                        subjectSelect.append(`<option value="${subject.id}">${subject.subject_name}</option>`);
                    });
                } else {
                    subjectSelect.append('<option value="">No Subjects for this Class</option>');
                }
                updateClassGradeRules(); // Update grade rules for the new class
            } else {
                subjectSelect.append('<option value="">-- Select Class First --</option>');
                currentClassGradeRules = []; // Clear grade rules
            }
        });

        // Function to calculate and display grade in real-time
        window.calculateGradeDisplay = function(inputElement) {
            const marks = parseInt(inputElement.value);
            const studentId = $(inputElement).data('student-id');
            const subjectId = $(inputElement).data('subject-id');
            const gradeDisplayElement = $(`#grade-${studentId}-${subjectId}`);

            const grade = calculateGradeFromRules(marks);
            gradeDisplayElement.text(grade);
        };

        // Event listener for marks input change (with debouncing for performance)
        let typingTimer;
        const doneTypingInterval = 800; // milliseconds

        $('#marksTable').on('input', '.marks-input', function() {
            const inputElement = this;
            clearTimeout(typingTimer); // Clear previous timer
            typingTimer = setTimeout(function() {
                const studentId = $(inputElement).data('student-id');
                const subjectId = $(inputElement).data('subject-id');
                const marks = parseInt(inputElement.value);
                const term = $('input[name="term"]').val();
                const year = $('input[name="year"]').val();
                const class_id = $('input[name="class_id_selected"]').val(); // Get selected class_id

                if (isNaN(marks) || marks < 0 || marks > 100) {
                    showToast('danger', 'Marks must be between 0 and 100.');
                    // Optionally reset the input or grade display
                    // $(inputElement).val('');
                    // $(`#grade-${studentId}-${subjectId}`).text('N/A');
                    return;
                }

                $.ajax({
                    url: '', // Send to the same PHP file
                    type: 'POST',
                    data: {
                        action: 'save_mark',
                        student_id: studentId,
                        subject_id: subjectId,
                        marks: marks,
                        term: term,
                        year: year,
                        class_id: class_id // Pass class_id for grade lookup
                    },
                    dataType: 'json', // Expect JSON response
                    success: function(response) {
                        if (response.status === 'success') {
                            showToast('success', response.message);
                            // Update the grade displayed on the page
                            $(`#grade-${studentId}-${subjectId}`).text(response.grade);
                        } else {
                            showToast('danger', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error);
                        showToast('danger', 'An error occurred while saving marks.');
                    }
                });
            }, doneTypingInterval); // Wait for user to stop typing
        });
    });
</script>
</body>
</html>