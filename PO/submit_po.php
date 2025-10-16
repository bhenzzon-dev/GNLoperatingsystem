<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Shared fields
    $supplier_name = $conn->real_escape_string($_POST['supplier_name'] ?? '');
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    $contact_number = $conn->real_escape_string($_POST['contact_number'] ?? '');
    $contact_person = $conn->real_escape_string($_POST['contact_person'] ?? '');

    // Multiple entry fields (arrays)
    $mrf_ids = $_POST['id'] ?? []; // these are the per-row MRF item IDs
    $categories = $_POST['category'] ?? [];
    $descriptions = $_POST['item_description'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $units = $_POST['unit'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    // Check if data is consistent
    $totalItems = count($categories);
    if (
        $totalItems !== count($mrf_ids) ||
        $totalItems !== count($descriptions) ||
        $totalItems !== count($qtys) ||
        $totalItems !== count($units) ||
        $totalItems !== count($unit_prices)
    ) {
        echo "Form data mismatch. Please try again.";
        exit;
    }

    // Prepare insert statement
    $query = "INSERT INTO temp_purchase_orders 
        (mrf_id, category, item_description, qty, unit, unit_price, price, supplier_name, address, contact_number, contact_person, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    if ($stmt = $conn->prepare($query)) {
        for ($i = 0; $i < $totalItems; $i++) {
            $mrf_id = (int)$mrf_ids[$i]; // Use individual id as mrf_id

            // Clean and calculate values
            $category = $conn->real_escape_string($categories[$i]);
            $description = $conn->real_escape_string($descriptions[$i]);
            $qty = floatval($qtys[$i]);
            $unit = $conn->real_escape_string($units[$i]);
            $unit_price = floatval($unit_prices[$i]);
            $price = $qty * $unit_price;

            // Bind and insert
            $stmt->bind_param(
                "issdsddssss",
                $mrf_id,
                $category,
                $description,
                $qty,
                $unit,
                $unit_price,
                $price,
                $supplier_name,
                $address,
                $contact_number,
                $contact_person
            );

            if (!$stmt->execute()) {
                echo "Error inserting row $i: " . $stmt->error;
                exit;
            }
        }

        $stmt->close();

        // ✅ Update status in mrf table
        if (!empty($mrf_ids)) {
            $placeholders = implode(',', array_fill(0, count($mrf_ids), '?'));
            $types = str_repeat('i', count($mrf_ids));

            $update_query = "UPDATE mrf SET status = 'Processing' WHERE id IN ($placeholders)";
            $update_stmt = $conn->prepare($update_query);

            if ($update_stmt) {
                $update_stmt->bind_param($types, ...$mrf_ids);
                if (!$update_stmt->execute()) {
                    echo "Error updating status: " . $update_stmt->error;
                    exit;
                }
                $update_stmt->close();
            } else {
                echo "Error preparing update statement: " . $conn->error;
                exit;
            }
        }

        // Get group mrf_id and ids for redirection
        $group_mrf_id = $_POST['mrf_id'] ?? '';
        $encoded_ids = urlencode(implode(',', $mrf_ids));

        // Redirect back to specific grouping page
        header("Location: create_po_form.php?mrf_id=$group_mrf_id&ids=$encoded_ids&success=true");
        exit;

    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

$conn->close();
?>
