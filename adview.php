<?php
session_start(); // MUHIMU: Anza session mwanzoni mwa faili

// Unganisha na database
include 'connection/db.php'; 

// Redirect if user is not logged in or not an Admin (kulingana na mahitaji yako)
// Nimebadilisha sharti hapa ili Admin ndiye aone ukurasa huu kama ripoti.
// Ikiwa unataka walimu waweze kuona ripoti pia, unaweza kuacha sharti la 'teacher'
// au kuongeza 'OR $_SESSION['user_data']['role'] === 'teacher''
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php"); // Rudisha kwenye ukurasa wa kuingia
    exit(); // Maliza script
}

// Pata ID na taarifa nyingine za mtumiaji aliyeingia (kwa Admin)
$current_user_id = $_SESSION['user_data']['id']; 
$user_first_name = $_SESSION['user_data']['first_name'];
$user_last_name = $_SESSION['user_data']['last_name'];
$user_role = $_SESSION['user_data']['role'];

// Initialize variables for messages
$errors = [];
$success = '';

// Function to calculate grade based on marks (for individual subjects)
function calculateGrade($marks) {
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
function calculateDivision($totalMarks, $numSubjects) {
    if ($numSubjects == 0) {
        return 'N/A';
    }
    $averageMarks = $totalMarks / $numSubjects;

    if ($averageMarks >= 80) {
        return 'DIVISION ONE';
    } elseif ($averageMarks >= 65) {
        return 'DIVISION TWO';
    } elseif ($averageMarks >= 45) {
        return 'DIVISION THREE';
    } elseif ($averageMarks >= 30) {
        return 'DIVISION FOUR';
    } elseif ($averageMarks >= 0) {
        return 'ZERO';
    } else {
        return 'N/A';
    }
}

// NOTE: KWA UKURASA WA ADMIN WA KUONESHA RIPOTI, HATUTAHITAJI LOGIC YA AJAX YA KUHIFADHI
// KWA SABABU ADMIN ATAKUWA ANAANGALIA RIPOTI TU.
// Kwa hivyo, nimetoa block ya if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_all_marks')
// kama inavyopaswa kuwa kwa ukurasa wa Admin.


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
$terms = ['Term 1', 'Term 2', 'Term 3'];
$current_year_for_dropdown = date('Y'); 
$years = range($current_year_for_dropdown - 5, $current_year_for_dropdown + 2); 

// Default selected values (for initial display)
$selected_class_id = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_VALIDATE_INT) : null;
$selected_term = isset($_GET['term']) ? trim($_GET['term']) : null;
$selected_year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;
// $selected_subject_id haihitajiki tena kwa ripoti ya jumla ya Admin
// $selected_subject_id = isset($_GET['subject_id']) ? filter_var($_GET['subject_id'], FILTER_VALIDATE_INT) : null; 


// Fetch data based on selected class, term, and year
$students_in_class = [];
$subjects_to_display = []; // Sasa itakuwa masomo yote ya darasa
$all_results_by_student_subject = []; // Matokeo yote yaliyopangwa kwa student_id na subject_id

if ($selected_class_id && $selected_term && $selected_year) { 
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

    // 2. Fetch ALL subjects for the selected class (for displaying columns)
    // Sasa tunaleta masomo yote yanayofundishwa katika darasa hili
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

    // 3. Fetch all existing results for these students and subjects in the selected term/year
    if (!empty($students_in_class) && !empty($subjects_to_display)) {
        $student_ids = array_column($students_in_class, 'id');
        $subject_ids = array_column($subjects_to_display, 'id'); 

        // Jenga placeholders kwa IN clause kwa student_ids na subject_ids
        $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));
        $placeholders_subjects = implode(',', array_fill(0, count($subject_ids), '?'));
        
        // Andaa aina za vigezo na array ya vigezo
        $bind_types = str_repeat('i', count($student_ids)) . str_repeat('i', count($subject_ids)) . 'si'; 
        $bind_params = array_merge($student_ids, $subject_ids, [$selected_term, $selected_year]);

        $query_all_results_fetch = "
            SELECT 
                student_id, 
                subject_id, 
                marks, 
                grade 
            FROM 
                results 
            WHERE 
                student_id IN ($placeholders_students) 
                AND subject_id IN ($placeholders_subjects) 
                AND term = ? 
                AND year = ?
            ";
        
        $stmt_all_results_fetch = $conn->prepare($query_all_results_fetch);

        if ($stmt_all_results_fetch === false) {
            die("Error preparing results fetch statement: " . $conn->error);
        }

        // Dynamically bind parameters using call_user_func_array
        // This is a common way to bind a variable number of parameters
        // For PHP 5.6+ using ...$params would be simpler, but for wider compatibility
        // (especially with older XAMPP versions) call_user_func_array with refValues is safer.
        // Nimebadilisha kwenda ...$params kama ulivyokuwa umetumia kwenye code uliyonipa.
        $stmt_all_results_fetch->bind_param($bind_types, ...$bind_params);
        
        $stmt_all_results_fetch->execute();
        $result_fetch = $stmt_all_results_fetch->get_result();
        while ($row = $result_fetch->fetch_assoc()) {
            $all_results_by_student_subject[$row['student_id']][$row['subject_id']] = [
                'marks' => $row['marks'],
                'grade' => $row['grade']
            ];
        }
        $stmt_all_results_fetch->close();
    }
}
$conn->close(); // Funga connection ya database mwishoni mwa PHP logic
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
            overflow: hidden; /* Prevent body from scrolling */
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
            height: 100vh; /* Take full viewport height */
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
            <h4 class="card-title mb-4">Chagua Vigezo vya Ripoti</h4>
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
                <div class="col-12 d-flex justify-content-center align-items-end">
                     <button type="submit" class="btn btn-primary w-50">Onyesha Ripoti</button>
                </div>
            </form>
        </div>

        <?php 
        // Logic ya kuonyesha meza au ujumbe wa kutokuwepo kwa data
        if ($selected_class_id && $selected_term && $selected_year): ?>
            <?php if (empty($students_in_class)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Hakuna wanafunzi waliosajiliwa katika darasa hili kwa sasa.
                </div>
            <?php elseif (empty($subjects_to_display)): ?>
                <div class="alert alert-warning text-center" role="alert">
                    Hakuna masomo yaliyosajiliwa kwa darasa hili.
                </div>
            <?php else: ?>
                <div class="card p-4">
                    <h4 class="card-title mb-4">Ripoti ya Matokeo kwa Darasa la: <span class="text-primary"><?php echo htmlspecialchars($classes_data[array_search($selected_class_id, array_column($classes_data, 'id'))]['class_name']); ?></span> (<small><?php echo htmlspecialchars($selected_term); ?>, <?php echo htmlspecialchars($selected_year); ?></small>)</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Jina la Mwanafunzi</th>
                                    <?php foreach ($subjects_to_display as $subject): ?>
                                        <th><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Jumla</th>
                                    <th>Wastani</th>
                                    <th>Gredi ya Jumla (Division)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($students_in_class)): ?>
                                    <?php foreach ($students_in_class as $student): ?>
                                        <tr>
                                            <td class="text-start"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <?php 
                                                $student_total_marks = 0;
                                                $student_subjects_counted = 0; // Kufuatilia masomo yaliyojazwa
                                                foreach ($subjects_to_display as $subject): 
                                                    $marks = $all_results_by_student_subject[$student['id']][$subject['id']]['marks'] ?? '';
                                                    $grade = $all_results_by_student_subject[$student['id']][$subject['id']]['grade'] ?? 'N/A';
                                                ?>
                                                <td>
                                                    <?php 
                                                        if ($marks !== '') {
                                                            echo htmlspecialchars($grade) . ' (' . htmlspecialchars($marks) . ')';
                                                            $student_total_marks += (int)$marks; // Ongeza alama kwenye jumla
                                                            $student_subjects_counted++; // Ongeza idadi ya masomo yaliyopatikana
                                                        } else {
                                                            echo '-'; // Au unaweza kuweka 'N/A'
                                                        }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <span class="total-marks"><?php echo htmlspecialchars($student_total_marks); ?></span>
                                            </td>
                                            <td>
                                                <span class="average-marks">
                                                    <?php 
                                                        if ($student_subjects_counted > 0) {
                                                            echo htmlspecialchars(round($student_total_marks / $student_subjects_counted, 2));
                                                        } else {
                                                            echo '0';
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="overall-division">
                                                    <?php echo htmlspecialchars(calculateDivision($student_total_marks, $student_subjects_counted)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo (count($subjects_to_display) + 4); ?>" class="text-center text-muted">Hakuna wanafunzi waliopatikana kwa darasa na vigezo vilivyochaguliwa.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Tafadhali chagua **Darasa**, **Muhula**, na **Mwaka** kutoka kwenye fomu hapo juu ili uone ripoti ya matokeo.
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

    // Huna haja tena ya AJAX save logic au dynamic grade calculation kwa sababu huu ni ukurasa wa ripoti.
    // Unaweza kuondoa script ya AJAX ikiwa huna matumizi mengine nayo.
    // $('#saveAllMarksBtn').on('click', function(e) { ... });
    // function calculateGradeDisplay(inputElement) { ... };
</script>

</body>
</html>