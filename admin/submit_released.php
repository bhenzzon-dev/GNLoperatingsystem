<?php 
session_start();
require_once 'db_connect.php';

$project_id = $_POST['project_id'];
$release_date = $_POST['release_date'];
$group_number = $_POST['group_number'];
$particulars = $_POST['particulars'];
$amount = $_POST['amount'];


$stmt = $conn->prepare("INSERT into emergency_released (project_id, released_date, group_number, particulars, amount) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("isisi", $project_id, $release_date, $group_number, $particulars, $amount);
$stmt->execute();
$stmt->close();

header("Location: emergency_released_form.php?success=1");
exit();
?>