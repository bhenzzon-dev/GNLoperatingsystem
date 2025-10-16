<?php
session_start();
require_once 'db_connect.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $project_id = $_POST['project_id'];
    $supplier_name = $_POST['supplier_name'];
    $contact_number = $_POST['contact_number'];
    $tcp = $_POST['tcp'];
    $particular = $_POST['particular'];
    $category = $_POST['category']; // Get the category field from the form

    // Prepare the SQL statement for inserting data into the sub_contracts table
    $stmt = $conn->prepare("INSERT INTO sub_contracts (project_id, supplier_name, contact_number, tcp, particular, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdss", $project_id, $supplier_name, $contact_number, $tcp, $particular, $category);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect with success message
        header('Location: sub_contract_form.php?success=1');
    } else {
        // Handle error
        echo "Error: " . $stmt->error;
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>
