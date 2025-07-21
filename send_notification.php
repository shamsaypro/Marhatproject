<?php
session_start();
require 'db.php';

// Admin check
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get all parents
$parents = $conn->query("SELECT id, username FROM users WHERE role='parent'");

// Send notification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $parent_id = $_POST['parent_id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    
    // 1. Save to database (works offline)
    $stmt = $conn->prepare("INSERT INTO notifications (parent_id, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $parent_id, $title, $message);
    $stmt->execute();
    
    // 2. Send real-time push notification (if online)
    $tokens = $conn->query("SELECT device_token FROM notification_tokens WHERE user_id=$parent_id");
    
    while ($token = $tokens->fetch_assoc()) {
        // This is a simplified example - you'll need Firebase Cloud Messaging for Android/iOS
        sendPushNotification($token['device_token'], $title, $message);
    }
    
    $success = "Notification sent successfully!";
}

function sendPushNotification($token, $title, $message) {
    // Implement actual FCM/APNS logic here
    // This is just a placeholder
    file_put_contents('notifications.log', "$token|$title|$message\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .notification-card {
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card notification-card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-bell"></i> Send Notification</h4>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Select Parent</label>
                        <select name="parent_id" class="form-select" required>
                            <option value="">-- Select Parent --</option>
                            <?php while ($parent = $parents->fetch_assoc()): ?>
                                <option value="<?= $parent['id'] ?>"><?= $parent['username'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notification Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>