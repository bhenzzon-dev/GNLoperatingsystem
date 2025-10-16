<?php
date_default_timezone_set('Asia/Manila'); // Set timezone to Philippines

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'];
    $item_description = $_POST['item_description'];
    $qty = $_POST['qty'];
    $unit = $_POST['unit'];
    $comment = $_POST['comment'];
    $category = $_POST['category'];

    // Generate a unique mrf_id
    $mrf_id = uniqid('mrf_', true);

    // Optional: get current datetime in PH time
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO mrf (project_id, item_description, qty, unit, comment, category, mrf_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $numItems = count($item_description);
    for ($i = 0; $i < $numItems; $i++) {
        $stmt->bind_param(
            "isssssss",
            $project_id,
            $item_description[$i],
            $qty[$i],
            $unit[$i],
            $comment[$i],
            $category[$i],
            $mrf_id,
            $created_at // insert PH datetime
        );

        if (!$stmt->execute()) {
            echo "error";
            $stmt->close();
            $conn->close();
            exit();
        }
    }

    echo "success";

    $stmt->close();
    $conn->close();
}
?>
