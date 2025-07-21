<?php
ob_start(); // Start output buffering
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'connection/db.php';

// Redirect if user is not logged in or not an Admin
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php"); // Redirect to login page
    exit(); // Terminate script
}

// Get ID and other information of the logged-in user (for Admin)
$current_user_id = $_SESSION['user_data']['id']; 
$user_first_name = $_SESSION['user_data']['first_name'];
$user_last_name = $_SESSION['user_data']['last_name'];
$user_role = $_SESSION['user_data']['role'];

// Initialize variables for messages
$errors = [];
$success = '';

/**
 * Calculates the overall division based on total marks and number of subjects.
 * This function uses hardcoded thresholds for divisions.
 * @param int $totalMarks Sum of marks for all subjects for a student.
 * @param int $numSubjects Number of subjects counted.
 * @return string The division (e.g., 'I', 'II', 'ZERO') or 'N/A'.
 */
function calculateDivision($totalMarks, $numSubjects) {
    if ($numSubjects == 0) {
        return 'N/A';
    }
    $averageMarks = $totalMarks / $numSubjects;

    if ($averageMarks >= 80) {
        return 'I';
    } elseif ($averageMarks >= 65) {
        return 'II';
    } elseif ($averageMarks >= 45) {
        return 'III';
    } elseif ($averageMarks >= 30) {
        return 'IV';
    } elseif ($averageMarks >= 0) {
        return 'ZERO';
    } else {
        return 'N/A';
    }
}

/**
 * Calculates the annual grade based on average annual marks using class-specific grade rules from the database.
 * @param int $averageMarks The average marks for the year for a student in a specific class.
 * @param int $class_id The ID of the class to look up grade rules.
 * @param array $grade_systems_map An associative array of grade rules, keyed by class_id.
 * @return string The calculated annual grade name (e.g., 'A', 'B', 'C') or 'N/A'.
 */
function calculateAnnualGradeFromRules($averageMarks, $class_id, $grade_systems_map) {
    $averageMarks = (int)$averageMarks; // Ensure marks are integer
    if (!isset($grade_systems_map[$class_id]) || $averageMarks < 0 || $averageMarks > 100) {
        return 'N/A'; // No rules for this class or invalid average marks
    }

    $class_rules = $grade_systems_map[$class_id];
    foreach ($class_rules as $rule) {
        // Find the grade where the average marks fall within the rule's range
        if ($averageMarks >= $rule['min_marks'] && $averageMarks <= $rule['max_marks']) {
            return $rule['grade_name'];
        }
    }
    return 'N/A'; // No matching grade found for the average
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

// Fetch ALL grade systems from the database once, organized by class_id.
// This data will be used to dynamically determine grades for individual subjects and annual averages.
$all_grade_systems_map = [];
$stmt_all_grades = $conn->prepare("SELECT class_id, grade_name, min_marks, max_marks FROM grade_systems ORDER BY class_id, min_marks DESC");
if ($stmt_all_grades === false) {
    die("Error preparing grade systems statement: " . $conn->error);
}
$stmt_all_grades->execute();
$result_all_grades = $stmt_all_grades->get_result();
while ($row = $result_all_grades->fetch_assoc()) {
    $class_id_for_grade = $row['class_id'];
    if (!isset($all_grade_systems_map[$class_id_for_grade])) {
        $all_grade_systems_map[$class_id_for_grade] = [];
    }
    $all_grade_systems_map[$class_id_for_grade][] = $row;
}
$stmt_all_grades->close();

/**
 * Helper function to get the grade for a specific mark and class using the pre-fetched grade rules.
 * @param int $marks The marks scored.
 * @param int $class_id The ID of the class.
 * @param array $grade_systems_map The map of all grade systems by class ID.
 * @return string The grade name or 'N/A' if not found/invalid.
 */
function getGradeNameFromMarks($marks, $class_id, $grade_systems_map) {
    $marks = (int)$marks;
    if (!isset($grade_systems_map[$class_id]) || $marks < 0 || $marks > 100) {
        return 'N/A';
    }

    $class_rules = $grade_systems_map[$class_id];
    foreach ($class_rules as $rule) {
        if ($marks >= $rule['min_marks'] && $marks <= $rule['max_marks']) {
            return $rule['grade_name'];
        }
    }
    return 'N/A';
}


// Fixed terms and years for dropdowns
$current_year_for_dropdown = date('Y'); 
$years = range($current_year_for_dropdown - 5, $current_year_for_dropdown + 2); 

// Default selected values (for initial display)
$selected_class_id = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_VALIDATE_INT) : null;
$selected_year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;

// Variable to store the level of the selected class
$selected_class_level = null;

// Data structures to hold results
$students_in_class = [];
$subjects_to_display = [];
$term1_results_by_student_subject = [];
$term2_results_by_student_subject = [];

if ($selected_class_id && $selected_year) { 
    // Find the level of the selected class
    foreach ($classes_data as $class) {
        if ($class['id'] == $selected_class_id) {
            $selected_class_level = $class['level'];
            break;
        }
    }

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

    // 3. Fetch all existing results for these students and subjects for ALL terms in the selected year
    if (!empty($students_in_class) && !empty($subjects_to_display)) {
        $student_ids = array_column($students_in_class, 'id');
        $subject_ids = array_column($subjects_to_display, 'id'); 

        // Check if there are any student or subject IDs to query
        if (!empty($student_ids) && !empty($subject_ids)) {
            $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));
            $placeholders_subjects = implode(',', array_fill(0, count($subject_ids), '?'));
            
            $bind_types = str_repeat('i', count($student_ids)) . str_repeat('i', count($subject_ids)) . 'i'; 
            $bind_params = array_merge($student_ids, $subject_ids, [$selected_year]);

            $query_all_results_fetch = "
                SELECT 
                    student_id, 
                    subject_id, 
                    marks, 
                    grade,
                    term
                FROM 
                    results 
                WHERE 
                    student_id IN ($placeholders_students) 
                    AND subject_id IN ($placeholders_subjects) 
                    AND year = ?
                ORDER BY student_id, subject_id, term
                ";
            
            $stmt_all_results_fetch = $conn->prepare($query_all_results_fetch);

            if ($stmt_all_results_fetch === false) {
                die("Error preparing results fetch statement: " . $conn->error);
            }

            $stmt_all_results_fetch->bind_param($bind_types, ...$bind_params);
            
            $stmt_all_results_fetch->execute();
            $result_fetch = $stmt_all_results_fetch->get_result();
            
            while ($row = $result_fetch->fetch_assoc()) {
                if ($row['term'] === 'Term 1') {
                    $term1_results_by_student_subject[$row['student_id']][$row['subject_id']] = [
                        'marks' => $row['marks'],
                        'grade' => $row['grade'] // Use grade directly from DB for display
                    ];
                } elseif ($row['term'] === 'Term 2') {
                    $term2_results_by_student_subject[$row['student_id']][$row['subject_id']] = [
                        'marks' => $row['marks'],
                        'grade' => $row['grade'] // Use grade directly from DB for display
                    ];
                }
            }
            $stmt_all_results_fetch->close();
        }
    }
}
// Close connection only if it's still open
if ($conn && $conn->ping()) {
    $conn->close(); 
}
ob_end_flush(); // Flush the output buffer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Result view page</title>
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
            margin: 0;
            padding: 0;
        }

        /* Navbar at the top - full width */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1030;
            background-color: #ffffff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            height: 60px;
        }

        /* Sidebar below navbar - full height */
        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed;
            top: 60px; /* Below navbar */
            bottom: 0;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1020;
        }

        /* Main content area - starts after sidebar */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
            overflow-y: auto;
            min-height: calc(100vh - 60px); /* Full height minus navbar */
            box-sizing: border-box;
            padding-top: 80px; /* Space for navbar */
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

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                position: relative;
                top: auto;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding-top: 20px;
            }

            .navbar {
                position: relative;
                height: auto;
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
            margin-bottom: 40px;
        }
        
        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .table thead th {
            background-color: #f2f2f2;
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

        .header {
            margin-top: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'?> 
    
<div class="admin-sidebar">
    <?php include 'sidebar.php'; ?>
</div>
      
<div class="main-content">
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($user_first_name . ' ' . $user_last_name); ?>!</h1>
        <h3>Marks of the students</h3>
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
        <h4 class="card-title mb-4">Select the criteria</h4>
        <form method="GET" action="" class="filter-form">
            <div class="form-group">
                <label for="class_id">Class:</label>
                <select name="class_id" id="class_id" class="form-select" required>
                    <option value="">-- Select class --</option>
                    <?php foreach ($classes_data as $class): ?>
                        <option value="<?php echo htmlspecialchars($class['id']); ?>" 
                            <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' (Level ' . $class['level'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="year">Year:</label>
                <select name="year" id="year" class="form-select" required>
                    <option value="">-- Select a year --</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo htmlspecialchars($y); ?>"
                            <?php echo ($selected_year == $y) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($y); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-center align-items-end">
                   <button type="submit" class="btn btn-primary w-50">View marks</button>
            </div>
        </form>
    </div>

    <?php 
    // Logic ya kuonyesha meza au ujumbe wa kutokuwepo kwa data
    if ($selected_class_id && $selected_year): ?>
        <?php if (empty($students_in_class)): ?>
            <div class="alert alert-info text-center" role="alert">
                No students registered in this class.
            </div>
        <?php elseif (empty($subjects_to_display)): ?>
            <div class="alert alert-warning text-center" role="alert">
                No subjects registered for this class.
            </div>
        <?php else: ?>
            <div class="card p-4 mb-4">
                <h4 class="card-title mb-4">The marks of the class: <span class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> - Term 1 (<small><?php echo htmlspecialchars($selected_year); ?></small>)</h4>
                
                <?php if (empty($term1_results_by_student_subject)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No result for Term 1 yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="term1ResultsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <?php foreach ($subjects_to_display as $subject): ?>
                                        <th><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Total (T1)</th>
                                    <th>Average (T1)</th>
                                    <?php if ($selected_class_level !== 'Primary'): ?>
                                        <th>Division (T1)</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_in_class as $student): ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <?php 
                                            $student_total_marks_t1 = 0;
                                            $student_subjects_counted_t1 = 0;
                                            foreach ($subjects_to_display as $subject): 
                                                $marks_t1 = $term1_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? '';
                                                $grade_t1 = $term1_results_by_student_subject[$student['id']][$subject['id']]['grade'] ?? 'N/A';
                                        ?>
                                            <td>
                                                <?php 
                                                    if ($marks_t1 !== '') {
                                                        echo htmlspecialchars($grade_t1) . ' (' . htmlspecialchars($marks_t1) . ')';
                                                        $student_total_marks_t1 += (int)$marks_t1;
                                                        $student_subjects_counted_t1++;
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <span class="total-marks"><?php echo htmlspecialchars($student_total_marks_t1); ?></span>
                                        </td>
                                        <td>
                                            <span class="average-marks">
                                                <?php 
                                                    if ($student_subjects_counted_t1 > 0) {
                                                        echo htmlspecialchars(round($student_total_marks_t1 / $student_subjects_counted_t1, 2));
                                                    } else {
                                                        echo '0';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <?php if ($selected_class_level !== 'Primary'): ?>
                                            <td>
                                                <span class="overall-division">
                                                    <?php echo htmlspecialchars(calculateDivision($student_total_marks_t1, $student_subjects_counted_t1)); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card p-4 mb-4">
                <h4 class="card-title mb-4">The report of the marks of class <span class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> - Term 2 (<small><?php echo htmlspecialchars($selected_year); ?></small>)</h4>
                
                <?php if (empty($term2_results_by_student_subject)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No result for Term 2 yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="term2ResultsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <?php foreach ($subjects_to_display as $subject): ?>
                                        <th><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Total (T2)</th>
                                    <th>Average (T2)</th>
                                    <?php if ($selected_class_level !== 'Primary'): ?>
                                        <th>Division (T2)</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_in_class as $student): ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <?php 
                                            $student_total_marks_t2 = 0;
                                            $student_subjects_counted_t2 = 0;
                                            foreach ($subjects_to_display as $subject): 
                                                $marks_t2 = $term2_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? '';
                                                $grade_t2 = $term2_results_by_student_subject[$student['id']][$subject['id']]['grade'] ?? 'N/A';
                                        ?>
                                            <td>
                                                <?php 
                                                    if ($marks_t2 !== '') {
                                                        echo htmlspecialchars($grade_t2) . ' (' . htmlspecialchars($marks_t2) . ')';
                                                        $student_total_marks_t2 += (int)$marks_t2;
                                                        $student_subjects_counted_t2++;
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <span class="total-marks"><?php echo htmlspecialchars($student_total_marks_t2); ?></span>
                                        </td>
                                        <td>
                                            <span class="average-marks">
                                                <?php 
                                                    if ($student_subjects_counted_t2 > 0) {
                                                        echo htmlspecialchars(round($student_total_marks_t2 / $student_subjects_counted_t2, 2));
                                                    } else {
                                                        echo '0';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <?php if ($selected_class_level !== 'Primary'): ?>
                                            <td>
                                                <span class="overall-division">
                                                    <?php echo htmlspecialchars(calculateDivision($student_total_marks_t2, $student_subjects_counted_t2)); ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card p-4 annual-summary-section">
                <h4 class="card-title mb-4">Annual Report for Class <span class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> (<small><?php echo htmlspecialchars($selected_year); ?></small>)</h4>
                
                <?php 
                $has_annual_data = false;
                foreach ($students_in_class as $student) {
                    foreach ($subjects_to_display as $subject) {
                        $marks_t1 = $term1_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? null;
                        $marks_t2 = $term2_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? null;
                        if ($marks_t1 !== null || $marks_t2 !== null) {
                            $has_annual_data = true;
                            break 2; // Break both loops
                        }
                    }
                }
                ?>
                <?php if (!$has_annual_data): ?>
                    <div class="alert alert-info text-center" role="alert">
                        No annual marks data available yet (requires marks for at least one term).
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="annualResultsTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <?php foreach ($subjects_to_display as $subject): ?>
                                        <th><?php echo htmlspecialchars($subject['subject_name']); ?><br>(Total/Avg/Grade)</th>
                                    <?php endforeach; ?>
                                    <th>Annual Total</th>
                                    <th>Annual Average</th>
                                    <?php if ($selected_class_level !== 'Primary'): ?>
                                        <th>Annual Division</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_in_class as $student): ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <?php 
                                            $student_annual_overall_total_marks = 0;
                                            $student_annual_overall_subjects_counted = 0;
                                            
                                            foreach ($subjects_to_display as $subject): 
                                                $marks_t1 = $term1_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? null;
                                                $marks_t2 = $term2_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? null;
                                                
                                                $subject_annual_sum = 0;
                                                $terms_for_subject_count = 0;

                                                if ($marks_t1 !== null) {
                                                    $subject_annual_sum += (int)$marks_t1;
                                                    $terms_for_subject_count++;
                                                }
                                                if ($marks_t2 !== null) {
                                                    $subject_annual_sum += (int)$marks_t2;
                                                    $terms_for_subject_count++;
                                                }

                                                $subject_annual_avg = 0;
                                                $subject_annual_grade = 'N/A';
                                                if ($terms_for_subject_count > 0) {
                                                    $subject_annual_avg = round($subject_annual_sum / $terms_for_subject_count, 2);
                                                    // Calculate grade for the subject's annual average using class-specific rules
                                                    $subject_annual_grade = getGradeNameFromMarks($subject_annual_avg, $selected_class_id, $all_grade_systems_map);
                                                }

                                                // Only add to overall total/count if subject has at least one term mark
                                                if ($terms_for_subject_count > 0) {
                                                    $student_annual_overall_total_marks += $subject_annual_avg; // Use average for overall total
                                                    $student_annual_overall_subjects_counted++;
                                                }
                                        ?>
                                            <td>
                                                <?php 
                                                    if ($terms_for_subject_count > 0) {
                                                        echo htmlspecialchars($subject_annual_sum) . ' / ' . 
                                                             htmlspecialchars($subject_annual_avg) . ' / ' . 
                                                             htmlspecialchars($subject_annual_grade);
                                                    } else {
                                                        echo '-'; // No data for this subject for the year
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <span class="total-marks">
                                                <?php echo htmlspecialchars($student_annual_overall_total_marks); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="average-marks">
                                                <?php 
                                                    if ($student_annual_overall_subjects_counted > 0) {
                                                        $annual_average_overall = round($student_annual_overall_total_marks / $student_annual_overall_subjects_counted, 2);
                                                        echo htmlspecialchars($annual_average_overall);
                                                    } else {
                                                        echo '0';
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <?php if ($selected_class_level !== 'Primary'): ?>
                                            <td>
                                                <span class="overall-division">
                                                    <?php 
                                                        if ($student_annual_overall_subjects_counted > 0) {
                                                            echo htmlspecialchars(calculateDivision($student_annual_overall_total_marks, $student_annual_overall_subjects_counted));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info text-center" role="alert">
            Please select a **Class** and **Year** from the form above to view results.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>