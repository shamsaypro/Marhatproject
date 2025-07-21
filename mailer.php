<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


require 'vendor/autoload.php';
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->SMTPAuth = true;
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Username = "shamisnassor230@gmail.com";

    $mail->Password = "jkaj nvsb hgyl ldbd";

    $mail->isHTML(true);
    return $mail;

} catch (Exception $e) {
    error_log("PHPMailer configuration error: " . $e->getMessage());
    return null; 
}

?>