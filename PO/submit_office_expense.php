<?php
session_start();
require_once 'db_connect.php';

// Validate and sanitize form data
$supplier_name = $_POST['supplier_name'];
$tin_number = $_POST['tin_number'];
$invoice_number = $_POST['invoice_number'];
$particulars = $_POST['particulars'];
$amount = $_POST['amount'];

// Insert into the database
$sql = "INSERT INTO office_expenses (supplier_name, tin_number, invoice_number, particulars, amount) 
        VALUES ( ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssssi', $supplier_name, $tin_number, $invoice_number, $particulars, $amount);

if ($stmt->execute()) {
    header('Location: office_expenses_form.php?success=1');
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
