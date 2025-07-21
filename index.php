<?php
session_start(); // Anzisha session mwanzoni mwa faili
$error = '';

include 'connection/db.php';

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Andaa hoja ili kuchagua mtumiaji yeyote kwa barua pepe, bila kujali jukumu au hali awali
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Angalia kwanza kama password ni sahihi
        if (password_verify($password, $user['password'])) {
            // Ikiwa password ni sahihi, angalia hali ya akaunti (status)
            if ($user['status'] === 'Block') {
                $error = "Your account is blocked. Please contact admin.";
            } else {
                // Akaunti haijazuiliwa, endelea na login kulingana na jukumu
                $_SESSION['user_data'] = [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'role' => $user['role'] // Ongeza role kwenye session data
                ];
                $_SESSION['role'] = $user['role']; // Weka role kwenye session

                // Elekeza mtumiaji kwenye dashboard sahihi kulingana na jukumu
                if ($user['role'] === 'admin') {
                    header("Location: admin-dashboard.php");
                } elseif ($user['role'] === 'teacher') { 
                    header("Location: teacherdash.php"); //Nimebadilisha hapa
                } elseif ($user['role'] === 'parent') {
                    header("Location: parent-dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Email or password is incorrect. Please try again.";
        }
    } else {
        // Hakuna mtumiaji aliyepatikana na email hiyo
        $error = "Email or password is incorrect. Please try again.";
    }
    $stmt->close();
}

$conn->close(); // Funga connection baada ya matumizi
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login</title>
    <link rel="stylesheet" href="styles/style.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        h2 {
            color: #555;
            margin-bottom: 25px;
        }
        .form-container input[type="email"],
        .form-container input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-container input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .form-container input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        p {
            margin-top: 20px;
            color: #666;
        }
        p a {
            color: #007bff;
            text-decoration: none;
        }
        p a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 style="color:green;font-family: Times New Roman;">Online Result Manage <br>ment System</h1>
        <div id="login-box" class="form-container">
            <h2>Sign in</h2>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <input type="submit" name="submit" value="Log In"/> 
            </form>
            <p>Do you have any account? <a href="forgot_password.php">Forgot password</a></p>
        </div>
    </div>
</body>
</html>