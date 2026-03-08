<?php
require_once "db.php";

$data = input();
$manager_id = (int)($data["manager_id"] ?? 0);

if ($manager_id <= 0) fail("manager_id ไม่ถูกต้อง");

try {
  // เช็คว่าเป็น manager จริง
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='manager' LIMIT 1");
  $stmt->execute([$manager_id]);
  if (!$stmt->fetch()) fail("สิทธิ์ไม่ถูกต้อง (ต้องเป็นหัวหน้าช่าง)");

  // ดึงเฉพาะงานที่เสร็จแล้ว
  $stmt = $pdo->prepare("
    SELECT
      j.id,
      j.job_name,
      j.customer_name,
      j.address,
      j.created_by,
      j.technician_id,
      j.status,
      j.assign_status,
      j.result_image,
      j.status_updated_at,
      j.created_at,
      j.updated_at,
      u.name AS technician_name
    FROM jobs j
    LEFT JOIN users u ON u.id = j.technician_id
    WHERE j.created_by = ?
      AND j.status IN (3,4)
    ORDER BY j.updated_at DESC, j.id DESC
  ");
  $stmt->execute([$manager_id]);
  $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // ดึง logs ของทุกงานที่เสร็จ
  foreach ($jobs as &$job) {
    $jobId = (int)$job["id"];

    $stmtLog = $pdo->prepare("
      SELECT id, status, note, update_time
      FROM job_logs
      WHERE job_id = ?
      ORDER BY update_time DESC, id DESC
    ");
    $stmtLog->execute([$jobId]);
    $job["logs"] = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
  }

  ok($jobs, "summary");
} catch (PDOException $e) {
  fail($e->getMessage());
}