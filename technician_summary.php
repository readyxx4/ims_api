<?php
require_once "db.php";

$data = input();

$user_id = (int)($data["user_id"] ?? 0);
if ($user_id <= 0) fail("user_id ไม่ถูกต้อง");

try {
  // เช็กว่าเป็นช่างจริง
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='technician' LIMIT 1");
  $stmt->execute([$user_id]);
  if (!$stmt->fetch()) fail("สิทธิ์ไม่ถูกต้อง");

  $sql = "
    SELECT
      j.id,
      j.job_name,
      j.customer_name,
      j.address,
      j.status,
      j.result_image,
      j.created_at,
      j.updated_at,
      j.status_updated_at,
      u.name AS created_by_name
    FROM jobs j
    LEFT JOIN users u ON u.id = j.created_by
    WHERE j.technician_id = ?
      AND j.status IN (3,4)
    ORDER BY
      COALESCE(j.status_updated_at, j.updated_at, j.created_at) DESC,
      j.id DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$user_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  ok($rows, "technician_summary");
} catch (PDOException $e) {
  fail($e->getMessage());
}