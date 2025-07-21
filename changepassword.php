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

        <!-- FLASH ERROR -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <h1>Change password</h1>
        <div id="login-box" class="form-container">
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form action="process.php" method="POST">
                <input type="password" name="p1" placeholder="Create new password" required /><br>
                <input type="password" name="p2" placeholder="Confirm password" required /><br>
                <input type="submit" name="submit" value="Send email"/> 
            </form>
        </div>
    </div>
</body>
</html>