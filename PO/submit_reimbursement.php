<?php
session_start();


require_once 'db_connect.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $project_id = $_POST['project_id'] ?? '';
    $employee_name = trim($_POST['employee_name'] ?? '');
    $particulars = trim($_POST['particulars'] ?? '');
    $amount = $_POST['amount'] ?? '';

    // Validate required fields
    if (empty($project_id) || empty($employee_name) || empty($particulars) || empty($amount)) {
        header("Location: reimbursement_form.php?error=1");
        exit();
    }

    // Insert into database using prepared statements
    $stmt = $conn->prepare("INSERT INTO reimbursements (project_id, employee_name, particulars, amount) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("isss", $project_id, $employee_name, $particulars, $amount);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: reimbursement_form.php?success=1");
        exit();
    } else {
        $stmt->close();
        $conn->close();
        header("Location: reimbursement_form.php?error=1");
        exit();
    }
} else {
    // Redirect if accessed directly
    header("Location: reimbursement_form.php");
    exit();
}
?>
