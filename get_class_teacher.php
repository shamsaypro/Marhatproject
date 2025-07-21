<?php
// get_class_teacher.php
include 'connection/db.php';

if (isset($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
    
    $query = "SELECT c.teacher_id, u.first_name, u.last_name 
              FROM classes c 
              LEFT JOIN users u ON c.teacher_id = u.id 
              WHERE c.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'teacher_id' => $row['teacher_id'],
            'teacher_name' => $row['first_name'] . ' ' . $row['last_name']
        ]);
    } else {
        echo json_encode(['teacher_id' => '', 'teacher_name' => '']);
    }

    $stmt->close();
    $conn->close();
}
?>
