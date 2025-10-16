<?php
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

if (!isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'];
$groupData = [];

$tables = [
    'payroll',
    'immediate_material',
    'reimbursements',
    'misc_expenses',
    'office_expenses',
    'utilities_expenses',
    'sub_contracts',
    'released_summary',
    'emergency_released'
];

foreach ($tables as $table) {
    $sql = "SELECT DISTINCT group_number 
            FROM `$table` 
            WHERE DATE(released_date) = ? 
              AND group_number IS NOT NULL
            ORDER BY group_number ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $groupData[] = (int)$row['group_number'];
    }
    $stmt->close();
}

// Remove duplicates & sort
$groupData = array_unique($groupData);
sort($groupData);

echo json_encode($groupData);
