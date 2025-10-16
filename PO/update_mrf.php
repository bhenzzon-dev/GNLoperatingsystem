<?php
require 'db_connect.php';

$id = $_POST['id'];
$category = $_POST['category'];
$item_description = $_POST['item_description'];
$qty = $_POST['qty'];
$unit = $_POST['unit'];

// Only update category, description, qty, unit
$stmt = $conn->prepare("UPDATE mrf SET category = ?, item_description = ?, qty = ?, unit = ? WHERE id = ?");
$stmt->bind_param("ssisi", $category, $item_description, $qty, $unit, $id);

// Fetch updated data including the original status
$select = $conn->prepare("SELECT status FROM mrf WHERE id = ?");
$select->bind_param("i", $id);
$select->execute();
$selectResult = $select->get_result();
$row = $selectResult->fetch_assoc();

if ($stmt->execute()) {
    // Return updated data to the frontend
    echo json_encode([
        'success' => true,
        'id' => $id,
        'category' => $category,
        'item_description' => $item_description,
        'qty' => $qty,
        'unit' => $unit,
        'status' => $row['status'] // preserve the actual current status
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update item.'
    ]);
}
