<?php session_start();?>
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

        
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <h1>Reset your password</h1>
        <div id="login-box" class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form action="send_password.php" method="POST">
                <input type="email" name="email" placeholder="Email is required" required />
                <input type="submit" name="submit" value="Reset"/> 
            </form>
            <br>
            <a href="index.php">Back to login</a>
        </div>
    </div>
</body>
</html>