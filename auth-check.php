<?php
// auth_check.php
session_start();

function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit();
    }

    // Set role globally kwa matumizi mengine
    $_SESSION['role'] = $_SESSION['user']['role'];

    // Kama role inahitajika (mfano parent, teacher, admin)
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        switch ($_SESSION['role']) {
            case 'admin':
                header("Location: admin-dashboard.php");
                break;
            case 'teacher':
                header("Location: teacher_dashboard.php");
                break;
            case 'parent':
                header("Location: parent-dashboard.php");
                break;
            default:
                header("Location: login.php");
        }
        exit();
    }
}
?>
