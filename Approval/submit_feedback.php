<?php
header('Content-Type: application/json');

require 'db_connect.php';

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Sanitize input
$employee_name = $conn->real_escape_string($_POST['employee_name']);
$project_name = $conn->real_escape_string($_POST['project_name']);
$concern = $conn->real_escape_string($_POST['concern']);

// Handle image upload
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $imageName = basename($_FILES["image"]["name"]);
    $image_path = $targetDir . time() . "_" . $imageName;
    $imageFileType = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($imageFileType, $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']);
        exit;
    }

    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $image_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Image upload failed.']);
        exit;
    }
}

// Insert into database
$sql = "INSERT INTO feedback (employee_name, project_name, concern, image_path) 
        VALUES ('$employee_name', '$project_name', '$concern', '$image_path')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>
