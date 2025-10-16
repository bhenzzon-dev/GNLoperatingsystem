<?php
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$releaseDates = [];

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
    $sql = "SELECT DISTINCT DATE(released_date) AS date FROM `$table` WHERE released_date IS NOT NULL";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $releaseDates[] = trim($row['date']);
        }
    }
}

$releaseDates = array_unique($releaseDates);
sort($releaseDates);
echo json_encode(array_values($releaseDates));
?>
