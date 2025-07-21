<?php
// student_report.php

require_once 'auth-check.php';

require 'connection/db.php'; 

$studentData = null;
$studentResults = [];
$teacherComments = null;
$overallSummary = [];
$errorMessage = '';

if (isset($_GET['studentID'])) {
    $studentID = $_GET['studentID'];
    $parentID = $_SESSION['user']['userID'];

    // Validate studentID to prevent SQL injection (optional but good practice)
    if (!filter_var($studentID, FILTER_VALIDATE_INT)) {
        $errorMessage = "Kitambulisho cha mwanafunzi si sahihi.";
    } else {
        // Angalia kama mwanafunzi huyu anamilikiwa na mzazi huyu (security check)
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE studentID = ? AND parent_ID = ?");
            if ($stmt === false) {
                throw new Exception("MySQLi prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ii', $studentID, $parentID);
            $stmt->execute();
            $stmt->bind_result($is_parent_of_student);
            $stmt->fetch();
            $stmt->close();

            if ($is_parent_of_student == 0) {
                $errorMessage = "Huruhusiwi kuangalia matokeo ya mwanafunzi huyu.";
            } else {
                // Pata Taarifa za Mwanafunzi
                $stmt = $conn->prepare("SELECT studentID, first_name, last_name, class, gender, age, region FROM students WHERE studentID = ?");
                if ($stmt === false) {
                    throw new Exception("MySQLi prepare failed: " . $conn->error);
                }
                $stmt->bind_param('i', $studentID);
                $stmt->execute();
                $result = $stmt->get_result();
                $studentData = $result->fetch_assoc();
                $stmt->close();

                if ($studentData) {
                    // Pata Matokeo ya Kisomo (grades)
                    // Hapa unaweza kubadilisha 'Muhula wa Pili' na 'Mwaka 2023'
                    // au kuongeza filters za kuchagua muhula na mwaka
                    $stmt = $conn->prepare("
                        SELECT 
                            s.subject_name, 
                            g.score1, g.grade1, 
                            g.score2, g.grade2, 
                            g.comments 
                        FROM grades g
                        JOIN subjects s ON g.subjectID = s.subjectID
                        WHERE g.studentID = ?
                        ORDER BY s.subject_name
                    ");
                    if ($stmt === false) {
                        throw new Exception("MySQLi prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param('i', $studentID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $studentResults = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // Pata Maoni ya Walimu
                    $stmt = $conn->prepare("SELECT teacher_comment FROM teachers_comments WHERE studentID = ? ORDER BY commentID DESC LIMIT 1");
                    if ($stmt === false) {
                        throw new Exception("MySQLi prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param('i', $studentID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $teacherCommentRow = $result->fetch_assoc();
                    $teacherComments = $teacherCommentRow ? $teacherCommentRow['teacher_comment'] : 'Hakuna maoni ya walimu bado.';
                    $stmt->close();

                    // Kokotoa Jumla ya Alama na Wastani
                    $totalScore = 0;
                    $totalSubjects = count($studentResults);
                    $passedSubjects = 0;
                    $bestSubjectScore = 0;
                    $bestSubjectName = '';
                    $challengingSubjectScore = 101; // Max score + 1
                    $challengingSubjectName = '';

                    foreach ($studentResults as $result) {
                        $term1_score = (int)$result['score1'];
                        $term2_score = (int)$result['score2'];
                        $totalSubjectScore = $term1_score + $term2_score;
                        $averageSubjectScore = $totalSubjectScore / 2;
                        $totalScore += $averageSubjectScore; // Using average for overall calculation

                        // Example passing mark (adjust as needed)
                        if ($averageSubjectScore >= 50) { 
                            $passedSubjects++;
                        }

                        if ($averageSubjectScore > $bestSubjectScore) {
                            $bestSubjectScore = $averageSubjectScore;
                            $bestSubjectName = $result['subject_name'];
                        }
                        if ($averageSubjectScore < $challengingSubjectScore) {
                            $challengingSubjectScore = $averageSubjectScore;
                            $challengingSubjectName = $result['subject_name'];
                        }
                    }

                    $overallAverage = $totalSubjects > 0 ? ($totalScore / $totalSubjects) : 0;
                    $overallGrade = ''; // Logic to determine overall grade (A, B, C, etc.)
                    if ($overallAverage >= 80) $overallGrade = 'A';
                    else if ($overallAverage >= 70) $overallGrade = 'B+';
                    else if ($overallAverage >= 60) $overallGrade = 'B';
                    else if ($overallAverage >= 50) $overallGrade = 'C';
                    else $overallGrade = 'D';

                    $overallSummary = [
                        'total_score' => $totalScore, // Or sum of all average scores
                        'overall_average' => round($overallAverage, 2),
                        'overall_grade' => $overallGrade,
                        'position' => 'N/A', // Hii inahitaji hesabu ngumu zaidi kutoka kwenye database nzima ya darasa
                        'passed_subjects' => $passedSubjects,
                        'total_subjects' => $totalSubjects,
                        'best_subject_name' => $bestSubjectName,
                        'best_subject_score' => round($bestSubjectScore, 2),
                        'challenging_subject_name' => $challengingSubjectName,
                        'challenging_subject_score' => round($challengingSubjectScore, 2),
                    ];

                } else {
                    $errorMessage = "Taarifa za mwanafunzi hazikupatikana.";
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching student report: " . $e->getMessage());
            $errorMessage = "Imetokea tatizo wakati wa kupata ripoti. Tafadhali jaribu tena.";
        } finally {
             // Funga connection hapa ikiwa haitumiki tena
            // $conn->close();
        }
    }
} else {
    $errorMessage = "Hakuna kitambulisho cha mwanafunzi kilichotolewa.";
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripoti ya Matokeo ya Mwanafunzi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
            margin-bottom: 30px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h1, h2, h3, h4 {
            color: #34495e;
            margin-bottom: 20px;
        }
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
        }
        .student-info, .performance-summary, .teacher-comments {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f2f4f6;
            border-radius: 5px;
            border-left: 5px solid #3498db;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table thead th {
            background-color: #3498db;
            color: white;
        }
        .back-button {
            margin-top: 20px;
        }
        .grade-A { color: #27ae60; font-weight: bold; } /* Green for A */
        .grade-B { color: #2980b9; font-weight: bold; } /* Blue for B */
        .grade-C { color: #f39c12; font-weight: bold; } /* Orange for C */
        .grade-D, .grade-F { color: #e74c3c; font-weight: bold; } /* Red for D/F */

    </style>
</head>
<body>
    <div class="container">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $errorMessage; ?>
            </div>
            <a href="parent-dashboard.php" class="btn btn-secondary back-button">Rudi Kwenye Dashboard</a>
        <?php else: ?>
            <div class="header-section">
                <h1>TAARIFA YA MATOKEO YA MWANAFUNZI</h1>
                <p class="lead">Shule ya Sekondari ABC</p>
                <p>Muhula wa Pili - Mwaka 2023</p>
            </div>

            <?php if ($studentData): ?>
                <div class="student-info">
                    <h2>TAARIFA YA MWANAFUNZI</h2>
                    <p><strong>Jina kamili:</strong> <?php echo htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']); ?></p>
                    <p><strong>Darasa:</strong> <?php echo htmlspecialchars($studentData['class']); ?></p>
                    <p><strong>Jinsia:</strong> <?php echo htmlspecialchars($studentData['gender']); ?></p>
                    <p><strong>Miaka:</strong> <?php echo htmlspecialchars($studentData['age']); ?></p>
                    <p><strong>Mkoa:</strong> <?php echo htmlspecialchars($studentData['region']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($studentResults)): ?>
                <h3>MATOKEO YA KISOMO</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Somo</th>
                                <th>Alama (I)</th>
                                <th>Gredi (I)</th>
                                <th>Alama (II)</th>
                                <th>Gredi (II)</th>
                                <th>Jumla</th>
                                <th>Wastani</th>
                                <th>Maoni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1; $totalOverallScore = 0; $totalOverallAverage = 0; ?>
                            <?php foreach ($studentResults as $result): ?>
                                <?php 
                                    $score1 = (int)$result['score1'];
                                    $score2 = (int)$result['score2'];
                                    $totalSubject = $score1 + $score2;
                                    $averageSubject = $totalSubject / 2;
                                    $totalOverallScore += $totalSubject;
                                    $totalOverallAverage += $averageSubject;

                                    // Function to determine grade class for styling
                                    function getGradeClass($grade) {
                                        switch (strtoupper($grade)) {
                                            case 'A':
                                            case 'A+': return 'grade-A';
                                            case 'B+':
                                            case 'B': return 'grade-B';
                                            case 'C': return 'grade-C';
                                            case 'D':
                                            case 'F': return 'grade-D';
                                            default: return '';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $sn++; ?></td>
                                    <td><?php echo htmlspecialchars($result['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($score1); ?></td>
                                    <td class="<?php echo getGradeClass($result['grade1']); ?>"><?php echo htmlspecialchars($result['grade1']); ?></td>
                                    <td><?php echo htmlspecialchars($score2); ?></td>
                                    <td class="<?php echo getGradeClass($result['grade2']); ?>"><?php echo htmlspecialchars($result['grade2']); ?></td>
                                    <td><?php echo $totalSubject; ?></td>
                                    <td><?php echo round($averageSubject, 2); ?></td>
                                    <td><?php echo htmlspecialchars($result['comments']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Jumla ya alama:</th>
                                <th><?php echo $totalOverallScore; ?></th>
                                <th colspan="2"></th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end">Wastani wa jumla:</th>
                                <th><?php echo round($overallSummary['overall_average'], 2); ?>%</th>
                                <th colspan="1"></th>
                            </tr>
                            <tr>
                                <th colspan="7" class="text-end">Daraja la jumla:</th>
                                <th colspan="2" class="<?php echo getGradeClass($overallSummary['overall_grade']); ?>"><?php echo htmlspecialchars($overallSummary['overall_grade']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    Hakuna matokeo ya kisomo yaliyopatikana kwa mwanafunzi huyu.
                </div>
            <?php endif; ?>

            <?php if (!empty($overallSummary)): ?>
                <div class="performance-summary">
                    <h2>UFUPISHO WA UTENDAAJI</h2>
                    <p><strong>Msimamo wa darasani:</strong> <?php echo htmlspecialchars($overallSummary['position']); ?></p>
                    <p><strong>Idadi ya masomo yaliyofuzu:</strong> <?php echo htmlspecialchars($overallSummary['passed_subjects']) . ' kati ya ' . htmlspecialchars($overallSummary['total_subjects']); ?></p>
                    <p><strong>Somo bora:</strong> <?php echo htmlspecialchars($overallSummary['best_subject_name']); ?> (<?php echo htmlspecialchars($overallSummary['best_subject_score']); ?>%)</p>
                    <p><strong>Somo lenye changamoto:</strong> <?php echo htmlspecialchars($overallSummary['challenging_subject_name']); ?> (<?php echo htmlspecialchars($overallSummary['challenging_subject_score']); ?>%)</p>
                </div>
            <?php endif; ?>

            <div class="teacher-comments">
                <h2>MAONI YA WALIMU</h2>
                <p>"<?php echo htmlspecialchars($teacherComments); ?>"</p>
            </div>

            <a href="parent-dashboard.php" class="btn btn-primary back-button">Rudi Kwenye Dashboard</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>