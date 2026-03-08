<?php
require_once "db.php";

$data = input();
$manager_id = (int)($data["manager_id"] ?? 0);
$mode = trim($data["mode"] ?? "");

try {
  $sql = "
    SELECT
      j.id,
      j.job_name,
      j.customer_name,
      j.address,
      j.created_by,
      u1.name AS created_by_name,
      j.technician_id,
      u2.name AS technician_name,
      j.status,
      j.assign_status,
      j.status_updated_at,
      j.result_image,
      j.created_at,
      j.updated_at
    FROM jobs j
    LEFT JOIN users u1 ON u1.id = j.created_by
    LEFT JOIN users u2 ON u2.id = j.technician_id
  ";

  $params = [];
  $where = [];

  if ($manager_id > 0) {
    $where[] = "j.created_by = ?";
    $params[] = $manager_id;
  }

  if ($mode === "pending_assign") {
    $where[] = "(j.technician_id IS NULL OR j.assign_status = 0)";
  } elseif ($mode === "progress") {
    $where[] = "j.status IN (2,3)";
  }

  if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }

  if ($mode === "pending_assign") {
    $sql .= " ORDER BY j.created_at ASC, j.id ASC ";
  } elseif ($mode === "progress") {
    $sql .= "
      ORDER BY
        CASE
          WHEN j.status = 3 THEN 0
          ELSE 1
        END,
        j.updated_at DESC,
        j.id DESC
    ";
  } else {
    $sql .= " ORDER BY j.created_at ASC, j.id ASC ";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ok(data: $rows, message: "list_jobs");
} catch (PDOException $e) {
  fail(message: $e->getMessage());
}