<?php
require_once "db.php";

$data = input();

$user_id = (int)($data["user_id"] ?? 0);
$mode = trim($data["mode"] ?? "");

if ($user_id <= 0) fail("user_id ไม่ถูกต้อง");

try {
  $where = "WHERE a.user_id = ?";
  $params = [$user_id];

  // mode:
  // assigned = งานที่มอบหมายแล้ว รอช่างรับงาน
  // active   = งานที่ช่างรับแล้ว / กำลังทำ / ส่งงานแล้ว
  if ($mode === "assigned") {
    $where .= " AND a.status = 0";
  } else if ($mode === "active") {
    $where .= " AND a.status = 1 AND j.status IN (1, 2, 3)";
  }

  $sql = "
    SELECT
      j.id,
      j.job_name,
      j.customer_name,
      j.address,
      j.status,
      j.created_at,
      j.updated_at,
      a.status AS assign_status,
      a.assigned_at,
      a.user_id AS technician_id,
      tu.name AS technician_name,
      u.name AS created_by_name,
      COALESCE(j.updated_at, j.created_at) AS status_updated_at
    FROM assignments a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN users u ON j.created_by = u.id
    LEFT JOIN users tu ON a.user_id = tu.id
    $where
    ORDER BY a.assigned_at DESC, j.id DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  ok($rows, "my_jobs");
} catch (PDOException $e) {
  fail($e->getMessage());
}