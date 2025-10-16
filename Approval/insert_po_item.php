<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = $_POST['po_num'];
    $item_description = $_POST['item_description'];
    $qty = $_POST['qty'];
    $unit = $_POST['unit'];
    $unit_price = $_POST['unit_price'];
    $total_price = $_POST['total_price'];
    
    // These are now also included
    $supplier_name = $_POST['supplier_name'];
    $address = $_POST['address'];
    $contact_number = $_POST['contact_number'];
    $contact_person = $_POST['contact_person'];
    $ship_project_name = $_POST['ship_project_name'];
    $ship_address = $_POST['ship_address'];
    $ship_contact_number = $_POST['ship_contact_number'];
    $ship_contact_person = $_POST['ship_contact_person'];
    $particulars = $_POST['particulars'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO purchase_orders 
        (po_number, item_description, qty, unit, unit_price, total_price,
        supplier_name, address, contact_number, contact_person,
        ship_project_name, ship_address, ship_contact_number, ship_contact_person,
        particulars, date, po_num)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssissdsssssssssss", $po_number, $item_description, $qty, $unit, $unit_price, $total_price,
        $supplier_name, $address, $contact_number, $contact_person,
        $ship_project_name, $ship_address, $ship_contact_number, $ship_contact_person,
        $particulars, $date, $po_number);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "DB Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
