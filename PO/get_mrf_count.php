<?php
include 'db_connect.php'; // or whatever your DB file is

if (isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);

    $stmt = $conn->prepare("SELECT COUNT(*) AS mrf_count FROM mrf WHERE project_id = ? AND status != 'Acknowledged'");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode(['mrf_count' => $result['mrf_count']]);
}
?>
