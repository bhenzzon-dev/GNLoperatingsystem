<?php
session_start();
require_once "db_connect.php";

if (!isset($_SESSION["architect_loggedin"]) || $_SESSION["architect_loggedin"] !== true) {
    http_response_code(403);
    exit("Unauthorized");
}

// Fetch record details (for modal)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM mrf WHERE id = $id");
    $data = $result->fetch_assoc();
    echo json_encode($data);
    exit;
}

// Update record (AJAX form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $item_description = $_POST['item_description'];
    $qty = intval($_POST['qty']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE mrf SET item_description = ?, qty = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sisi", $item_description, $qty, $status, $id);
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}
?>
