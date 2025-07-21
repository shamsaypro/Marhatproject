<?php
ob_start(); // Start output buffering

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include 'connection/db.php'; // Ensure this path is correct

// Redirect if user is not logged in or not an Admin
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get logged-in user's data (Admin)
$current_user_id = $_SESSION['user_data']['id'];
$user_first_name = $_SESSION['user_data']['first_name'];
$user_last_name = $_SESSION['user_data']['last_name'];
$user_role = $_SESSION['user_data']['role'];

// Initialize variables for messages
$errors = [];
$success = '';

// Function to calculate grade based on marks (for individual subjects)
function calculateGrade($marks) {
    if (!is_numeric($marks)) {
        return 'N/A';
    }
    if ($marks >= 80 && $marks <= 100) {
        return 'A';
    } elseif ($marks >= 65 && $marks <= 79) {
        return 'B';
    } elseif ($marks >= 50 && $marks <= 64) {
        return 'C';
    } elseif ($marks >= 30 && $marks <= 49) {
        return 'D';
    } elseif ($marks >= 0 && $marks <= 29) {
        return 'F';
    } else {
        return 'N/A'; // Not Applicable or invalid marks
    }
}

// Function to calculate overall division
function calculateDivision($averageMarks) {
    if (!is_numeric($averageMarks)) {
        return 'N/A';
    }
    if ($averageMarks >= 80) {
        return 'A';
    } elseif ($averageMarks >= 65) {
        return 'B';
    } elseif ($averageMarks >= 50) {
        return 'C';
    } elseif ($averageMarks >= 30) {
        return 'D';
    } elseif ($averageMarks >= 0) {
        return 'F';
    } else {
        return 'N/A';
    }
}

// --- Handle "Publish Results" action (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_results'])) {
    $publish_class_id = filter_var($_POST['publish_class_id'], FILTER_VALIDATE_INT);
    $publish_year = filter_var($_POST['publish_year'], FILTER_VALIDATE_INT);
    $publish_term = htmlspecialchars($_POST['publish_term']);

    if (!$publish_class_id || !$publish_year || !in_array($publish_term, ['Term 1', 'Term 2', 'Annual'])) {
        $errors[] = "Tafadhali jaza vigezo sahihi vya darasa, mwaka, na muhula ili kuchapisha matokeo.";
    } else {
        // Prepare to update the is_published status of results to TRUE (1)
        // For 'Annual', we need to publish both Term 1 and Term 2 for the specified class and year.
        // For specific terms (Term 1, Term 2), only publish for that term.

        $update_query = "UPDATE results SET is_published = 1 WHERE class_id = ? AND year = ?";
        $bind_types = "ii";
        $bind_params = [$publish_class_id, $publish_year];

        if ($publish_term !== 'Annual') {
            $update_query .= " AND term = ?";
            $bind_types .= "s";
            $bind_params[] = $publish_term;
        }

        $stmt_update = $conn->prepare($update_query);

        if ($stmt_update === false) {
            $errors[] = "Kuna tatizo la kiufundi la database: " . $conn->error;
        } else {
            // Dynamically bind parameters
            $stmt_update->bind_param($bind_types, ...$bind_params);

            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    // Fetch class name for success message
                    $class_name_for_msg = '';
                    $stmt_class_name = $conn->prepare("SELECT class_name FROM classes WHERE id = ?");
                    if ($stmt_class_name) {
                        $stmt_class_name->bind_param("i", $publish_class_id);
                        $stmt_class_name->execute();
                        $result_class_name = $stmt_class_name->get_result();
                        if ($row_class_name = $result_class_name->fetch_assoc()) {
                            $class_name_for_msg = $row_class_name['class_name'];
                        }
                        $stmt_class_name->close();
                    }
                    $success = "Matokeo ya darasa la " . htmlspecialchars($class_name_for_msg) . " kwa " . htmlspecialchars($publish_term) . " mwaka " . htmlspecialchars($publish_year) . " yamechapishwa kwa mafanikio!";
                } else {
                    $errors[] = "Hakuna matokeo yaliyopatikana au yaliyohitaji kuchapishwa kwa vigezo ulivyochagua.";
                }
            } else {
                $errors[] = "Kushindwa kuchapisha matokeo: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
    // Redirect to prevent form resubmission and display messages
    $redirect_url = "admin_results.php?class_id=" . urlencode($publish_class_id) . "&year=" . urlencode($publish_year) . "&term=" . urlencode($publish_term);
    if ($success) {
        $redirect_url .= "&msg=" . urlencode($success);
    }
    if (!empty($errors)) {
        $redirect_url .= "&err=" . urlencode(implode(",", $errors));
    }
    header("Location: " . $redirect_url);
    exit();
}

// Fetch success/error messages from URL if redirected
if (isset($_GET['msg'])) {
    $success = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['err'])) {
    $errors = array_merge($errors, explode(",", htmlspecialchars($_GET['err'])));
}

// --- End Handle "Publish Results" action ---

// Fetch all classes for dropdown
$classes_data = [];
$query_classes = "SELECT id, class_name, level FROM classes ORDER BY level, class_name";
$result_classes = $conn->query($query_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
}

// Fixed terms and years for dropdowns
$terms = ['Term 1', 'Term 2', 'Annual'];
$current_year_for_dropdown = date('Y');
$years = range($current_year_for_dropdown - 5, $current_year_for_dropdown + 2);

// Default selected values (for initial display)
$selected_class_id = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_VALIDATE_INT) : null;
$selected_year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;
$selected_term = isset($_GET['term']) ? htmlspecialchars($_GET['term']) : null;

// Data structures to hold results
$students_in_class = [];
$subjects_to_display = [];
$results_by_student_subject_term = []; // New structure to hold results by student, subject, and term

if ($selected_class_id && $selected_year && $selected_term) {
    // 1. Fetch students in the selected class
    $stmt_students = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name");
    if ($stmt_students === false) {
        die("Error preparing student statement: " . $conn->error);
    }
    $stmt_students->bind_param("i", $selected_class_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    while ($row = $result_students->fetch_assoc()) {
        $students_in_class[] = $row;
    }
    $stmt_students->close();

    // 2. Fetch ALL subjects for the selected class
    $subject_query = "SELECT id, subject_name FROM subjects WHERE class_id = ? ORDER BY subject_name";
    $stmt_subjects = $conn->prepare($subject_query);
    if ($stmt_subjects === false) {
        die("Error preparing subject statement: " . $conn->error);
    }
    $stmt_subjects->bind_param("i", $selected_class_id);
    $stmt_subjects->execute();
    $result_subjects = $stmt_subjects->get_result();
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects_to_display[] = $row;
    }
    $stmt_subjects->close();

    // 3. Fetch all existing results for these students and subjects for all terms if 'Annual' is selected,
    // otherwise for the specific term.
    if (!empty($students_in_class) && !empty($subjects_to_display)) {
        $student_ids = array_column($students_in_class, 'id');
        $subject_ids = array_column($subjects_to_display, 'id');

        // Create placeholders for IN clause
        $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));
        $placeholders_subjects = implode(',', array_fill(0, count($subject_ids), '?'));

        // Prepare bind types and parameters for the query
        $bind_types = str_repeat('i', count($student_ids)) . str_repeat('i', count($subject_ids)) . 'i';
        $bind_params = array_merge($student_ids, $subject_ids, [$selected_year]);

        $term_condition = "";
        if ($selected_term !== 'Annual') {
            $term_condition = " AND term = ?";
            $bind_types .= 's';
            $bind_params[] = $selected_term;
        }

        $query_all_results_fetch = "
            SELECT
                student_id,
                subject_id,
                marks,
                grade,
                term,
                is_published
            FROM
                results
            WHERE
                student_id IN ($placeholders_students)
                AND subject_id IN ($placeholders_subjects)
                AND year = ?
                $term_condition
            ORDER BY student_id, subject_id, term
        ";

        $stmt_all_results_fetch = $conn->prepare($query_all_results_fetch);

        if ($stmt_all_results_fetch === false) {
            die("Error preparing results fetch statement: " . $conn->error);
        }

        // Use call_user_func_array for binding parameters dynamically
        // Note: For PHP 8+, you can directly use ...$bind_params with prepare/bind_param
        $stmt_all_results_fetch->bind_param($bind_types, ...$bind_params);

        $stmt_all_results_fetch->execute();
        $result_fetch = $stmt_all_results_fetch->get_result();

        while ($row = $result_fetch->fetch_assoc()) {
            $results_by_student_subject_term[$row['student_id']][$row['subject_id']][$row['term']] = [
                'marks' => $row['marks'],
                'grade' => $row['grade'],
                'is_published' => (bool)$row['is_published'] // Store as boolean
            ];
        }
        $stmt_all_results_fetch->close();
    }
}
$conn->close(); // Close database connection at the end of PHP logic
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ukurasa wa Matokeo ya Admin</title>
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed; /* Keep sidebar fixed */
            top: 0;
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
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
            overflow-y: auto; /* Allow content to scroll */
            min-height: 100vh; /* Take full viewport height */
            box-sizing: border-box; /* Include padding in height calculation */
            padding-top: 70px; /* Adjust for fixed navbar height if it exists */
        }

        /* Adjustments for fixed navbar - assuming 'navbar.php' generates a standard Bootstrap navbar */
        .navbar {
            position: fixed;
            top: 0;
            width: calc(100% - 280px); /* Full width minus sidebar width */
            margin-left: 280px; /* Aligned with main content */
            z-index: 999; /* Below sidebar, above main content */
            background-color: #ffffff; /* Example: ensure navbar has a background */
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative; /* Allow sidebar to flow normally on small screens */
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
            }
        }

        /* Specific styles for marks display */
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
            margin-bottom: 40px; /* Spacing between tables */
        }

        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .table thead th {
            background-color: #f2f2f2; /* Light gray for table header */
            color: #333;
            text-align: center;
            vertical-align: middle;
        }

        .table tbody td {
            text-align: center;
            vertical-align: middle;
        }

        .grade-display {
            font-weight: bold;
            text-align: center;
        }

        .total-grade {
            font-weight: bold;
            color: var(--primary-color);
        }
        .overall-division {
            font-weight: bold;
            color: var(--success-color);
            font-size: 1.1em;
        }
        .annual-summary-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid var(--primary-color);
        }
        .report-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 30px;
            margin-bottom: 30px;
        }
        .report-card-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .report-card-header h2 {
            color: var(--primary-color);
            font-weight: bold;
        }
        .student-info, .summary-info, .teacher-comments {
            margin-bottom: 20px;
        }
        .student-info strong, .summary-info strong {
            color: #555;
        }
        .subject-table th, .subject-table td {
            text-align: left;
            padding: 8px;
        }
        .subject-table thead th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'?>
<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid p-3 main-content">
        <div class="header" style="margin-top: 80px;">
            <h1>Karibu, <?php echo htmlspecialchars($user_first_name . ' ' . $user_last_name); ?>!</h1>
            <h3>Matokeo ya Wanafunzi</h3>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
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

        <div class="card p-4 mb-4">
            <h4 class="card-title mb-4">Chagua Vigezo vya Ripoti ya Matokeo</h4>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label for="class_id">Darasa:</label>
                    <select name="class_id" id="class_id" class="form-select" required>
                        <option value="">-- Chagua Darasa --</option>
                        <?php foreach ($classes_data as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' (Level ' . $class['level'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year">Mwaka:</label>
                    <select name="year" id="year" class="form-select" required>
                        <option value="">-- Chagua Mwaka --</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo htmlspecialchars($y); ?>"
                                <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="term">Muhula:</label>
                    <select name="term" id="term" class="form-select" required>
                        <option value="">-- Chagua Muhula --</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"
                                <?php echo ($selected_term == $t) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-center align-items-end">
                    <button type="submit" class="btn btn-primary w-50">Onyesha Ripoti</button>
                </div>
            </form>
        </div>

        <?php
        // Logic to display table or no data message
        if ($selected_class_id && $selected_year && $selected_term): ?>
            <?php if (empty($students_in_class)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Hakuna wanafunzi waliosajiliwa katika darasa hili kwa sasa.
                </div>
            <?php elseif (empty($subjects_to_display)): ?>
                <div class="alert alert-warning text-center" role="alert">
                    Hakuna masomo yaliyosajiliwa kwa darasa hili.
                </div>
            <?php else:
                // Determine if there's any data for the selected term/annual
                $has_data_to_display = false;
                foreach ($students_in_class as $student) {
                    if (isset($results_by_student_subject_term[$student['id']])) {
                        if ($selected_term === 'Annual') {
                            foreach ($subjects_to_display as $subject) {
                                $marks_t1 = $results_by_student_subject_term[$student['id']][$subject['id']]['Term 1']['marks'] ?? null;
                                $marks_t2 = $results_by_student_subject_term[$student['id']][$subject['id']]['Term 2']['marks'] ?? null;
                                if ($marks_t1 !== null || $marks_t2 !== null) {
                                    $has_data_to_display = true;
                                    break 2; // Break both inner and outer loops
                                }
                            }
                        } else {
                            // For a specific term, just check if any subject has data
                            foreach ($subjects_to_display as $subject) {
                                if (isset($results_by_student_subject_term[$student['id']][$subject['id']][$selected_term])) {
                                    $has_data_to_display = true;
                                    break 2; // Break both inner and outer loops
                                }
                            }
                        }
                    }
                }

                if (!$has_data_to_display): ?>
                    <div class="alert alert-info text-center" role="alert">
                        Hakuna matokeo yaliyopatikana kwa muhula huu (<?php echo htmlspecialchars($selected_term); ?>)
                        na mwaka (<?php echo htmlspecialchars($selected_year); ?>) kwa darasa hili.
                    </div>
                <?php else: ?>
                    <div class="card p-4 mb-4">
                        <h4 class="card-title mb-4">Ripoti ya Matokeo kwa Darasa la: <span
                                class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> -
                            <small><?php echo htmlspecialchars($selected_term); ?> (<?php echo htmlspecialchars($selected_year); ?>)</small>
                        </h4>

                        <?php foreach ($students_in_class as $student):
                            $student_id = $student['id'];
                            $student_full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);

                            $student_total_marks = 0;
                            $student_subjects_counted = 0;
                            $subject_results = [];
                            $best_subject_mark = -1;
                            $best_subject_name = '';
                            $worst_subject_mark = 101;
                            $worst_subject_name = '';
                            $passed_subjects_count = 0;
                            $is_any_result_for_student_published = false; // Track if any result for this student is published

                            foreach ($subjects_to_display as $subject) {
                                $subject_id = $subject['id'];
                                $subject_name = htmlspecialchars($subject['subject_name']);

                                $marks_I = 'N/A';
                                $grade_I = 'N/A';
                                $marks_II = 'N/A';
                                $grade_II = 'N/A';
                                $total_subject_marks = 'N/A';
                                $average_subject_marks = 'N/A';
                                $comments_subject = 'Hakuna maoni'; // Default comment

                                if ($selected_term === 'Annual') {
                                    $result_data_t1 = $results_by_student_subject_term[$student_id][$subject_id]['Term 1'] ?? null;
                                    $result_data_t2 = $results_by_student_subject_term[$student_id][$subject_id]['Term 2'] ?? null;

                                    $marks_t1 = $result_data_t1['marks'] ?? null;
                                    $grade_t1 = $result_data_t1['grade'] ?? 'N/A';
                                    $marks_t2 = $result_data_t2['marks'] ?? null;
                                    $grade_t2 = $result_data_t2['grade'] ?? 'N/A';

                                    $is_published_t1 = $result_data_t1['is_published'] ?? false;
                                    $is_published_t2 = $result_data_t2['is_published'] ?? false;

                                    // If any term result is published, set the flag for the student
                                    if ($is_published_t1 || $is_published_t2) {
                                        $is_any_result_for_student_published = true;
                                    }

                                    $marks_I = ($marks_t1 !== null) ? $marks_t1 : 'N/A';
                                    $grade_I = ($grade_t1 !== 'N/A') ? $grade_t1 : 'N/A';
                                    $marks_II = ($marks_t2 !== null) ? $marks_t2 : 'N/A';
                                    $grade_II = ($grade_t2 !== 'N/A') ? $grade_t2 : 'N/A';

                                    $current_subject_total = 0;
                                    $current_terms_for_subject = 0;

                                    if ($marks_t1 !== null && is_numeric($marks_t1)) {
                                        $current_subject_total += (int)$marks_t1;
                                        $current_terms_for_subject++;
                                    }
                                    if ($marks_t2 !== null && is_numeric($marks_t2)) {
                                        $current_subject_total += (int)$marks_t2;
                                        $current_terms_for_subject++;
                                    }

                                    if ($current_terms_for_subject > 0) {
                                        $total_subject_marks = $current_subject_total;
                                        $average_subject_marks = round($current_subject_total / $current_terms_for_subject, 2);
                                        $student_total_marks += $average_subject_marks;
                                        $student_subjects_counted++;

                                        if ($average_subject_marks >= 50) { // Assuming 50 is passing mark
                                            $passed_subjects_count++;
                                        }

                                        if ($average_subject_marks > $best_subject_mark) {
                                            $best_subject_mark = $average_subject_marks;
                                            $best_subject_name = $subject_name;
                                        }
                                        if ($average_subject_marks < $worst_subject_mark) {
                                            $worst_subject_mark = $average_subject_marks;
                                            $worst_subject_name = $subject_name;
                                        }
                                    }

                                } else { // For Term 1 or Term 2
                                    $result_data = $results_by_student_subject_term[$student_id][$subject_id][$selected_term] ?? null;
                                    if ($result_data) {
                                        $marks_I = $result_data['marks'];
                                        $grade_I = $result_data['grade'];
                                        $is_published_current_term = $result_data['is_published'] ?? false;

                                        if ($is_published_current_term) {
                                            $is_any_result_for_student_published = true;
                                        }

                                        $total_subject_marks = $marks_I;
                                        $average_subject_marks = $marks_I; // For single term, total is average

                                        if (is_numeric($marks_I)) {
                                            $student_total_marks += (int)$marks_I;
                                            $student_subjects_counted++;

                                            if ($marks_I >= 50) { // Assuming 50 is passing mark for a term
                                                $passed_subjects_count++;
                                            }

                                            if ($marks_I > $best_subject_mark) {
                                                $best_subject_mark = $marks_I;
                                                $best_subject_name = $subject_name;
                                            }
                                            if ($marks_I < $worst_subject_mark) {
                                                $worst_subject_mark = $marks_I;
                                                $worst_subject_name = $subject_name;
                                            }
                                        }
                                    }
                                }

                                $subject_results[] = [
                                    's_n' => count($subject_results) + 1,
                                    'subject_name' => $subject_name,
                                    'marks_I' => $marks_I,
                                    'grade_I' => $grade_I,
                                    'marks_II' => $marks_II,
                                    'grade_II' => $grade_II,
                                    'total_subject_marks' => $total_subject_marks,
                                    'average_subject_marks' => $average_subject_marks,
                                    'comments' => $comments_subject
                                ];
                            }

                            $overall_average_marks = ($student_subjects_counted > 0) ? round($student_total_marks / $student_subjects_counted, 2) : 0;
                            $overall_grade = calculateDivision($overall_average_marks); // Using division for overall grade

                            // Placeholder for class ranking (requires fetching all students' overall averages and sorting)
                            $class_ranking = 'N/A'; // This needs a more complex query or post-processing of all students' results

                            // Display report card only if there's any data for the student for the selected term/annual
                            if (!empty($subject_results) && ($student_subjects_counted > 0 || $selected_term !== 'Annual')) :
                                ?>
                                <div class="report-card mb-5">
                                    <div class="report-card-header">
                                        <h2>MATOKEO YA KISOMO</h2>
                                        <h4>Mwanafunzi: <?php echo $student_full_name; ?></h4>
                                        <h4>Darasa: <?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></h4>
                                        <h4>Muhula: <?php echo htmlspecialchars($selected_term); ?>, Mwaka: <?php echo htmlspecialchars($selected_year); ?></h4>
                                    </div>

                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered subject-table">
                                            <thead>
                                                <tr>
                                                    <th>Na.</th>
                                                    <th>Somo</th>
                                                    <?php if ($selected_term === 'Annual'): ?>
                                                        <th>Alama Muhula 1</th>
                                                        <th>Daraja Muhula 1</th>
                                                        <th>Alama Muhula 2</th>
                                                        <th>Daraja Muhula 2</th>
                                                        <th>Jumla</th>
                                                        <th>Wastani</th>
                                                    <?php else: ?>
                                                        <th>Alama</th>
                                                        <th>Daraja</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subject_results as $s_result): ?>
                                                    <tr>
                                                        <td><?php echo $s_result['s_n']; ?></td>
                                                        <td><?php echo $s_result['subject_name']; ?></td>
                                                        <?php if ($selected_term === 'Annual'): ?>
                                                            <td><?php echo $s_result['marks_I']; ?></td>
                                                            <td><?php echo $s_result['grade_I']; ?></td>
                                                            <td><?php echo $s_result['marks_II']; ?></td>
                                                            <td><?php echo $s_result['grade_II']; ?></td>
                                                            <td><span class="total-grade"><?php echo $s_result['total_subject_marks']; ?></span></td>
                                                            <td><span class="total-grade"><?php echo $s_result['average_subject_marks']; ?></span></td>
                                                        <?php else: ?>
                                                            <td><?php echo $s_result['marks_I']; ?></td>
                                                            <td><?php echo $s_result['grade_I']; ?></td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="summary-info mb-4">
                                        <p><strong>Jumla ya Alama:</strong> <span class="text-primary"><?php echo $student_total_marks; ?></span></p>
                                        <p><strong>Wastani wa Alama:</strong> <span class="overall-division"><?php echo $overall_average_marks; ?>%</span></p>
                                        <p><strong>Daraja la Jumla:</strong> <span class="overall-division"><?php echo $overall_grade; ?></span></p>
                                        <p><strong>Masomo Aliyofaulu:</strong> <?php echo $passed_subjects_count; ?> kati ya <?php echo $student_subjects_counted; ?></p>
                                        <p><strong>Somo Bora:</strong> <?php echo ($best_subject_name !== '') ? $best_subject_name . ' (' . $best_subject_mark . ')' : 'N/A'; ?></p>
                                        <p><strong>Somo Dhaifu:</strong> <?php echo ($worst_subject_name !== '') ? $worst_subject_name . ' (' . $worst_subject_mark . ')' : 'N/A'; ?></p>
                                        <p><strong>Nafasi Darasani:</strong> <?php echo $class_ranking; ?></p>
                                    </div>

                                    <div class="teacher-comments mb-4">
                                        <h5>Maoni ya Mwalimu:</h5>
                                        <textarea class="form-control" rows="3" placeholder="Ingiza maoni ya mwalimu hapa..." readonly>
                                        <?php
                                        // Example comments based on performance
                                        if ($overall_average_marks >= 80) {
                                            echo "Hongera sana kwa matokeo mazuri! Endelea kujitahidi.";
                                        } elseif ($overall_average_marks >= 65) {
                                            echo "Umejitahidi sana, fanya bidii zaidi kufikia kilele.";
                                        } elseif ($overall_average_marks >= 50) {
                                            echo "Matokeo mazuri, ila bado unaweza kufanya vizuri zaidi. Zingatia maeneo dhaifu.";
                                        } elseif ($overall_average_marks >= 30) {
                                            echo "Unahitaji kujitahidi zaidi. Soma kwa bidii na uulize maswali usipoelewa.";
                                        } else {
                                            echo "Kuna haja kubwa ya kubadilika na kujitahidi zaidi katika masomo yako.";
                                        }
                                        ?>
                                        </textarea>
                                    </div>

                                    <?php
                                    // Only show publish button if results are not already published for this student and term
                                    // This checks if any of the results for the current student in the chosen term (or any term for Annual) are NOT published.
                                    // If all are published, then the button won't show.
                                    $all_published_for_student = true;
                                    if (isset($results_by_student_subject_term[$student_id])) {
                                        foreach ($subjects_to_display as $subject) {
                                            $subject_id = $subject['id'];
                                            if ($selected_term === 'Annual') {
                                                if (isset($results_by_student_subject_term[$student_id][$subject_id]['Term 1']) && !$results_by_student_subject_term[$student_id][$subject_id]['Term 1']['is_published']) {
                                                    $all_published_for_student = false;
                                                    break;
                                                }
                                                if (isset($results_by_student_subject_term[$student_id][$subject_id]['Term 2']) && !$results_by_student_subject_term[$student_id][$subject_id]['Term 2']['is_published']) {
                                                    $all_published_for_student = false;
                                                    break;
                                                }
                                            } else {
                                                if (isset($results_by_student_subject_term[$student_id][$subject_id][$selected_term]) && !$results_by_student_subject_term[$student_id][$subject_id][$selected_term]['is_published']) {
                                                    $all_published_for_student = false;
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        // If no results exist for the student for the chosen criteria, there's nothing to publish
                                        $all_published_for_student = true; // Treat as already published or no data to publish
                                    }

                                    if (!$all_published_for_student): ?>
                                        <form method="POST" action="" class="text-center">
                                            <input type="hidden" name="publish_class_id" value="<?php echo htmlspecialchars($selected_class_id); ?>">
                                            <input type="hidden" name="publish_year" value="<?php echo htmlspecialchars($selected_year); ?>">
                                            <input type="hidden" name="publish_term" value="<?php echo htmlspecialchars($selected_term); ?>">
                                            <button type="submit" name="publish_results" class="btn btn-success mt-3">Chapisha Matokeo ya Darasa Hili</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert alert-success text-center mt-3" role="alert">
                                            Matokeo ya darasa hili tayari yamechapishwa.
                                        </div>
                                    <?php endif; ?>

                                </div>
                                ---
                            <?php endif; // End of student report card display condition ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; // End of has_data_to_display condition ?>
            <?php endif; // End of empty students/subjects condition ?>
        <?php endif; // End of selected_class_id && selected_year && selected_term condition ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>