<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "reesult_management";

$mysqli = new mysqli($servername, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$token = $_GET['token'] ?? '';
$token_hash = hash("sha256", $token);
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);

$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user === NULL) {
    $_SESSION['error'] = "Token haijapatikana au imekwisha muda. Tafadhali omba kuweka upya neno la siri tena.";
    header("Location: forgot-password.php"); 
    exit();
}


$stmt->close();
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reset password</title>
    <link rel="stylesheet" href="styles/style.css" />
</head>

<body>
    <div class="container">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="success-message"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <!-- Display error messages from session -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <h1>Change password</h1>
        <div id="login-box" class="form-container">
            <?php // The $error variable is not used here as errors are handled via $_SESSION ?>
            <form action="process.php" method="POST">
                <!-- IMPORTANT: This hidden input field ensures the token is passed to process.php -->
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <label for="p1">Ceate new password:</label>
                <input type="password" id="p1" name="p1" placeholder="New password" required /><br>

                <label for="p2">Confirm the password:</label>
                <input type="password" id="p2" name="p2" placeholder="Confirm password" required /><br>

                <input type="submit" name="submit" value="Change password"/>
            </form>
        </div>
    </div>
</body>
</html>
