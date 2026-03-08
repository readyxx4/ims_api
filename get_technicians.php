<?php
require_once "db.php";

try {
  $stmt = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='technician'
    ORDER BY name
  ");

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ok(data: $rows, message: "technicians");

} catch (Exception $e) {
  fail(message: $e->getMessage());
}