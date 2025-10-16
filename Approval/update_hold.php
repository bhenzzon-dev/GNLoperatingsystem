<?php
require_once 'db_connect.php';

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? null;
$po_number = $_POST['po_number'] ?? null;

// List of allowed tables to prevent SQL injection
$allowedTables = [
    'summary_approved',
    'payroll',
    'immediate_material',
    'reimbursements',
    'misc_expenses',
    'office_expenses',
    'utilities_expenses',
    'sub_contracts'
];

if (!in_array($table, $allowedTables)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid table specified']);
    exit;
}

if ($table === 'summary_approved' && $po_number) {
    $stmt = $conn->prepare("UPDATE $table SET status = 'Hold' WHERE po_number = ?");
    $stmt->bind_param('s', $po_number);
} elseif ($id) {
    $stmt = $conn->prepare("UPDATE $table SET status = 'Hold' WHERE id = ?");
    $stmt->bind_param('i', $id);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Entry has been put on hold.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update entry.']);
}

$stmt->close();
$conn->close();
