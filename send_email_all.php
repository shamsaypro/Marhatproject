<?php
session_start();
include 'connection/db.php'; 
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_email_all'])) {
    // Validate file
    $document = $_FILES['document'];
    $allowedTypes = ['application/pdf', 'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $fileType = mime_content_type($document['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['message'] = "Tafadhali tumia PDF au Word documents tu.";
        $_SESSION['message_type'] = 'danger';
        header("Location: email_form.php");
        exit();
    }

    // Upload document
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($document['name']);
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($document['tmp_name'], $filePath)) {
        $_SESSION['message'] = "Kuna tatizo katika kupakia document.";
        $_SESSION['message_type'] = 'danger';
        header("Location: email_form.php");
        exit();
    }

    // Get selected parent IDs or all if none selected
    $parentIds = $_POST['parent_ids'] ?? [];
    
    if (empty($parentIds)) {
        // If none selected, get all parents
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'parent'");
        while ($row = $stmt->fetch_assoc()) {
            $parentIds[] = $row['id'];
        }
    }

    // Initialize counters
    $successCount = 0;
    $errorCount = 0;
    $errorMessages = [];

    // Process each parent
    foreach ($parentIds as $parentId) {
        // Get parent details
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $parentId);
        $stmt->execute();
        $parent = $stmt->get_result()->fetch_assoc();

        if (!$parent) continue;

        // Send email
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
            $mail->setFrom('norereply@gmail.com', 'Online Result Manageent System');
            $mail->addAddress($parent['email'], $parent['first_name'] . ' ' . $parent['last_name']);

            // Attachments
            $mail->addAttachment($filePath);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Kikao cha mkutano wa shule';
            $mail->Body    = "Habari {$parent['first_name']} {$parent['last_name']},<br><br>
                            Tafadhali angalia attachment kwa kupata taarifa kuusu kikao cha shule.<br><br>
                            Kwa maelezo zaidi, tafadhali wasiliana na shule.<br><br>
                            Kwa heshima,<br>
                            Online Result Manageent System";

            $mail->send();
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
            $errorMessages[] = "Failed to send to {$parent['email']}: " . $mail->ErrorInfo;
        }
    }

    // Set session message
    $_SESSION['message'] = "Document is sent successfully to {$successCount} parent(s). ";
    if ($errorCount > 0) {
        $_SESSION['message'] .= "Imeshindwa kwa {$errorCount} wazazi.";
        $_SESSION['error_details'] = $errorMessages;
    }
    $_SESSION['message_type'] = ($errorCount == 0) ? 'success' : 'warning';

    // Clean up - delete the uploaded file after sending
    unlink($filePath);

    header("Location: email_form.php");
    exit();
}
?>