<?php
require_once 'db_connect.php'; // adjust path if needed

$searchTerm = $_GET['q'] ?? '';

if (!empty($searchTerm)) {
    $like = "%" . $searchTerm . "%";
    $stmt = $conn->prepare("SELECT item_description, unit, unit_price, supplier_name, created_at FROM released_summary WHERE item_description LIKE ?");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    echo json_encode($results);
} else {
    echo json_encode([]);
}
?>
