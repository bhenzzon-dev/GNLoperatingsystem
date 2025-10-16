<?php
session_start();

require_once 'db_connect.php';

// Get the form data
$project_id = $_POST['project_id'];
$supplier_name = $_POST['supplier_name'];
$tin_number = $_POST['tin_number'];
$invoice_number = $_POST['invoice_number'];
$particulars = $_POST['particulars'];
$amount = $_POST['amount'];

// Insert into the database
$stmt = $conn->prepare("INSERT INTO misc_expenses (project_id, supplier_name, tin_number, invoice_number, particulars, amount, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("isssss", $project_id, $supplier_name, $tin_number, $invoice_number, $particulars, $amount);
$stmt->execute();
$stmt->close();

// Redirect with success message
header("Location: misc_expenses_form.php?success=1");
exit();
?>
