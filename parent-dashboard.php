<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'connection/db.php';
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'parent') {
    header("Location: index.php"); 
    exit();
}


$current_parent_user_id = $_SESSION['user_data']['id'];
$user_first_name = $_SESSION['user_data']['first_name'];
$user_last_name = $_SESSION['user_data']['last_name'];

// Get selected term from GET request, default to 'All'
$selected_term = $_GET['term'] ?? 'All'; // Default to 'All' to show all terms initially

/**
 * Calculates the overall division based on average marks.
 * This function uses hardcoded thresholds for divisions (I, II, III, IV, ZERO).
 * @param float $averageMarks The average marks for a student across subjects.
 * @return string The calculated division or 'N/A'.
 */
function calculateDivision($averageMarks) {
    if (!is_numeric($averageMarks)) {
        return 'N/A';
    }
    if ($averageMarks >= 80) {
        return 'A';
    } elseif ($averageMarks >= 65) {
        return 'B';
    } elseif ($averageMarks >= 45) {
        return 'C';
    } elseif ($averageMarks >= 30) {
        return 'D';
    } elseif ($averageMarks >= 0) {
        return 'F';
    } else {
        return 'N/A';
    }
}

/**
 * Get comments based on marks.
 * This function uses hardcoded thresholds for comments.
 * @param int $marks The marks scored.
 * @return string The comment for the given marks.
 */
function getCommentForMarks($marks) {
    if (!is_numeric($marks)) {
        return 'Not Graded Yet';
    }
    if ($marks >= 80) {
        return 'EXCELLENT';
    } elseif ($marks >= 65) {
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

// Data structures to hold results for the parent's children
$children_results = [];

// 1. Get all students associated with this parent
$stmt_children = $conn->prepare("SELECT id, first_name, last_name, class_id FROM students WHERE parent_id = ? ORDER BY first_name, last_name");
if ($stmt_children === false) {
    die("Error preparing children statement: " . $conn->error);
}
$stmt_children->bind_param("i", $current_parent_user_id);
$stmt_children->execute();
$result_children = $stmt_children->get_result();

$parent_students_ids = [];
$students_data = []; // Store student full data
while ($row = $result_children->fetch_assoc()) {
    $parent_students_ids[] = $row['id'];
    $students_data[$row['id']] = $row;
}
$stmt_children->close();

$all_grade_systems_map = []; // To store grade systems by class_id

if (!empty($parent_students_ids)) {
    // 2. Get classes of these students (for subject fetching and grade systems)
    $class_ids_of_children = array_unique(array_column($students_data, 'class_id'));
    $class_names = [];
    if (!empty($class_ids_of_children)) {
        $placeholders_classes = implode(',', array_fill(0, count($class_ids_of_children), '?'));
        $stmt_classes = $conn->prepare("SELECT id, class_name FROM classes WHERE id IN ($placeholders_classes)");
        if ($stmt_classes === false) {
            die("Error preparing class names statement: " . $conn->error);
        }
        $bind_types_classes = str_repeat('i', count($class_ids_of_children));
        $stmt_classes->bind_param($bind_types_classes, ...$class_ids_of_children);
        $stmt_classes->execute();
        $res_classes = $stmt_classes->get_result();
        while ($row = $res_classes->fetch_assoc()) {
            $class_names[$row['id']] = $row['class_name'];
        }
        $stmt_classes->close();

        // Fetch grade systems for these classes
        $stmt_grade_systems = $conn->prepare("SELECT class_id, grade_name, min_marks, max_marks FROM grade_systems WHERE class_id IN ($placeholders_classes) ORDER BY class_id, min_marks DESC");
        if ($stmt_grade_systems === false) {
            die("Error preparing grade systems statement: " . $conn->error);
        }
        $stmt_grade_systems->bind_param($bind_types_classes, ...$class_ids_of_children);
        $stmt_grade_systems->execute();
        $res_grade_systems = $stmt_grade_systems->get_result();
        while ($row = $res_grade_systems->fetch_assoc()) {
            $class_id_for_grade = $row['class_id'];
            if (!isset($all_grade_systems_map[$class_id_for_grade])) {
                $all_grade_systems_map[$class_id_for_grade] = [];
            }
            $all_grade_systems_map[$class_id_for_grade][] = $row;
        }
        $stmt_grade_systems->close();
    }

    $all_subjects = [];
    if (!empty($class_ids_of_children)) {
        $placeholders_classes_subjects = implode(',', array_fill(0, count($class_ids_of_children), '?'));
        $stmt_all_subjects = $conn->prepare("SELECT id, subject_name, class_id FROM subjects WHERE class_id IN ($placeholders_classes_subjects) ORDER BY class_id, subject_name");
        if ($stmt_all_subjects === false) {
            die("Error preparing all subjects statement: " . $conn->error);
        }
        $bind_types_all_subjects = str_repeat('i', count($class_ids_of_children));
        $stmt_all_subjects->bind_param($bind_types_all_subjects, ...$class_ids_of_children);
        $stmt_all_subjects->execute();
        $res_all_subjects = $stmt_all_subjects->get_result();
        while ($row = $res_all_subjects->fetch_assoc()) {
            $all_subjects[$row['class_id']][$row['id']] = $row['subject_name'];
        }
        $stmt_all_subjects->close();
    }


    // 4. Get all published results for these students, filtered by term if selected
    $placeholders_students = implode(',', array_fill(0, count($parent_students_ids), '?'));
    $query_results = "
        SELECT
            r.student_id,
            r.subject_id,
            r.marks,
            r.grade,
            r.term,
            r.year,
            s.class_id,
            subj.subject_name
        FROM
            results r
        JOIN
            students s ON r.student_id = s.id
        JOIN
            subjects subj ON r.subject_id = subj.id
        WHERE
            r.student_id IN ($placeholders_students) AND r.is_published = '1'
    ";

    $params = $parent_students_ids;
    $types = str_repeat('i', count($parent_students_ids));

    if ($selected_term !== 'All' && $selected_term !== 'Annual') {
        $query_results .= " AND r.term = ?";
        $params[] = $selected_term;
        $types .= 's';
    }

    $query_results .= " ORDER BY r.student_id, r.year DESC, FIELD(r.term, 'Term 1', 'Term 2', 'Annual'), r.subject_id";

    $stmt_results = $conn->prepare($query_results);
    if ($stmt_results === false) {
        die("Error preparing results statement: " . $conn->error);
    }
    $stmt_results->bind_param($types, ...$params);
    $stmt_results->execute();
    $fetched_results = $stmt_results->get_result();

    // Organize results into an easy-to-use structure
    while ($row = $fetched_results->fetch_assoc()) {
        $children_results[$row['student_id']][$row['year']][$row['term']][$row['subject_id']] = [
            'marks' => $row['marks'],
            'grade' => $row['grade'],
            'subject_name' => $row['subject_name']
        ];
    }
    $stmt_results->close();

    // --- Calculate Positions for Each Subject and Term/Annual ---
    $all_student_subject_marks_for_ranking = []; // [year][term][subject_id][] = ['student_id' => ..., 'marks' => ...]

    foreach ($students_data as $student_id_val => $student_info_val) {
        if (isset($children_results[$student_id_val])) {
            foreach ($children_results[$student_id_val] as $year_val => $terms_data_val) {
                // For individual terms (Term 1, Term 2)
                foreach (['Term 1', 'Term 2'] as $term_val) {
                    if (isset($terms_data_val[$term_val])) {
                        foreach ($terms_data_val[$term_val] as $subj_id_val => $result_val) {
                            if (is_numeric($result_val['marks'])) {
                                $all_student_subject_marks_for_ranking[$year_val][$term_val][$subj_id_val][] = [
                                    'student_id' => $student_id_val,
                                    'marks' => (int)$result_val['marks']
                                ];
                            }
                        }
                    }
                }

                // For Annual averages (needed for Annual ranking)
                $relevant_subjects_for_ranking = $all_subjects[$student_info_val['class_id']] ?? [];
                foreach ($relevant_subjects_for_ranking as $subj_id_val => $subj_name_val) {
                    $marks_t1 = $children_results[$student_id_val][$year_val]['Term 1'][$subj_id_val]['marks'] ?? null;
                    $marks_t2 = $children_results[$student_id_val][$year_val]['Term 2'][$subj_id_val]['marks'] ?? null;

                    $current_subject_total = 0;
                    $current_terms_for_subject = 0;

                    if (is_numeric($marks_t1)) {
                        $current_subject_total += (int)$marks_t1;
                        $current_terms_for_subject++;
                    }
                    if (is_numeric($marks_t2)) {
                        $current_subject_total += (int)$marks_t2;
                        $current_terms_for_subject++;
                    }

                    if ($current_terms_for_subject > 0) {
                        $average_subject_marks_for_annual = round($current_subject_total / $current_terms_for_subject, 2);
                        $all_student_subject_marks_for_ranking[$year_val]['Annual'][$subj_id_val][] = [
                            'student_id' => $student_id_val,
                            'marks' => $average_subject_marks_for_annual
                        ];
                    }
                }
            }
        }
    }

    $subject_positions = []; // [student_id][year][term][subject_id] = position

    // Now calculate positions for each group
    foreach ($all_student_subject_marks_for_ranking as $year => $terms) {
        foreach ($terms as $term => $subjects) {
            foreach ($subjects as $subj_id => $students_marks) {
                // Sort students for this subject and term by marks descending
                usort($students_marks, function($a, $b) {
                    return $b['marks'] <=> $a['marks']; // Spaceship operator for comparison
                });

                $total_students_with_marks = count($students_marks); // Total students who have marks for this subject/term/year

                // Assign ranks, handling ties
                $rank = 1;
                $prev_marks = null;
                $tied_rank_start = 1;

                foreach ($students_marks as $i => $student_mark_data) {
                    if ($prev_marks !== null && $student_mark_data['marks'] < $prev_marks) {
                        $rank = $i + 1;
                        $tied_rank_start = $i + 1; // Reset tied rank start
                    } else if ($prev_marks !== null && $student_mark_data['marks'] === $prev_marks) {
                        // If tied, use the rank from the start of the tie
                        $rank = $tied_rank_start;
                    } else {
                        // First student or if no tie, rank is current index + 1
                        $rank = $i + 1;
                        $tied_rank_start = $i + 1;
                    }

                    $subject_positions[$student_mark_data['student_id']][$year][$term][$subj_id] = $rank . ' of ' . $total_students_with_marks;
                    $prev_marks = $student_mark_data['marks'];
                }
            }
        }
    }
    // --- End Calculate Positions ---

}
$conn->close(); // Close database connection at the end of PHP logic
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Parent Portal - Student Results</title>
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
            overflow-x: hidden;
        }

        /* Simplified sidebar/navbar for parent portal - adjust as needed */
        .parent-navbar {
            background-color: var(--sidebar-bg);
            padding: 15px 30px;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .parent-navbar .nav-link {
            color: white;
            margin-left: 20px;
        }
        .parent-navbar .nav-link:hover {
            color: var(--primary-color);
        }
        .main-content {
            padding: 30px;
            padding-top: 90px; /* Adjust for fixed navbar */
            min-height: 100vh;
            box-sizing: border-box;
        }

        .report-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 30px;
            margin-bottom: 40px;
        }
        .report-card-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
        }
        .report-card-header h2 {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 10px;
        }
        .student-info, .summary-info, .teacher-comments {
            margin-bottom: 20px;
        }
        .student-info strong, .summary-info strong {
            color: #555;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .subject-table th, .subject-table td {
            text-align: left;
            padding: 8px;
            vertical-align: middle;
        }
        .subject-table thead th {
            background-color: #f2f2f2;
            color: #333;
        }
        .no-results {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #666;
        }

        /* Grade System Table Specific Styles */
        .grade-system-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }
        .grade-system-info h4 {
            color: #34495e;
            margin-bottom: 15px;
        }
        .grade-system-table th, .grade-system-table td {
            text-align: center;
            vertical-align: middle;
        }
        .grade-system-table th {
            background-color: #e9ecef;
        }

        /* Styles for Print */
        @media print {
            body {
                background-color: #fff;
            }
            .parent-navbar, .filter-section, .print-button-container {
                display: none; /* Hide elements not needed in print */
            }
            .main-content {
                padding-top: 20px; /* Reset padding for print */
            }
            .report-card {
                box-shadow: none; /* Remove shadow for print */
                border: 1px solid #ccc;
                margin-bottom: 20px;
                page-break-inside: avoid; /* Avoid breaking report card across pages */
            }
            .report-card-header {
                border-bottom: 1px solid #eee;
            }
            .table-responsive {
                overflow: visible !important; /* Ensure table is fully visible */
            }
            .subject-table {
                width: 100%;
                border-collapse: collapse;
            }
            .subject-table th, .subject-table td {
                border: 1px solid #ddd;
                padding: 5px;
                font-size: 0.8em;
            }
            .summary-info, .teacher-comments {
                font-size: 0.9em;
            }
            .grade-system-info {
                border-top: 1px solid #eee; /* Light border for print */
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg parent-navbar">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="#">Parent Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="#">Welcome, <?php echo htmlspecialchars($user_first_name); ?>!</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid main-content">
    <div class="header">
        <h1>Your Children's Results</h1>
    </div>

    <?php if (empty($parent_students_ids)): ?>
        <div class="alert alert-info text-center mt-4" role="alert">
            No students are registered under your account. Please contact school administration.
        </div>
    <?php elseif (empty($children_results) && $selected_term !== 'All'): ?>
        <div class="alert alert-warning text-center mt-4" role="alert">
            No results have been published for the selected term (<?php echo htmlspecialchars($selected_term); ?>) yet.
        </div>
    <?php elseif (empty($children_results) && $selected_term === 'All'): ?>
        <div class="alert alert-warning text-center mt-4" role="alert">
            No results have been published for your children yet. Please wait for the school administration to publish them.
        </div>
    <?php else: ?>
        <div class="filter-section my-3">
            <form action="" method="GET" class="d-flex align-items-center">
                <label for="term_select" class="form-label me-2 mb-0">Select Term:</label>
                <select name="term" id="term_select" class="form-select me-2" style="width: 200px;">
                    <option value="All" <?php echo ($selected_term === 'All') ? 'selected' : ''; ?>>All Terms</option>
                    <option value="Term 1" <?php echo ($selected_term === 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                    <option value="Term 2" <?php echo ($selected_term === 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                    <option value="Annual" <?php echo ($selected_term === 'Annual') ? 'selected' : ''; ?>>Annual</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <button type="button" class="btn btn-success ms-3 print-button-container" onclick="window.print()">Print Report <i class="fas fa-print"></i></button>
            </form>
        </div>

        <?php
        foreach ($students_data as $student_id => $student_info):
            $student_full_name = htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']);
            $student_class_name = $class_names[$student_info['class_id']] ?? 'N/A';
            $student_has_published_results = isset($children_results[$student_id]);
            $current_student_class_id = $student_info['class_id']; // Get class ID for grade system lookup

            if ($student_has_published_results):
                foreach ($children_results[$student_id] as $year => $terms_data):
                    $terms_to_display = [];
                    if ($selected_term === 'All') {
                        $terms_to_display = array_keys($terms_data);
                        // Sort terms for consistent display
                        usort($terms_to_display, function($a, $b) {
                            $order = ['Term 1', 'Term 2', 'Annual'];
                            return array_search($a, $order) - array_search($b, $order);
                        });
                    } elseif ($selected_term === 'Annual') {
                        // For Annual, we explicitly look for Term 1 and Term 2 data to compute annual
                        if (isset($terms_data['Term 1']) || isset($terms_data['Term 2'])) {
                            $terms_to_display[] = 'Annual';
                        }
                    } else {
                        if (isset($terms_data[$selected_term])) {
                            $terms_to_display[] = $selected_term;
                        }
                    }

                    foreach ($terms_to_display as $term_to_show):
                        $student_total_marks = 0;
                        $student_subjects_counted = 0;
                        $best_subject_mark = -1;
                        $best_subject_name = '';
                        $worst_subject_mark = 101;
                        $worst_subject_name = '';
                        $passed_subjects_count = 0;
                        $current_term_subject_results = [];
                        $s_n = 1;

                        $display_this_report = false; // Flag to determine if this specific report card should be shown

                        // Fetch subjects relevant to the student's class
                        $relevant_subjects = $all_subjects[$student_info['class_id']] ?? [];

                        foreach ($relevant_subjects as $subj_id => $subj_name) {
                            $marks_I = 'N/A';
                            $grade_I = 'N/A';
                            $marks_II = 'N/A';
                            $grade_II = 'N/A';
                            $total_subject_marks = 'N/A';
                            $average_subject_marks = 'N/A';
                            $subject_comment = 'No Marks Recorded';
                            $subject_position = 'N/A'; // Initialize position

                            if ($term_to_show === 'Annual') {
                                $marks_t1 = $children_results[$student_id][$year]['Term 1'][$subj_id]['marks'] ?? null;
                                $grade_t1 = $children_results[$student_id][$year]['Term 1'][$subj_id]['grade'] ?? 'N/A';
                                $marks_t2 = $children_results[$student_id][$year]['Term 2'][$subj_id]['marks'] ?? null;
                                $grade_t2 = $children_results[$student_id][$year]['Term 2'][$subj_id]['grade'] ?? 'N/A';

                                $marks_I = ($marks_t1 !== null) ? $marks_t1 : 'N/A';
                                $grade_I = ($grade_t1 !== 'N/A') ? $grade_t1 : 'N/A';
                                $marks_II = ($marks_t2 !== null) ? $marks_t2 : 'N/A';
                                $grade_II = ($grade_t2 !== 'N/A') ? $grade_t2 : 'N/A';

                                $current_subject_total = 0;
                                $current_terms_for_subject = 0;

                                if (is_numeric($marks_t1)) {
                                    $current_subject_total += (int)$marks_t1;
                                    $current_terms_for_subject++;
                                }
                                if (is_numeric($marks_t2)) {
                                    $current_subject_total += (int)$marks_t2;
                                    $current_terms_for_subject++;
                                }

                                if ($current_terms_for_subject > 0) {
                                    $total_subject_marks = $current_subject_total;
                                    $average_subject_marks = round($current_subject_total / $current_terms_for_subject, 2);
                                    $subject_comment = getCommentForMarks($average_subject_marks); // Use getCommentForMarks for comments

                                    $student_total_marks += $average_subject_marks;
                                    $student_subjects_counted++;
                                    $display_this_report = true; // At least one subject has marks for Annual

                                    // Get position for Annual average for this subject
                                    $subject_position = $subject_positions[$student_id][$year]['Annual'][$subj_id] ?? 'N/A';

                                    // Assuming 30 is passing mark for a subject (for calculating passed subjects count)
                                    // This threshold for passing might also ideally come from grade_systems for consistency
                                    $passing_min_mark = 30; // Default if not found in grade system
                                    if (isset($all_grade_systems_map[$current_student_class_id])) {
                                        // Find the lowest passing grade's min_marks
                                        foreach ($all_grade_systems_map[$current_student_class_id] as $rule) {
                                            if ($rule['grade_name'] !== 'F' && $rule['min_marks'] < $passing_min_mark) { // Assuming 'F' is the failing grade
                                                $passing_min_mark = $rule['min_marks'];
                                            }
                                        }
                                    }
                                    if ($average_subject_marks >= $passing_min_mark) { 
                                        $passed_subjects_count++;
                                    }

                                    if ($average_subject_marks > $best_subject_mark) {
                                        $best_subject_mark = $average_subject_marks;
                                        $best_subject_name = $subj_name;
                                    }
                                    if ($average_subject_marks < $worst_subject_mark) {
                                        $worst_subject_mark = $average_subject_marks;
                                        $worst_subject_name = $subj_name;
                                    }
                                } else {
                                    $subject_comment = 'No Marks Recorded';
                                }

                            } else { // For Term 1 or Term 2
                                $result_data = $terms_data[$term_to_show][$subj_id] ?? null;
                                if ($result_data) {
                                    $marks_I = $result_data['marks'];
                                    $grade_I = $result_data['grade'];
                                    $total_subject_marks = $marks_I;
                                    $average_subject_marks = $marks_I; // For single term, total is average
                                    $subject_comment = getCommentForMarks($marks_I); // Use getCommentForMarks for comments

                                    $student_total_marks += (int)$marks_I;
                                    $student_subjects_counted++;
                                    $display_this_report = true; // At least one subject has marks for this term

                                    // Get position for this term and subject
                                    $subject_position = $subject_positions[$student_id][$year][$term_to_show][$subj_id] ?? 'N/A';

                                    $passing_min_mark = 30; // Default if not found in grade system
                                    if (isset($all_grade_systems_map[$current_student_class_id])) {
                                        foreach ($all_grade_systems_map[$current_student_class_id] as $rule) {
                                            if ($rule['grade_name'] !== 'F' && $rule['min_marks'] < $passing_min_mark) {
                                                $passing_min_mark = $rule['min_marks'];
                                            }
                                        }
                                    }
                                    if ($marks_I >= $passing_min_mark) { 
                                        $passed_subjects_count++;
                                    }

                                    if ($marks_I > $best_subject_mark) {
                                        $best_subject_mark = $marks_I;
                                        $best_subject_name = $subj_name;
                                    }
                                    if ($marks_I < $worst_subject_mark) {
                                        $worst_subject_mark = $marks_I;
                                        $worst_subject_name = $subj_name;
                                    }
                                } else {
                                    $subject_comment = 'No Marks Recorded';
                                }
                            }

                            // Add to current term's subject results if any marks found or if it's Annual
                            // Ensure 'N/A' marks are also displayed in the table for completeness
                            $current_term_subject_results[] = [
                                's_n' => $s_n++,
                                'subject_name' => $subj_name,
                                'marks_I' => $marks_I,
                                'grade_I' => $grade_I,
                                'marks_II' => $marks_II, // Will be N/A for Term 1/Term 2 reports
                                'grade_II' => $grade_II, // Will be N/A for Term 1/Term 2 reports
                                'total_subject_marks' => is_numeric($total_subject_marks) ? $total_subject_marks : 'N/A',
                                'average_subject_marks' => is_numeric($average_subject_marks) ? $average_subject_marks : 'N/A',
                                'comments' => $subject_comment,
                                'position' => $subject_position // New field for position
                            ];
                        }

                        if ($display_this_report):
                            $overall_average_marks = ($student_subjects_counted > 0) ? round($student_total_marks / $student_subjects_counted, 2) : 'N/A';
                            $overall_division = calculateDivision($overall_average_marks); // Uses hardcoded division logic
                        ?>
                        <div class="report-card mb-5">
                            <div class="report-card-header">
                                <h2>ACADEMIC RESULTS</h2>
                                <h4>Student: <?php echo $student_full_name; ?></h4>
                                <h4>Class: <?php echo htmlspecialchars($student_class_name); ?></h4>
                                <h4>Year: <?php echo htmlspecialchars($year); ?> | Term: <?php echo htmlspecialchars($term_to_show); ?></h4>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered subject-table">
                                    <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>Subject</th>
                                        <th>Marks (Term 1)</th>
                                        <th>Grade (Term 1)</th>
                                        <?php if ($term_to_show === 'Annual'): ?>
                                            <th>Marks (Term 2)</th>
                                            <th>Grade (Term 2)</th>
                                        <?php endif; ?>
                                     
                                        <th>Position</th> <!-- New column header for Position -->
                                        <th>Remark</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($current_term_subject_results as $s_result): ?>
                                        <tr>
                                            <td><?php echo $s_result['s_n']; ?></td>
                                            <td><?php echo $s_result['subject_name']; ?></td>
                                            <td><?php echo $s_result['marks_I']; ?></td>
                                            <td><?php echo $s_result['grade_I']; ?></td>
                                            <?php if ($term_to_show === 'Annual'): ?>
                                                <td><?php echo $s_result['marks_II']; ?></td>
                                                <td><?php echo $s_result['grade_II']; ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($s_result['position']); ?></td> <!-- Display position here -->
                                            <td><?php echo htmlspecialchars($s_result['comments']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="summary-info">
                                <p><strong>Total Marks:</strong> <?php echo is_numeric($student_total_marks) ? htmlspecialchars(round($student_total_marks)) : 'N/A'; ?></p>
                                <p><strong>Overall Average:</strong> <?php echo is_numeric($overall_average_marks) ? htmlspecialchars($overall_average_marks) . '%' : 'N/A'; ?></p>
                                <p><strong>Overall Division:</strong> <?php echo htmlspecialchars($overall_division); ?></p>
                            </div>

                            <div class="summary-info">
                                <h3>PERFORMANCE SUMMARY</h3>
                                <p><strong>Number of subjects passed:</strong> <?php echo htmlspecialchars($passed_subjects_count); ?> out of <?php echo htmlspecialchars(count($relevant_subjects)); ?></p>
                                <?php if ($best_subject_name !== '' && $best_subject_mark !== -1 && is_numeric($best_subject_mark)): ?>
                                    <p><strong>Best Subject:</strong> <?php echo htmlspecialchars($best_subject_name); ?> (<?php echo htmlspecialchars(round($best_subject_mark, 2)); ?>%)</p>
                                <?php endif; ?>
                                <?php if ($worst_subject_name !== '' && $worst_subject_mark <= 100 && is_numeric($worst_subject_mark)): ?>
                                    <p><strong>Most Challenging Subject:</strong> <?php echo htmlspecialchars($worst_subject_name); ?> (<?php echo htmlspecialchars(round($worst_subject_mark, 2)); ?>%)</p>
                                <?php endif; ?>
                            </div>

                            <div class="teacher-comments">
                                <h3>TEACHER'S COMMENTS</h3>
                                <p>Student <?php echo $student_full_name; ?> has shown great determination in their studies. They need to put more effort into <?php echo htmlspecialchars($worst_subject_name); ?> by focusing more on reading and writing exercises. They show good aptitude in science subjects.
                                    <br>
                                </p>
                            </div>

                            <!-- New section for Grade System Table -->
                            <?php if (isset($all_grade_systems_map[$current_student_class_id]) && !empty($all_grade_systems_map[$current_student_class_id])): ?>
                            <div class="grade-system-info">
                                <h4>Grade System for <?php echo htmlspecialchars($student_class_name); ?> (Marks %)</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered grade-system-table">
                                        <thead>
                                            <tr>
                                                <th>Grade</th>
                                                <th>Interval</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_grade_systems_map[$current_student_class_id] as $grade_rule): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($grade_rule['grade_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($grade_rule['min_marks']); ?> - <?php echo htmlspecialchars($grade_rule['max_marks']); ?></td>
                                                  
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info text-center mt-3" role="alert">
                                No specific grade system defined for <?php echo htmlspecialchars($student_class_name); ?>.
                            </div>
                            <?php endif; ?>
                            <!-- End New section for Grade System Table -->

                        </div>
                        <?php endif; // End if $display_this_report ?>
                    <?php endforeach; // End foreach term_to_show ?>
                <?php endforeach; // End foreach year ?>
            <?php endif; // End if $student_has_published_results ?>
        <?php endforeach; // End foreach $students_data ?>
    <?php endif; // End if !empty($children_results) ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php ob_end_flush(); ?>
