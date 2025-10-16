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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $item = $_POST['item_description'];
    $qty = $_POST['qty'];

    // Fetch current status to preserve it if not provided
    $result = $conn->prepare("SELECT status FROM mrf WHERE id=?");
    $result->bind_param("i", $id);
    $result->execute();
    $res = $result->get_result();
    $current = $res->fetch_assoc();
    $status = $current['status'];

    // If the action is cancel, change status to Cancelled
    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $status = 'Cancelled';
    }

    $stmt = $conn->prepare("UPDATE mrf SET item_description=?, qty=?, status=? WHERE id=?");
    $stmt->bind_param("sisi", $item, $qty, $status, $id);
    $stmt->execute();

    echo "OK";
}

?>
