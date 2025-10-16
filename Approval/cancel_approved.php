<?php
include 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error']);
    exit;
}

$id = $_POST['id'] ?? null;
$table = $_POST['table'] ?? null;

if (!$id || !$table) {
    echo json_encode(['status' => 'error']);
    exit;
}

$conn->begin_transaction();

try {
    if ($table === 'summary_approved') {
        // Fetch the approved summary record
        $stmt = $conn->prepare("SELECT * FROM summary_approved WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $conn->rollback();
            echo json_encode(['status' => 'error']);
            exit;
        }

        // Insert into purchase_orders (do NOT include `id` column)
        $insert = $conn->prepare("
            INSERT INTO purchase_orders
            (mrf_id, po_number, item_description, qty, unit, unit_price, total_price,
             supplier_name, address, contact_number, contact_person,
             ship_project_name, ship_address, ship_contact_number, ship_contact_person,
             date, particulars, po_num, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $insert->bind_param(
            "issidddsssssssssss",
            $row['mrf_id'],
            $row['po_number'],
            $row['item_description'],
            $row['qty'],
            $row['unit'],
            $row['unit_price'],
            $row['total_price'],
            $row['supplier_name'],
            $row['address'],
            $row['contact_number'],
            $row['contact_person'],
            $row['ship_project_name'],
            $row['ship_address'],
            $row['ship_contact_number'],
            $row['ship_contact_person'],
            $row['date'],
            $row['particulars'],
            $row['po_num']
        );

        $insert->execute();
        $insert->close();

        // Delete original summary_approved row
        $del = $conn->prepare("DELETE FROM summary_approved WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        $conn->commit();
        echo json_encode(['status' => 'success']); // ✅ No message
        exit;
    }

    // Handle other allowed tables
    $allowedTables = [
        'payroll', 'immediate_material', 'reimbursements',
        'misc_expenses', 'office_expenses', 'utilities_expenses', 'sub_contracts'
    ];

    if (in_array($table, $allowedTables)) {
        $update = $conn->prepare("UPDATE `$table` SET status = 'pending' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();
        $update->close();

        $conn->commit();
        echo json_encode(['status' => 'success']);
        exit;
    }

    $conn->rollback();
    echo json_encode(['status' => 'error']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error']);
    exit;
}
?>
