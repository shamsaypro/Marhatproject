<?php

include 'connection/db.php';

ob_start(); // Ikiwa unahitaji output buffering

if (session_status() == PHP_SESSION_NONE) {

    session_start();
if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
}?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - Result Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet">

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
            /* Add padding to the top of the body to prevent content from being hidden by the fixed navbar */
            padding-top: 56px; /* Approximate height of Bootstrap navbar */

        }
        /* Fixed Navbar Styles - Assuming navbar.php generates a <nav> tag or similar */
        .main-navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030; /* Higher z-index than sidebar to be on top */
            background-color: #fff; /* Ensure navbar has a background */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Optional: add shadow */
        }

        /* Sidebar Styles */

        .admin-sidebar {

            background-color: var(--sidebar-bg);
            height: 100%; /* Make sidebar take full height of its parent */
            color: var(--text-color);

            padding: 0;

            width: 280px;

            position: fixed;
            top: 56px; /* Push sidebar down by navbar height */
            bottom: 0; /* Make sidebar extend to the bottom of the viewport */
            overflow-y: auto; /* Enable scrolling for sidebar content if it overflows */

            transition: all 0.3s;

            z-index: 1020; /* Lower z-index than navbar */

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
        .dropdown-menu {

            background-color: var(--sidebar-hover);

            border: none;

            border-radius: 0;

            padding: 0;

        }
        .dropdown-item {

            color: var(--text-color);

            padding: 10px 20px 10px 50px;

            border-left: 3px solid transparent;

        }
        .dropdown-item:hover, .dropdown-item.active {

            background-color: rgba(255,255,255,0.1);

            border-left: 3px solid var(--sidebar-active);

        }
        /* Main Content Styles */

        .main-content {
            /* Adjusted margin-left to account for fixed sidebar, and margin-top for fixed navbar */
            margin-left: 280px; 
            margin-top: 0px; /* Removed margin-top as body padding now handles it */
            padding: 20px;

            transition: all 0.3s;
            /* Allow main content to scroll independently */
            overflow-y: auto;
            height: calc(100vh - 56px); /* Full viewport height minus navbar height */
            box-sizing: border-box; /* Include padding in the height calculation */

        }
        /* Dashboard Cards */

        .stat-card {

            border-radius: 8px;

            overflow: hidden;

            border-left: 4px solid;

            transition: transform 0.3s;

        }
        .stat-card:hover {

            transform: translateY(-5px);

        }
        .stat-card.primary {

            border-left-color: var(--primary-color);

        }
        .stat-card.success {

            border-left-color: var(--success-color);

        }
        .stat-card.warning {

            border-left-color: var(--warning-color);

        }
        .stat-card.danger {

            border-left-color: var(--danger-color);

        }
        /* Activity Items */

        .activity-item {

            padding-left: 10px;

            border-left: 3px solid #eee;

            transition: all 0.3s;

            margin-bottom: 15px;

        }
        .activity-item:hover {

            border-left: 3px solid var(--primary-color);

            background-color: #f8f9fa;

        }
        /* Responsive Adjustments */

        @media (max-width: 768px) {

            .admin-sidebar {

                width: 100%;

                height: auto;

                position: relative;
                top: auto; /* Reset top for mobile */
                bottom: auto; /* Reset bottom for mobile */
                overflow-y: visible; /* Reset overflow for mobile */

            }
            .main-content {

                margin-left: 0;
                height: auto; /* Reset height for mobile */
                
            }
            body {
                padding-top: 0; /* Remove body padding on small screens if navbar becomes relative */
            }
            .main-navbar {
                position: relative; /* Make navbar relative on small screens */
            }

        }

        /* Chart specific styles to make it larger */
        .chart-container {
            position: relative;
            height: 600px; /* Increased height for larger charts */
            width: 100%;
            margin-top: 20px;
        }

    </style>

</head>

<body>

    <div class="main-navbar">
        <?php include 'navbar.php'?>
    </div>
    
    <div class="d-flex">
        <?php include 'sidebar.php'?>


        <div class="main-content" id="dashboardContent">

            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-4">

                    <h1 class="h3 mb-0 text-gray-800">Dashboard Overview</h1>

                </div>

    

                <div class="row">

                    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 rounded-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Students Registered
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_students FROM students");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            echo $row['total_students'];
                            $stmt->close();
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-primary"></i> </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 rounded-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Parent Registered
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_parents FROM users WHERE role = ?");
                            $role = 'parent';
                            $stmt->bind_param("s", $role);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            echo $row['total_parents'];
                            $stmt->close();
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-success"></i> </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 rounded-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Teachers Registered
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_teachers FROM users WHERE role = ?");
                            $role = 'teacher';
                            $stmt->bind_param("s", $role);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            echo $row['total_teachers'];
                            $stmt->close();
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chalkboard-teacher fa-2x text-warning"></i> </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2 rounded-3">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Class Registered
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $stmt = $conn->prepare("SELECT COUNT(*) AS total_classes FROM classes");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            echo $row['total_classes'];
                            $stmt->close();
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-school fa-2x text-danger"></i> </div>
                </div>
            </div>
        </div>
    </div>
</div>

                </div>

                <!-- Chart Row for Student Count per Class -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Number of student for each class(Primary na Secondary)</h6>
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

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
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
