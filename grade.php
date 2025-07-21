<?php
session_start(); 
include 'connection/db.php';
if (!isset($_SESSION['user_data']['id']) || !in_array($_SESSION['user_data']['role'], ['teacher', 'admin'])) {
    header("Location: index.php"); 
    exit(); 
}

// Get ID and other information of the logged-in user
$current_user_id = $_SESSION['user_data']['id'];
$user_role = $_SESSION['user_data']['role'];
$user_first_name = $_SESSION['user_data']['first_name'];
$user_last_name = $_SESSION['user_data']['last_name'];

// Initialize variables for messages
$errors = [];
$success = '';

// Handle form submissions for grade system
if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    if (isset($_POST['add_grade'])) {
        // Add new grade system
        $class_id = filter_var($_POST['class_id'], FILTER_VALIDATE_INT);
        $grade_name = trim($_POST['grade_name']);
        $min_marks = filter_var($_POST['min_marks'], FILTER_VALIDATE_INT);
        $max_marks = filter_var($_POST['max_marks'], FILTER_VALIDATE_INT);
        
        // Validation
        if (!$class_id || empty($grade_name) || $min_marks === false || $max_marks === false) {
            $errors[] = "All fields are required and must be valid integers for marks.";
        } elseif ($min_marks > $max_marks) {
            $errors[] = "Minimum marks cannot be greater than maximum marks.";
        } elseif ($min_marks < 0 || $max_marks > 100) {
            $errors[] = "Marks must be between 0 and 100.";
        } else {
            // Check if grade name already exists for this class
            $stmt = $conn->prepare("SELECT id FROM grade_systems WHERE class_id = ? AND grade_name = ?");
            $stmt->bind_param("is", $class_id, $grade_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This grade name already exists for the selected class.";
            } else {
                // Insert new grade system
                $insert_stmt = $conn->prepare("INSERT INTO grade_systems (class_id, grade_name, min_marks, max_marks) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("isii", $class_id, $grade_name, $min_marks, $max_marks);
                
                if ($insert_stmt->execute()) {
                    $success = "Grade system added successfully!";
                } else {
                    $errors[] = "Failed to add grade system: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_grade'])) {
        // Update existing grade system
        $grade_id = filter_var($_POST['grade_id'], FILTER_VALIDATE_INT);
        $min_marks = filter_var($_POST['min_marks'], FILTER_VALIDATE_INT);
        $max_marks = filter_var($_POST['max_marks'], FILTER_VALIDATE_INT);
        
        // Validation
        if (!$grade_id || $min_marks === false || $max_marks === false) {
            $errors[] = "All fields are required and must be valid.";
        } elseif ($min_marks > $max_marks) {
            $errors[] = "Minimum marks cannot be greater than maximum marks.";
        } elseif ($min_marks < 0 || $max_marks > 100) {
            $errors[] = "Marks must be between 0 and 100.";
        } else {
            // Update grade system
            $update_stmt = $conn->prepare("UPDATE grade_systems SET min_marks = ?, max_marks = ? WHERE id = ?");
            $update_stmt->bind_param("iii", $min_marks, $max_marks, $grade_id);
            
            if ($update_stmt->execute()) {
                $success = "Grade system updated successfully!";
            } else {
                $errors[] = "Failed to update grade system: " . $conn->error;
            }
            $update_stmt->close();
        }
    } elseif (isset($_POST['delete_grade'])) {
        // Delete grade system
        $grade_id = filter_var($_POST['grade_id'], FILTER_VALIDATE_INT);
        
        if ($grade_id) {
            $delete_stmt = $conn->prepare("DELETE FROM grade_systems WHERE id = ?");
            $delete_stmt->bind_param("i", $grade_id);
            
            if ($delete_stmt->execute()) {
                $success = "Grade system deleted successfully!";
            } else {
                $errors[] = "Failed to delete grade system: " . $conn->error;
            }
            $delete_stmt->close();
        } else {
            $errors[] = "Invalid grade system ID.";
        }
    }
}

// Fetch classes based on user role
$classes_data = [];
if ($user_role === 'admin') { // Admin can see all classes
    $query_classes = "SELECT id, class_name, level FROM classes ORDER BY level, class_name";
    $stmt_classes = $conn->prepare($query_classes);
    $stmt_classes->execute();
    $result_classes = $stmt_classes->get_result();
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
    $stmt_classes->close();
} elseif ($user_role === 'teacher') { // Teacher sees only classes they teach
    $query_classes = "
        SELECT DISTINCT c.id, c.class_name, c.level
        FROM classes c
        JOIN subjects s ON c.id = s.class_id
        WHERE s.teacher_id = ?
        ORDER BY c.level, c.class_name
    ";
    $stmt_classes = $conn->prepare($query_classes);
    $stmt_classes->bind_param("i", $current_user_id);
    $stmt_classes->execute();
    $result_classes = $stmt_classes->get_result();
    while ($row = $result_classes->fetch_assoc()) {
        $classes_data[] = $row;
    }
    $stmt_classes->close();
}

// Fetch all grade systems for display (relevant to the fetched classes)
$grade_systems = [];
$grade_systems_by_class = [];

if (!empty($classes_data)) {
    $class_ids_for_query = array_column($classes_data, 'id');
    $placeholders = implode(',', array_fill(0, count($class_ids_for_query), '?'));
    
    $query_grade_systems = "
        SELECT gs.*, c.class_name, c.level 
        FROM grade_systems gs
        JOIN classes c ON gs.class_id = c.id
        WHERE gs.class_id IN ($placeholders)
        ORDER BY c.level, c.class_name, gs.min_marks DESC
    ";
    
    $stmt_grade_systems = $conn->prepare($query_grade_systems);
    // Dynamically bind parameters based on the number of class IDs
    $types = str_repeat('i', count($class_ids_for_query));
    $stmt_grade_systems->bind_param($types, ...$class_ids_for_query);
    $stmt_grade_systems->execute();
    $result_grade_systems = $stmt_grade_systems->get_result();
    
    while ($row = $result_grade_systems->fetch_assoc()) {
        // Group grade systems by class for card display
        $class_id_key = $row['class_id'];
        if (!isset($grade_systems_by_class[$class_id_key])) {
            $grade_systems_by_class[$class_id_key] = [
                'class_name_full' => htmlspecialchars($row['class_name'] . ' (' . $row['level'] . ')'),
                'grades' => []
            ];
        }
        $grade_systems_by_class[$class_id_key]['grades'][] = $row;
    }
    $stmt_grade_systems->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Grade Systems Management</title>
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

        .navbar {
            position: fixed; /* Added this line for fixed navbar */
            top: 0;
            width: 100%;
            z-index: 1030;
            background-color: #ffffff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed; 
            top: 0; 
            bottom: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            padding-top: 60px; /* Space for the fixed navbar */
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
            margin-left: 280px; /* Space for the fixed sidebar */
            padding: 20px;
            padding-top: 80px; /* Space for the fixed navbar */
            transition: all 0.3s;
            min-height: calc(100vh - 60px); 
            box-sizing: border-box;
            background-color: #f8f9fa; 
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative; 
                top: 0;
                overflow-y: visible;
                padding-top: 0; 
            }

            .main-content {
                margin-left: 0; 
                padding-top: 20px; 
                min-height: auto;
            }

            .navbar {
                position: relative; /* Changed to relative for smaller screens if it was fixed. */
                width: 100%;
                margin-left: 0;
                margin-bottom: 0;
            }
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            font-weight: bold;
        }

        .table-responsive {
            margin-top: 20px;
        }

        .table th {
            background-color: #f8f9fa;
        }

        .badge-class {
            background-color: #4e73df;
            color: white;
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.85em;
        }

        .badge-grade {
            background-color: #1cc88a;
            color: white;
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.85em;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .alert-container {
            position: fixed;
            top: 80px; 
            right: 20px;
            z-index: 1050;
            width: 350px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'?>

<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid p-3 main-content">
        <?php if (!empty($errors)): ?>
            <div class="alert-container">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert-container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center mt-5">
                <h5 class="mb-0">Manage Grade Systems</h5>
                <button type="button" class="btn btn-primary add-grade-btn" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    <i class="fas fa-plus"></i> Add New Grade
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($classes_data) && empty($grade_systems_by_class)): ?>
                    <div class="alert alert-info">
                        <?php if ($user_role === 'teacher'): ?>
                            You are not assigned to teach any classes yet, or no grade systems exist for your classes.
                        <?php else: /* admin */ ?>
                            No classes or grade systems have been registered in the system yet.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php if (empty($grade_systems_by_class)): ?>
                            <div class="col-12">
                                <div class="alert alert-warning">No grade systems have been defined for the available classes yet.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($grade_systems_by_class as $class_id => $class_data): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0">Class: <?php echo $class_data['class_name_full']; ?></h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Grade</th>
                                                            <th>Min Marks</th>
                                                            <th>Max Marks</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($class_data['grades'] as $grade): ?>
                                                            <tr>
                                                                <td><span class="badge badge-grade"><?php echo htmlspecialchars($grade['grade_name']); ?></span></td>
                                                                <td><?php echo htmlspecialchars($grade['min_marks']); ?></td>
                                                                <td><?php echo htmlspecialchars($grade['max_marks']); ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-warning action-btn edit-grade-btn" 
                                                                        data-id="<?php echo $grade['id']; ?>"
                                                                        data-grade-name="<?php echo htmlspecialchars($grade['grade_name']); ?>"
                                                                        data-min-marks="<?php echo $grade['min_marks']; ?>"
                                                                        data-max-marks="<?php echo $grade['max_marks']; ?>">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-danger action-btn delete-grade-btn" 
                                                                        data-id="<?php echo $grade['id']; ?>"
                                                                        data-grade-name="<?php echo htmlspecialchars($grade['grade_name']); ?>">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGradeModalLabel">Add New Grade System</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_id" class="form-label">Class</label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes_data as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['level'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="grade_name" class="form-label">Grade Name</label>
                        <input type="text" class="form-control" id="grade_name" name="grade_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="min_marks" class="form-label">Minimum Marks</label>
                        <input type="number" class="form-control" id="min_marks" name="min_marks" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="max_marks" class="form-label">Maximum Marks</label>
                        <input type="number" class="form-control" id="max_marks" name="max_marks" min="0" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_grade" class="btn btn-primary">Save Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGradeModalLabel">Edit Grade System</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_grade_id" name="grade_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Name</label>
                        <input type="text" class="form-control" id="edit_grade_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_min_marks" class="form-label">Minimum Marks</label>
                        <input type="number" class="form-control" id="edit_min_marks" name="min_marks" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_max_marks" class="form-label">Maximum Marks</label>
                        <input type="number" class="form-control" id="edit_max_marks" name="max_marks" min="0" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_grade" class="btn btn-primary">Update Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteGradeModal" tabindex="-1" aria-labelledby="deleteGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGradeModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="delete_grade_id" name="grade_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the grade system for <strong id="delete_grade_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_grade" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        $('.edit-grade-btn').click(function() {
            const id = $(this).data('id');
            const gradeName = $(this).data('grade-name');
            const minMarks = $(this).data('min-marks');
            const maxMarks = $(this).data('max-marks');
            
            $('#edit_grade_id').val(id);
            $('#edit_grade_name').val(gradeName);
            $('#edit_min_marks').val(minMarks);
            $('#edit_max_marks').val(maxMarks);
            
            $('#editGradeModal').modal('show');
        });
        $('.delete-grade-btn').click(function() {
            const id = $(this).data('id');
            const gradeName = $(this).data('grade-name');
            
            $('#delete_grade_id').val(id);
            $('#delete_grade_name').text(gradeName);
            
            $('#deleteGradeModal').modal('show');
        });
        $('#addGradeModal form').submit(function(e) {
            const minMarks = parseInt($('#min_marks').val());
            const maxMarks = parseInt($('#max_marks').val());
            
            if (isNaN(minMarks) || isNaN(maxMarks)) {
                alert('Please enter valid numbers for Minimum Marks and Maximum Marks.');
                e.preventDefault();
                return false;
            }
            if (minMarks > maxMarks) {
                alert('Minimum marks cannot be greater than maximum marks.');
                e.preventDefault();
                return false;
            }
            
            if (minMarks < 0 || maxMarks > 100) {
                alert('Marks must be between 0 and 100.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Validate min/max marks in edit form
        $('#editGradeModal form').submit(function(e) {
            const minMarks = parseInt($('#edit_min_marks').val());
            const maxMarks = parseInt($('#edit_max_marks').val());

            if (isNaN(minMarks) || isNaN(maxMarks)) {
                alert('Please enter valid numbers for Minimum Marks and Maximum Marks.');
                e.preventDefault();
                return false;
            }
            
            if (minMarks > maxMarks) {
                alert('Minimum marks cannot be greater than maximum marks.');
                e.preventDefault();
                return false;
            }
            
            if (minMarks < 0 || maxMarks > 100) {
                alert('Marks must be between 0 and 100.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    });
</script>
</body>
</html>