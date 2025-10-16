<?php
require 'db_connection.php'; // or your DB config

if (isset($_POST['id'])) {
  $id = intval($_POST['id']);
  $stmt = $pdo->prepare("SELECT category, item_description, qty, unit FROM mrf WHERE id = ?");
  $stmt->execute([$id]);
  $item = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode($item);
}
?>
