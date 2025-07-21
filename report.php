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

/**
 * Calculates the grade based on marks.
 * @param int $marks The student's marks.
 * @return string The calculated grade (A, B, C, D, F) or 'N/A'.
 */
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

/**
 * Calculates the overall division based on average marks.
 * @param float $averageMarks The student's average marks.
 * @return string The calculated division (DIV I, DIV II, etc.) or 'N/A'.
 */
function calculateDivision($averageMarks) {
    if (!is_numeric($averageMarks)) {
        return 'N/A';
    }
    if ($averageMarks >= 80) {
        return 'DIV I';
    } elseif ($averageMarks >= 65) {
        return 'DIV II';
    } elseif ($averageMarks >= 50) {
        return 'DIV III';
    } elseif ($averageMarks >= 30) {
        return 'DIV IV';
    } elseif ($averageMarks >= 0) {
        return 'FAIL';
    } else {
        return 'N/A';
    }
}

/**
 * Gets a comment based on marks.
 * @param int $marks The student's marks.
 * @return string The comment (EXCELLENT, VERY GOOD, etc.) or 'Not Graded Yet'.
 */
function getCommentForMarks($marks) {
    if (!is_numeric($marks)) {
        return 'Not Graded Yet';
    }
    if ($marks >= 85) {
        return 'EXCELLENT';
    } elseif ($marks >= 75) {
        return 'VERY GOOD';
    } elseif ($marks >= 45) {
        return 'GOOD';
    } elseif ($marks >= 30) {
        return 'POOR';
    } elseif ($marks >= 0) {
        return 'FAIL';
    } else {
        return 'Invalid Marks';
    }
}


// Fetch success/error messages from URL if redirected
if (isset($_GET['msg'])) {
    $success = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['err'])) {
    $errors = array_merge($errors, explode(",", htmlspecialchars($_GET['err'])));
}

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
$report_type = isset($_GET['report_type']) ? htmlspecialchars($_GET['report_type']) : 'general'; // Default to general
$selected_student_id = isset($_GET['student_id']) ? filter_var($_GET['student_id'], FILTER_VALIDATE_INT) : null;

// Data structures to hold results
$students_in_class = []; // This will hold all students or just the selected one, depending on report_type
$students_for_dropdown = []; // This will hold all students for the individual report dropdown (always populated if class selected)
$subjects_to_display = [];
$results_by_student_subject_term = []; // New structure to hold results by student, subject, and term
$best_student_overall = ['name' => 'N/A', 'total_average_marks' => -1, 'division' => 'N/A', 'comment' => 'N/A'];
$best_students_by_subject = [];
$class_overall_average = 0;
$num_students_with_results = 0;
$individual_student_data = null; // To store data for a single student report if report_type is 'individual'

// Fetch all students in the selected class for the 'Individual Student' dropdown, regardless of report type
if ($selected_class_id) {
    $stmt_all_students_in_class = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name");
    if ($stmt_all_students_in_class === false) {
        die("Error preparing all students for dropdown statement: " . $conn->error);
    }
    $stmt_all_students_in_class->bind_param("i", $selected_class_id);
    $stmt_all_students_in_class->execute();
    $result_all_students = $stmt_all_students_in_class->get_result();
    while ($row = $result_all_students->fetch_assoc()) {
        $students_for_dropdown[] = $row;
    }
    $stmt_all_students_in_class->close();
}


// Process report generation only if essential filters are set
if ($selected_class_id && $selected_year && $selected_term) {
    // 1. Determine which students to fetch based on report_type
    if ($report_type === 'individual' && $selected_student_id) {
        $stmt_student = $conn->prepare("SELECT id, first_name, middle_name, last_name FROM students WHERE id = ? AND class_id = ?");
        if ($stmt_student === false) {
            die("Error preparing individual student statement: " . $conn->error);
        }
        $stmt_student->bind_param("ii", $selected_student_id, $selected_class_id);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();
        if ($result_student->num_rows > 0) {
            $students_in_class[] = $result_student->fetch_assoc();
            $individual_student_data = $students_in_class[0]; // Store for individual report display
        } else {
            $errors[] = "Selected student not found in this class or does not exist.";
        }
        $stmt_student->close();
    } elseif ($report_type === 'general') {
        // Fetch all students in the selected class for the general report table
        $stmt_students_general = $conn->prepare("SELECT id, first_name, middle_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name");
        if ($stmt_students_general === false) {
            die("Error preparing general student statement: " . $conn->error);
        }
        $stmt_students_general->bind_param("i", $selected_class_id);
        $stmt_students_general->execute();
        $result_students_general = $stmt_students_general->get_result();
        while ($row = $result_students_general->fetch_assoc()) {
            $students_in_class[] = $row;
        }
        $stmt_students_general->close();
    }

    // 2. Fetch ALL subjects for the selected class (needed for both general and individual reports)
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
        // Initialize best student for each subject (only relevant for general report)
        if ($report_type === 'general') {
             $best_students_by_subject[$row['id']] = ['name' => 'N/A', 'mark' => -1];
        }
    }
    $stmt_subjects->close();

    // 3. Fetch results based on selected filters
    if (!empty($students_in_class) && !empty($subjects_to_display)) {
        $student_ids_to_fetch = array_column($students_in_class, 'id');
        $subject_ids_to_fetch = array_column($subjects_to_display, 'id');

        // Create placeholders for IN clause for students and subjects
        $placeholders_students = implode(',', array_fill(0, count($student_ids_to_fetch), '?'));
        $placeholders_subjects = implode(',', array_fill(0, count($subject_ids_to_fetch), '?'));

        // Prepare bind types and parameters for the query
        $bind_types = str_repeat('i', count($student_ids_to_fetch)) . str_repeat('i', count($subject_ids_to_fetch)) . 'i';
        $bind_params = array_merge($student_ids_to_fetch, $subject_ids_to_fetch, [$selected_year]);

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

        // Use call_user_func_array for binding parameters dynamically for older PHP versions if needed,
        // but for PHP 8+, ...$bind_params directly works.
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

    // Calculate best student and best in subject (only for general report)
    if ($report_type === 'general') {
        $student_overall_performance = [];
        $total_marks_for_class_average = 0;

        foreach ($students_in_class as $student) {
            $student_id = $student['id'];
            $student_full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
            $total_student_marks_current_period = 0;
            $subjects_graded_count = 0;

            foreach ($subjects_to_display as $subject) {
                $subject_id = $subject['id'];
                $current_marks = null;

                if ($selected_term === 'Annual') {
                    // For Annual, average Term 1 and Term 2 marks
                    $term1_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 1']['marks'] ?? null;
                    $term2_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 2']['marks'] ?? null;

                    $valid_marks = [];
                    if (is_numeric($term1_marks)) {
                        $valid_marks[] = $term1_marks;
                    }
                    if (is_numeric($term2_marks)) {
                        $valid_marks[] = $term2_marks;
                    }

                    if (!empty($valid_marks)) {
                        $current_marks = array_sum($valid_marks) / count($valid_marks);
                    }
                } else {
                    // For specific term, use marks for that term
                    $current_marks = $results_by_student_subject_term[$student_id][$subject_id][$selected_term]['marks'] ?? null;
                }

                if (is_numeric($current_marks)) {
                    $total_student_marks_current_period += $current_marks;
                    $subjects_graded_count++;

                    // Check for best student in this subject
                    if ($current_marks > $best_students_by_subject[$subject_id]['mark']) {
                        $best_students_by_subject[$subject_id]['mark'] = $current_marks;
                        $best_students_by_subject[$subject_id]['name'] = $student_full_name;
                    }
                }
            }

            // Calculate overall performance for the current student for class summary
            if ($subjects_graded_count > 0) {
                $average_student_marks_current_period = $total_student_marks_current_period / $subjects_graded_count;
                $student_overall_performance[$student_id] = $average_student_marks_current_period;
                $total_marks_for_class_average += $average_student_marks_current_period;
                $num_students_with_results++;

                // Check for overall best student in the class
                if ($average_student_marks_current_period > $best_student_overall['total_average_marks']) {
                    $best_student_overall['total_average_marks'] = $average_student_marks_current_period;
                    $best_student_overall['name'] = $student_full_name;
                    $best_student_overall['division'] = calculateDivision($average_student_marks_current_period);
                    $best_student_overall['comment'] = getCommentForMarks($average_student_marks_current_period);
                }
            }
        }

        // Calculate overall class average
        if ($num_students_with_results > 0) {
            $class_overall_average = $total_marks_for_class_average / $num_students_with_results;
        }
    }
}
$conn->close(); // Close database connection at the end of PHP logic
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Results Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        /* CSS styles remain mostly the same as provided, ensuring responsiveness and good aesthetics */
        :root {
            --sidebar-width: 280px; /* Define sidebar width as a variable */
            --navbar-height: 60px; /* Approximate height of your navbar */
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
            margin: 0; /* Remove default body margin */
        }

        /* Fixed Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--navbar-height); /* Use variable for height */
            z-index: 1050; /* Higher than sidebar if it moves */
            background-color: #ffffff; /* Ensure navbar has a background */
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            display: flex; /* Use flex to align items if needed */
            align-items: center; /* Vertically center items */
            padding: 0 1rem; /* Add some padding */
        }

        /* Main Wrapper for Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
            padding-top: var(--navbar-height); /* Push content below fixed navbar */
            box-sizing: border-box; /* Include padding in total height */
        }

        /* Sidebar */
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            position: fixed; /* Make sidebar fixed */
            top: var(--navbar-height); /* Start below the navbar */
            bottom: 0; /* Extend to the bottom of the viewport */
            left: 0;
            overflow-y: auto; /* Enable scrolling for sidebar content if it overflows */
            z-index: 1040; /* Lower than navbar */
            padding-top: 0; /* Remove top padding as it starts exactly below navbar */
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width); /* Push main content to the right of the sidebar */
            flex-grow: 1; /* Allow main content to take remaining horizontal space */
            padding: 20px; /* Add internal padding */
            transition: margin-left 0.3s; /* Smooth transition if sidebar changes width */
            box-sizing: border-box; /* Include padding in total width */
        }

        /* Sidebar and Navbar content styling (original, for context) */
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

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .admin-sidebar {
                left: -var(--sidebar-width); /* Hide sidebar by default on small screens */
                transition: left 0.3s;
                /* Optional: Add a toggle button and JavaScript to show/hide */
            }
            .main-content {
                margin-left: 0; /* No margin on small screens */
            }
            /* If you implement a sidebar toggle, you'd add a class like 'sidebar-open' to .wrapper */
            /* .wrapper.sidebar-open .admin-sidebar { left: 0; } */
            /* .wrapper.sidebar-open .main-content { margin-left: var(--sidebar-width); } */
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
        .summary-box {
            background-color: #e9f7ef; /* Light green background for summary */
            border: 1px solid #d4edda;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .summary-box h4 {
            color: #28a745; /* Green color for heading */
            margin-bottom: 15px;
        }
        .summary-item {
            margin-bottom: 10px;
        }
        .summary-item strong {
            color: #333;
        }

        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .report-card, .report-card * {
                visibility: visible;
            }
            .report-card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <?php
    // Include navbar.php (assuming it exists and contains navigation)
    // Make sure 'navbar.php' and 'sidebar.php' are in the correct relative path
    include 'navbar.php';
    ?>

    <div class="wrapper">
        <?php
        // Include sidebar.php (assuming it exists and contains navigation)
        include 'sidebar.php';
        ?>

        <div class="container-fluid p-3 main-content">
            <div class="header" style="margin-top: 80px;">
                <h1>Welcome, <?php echo htmlspecialchars($user_first_name . ' ' . $user_last_name); ?>!</h1>
                <h3>Class Results Report</h3>
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

            <div class="card p-4 mb-4 no-print">
                <h4 class="card-title mb-4">Select Report Criteria</h4>
                <form method="GET" action="" class="filter-form">
                    <div class="form-group">
                        <label for="report_type">Report Type:</label>
                        <select name="report_type" id="report_type" class="form-select" required>
                            <option value="general" <?php echo ($report_type == 'general') ? 'selected' : ''; ?>>General (All Students)</option>
                            <option value="individual" <?php echo ($report_type == 'individual') ? 'selected' : ''; ?>>Individual Student</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="class_id">Class:</label>
                        <select name="class_id" id="class_id" class="form-select" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes_data as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                    <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . ' (Level ' . $class['level'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student dropdown, shown only if report_type is 'individual' and a class is selected -->
                    <div class="form-group" id="student_id_group" style="display: <?php echo ($report_type == 'individual' && $selected_class_id) ? 'block' : 'none'; ?>;">
                        <label for="student_id">Select Student:</label>
                        <select name="student_id" id="student_id" class="form-select">
                            <option value="">-- Select Student --</option>
                            <?php if ($selected_class_id): // Populate only if class is selected ?>
                                <?php foreach ($students_for_dropdown as $student_option): ?>
                                    <option value="<?php echo htmlspecialchars($student_option['id']); ?>"
                                        <?php echo ($selected_student_id == $student_option['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student_option['first_name'] . ' ' . $student_option['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year">Year:</label>
                        <select name="year" id="year" class="form-select" required>
                            <option value="">-- Select Year --</option>
                            <?php foreach ($years as $year_option): ?>
                                <option value="<?php echo htmlspecialchars($year_option); ?>"
                                    <?php echo ($selected_year == $year_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="term">Term:</label>
                        <select name="term" id="term" class="form-select" required>
                            <option value="">-- Select Term --</option>
                            <?php foreach ($terms as $term_option): ?>
                                <option value="<?php echo htmlspecialchars($term_option); ?>"
                                    <?php echo ($selected_term == $term_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Generate Report</button>
                    </div>
                </form>
            </div>

            <?php if ($selected_class_id && $selected_year && $selected_term && (!($report_type === 'individual' && !$selected_student_id))): ?>
                <div class="report-card mt-4">
                    <div class="report-card-header">
                        <h2>THE EXAMINATION REPORT – <?php echo htmlspecialchars($selected_term)?></h2>
                        <p><strong>Class:</strong> <?php
                            $selected_class_name = 'N/A';
                            $selected_class_level = 'N/A';
                            foreach ($classes_data as $class) {
                                if ($class['id'] == $selected_class_id) {
                                    $selected_class_name = $class['class_name'];
                                    $selected_class_level = $class['level'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($selected_class_name);
                        ?> | <strong>Year:</strong> <?php echo htmlspecialchars($selected_year); ?> | <strong>Term:</strong> <?php echo htmlspecialchars($selected_term); ?></p>
                    </div>

                 

                    <?php if ($report_type === 'general'): ?>
                        <h4 class="mt-5">Detailed Student Results</h4>
                        <?php if (!empty($students_in_class) && !empty($subjects_to_display)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Student Name</th>
                                            <?php foreach ($subjects_to_display as $subject): ?>
                                                <th><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                            <?php endforeach; ?>
                                            <th>Total</th>
                                            <th>Average</th>
                                            <th>Division</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $student_count = 1; ?>
                                        <?php foreach ($students_in_class as $student): ?>
                                            <?php
                                                $student_id = $student['id'];
                                                $total_marks_student = 0;
                                                $subjects_evaluated_count = 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $student_count++; ?></td>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <?php foreach ($subjects_to_display as $subject): ?>
                                                    <?php
                                                        $subject_id = $subject['id'];
                                                        $marks_display = 'N/A';
                                                        $grade_display = 'N/A';

                                                        if ($selected_term === 'Annual') {
                                                            $term1_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 1']['marks'] ?? null;
                                                            $term2_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 2']['marks'] ?? null;

                                                            $valid_marks = [];
                                                            if (is_numeric($term1_marks)) $valid_marks[] = $term1_marks;
                                                            if (is_numeric($term2_marks)) $valid_marks[] = $term2_marks;

                                                            if (!empty($valid_marks)) {
                                                                $current_marks_for_annual = array_sum($valid_marks) / count($valid_marks);
                                                                $marks_display = number_format($current_marks_for_annual, 2);
                                                                $grade_display = calculateGrade($current_marks_for_annual);
                                                                $total_marks_student += $current_marks_for_annual;
                                                                $subjects_evaluated_count++;
                                                            }
                                                        } else {
                                                            if (isset($results_by_student_subject_term[$student_id][$subject_id][$selected_term])) {
                                                                $marks = $results_by_student_subject_term[$student_id][$subject_id][$selected_term]['marks'];
                                                                $grade = $results_by_student_subject_term[$student_id][$subject_id][$selected_term]['grade'];

                                                                if (is_numeric($marks)) {
                                                                    $marks_display = number_format($marks, 2);
                                                                    $grade_display = $grade;
                                                                    $total_marks_student += $marks;
                                                                    $subjects_evaluated_count++;
                                                                }
                                                            }
                                                        }
                                                    ?>
                                                    <td>
                                                        <?php echo $marks_display; ?>
                                                        <?php if ($grade_display !== 'N/A'): ?>
                                                            <br><span class="grade-display">(<?php echo $grade_display; ?>)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>

                                                <td>
                                                    <?php
                                                        $final_total_marks = ($subjects_evaluated_count > 0) ? number_format($total_marks_student, 2) : 'N/A';
                                                        echo $final_total_marks;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $average_student = ($subjects_evaluated_count > 0) ? ($total_marks_student / $subjects_evaluated_count) : 0;
                                                        echo ($subjects_evaluated_count > 0) ? number_format($average_student, 2) . '%' : 'N/A';
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="overall-division">
                                                        <?php echo calculateDivision($average_student); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo getCommentForMarks($average_student); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No student results found for the selected criteria.</div>
                        <?php endif; ?>

                    <?php elseif ($report_type === 'individual' && $individual_student_data): ?>
                        <h4 class="mt-5">Individual Student Report Card</h4>
                        <div class="report-card mt-3">
                            <div class="student-info mb-4">
                                <h5>Student Information</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($individual_student_data['first_name'] . ' ' . $individual_student_data['middle_name'] . ' ' . $individual_student_data['last_name']); ?></p>
                                <p><strong>Class:</strong> <?php echo htmlspecialchars($selected_class_name . ' (Level ' . $selected_class_level . ')'); ?></p>
                                <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($selected_year); ?></p>
                                <p><strong>Term:</strong> <?php echo htmlspecialchars($selected_term); ?></p>
                            </div>

                            <?php if (!empty($subjects_to_display)): ?>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered subject-table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Marks</th>
                                                <th>Grade</th>
                                                <th>Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                                $student_id = $individual_student_data['id'];
                                                $total_individual_marks = 0;
                                                $individual_subjects_evaluated_count = 0;
                                            ?>
                                            <?php foreach ($subjects_to_display as $subject): ?>
                                                <?php
                                                    $subject_id = $subject['id'];
                                                    $marks_display = 'N/A';
                                                    $grade_display = 'N/A';
                                                    $comment_display = 'Not Available';

                                                    if ($selected_term === 'Annual') {
                                                        $term1_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 1']['marks'] ?? null;
                                                        $term2_marks = $results_by_student_subject_term[$student_id][$subject_id]['Term 2']['marks'] ?? null;

                                                        $valid_marks = [];
                                                        if (is_numeric($term1_marks)) $valid_marks[] = $term1_marks;
                                                        if (is_numeric($term2_marks)) $valid_marks[] = $term2_marks;

                                                        if (!empty($valid_marks)) {
                                                            $current_marks_for_annual = array_sum($valid_marks) / count($valid_marks);
                                                            $marks_display = number_format($current_marks_for_annual, 2);
                                                            $grade_display = calculateGrade($current_marks_for_annual);
                                                            $comment_display = getCommentForMarks($current_marks_for_annual);
                                                            $total_individual_marks += $current_marks_for_annual;
                                                            $individual_subjects_evaluated_count++;
                                                        }
                                                    } else {
                                                        if (isset($results_by_student_subject_term[$student_id][$subject_id][$selected_term])) {
                                                            $marks = $results_by_student_subject_term[$student_id][$subject_id][$selected_term]['marks'];
                                                            $grade = $results_by_student_subject_term[$student_id][$subject_id][$selected_term]['grade'];

                                                            if (is_numeric($marks)) {
                                                                $marks_display = number_format($marks, 2);
                                                                $grade_display = $grade;
                                                                $comment_display = getCommentForMarks($marks);
                                                                $total_individual_marks += $marks;
                                                                $individual_subjects_evaluated_count++;
                                                            }
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                    <td><?php echo $marks_display; ?></td>
                                                    <td><?php echo $grade_display; ?></td>
                                                    <td><?php echo $comment_display; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="summary-info mb-4">
                                    <h5>Overall Performance Summary</h5>
                                    <?php
                                        $individual_average = ($individual_subjects_evaluated_count > 0) ? ($total_individual_marks / $individual_subjects_evaluated_count) : 0;
                                        $individual_division = calculateDivision($individual_average);
                                        $individual_overall_comment = getCommentForMarks($individual_average);
                                    ?>
                                    <p><strong>Total Marks:</strong> <?php echo number_format($total_individual_marks, 2); ?></p>
                                    <p><strong>Average Marks:</strong> <?php echo number_format($individual_average, 2); ?>%</p>
                                    <p><strong>Overall Division:</strong> <span class="overall-division"><?php echo htmlspecialchars($individual_division); ?></span></p>
                                    <p><strong>Overall Comment:</strong> <?php echo htmlspecialchars($individual_overall_comment); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No subjects defined for this class or no results available for this student.</div>
                            <?php endif; ?>

                            <div class="teacher-comments">
                                <h5>Teacher's General Comments:</h5>
                                <p>
                                    <?php
                                    // Generate a general comment based on the individual student's overall performance
                                    if ($individual_overall_comment === 'EXCELLENT') {
                                        echo "An outstanding performance this term! Keep up the excellent work and continue to strive for greatness.";
                                    } elseif ($individual_overall_comment === 'VERY GOOD') {
                                        echo "Very good results this term. There's clear progress, and with continued effort, even higher achievements are possible.";
                                    } elseif ($individual_overall_comment === 'GOOD') {
                                        echo "A good effort overall. Focus on areas needing improvement to boost your performance further.";
                                    } elseif ($individual_overall_comment === 'POOR') {
                                        echo "Performance requires significant improvement. We encourage you to seek additional support and dedicate more time to studies.";
                                    } elseif ($individual_overall_comment === 'FAIL') {
                                        echo "Results indicate a need for immediate intervention and intensive study. Please consult with your teachers and parents.";
                                    } else {
                                        echo "Comments will be provided upon full grading of all subjects.";
                                    }
                                    ?>
                                </p>
                            </div>

                            <button class="btn btn-info no-print" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report Card</button>
                        </div>
                    <?php endif; ?>

                </div>
            <?php else: ?>
                <div class="alert alert-info mt-4">Please select a Class, Year, Term, and Report Type to generate the report. For individual reports, also select a student.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Function to update student dropdown based on selected class
            function updateStudentDropdown() {
                var classId = $('#class_id').val();
                var studentDropdown = $('#student_id');
                studentDropdown.empty().append('<option value="">-- Loading Students --</option>');

                if (classId) {
                    $.ajax({
                        url: 'get_students_by_class.php', // A new PHP file is needed here
                        type: 'GET',
                        data: { class_id: classId },
                        dataType: 'json',
                        success: function(data) {
                            studentDropdown.empty().append('<option value="">-- Select Student --</option>');
                            $.each(data, function(key, student) {
                                // Preserve selected student if it matches after loading
                                var selected = (student.id == <?php echo json_encode($selected_student_id); ?>) ? 'selected' : '';
                                studentDropdown.append('<option value="' + student.id + '" ' + selected + '>' + student.first_name + ' ' + student.last_name + '</option>');
                            });
                        },
                        error: function() {
                            studentDropdown.empty().append('<option value="">-- Error loading students --</option>');
                        }
                    });
                } else {
                    studentDropdown.empty().append('<option value="">-- Select Class First --</option>');
                }
            }

            // Call on page load if a class is already selected AND report type is individual
            // This ensures the student dropdown is correctly populated on page load if applicable
            if ($('#class_id').val() !== '' && $('#report_type').val() === 'individual') {
                updateStudentDropdown();
            }

            // Event listener for class dropdown change
            $('#class_id').change(function() {
                // Only update student dropdown if 'individual' report type is selected
                if ($('#report_type').val() === 'individual') {
                    updateStudentDropdown();
                }
            });

            // Event listener for report type change
            $('#report_type').change(function() {
                var reportType = $(this).val();
                if (reportType === 'individual') {
                    $('#student_id_group').slideDown();
                    $('#student_id').prop('required', true);
                    updateStudentDropdown(); // Populate students when switching to individual
                } else {
                    $('#student_id_group').slideUp();
                    $('#student_id').prop('required', false);
                }
            });

            // Ensure correct visibility of student dropdown on page load
            if ($('#report_type').val() === 'individual') {
                $('#student_id_group').show();
                $('#student_id').prop('required', true);
            } else {
                $('#student_id_group').hide();
                $('#student_id').prop('required', false);
            }
        });
    </script>
</body>
</html>
