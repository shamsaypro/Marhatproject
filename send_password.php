<?php 
session_start();
$servername = "localhost"; 
$username = "root";       
$password = "";            
$dbname = "reesult_management"; 
$email = $_POST['email'];
$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256",$token);

$exiry=date("Y-m-d H:i:s",time() + 60 * 30);
$mysqli = new mysqli($servername, $username, $password, $dbname); 
$sql = "UPDATE users SET reset_token_hash = ?,rese_expire_at = ? WHERE email = ?";
$stmt=$mysqli->prepare($sql);
$stmt->bind_param("sss",$token_hash,$exiry,$email);
$stmt->execute();
if($mysqli->affected_rows){
    $mail = require "mailer.php";
    $mail->setFrom("noreply@example.com", "Online Result Management System"); // unaweza kuweka jina
    $mail->addAddress($email);
    $mail->Subject = "Reset Password Request";
    $mail->Body = <<<END
    To reset your password in our Online Result Management System please
    Click <a href='localhost/marhat/marhatpro/reset-password.php?token=$token'>here</a> to reset your password.
    END;
    if ($mail->send()) {
            $_SESSION['success'] = "Reset link sent! Check your email.";
        } else {
            $_SESSION['error'] = "Failed to send email. Try again.";
        }


}
header("Location: forgot_password.php"); 
exit();

?>