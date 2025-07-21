<?php
// Database configuration - Connect to your result_management database
include 'connection/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Process form submission
if (isset($_POST['upload'])) {
    // Validate required fields
    $required = ['class', 'term', 'exam_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode([
                'status' => 'error',
                'message' => "All fields are required. Missing: $field"
            ]);
            exit;
        }
    }

    $class = trim($_POST['class']);
    $term = trim($_POST['term']);
    $exam_id = trim($_POST['exam_id']);
    
    // File upload handling
    if (!isset($_FILES['result_file'])) {
        echo json_encode([
            'status' => 'error',
            'message' => "No file uploaded"
        ]);
        exit;
    }

    $file = $_FILES['result_file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Allow only CSV files
    if ($fileExt != 'csv') {
        echo json_encode([
            'status' => 'error',
            'message' => "Invalid file type. Only CSV files allowed"
        ]);
        exit;
    }
    
    // Validate file size (5MB max)
    if ($fileSize > 5000000) {
        echo json_encode([
            'status' => 'error',
            'message' => "File is too large (max 5MB)"
        ]);
        exit;
    }
    
    // Validate no upload errors
    if ($fileError !== 0) {
        echo json_encode([
            'status' => 'error',
            'message' => "Error uploading file (Code: $fileError)"
        ]);
        exit;
    }
    
    // Process CSV file
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    if (($handle = fopen($fileTmpName, 'r')) === FALSE) {
        echo json_encode([
            'status' => 'error',
            'message' => "Could not open CSV file"
        ]);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Validate minimum columns (5 columns: student_id, subject_id, marks, grade, remarks)
            if (count($data) < 5) {
                $errors[] = "Invalid data format in row (requires 5 columns): " . implode(",", $data);
                $errorCount++;
                continue;
            }
            
            // Clean and validate data
            $student_id = $conn->real_escape_string(trim($data[0]));
            $subject_id = $conn->real_escape_string(trim($data[1]));
            $marks = (float)$data[2];
            $grade = $conn->real_escape_string(trim($data[3]));
            $remarks = $conn->real_escape_string(trim($data[4]));
            
            // Validate required fields
            if (empty($student_id) || empty($subject_id) || !is_numeric($marks)) {
                $errors[] = "Invalid data in row: " . implode(",", $data);
                $errorCount++;
                continue;
            }
            
            // Check if result exists in result_management.upload table
            $check_sql = "SELECT id FROM result_management.upload 
                         WHERE student_id = '$student_id' 
                         AND subject_id = '$subject_id' 
                         AND exam_id = '$exam_id'";
            $result = $conn->query($check_sql);
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Result already exists for Student $student_id, Subject $subject_id in Exam $exam_id";
                $errorCount++;
                continue;
            }
            
            // Insert into result_management.upload table
            $sql = "INSERT INTO result_management.upload 
                   (student_id, subject_id, exam_id, marks, grade, remarks, class, term) 
                   VALUES 
                   ('$student_id', '$subject_id', '$exam_id', $marks, '$grade', '$remarks', '$class', '$term')";
            
            if ($conn->query($sql)) {
                $successCount++;
            } else {
                $errors[] = "Error inserting record: " . $conn->error;
                $errorCount++;
            }
        }
        
        $conn->commit();
        fclose($handle);
        
        echo json_encode([
            'status' => 'success',
            'message' => "Results uploaded successfully to result_management database! $successCount records added.",
            'errors' => $errors,
            'total_rows' => ($successCount + $errorCount),
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        if (is_resource($handle)) {
            fclose($handle);
        }
        echo json_encode([
            'status' => 'error',
            'message' => "Database error: " . $e->getMessage(),
            'error_details' => $conn->error
        ]);
    }
    
    exit;
}

// If not a POST request
echo json_encode([
    'status' => 'error',
    'message' => "Invalid request method. Use POST."
]);

$conn->close();
?>