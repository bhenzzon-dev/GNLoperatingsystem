<?php
session_start();
require_once 'db_connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'];
    $utility_type = trim($_POST['utility_type']);
    $billing_period = trim($_POST['billing_period']);
    $account_number = trim($_POST['account_number']);
    $amount = $_POST['amount'];

    // Input validation (basic)
    if (empty($project_id) || empty($utility_type) || empty($billing_period) || empty($account_number) || empty($amount)) {
        die("Please fill out all required fields.");
    }

    // Prepare and execute the insert query
    $stmt = $conn->prepare("INSERT INTO utilities_expenses (project_id, utility_type, billing_period, account_number, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssd", $project_id, $utility_type, $billing_period, $account_number, $amount);

    if ($stmt->execute()) {
        // Redirect with success message
        header("Location: utilities_form.php?success=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    // If accessed directly, redirect
    header('Location: utilities_form.php');
    exit();
}
?>
