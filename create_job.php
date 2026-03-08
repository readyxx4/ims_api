<?php
require_once "db.php";

$data = input();

$job_name = trim($data["job_name"] ?? "");
$customer_name = trim($data["customer_name"] ?? "");
$address = trim($data["address"] ?? "");
$created_by = (int)($data["created_by"] ?? 0);

if ($job_name === "" || $customer_name === "" || $address === "" || $created_by <= 0) {
  fail("ข้อมูลไม่ครบ");
}

try {
  // เช็คว่าเป็น manager จริง
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='manager' LIMIT 1");
  $stmt->execute([$created_by]);
  if (!$stmt->fetch()) fail("สิทธิ์ไม่ถูกต้อง (ต้องเป็นหัวหน้าช่าง)");

  $now = date("Y-m-d H:i:s");

  $stmt = $pdo->prepare("
    INSERT INTO jobs (
      job_name,
      customer_name,
      address,
      created_by,
      technician_id,
      status,
      assign_status,
      status_updated_at,
      result_image,
      created_at,
      updated_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmt->execute([
    $job_name,
    $customer_name,
    $address,
    $created_by,
    null,   // ยังไม่มอบหมายช่าง
    0,      // งานใหม่
    -1,     // ยังไม่ได้มอบหมายงาน
    $now,
    null,
    $now,
    $now
  ]);

  $jobId = (int)$pdo->lastInsertId();

  $stmt = $pdo->prepare("
    INSERT INTO job_logs(job_id, status, note, update_time)
    VALUES (?, ?, ?, NOW())
  ");
  $stmt->execute([$jobId, 0, "สร้างงานติดตั้ง"]);

  ok(["job_id" => $jobId], "สร้างงานสำเร็จ");

} catch (PDOException $e) {
  fail("create_job error: " . $e->getMessage());
}