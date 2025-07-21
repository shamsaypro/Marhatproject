<?php
session_start();
include 'connection/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_email1'])) {
    $parentIds = $_POST['parent_ids'] ?? [];

    if (empty($parentIds)) {
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'parent'");
        while ($row = $stmt->fetch_assoc()) {
            $parentIds[] = $row['id'];
        }
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errorMessages = [];
    foreach ($parentIds as $parentId) {
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $parentId);
        $stmt->execute();
        $parent = $stmt->get_result()->fetch_assoc();

        if (!$parent) continue;
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'shamisnassor230@gmail.com';
            $mail->Password   = 'aere jfbi jtoe uomm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('norereply@gmail.com', 'Online Result Management System');
            $mail->addAddress($parent['email'], $parent['first_name'] . ' ' . $parent['last_name']);

            // NO ATTACHMENTS - Removed: $mail->addAttachment($filePath);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Matokeo ya wanafunzi';
            $mail->Body    = "Habari {$parent['first_name']} {$parent['last_name']},<br><br>
                                Uongozi wa shule unakutaarifu ndugu mzazi/mlezi kuwa matokeo yamekwisha tolewa tayari,
                                tafadhali tembelea mfumo wetu kwa ajili ya kuona matokeo ya mtoto/watoto wako.<br><br>
                                Kwa maelezo zaidi, tafadhali wasiliana na shule.<br><br>
                                Kwa heshima,<br>
                                Online Result Management System";

            $mail->send();
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
            $errorMessages[] = "Failed to send to {$parent['email']}: " . $mail->ErrorInfo;
        }
    }

    // Set session message
    $_SESSION['message'] = "Message is sent successfully to {$successCount} parent(s). ";
    if ($errorCount > 0) {
        $_SESSION['message'] .= "Imeshindwa kwa {$errorCount} wazazi.";
        $_SESSION['error_details'] = $errorMessages;
    }
    $_SESSION['message_type'] = ($errorCount == 0) ? 'success' : 'warning';

    // NO FILE DELETION - Removed: unlink($filePath);

    header("Location: res_note.php");
    exit();
}
?>