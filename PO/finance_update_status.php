<?php
require 'db_connect.php'; // your DB connection file

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;
$type = $_POST['type'] ?? null;

$allowedTypes = [
    'payroll' => 'payroll',
    'immediate_material' => 'immediate_material',
    'reimbursements' => 'reimbursements',
    'misc_expenses' => 'misc_expenses',
    'office_expenses' => 'office_expenses',
    'utilities_expenses' => 'utilities_expenses',
    'sub_contracts' => 'sub_contracts'
];

if (!$id || !$status || !isset($allowedTypes[$type])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$table = $allowedTypes[$type];
$stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
