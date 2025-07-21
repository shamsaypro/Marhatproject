<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "reesult_management";

// Establish database connection
$mysqli = new mysqli($servername, $username, $password, $dbname);

// Check for connection errors
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get the token from POST request and hash it
$token = $_POST['token'];
$token_hash = hash("sha256", $token);

// Prepare and execute statement to find user by reset token hash
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If user not found, die with an error
if ($user === NULL) {
    $_SESSION['error'] = "Token not found or invalid.";
    header("Location: reset-password.php?token=" . $token); // Redirect back with token
    exit();
}

// Get the new passwords from POST request
$p1 = $_POST['p1'];
$p2 = $_POST['p2'];

// Validate passwords
if (empty($p1) || empty($p2)) {
    $_SESSION['error'] = "Tafadhali jaza maneno yote mawili ya siri.";
    header("Location: reset-password.php?token=" . $token); // Redirect back with token
    exit();
}

if ($p1 !== $p2) {
    $_SESSION['error'] = "Maneno ya siri hayalingani.";
    header("Location: reset-password.php?token=" . $token); // Redirect back with token
    exit();
}

if (strlen($p1) < 8) {
    $_SESSION['error'] = "Neno la siri lazima liwe na angalau herufi 8.";
    header("Location: reset-password.php?token=" . $token); // Redirect back with token
    exit();
}

// Hash the new password before updating
$new_password_hash = password_hash($p1, PASSWORD_DEFAULT);

// Update the user's password and clear the reset token fields
$sql_update = "UPDATE users SET password = ?, reset_token_hash = NULL, rese_expire_at = NULL WHERE id = ?";
$stmt_update = $mysqli->prepare($sql_update);
$stmt_update->bind_param("si", $new_password_hash, $user['id']);

if ($stmt_update->execute()) {
    $_SESSION['success'] = "The password is changed successfully!, please login";
    header("Location: index.php"); // Redirect to login page after successful password reset
    exit();
} else {
    $_SESSION['error'] = "Kuna tatizo wakati wa kubadilisha neno la siri. Tafadhali jaribu tena.";
    header("Location: reset-password.php?token=" . $token); // Redirect back with token
    exit();
}


?>
