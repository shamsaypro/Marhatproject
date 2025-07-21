<?php
// Database connection
include 'connection/db.php';
session_start();

if (!isset($_SESSION['user_data']['id']) || $_SESSION['user_data']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) {
    // Sanitize and fetch form inputs
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $mobile     = trim($_POST['mobile']);
    $gender     = trim($_POST['gender']);
    $password   = $_POST['password'];
    $role       = trim($_POST['role']);

    // Validate inputs
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($mobile)) $errors[] = "Mobile number is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($role)) $errors[] = "Role is required";

    // Check if email already exists
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    if ($check_email->num_rows > 0) {
        $errors[] = "Email already exists";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, gender, phone_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $first_name, $last_name, $email, $gender, $mobile, $hashed_password, $role);

        if ($stmt->execute()) {
            $success = "User registered successfully!";
            // Refresh the page to show new user in table
            header("Refresh:2");
        } else {
            $errors[] = "Error: " . $conn->error;
        }
    }
}

// Handle form submission for editing user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $mobile     = trim($_POST['mobile']);
    $gender     = trim($_POST['gender']);
    $role       = trim($_POST['role']);

    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($mobile)) $errors[] = "Mobile number is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (empty($role)) $errors[] = "Role is required";

    // Check if email already exists for another user
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->bind_param("si", $email, $user_id);
    $check_email->execute();
    $check_email->store_result();

    if ($check_email->num_rows > 0) {
        $errors[] = "Email already exists for another user.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, gender = ?, phone_number = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $email, $gender, $mobile, $role, $user_id);

        if ($stmt->execute()) {
            $success = "User updated successfully!";
            header("Refresh:2");
        } else {
            $errors[] = "Error updating user: " . $conn->error;
        }
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Changed "delete" to "block/unblock" for demonstration, assuming 'status' column exists
    // You might want to adjust this logic based on your 'status' column values (e.g., 'active', 'inactive', 'blocked')
    // For a soft delete, you'd typically update a 'deleted' column or 'status' to 'inactive'
    // For a hard delete, it would be DELETE FROM users WHERE id = ?
    
    // Example: Toggle status between 'Block' and 'Unblock'
    $current_status_query = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $current_status_query->bind_param("i", $user_id);
    $current_status_query->execute();
    $current_status_query->bind_result($current_status);
    $current_status_query->fetch();
    $current_status_query->close();

    $new_status = ($current_status == 'Unblock') ? 'Unblock' : 'Block';

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);

    if ($stmt->execute()) {
        $success = "User status updated to " . $new_status . " successfully!";
        header("Refresh:2"); 
    } else {
        $errors[] = "Error updating user status: " . $conn->error;
    }
}

// Handle filtering
$filter_name = isset($_GET['filter_name']) ? trim($_GET['filter_name']) : '';
$filter_role = isset($_GET['filter_role']) ? trim($_GET['filter_role']) : '';
$filter_gender = isset($_GET['filter_gender']) ? trim($_GET['filter_gender']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';


$users = [];
$query = "SELECT id, first_name, last_name, email, gender, status, phone_number, role FROM users WHERE 1=1"; // Start with a true condition

$params = [];
$types = "";

if (!empty($filter_name)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
    $params[] = '%' . $filter_name . '%';
    $params[] = '%' . $filter_name . '%';
    $params[] = '%' . $filter_name . '%';
    $params[] = '%' . $filter_name . '%';
    $types .= "ssss";
}

if (!empty($filter_role)) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

if (!empty($filter_gender)) {
    $query .= " AND gender = ?";
    $params[] = $filter_gender;
    $types .= "s";
}
if (!empty($filter_status)) {
    $query .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Result Management System</title>
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

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            padding-top: 56px; /* Added for fixed navbar */
        }

        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
            padding: 0;
            width: 280px;
            position: fixed;
            height: calc(100vh - 56px);
            top: 56px;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
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
            margin-top: 0;
            height: calc(100vh - 56px);
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                position: relative;
                height: auto;
                top: 0;
            }

            .main-content {
                margin-left: 0;
                height: auto;
            }
        }

        .table-responsive {
            margin-top: 20px;
        }

        .table {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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
        
        /* Fixed navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'?>
<div class="d-flex">
    <?php include 'sidebar.php'; ?>

    <div class="container-fluid p-3 main-content">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#registerModal">
            <i class="fas fa-user-plus me-2"></i>Add user
        </button>

        <hr>

        <form method="GET" action="" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="filter_name" class="form-control" placeholder="Filter by Name, Email, or Phone" value="<?= htmlspecialchars($filter_name) ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="filter_role">
                        <option value="">Filter by Role</option>
                        <option value="admin" <?= ($filter_role == 'admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="parent" <?= ($filter_role == 'parent') ? 'selected' : '' ?>>Parent</option>
                        <option value="teacher" <?= ($filter_role == 'teacher') ? 'selected' : '' ?>>Teacher</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="filter_gender">
                        <option value="">Filter by Gender</option>
                        <option value="Male" <?= ($filter_gender == 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($filter_gender == 'Female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="filter_status">
                        <option value="">Filter by Status</option>
                        <option value="Block" <?= ($filter_status == 'Block') ? 'selected' : '' ?>>Blocked</option>
                        <option value="Unblock" <?= ($filter_status == 'Unblock') ? 'selected' : '' ?>>Unblocked</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-dark w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>FIRST NAME</th>
                        <th>LAST NAME</th>
                        <th>EMAIL</th>
                        <th>GENDER</th>
                        <th>PHONE NUMBER</th>
                        <th>ROLE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['first_name']) ?></td>
                                <td><?= htmlspecialchars($user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge gender-badge <?= ($user['gender'] == 'Male') ? 'male-badge' : 'female-badge' ?>">
                                        <?= htmlspecialchars($user['gender']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['phone_number']) ?></td>
                                <td><?= htmlspecialchars($user['role']) ?></td>
                                <td>
                                    <span class="badge <?= ($user['status'] == 'Block') ? 'bg-danger' : 'bg-success' ?>">
                                        <?= htmlspecialchars($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info text-white me-2"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            data-user-id="<?= $user['id'] ?>"
                                            data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                                            data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                                            data-email="<?= htmlspecialchars($user['email']) ?>"
                                            data-mobile="<?= htmlspecialchars($user['phone_number']) ?>"
                                            data-gender="<?= htmlspecialchars($user['gender']) ?>"
                                            data-role="<?= htmlspecialchars($user['role']) ?>">
                                        <i class="fas fa-edit"></i> 
                                    </button>
                                    <button type="button" class="btn btn-sm <?= ($user['status'] == 'Block') ? 'btn-success' : 'btn-danger' ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                            data-user-id="<?= $user['id'] ?>"
                                            data-current-status="<?= htmlspecialchars($user['status']) ?>">
                                        <i class="fas fa-user-alt-slash"></i> <?= htmlspecialchars($user['status']) == 'Block' ? 'Unblock' : 'Block' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="registerModalLabel">Register New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" id="lastName" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="tel" name="mobile" class="form-control" maxlength="10" id="mobile" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="">Select a role</option>
                                <option value="admin">Admin</option>
                                <option value="parent">Parent</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" name="gender" id="gender" required>
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" value="12345678" id="password" required>
                            <div class="form-text">Default password</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="register_user" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" id="editFirstName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" id="editLastName" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" id="editEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editMobile" class="form-label">Mobile Number</label>
                            <input type="tel" name="mobile" class="form-control" id="editMobile" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" name="role" id="editRole" required>
                                <option value="">Select a role</option>
                                <option value="admin">Admin</option>
                                <option value="parent">Parent</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="editGender" class="form-label">Gender</label>
                            <select class="form-select" name="gender" id="editGender" required>
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_user" class="btn btn-info text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteUserModalLabel">Manage User Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <p id="deleteModalMessage">Are you sure you want to change the status of this user?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger" id="confirmDeleteButton">Confirm</button>
                </div>
            </form>
        </div>
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

        // JavaScript to populate the Edit User Modal
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var userId = button.getAttribute('data-user-id');
            var firstName = button.getAttribute('data-first-name');
            var lastName = button.getAttribute('data-last-name');
            var email = button.getAttribute('data-email');
            var mobile = button.getAttribute('data-mobile');
            var gender = button.getAttribute('data-gender');
            var role = button.getAttribute('data-role'); // Get role from data attribute

            var modalUserId = editUserModal.querySelector('#editUserId');
            var modalFirstName = editUserModal.querySelector('#editFirstName');
            var modalLastName = editUserModal.querySelector('#editLastName');
            var modalEmail = editUserModal.querySelector('#editEmail');
            var modalMobile = editUserModal.querySelector('#editMobile');
            var modalGender = editUserModal.querySelector('#editGender');
            var modalRole = editUserModal.querySelector('#editRole'); 

            modalUserId.value = userId;
            modalFirstName.value = firstName;
            modalLastName.value = lastName;
            modalEmail.value = email;
            modalMobile.value = mobile;
            modalGender.value = gender;
            modalRole.value = role; // Set the role value
        });

        // JavaScript to populate the Delete/Block/Unblock User Modal
        var deleteUserModal = document.getElementById('deleteUserModal');
        deleteUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var userId = button.getAttribute('data-user-id');
            var currentStatus = button.getAttribute('data-current-status');

            var modalUserId = deleteUserModal.querySelector('#deleteUserId');
            var deleteModalMessage = deleteUserModal.querySelector('#deleteModalMessage');
            var confirmDeleteButton = deleteUserModal.querySelector('#confirmDeleteButton');

            modalUserId.value = userId;
            
            if (currentStatus === 'Block') {
                deleteModalMessage.textContent = 'Are you sure you want to unblock this user?';
                confirmDeleteButton.textContent = 'Unblock';
                confirmDeleteButton.classList.remove('btn-danger');
                confirmDeleteButton.classList.add('btn-success');
                deleteUserModal.querySelector('.modal-header').classList.remove('bg-danger');
                deleteUserModal.querySelector('.modal-header').classList.add('bg-success');
            } else {
                deleteModalMessage.textContent = 'Are you sure you want to block this user?';
                confirmDeleteButton.textContent = 'Block';
                confirmDeleteButton.classList.remove('btn-success');
                confirmDeleteButton.classList.add('btn-danger');
                deleteUserModal.querySelector('.modal-header').classList.remove('bg-success');
                deleteUserModal.querySelector('.modal-header').classList.add('bg-danger');
            }
        });
    });
</script>

</body>
</html>