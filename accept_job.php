<?php
require_once "db.php";

$data = input();

$job_id = (int)($data["job_id"] ?? 0);
$user_id = (int)($data["user_id"] ?? 0); // technician

if ($job_id <= 0 || $user_id <= 0) fail("ข้อมูลไม่ถูกต้อง");

try {
  $pdo->beginTransaction();

  // 1) ตรวจว่ามี assignment ของช่างคนนี้จริง
  $stmt = $pdo->prepare("
    SELECT id, status
    FROM assignments
    WHERE job_id = ? AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$job_id, $user_id]);
  $as = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$as) {
    $pdo->rollBack();
    fail("ไม่พบงานที่มอบหมายให้คุณ");
  }

  // 2) ตรวจว่า jobs นี้ถูก assign ให้ช่างคนนี้จริง
  $stmt = $pdo->prepare("
    SELECT id, technician_id, assign_status, status
    FROM jobs
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->execute([$job_id]);
  $job = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$job) {
    $pdo->rollBack();
    fail("ไม่พบงานนี้");
  }

  if ((int)($job["technician_id"] ?? 0) !== $user_id) {
    $pdo->rollBack();
    fail("งานนี้ไม่ได้ถูกมอบหมายให้คุณ");
  }

  // 3) กันรับซ้ำ
  if ((int)$as["status"] >= 1 || (int)($job["assign_status"] ?? 0) >= 1) {
    $pdo->rollBack();
    fail("คุณรับงานนี้แล้ว");
  }

  // 4) อัปเดต assignment ว่ารับงานแล้ว
  $stmt = $pdo->prepare("
    UPDATE assignments
    SET status = 1
    WHERE job_id = ? AND user_id = ?
  ");
  $stmt->execute([$job_id, $user_id]);

  // 5) อัปเดต jobs ให้เป็น 'รับงานแล้ว'
  // status = 1  => รับงานแล้ว
  // assign_status = 1 => ช่างรับงานแล้ว
  $stmt = $pdo->prepare("
    UPDATE jobs
    SET
      technician_id = ?,
      assign_status = 1,
      status = 1,
      status_updated_at = NOW(),
      updated_at = NOW()
    WHERE id = ?
  ");
  $stmt->execute([$user_id, $job_id]);

  if ($stmt->rowCount() == 0) {
    $pdo->rollBack();
    fail("รับงานไม่สำเร็จ");
  }

  // 6) log
  $stmt = $pdo->prepare("
    INSERT INTO job_logs(job_id, status, note, update_time)
    VALUES(?, ?, ?, NOW())
  ");
  $stmt->execute([
    $job_id,
    1,
    "ช่าง #".$user_id." รับงานแล้ว"
  ]);

  $pdo->commit();
  ok(message: "รับงานสำเร็จ");

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail(message: $e->getMessage());
}