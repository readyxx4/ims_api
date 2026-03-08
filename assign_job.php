<?php
require_once "db.php";

$data = input();
$job_id = (int)($data["job_id"] ?? 0);
$technician_id = (int)($data["technician_id"] ?? 0);
$manager_id = (int)($data["manager_id"] ?? 0);

if ($job_id <= 0 || $technician_id <= 0 || $manager_id <= 0) {
  fail("ข้อมูลไม่ถูกต้อง");
}

try {
  $pdo->beginTransaction();

  // 1) manager check
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='manager' LIMIT 1");
  $stmt->execute([$manager_id]);
  if (!$stmt->fetch()) {
    $pdo->rollBack();
    fail("สิทธิ์ไม่ถูกต้อง (role ไม่ใช่ manager)");
  }

  // 2) job exists + เป็นของ manager คนนี้
  $stmt = $pdo->prepare("SELECT id, created_by FROM jobs WHERE id=? LIMIT 1");
  $stmt->execute([$job_id]);
  $job = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$job) {
    $pdo->rollBack();
    fail("ไม่พบงานนี้");
  }

  if ((int)$job["created_by"] !== $manager_id) {
    $pdo->rollBack();
    fail("คุณไม่มีสิทธิ์มอบหมายงานนี้");
  }

  // 3) technician check
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='technician' LIMIT 1");
  $stmt->execute([$technician_id]);
  if (!$stmt->fetch()) {
    $pdo->rollBack();
    fail("ผู้ใช้นี้ไม่ใช่ช่าง");
  }

  // 4) assignments (เก็บประวัติ/รองรับเปลี่ยนช่าง)
  $stmt = $pdo->prepare("SELECT id, user_id FROM assignments WHERE job_id=? LIMIT 1");
  $stmt->execute([$job_id]);
  $as = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($as) {
    $stmt = $pdo->prepare("
      UPDATE assignments
      SET user_id=?, status=0, assigned_at=NOW()
      WHERE job_id=?
    ");
    $stmt->execute([$technician_id, $job_id]);
    $note = "เปลี่ยนช่างเป็น #".$technician_id;
  } else {
    $stmt = $pdo->prepare("
      INSERT INTO assignments(job_id, user_id, status, assigned_at)
      VALUES(?, ?, 0, NOW())
    ");
    $stmt->execute([$job_id, $technician_id]);
    $note = "มอบหมายงานให้ช่าง #".$technician_id;
  }

  // 5) ซิงก์ลง jobs
  // assign_status = 0 => มอบหมายแล้ว (รอรับงาน)
  // status = 0 => ยังไม่เริ่ม / งานใหม่
  $stmt = $pdo->prepare("
    UPDATE jobs
    SET
      technician_id=?,
      assign_status=0,
      status=0,
      status_updated_at=NOW(),
      updated_at=NOW(),
      result_image=NULL
    WHERE id=?
  ");
  $stmt->execute([$technician_id, $job_id]);

  // 6) log
  $stmt = $pdo->prepare("
    INSERT INTO job_logs(job_id, status, note, update_time)
    VALUES(?, ?, ?, NOW())
  ");
  $stmt->execute([$job_id, 0, $note]);

  $pdo->commit();
  ok(message: "assigned");

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail(message: $e->getMessage());
}