<?php
require_once 'db_connect.php'; // adjust the path as needed

if (isset($_POST['po_num'], $_POST['status'])) {
    $po_num = $_POST['po_num'];
    $status = $_POST['status'];

    // Only allow valid status values
    $allowed_statuses = ['approved', 'declined', 'pending'];
    if (!in_array(strtolower($status), $allowed_statuses)) {
        die("Invalid status value.");
    }

    // Use prepared statement with MySQLi
    $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE po_number = ?");
    $stmt->bind_param("ss", $status, $po_num);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "success";
    } else if ($stmt->affected_rows === 0) {
        echo "nochange"; // no update was made (status was probably the same)
    } else {
        echo "error";
    }
    

    $stmt->close();
    $conn->close();
    exit;
} else {
    die("Missing required data.");
}
