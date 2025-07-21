<?php
include 'connection/db.php';
session_start();
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}
$loggedInTeacherId = ($_SESSION['user_data']['id']) ?? 0;

$teacherClassId = null;
if ($loggedInTeacherId) {
    $stmt = $conn->prepare("SELECT id FROM classes WHERE teacher_id = ?");
    $stmt->bind_param("i", $loggedInTeacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacherClassId = $row['id'];
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - User & Password Management</title>
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
        html, body {
            height: 100%; /* Ensure html and body take full viewport height */
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow: hidden; /* Prevent overall body scroll */
        }
        .admin-sidebar {
            background-color: var(--sidebar-bg);
            min-height: 100vh;
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed; /* Keeps sidebar fixed */
            transition: all 0.3s;
            z-index: 1000;
            overflow-y: auto; /* Allow sidebar content to scroll if it overflows */
        }
        .chart-container {
            position: relative;
            height: 600px; /* Increased height for larger charts */
            width: 100%;
            margin-top: 20px;
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
            margin-left: 280px; /* Offset for the fixed sidebar */
            padding: 20px;
            transition: all 0.3s;
            height: 100vh; /* Make main content take full viewport height */
            overflow-y: auto; /* Allows content within main-content to scroll */
            padding-top: 20px; /* Adjust padding if there's a fixed top nav/header */
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative; /* On small screens, sidebar becomes part of normal flow */
                overflow-y: visible; /* Adjust for smaller screens */
            }
            .main-content {
                margin-left: 0; /* No offset on small screens */
                height: auto; /* Let content dictate height on small screens */
                overflow-y: visible; /* Adjust for smaller screens */
            }
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.35rem;
            overflow: hidden;
        }
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
        .table tbody tr:nth-of-type(even) {
            background-color: #f2f2f2;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        .badge {
            font-size: 0.85em;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }
        .gender-badge {
            text-transform: capitalize;
        }
        .male-badge {
            background-color: #3498db;
        }
        .female-badge {
            background-color: #e83e8c;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            border-radius: 0.35rem;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'?>
<div class="d-flex">
    <?php include 'sidebar.php'; ?>
    <div class="main-content" id="dashboardContent">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Teachers Dashboard Overview</h1>
            </div>

            <div class="row">
                <!-- Card 1: Male Students in Teacher's Class -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2 rounded-3">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Male Students in My Class
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $total_male_students_in_class = 0;
                                        if ($teacherClassId) {
                                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_male_students FROM students WHERE gender = 'Male' AND class_id = ?");
                                            $stmt->bind_param("i", $teacherClassId);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $row = $result->fetch_assoc();
                                            $total_male_students_in_class = $row['total_male_students'];
                                            $stmt->close();
                                        }
                                        echo $total_male_students_in_class;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-male fa-2x text-primary"></i> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Female Students in Teacher's Class -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2 rounded-3">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Female Students in My Class
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $total_female_students_in_class = 0;
                                        if ($teacherClassId) {
                                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_female_students FROM students WHERE gender = 'Female' AND class_id = ?");
                                            $stmt->bind_param("i", $teacherClassId);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $row = $result->fetch_assoc();
                                            $total_female_students_in_class = $row['total_female_students'];
                                            $stmt->close();
                                        }
                                        echo $total_female_students_in_class;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-female fa-2x text-success"></i> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Students (Male + Female) in Teacher's Class -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2 rounded-3">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Students in My Class
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $total_students_in_class = 0;
                                        if ($teacherClassId) {
                                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_students FROM students WHERE class_id = ?");
                                            $stmt->bind_param("i", $teacherClassId);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $row = $result->fetch_assoc();
                                            $total_students_in_class = $row['total_students'];
                                            $stmt->close();
                                        }
                                        echo $total_students_in_class;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-warning"></i> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Total Subjects in Teacher's Class -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2 rounded-3">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Total Subjects in My Class
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $total_subjects_in_class = 0;
                                        if ($teacherClassId) {
                                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_subjects FROM subjects WHERE class_id = ?");
                                            $stmt->bind_param("i", $teacherClassId);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $row = $result->fetch_assoc();
                                            $total_subjects_in_class = $row['total_subjects'];
                                            $stmt->close();
                                        }
                                        echo $total_subjects_in_class;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-book fa-2x text-danger"></i> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Number of student for each class (Primary and Secondary)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="studentCountChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            // PHP logic to fetch student count per class (Primary and Secondary)
            $student_count_per_class = [];
            $sql_student_count = "SELECT c.class_name, c.level, COUNT(s.id) AS student_count
                                    FROM classes c
                                    LEFT JOIN students s ON c.id = s.class_id
                                    GROUP BY c.class_name, c.level
                                    ORDER BY c.level, c.class_name";
            $result_student_count = mysqli_query($conn, $sql_student_count);

            if ($result_student_count && mysqli_num_rows($result_student_count) > 0) {
                while ($row_student_count = mysqli_fetch_assoc($result_student_count)) {
                    $student_count_per_class[] = [
                        'class_name' => $row_student_count['class_name'] . ' (' . $row_student_count['level'] . ')',
                        'student_count' => (int)$row_student_count['student_count']
                    ];
                }
            }
            ?>

            var studentCountData = <?php echo json_encode($student_count_per_class); ?>;

            // Function to create a Chart.js instance for student count
            function createStudentCountChart(chartId, data, titleText) {
                var classLabels = data.map(item => item.class_name);
                var studentCounts = data.map(item => item.student_count);
                
                var ctx = document.getElementById(chartId).getContext('2d');
                new Chart(ctx, {
                    type: 'bar', // A bar chart is suitable for this data
                    data: {
                        labels: classLabels,
                        datasets: [{
                            label: 'Number of students',
                            data: studentCounts,
                            backgroundColor: 'rgba(52, 152, 219, 0.7)', // Primary blue color
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of students'
                                },
                                ticks: {
                                    callback: function(value) {
                                        if (Number.isInteger(value)) {
                                            return value;
                                        }
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Class(level)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: titleText
                            }
                        }
                    }
                });
            }

            // Create chart for Student Count per Class
            createStudentCountChart('studentCountChart', studentCountData, 'The number of student for each class');
        });
    </script>

</body>
</html>