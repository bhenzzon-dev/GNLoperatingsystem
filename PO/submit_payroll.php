<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $category = $_POST['category'] ?? null;
    $particulars = $_POST['particulars'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $created_at = date('Y-m-d H:i:s');

    if ($project_id && $category && $particulars && $amount) {
        $stmt = $conn->prepare("INSERT INTO payroll (project_id, category, particulars, amount, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issds", $project_id, $category, $particulars, $amount, $created_at);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: payroll_form.php?success=1");
            exit;
        } else {
            $stmt->close();
            $conn->close();
            header("Location: payroll_form.php?error=1");
            exit;
        }
    } else {
        $conn->close();
        header("Location: payroll_form.php?missing=1");
        exit;
    }
} else {
    header("Location: payroll_form.php");
    exit;
}
?>
