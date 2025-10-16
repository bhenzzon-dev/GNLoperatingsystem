<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "Deleted";
        exit;

    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $description = $_POST['item_description'];
        $qty = (int) $_POST['qty'];
        $unit = $_POST['unit'];
        $unit_price = $_POST['unit_price'];

        $stmt = $conn->prepare("UPDATE purchase_orders SET item_description = ?, qty = ?, unit = ?, unit_price = ? WHERE id = ?");
        $stmt->bind_param("sissd", $description, $qty, $unit, $unit_price, $id);
        $stmt->execute();
        echo "Updated";
        exit;
    }
}
?>
