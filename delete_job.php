<?php
require_once "db.php";

$data = input();

$job_id = (int)($data["job_id"] ?? 0);
$manager_id = (int)($data["manager_id"] ?? 0);

if ($job_id <= 0 || $manager_id <= 0) {
  fail("ข้อมูลไม่ถูกต้อง");
}

try {
  $pdo->beginTransaction();

  // ตรวจว่าเป็น manager จริง
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='manager' LIMIT 1");
  $stmt->execute([$manager_id]);
  if (!$stmt->fetch()) {
    $pdo->rollBack();
    fail("สิทธิ์ไม่ถูกต้อง");
  }

  // ตรวจว่างานนี้เป็นของ manager คนนี้
  $stmt = $pdo->prepare("SELECT id FROM jobs WHERE id=? AND created_by=? LIMIT 1");
  $stmt->execute([$job_id, $manager_id]);
  if (!$stmt->fetch()) {
    $pdo->rollBack();
    fail("ไม่พบงาน หรือไม่มีสิทธิ์ลบ");
  }

  // ลบข้อมูลที่อ้างถึงก่อน
  $stmt = $pdo->prepare("DELETE FROM assignments WHERE job_id=?");
  $stmt->execute([$job_id]);

  $stmt = $pdo->prepare("DELETE FROM job_logs WHERE job_id=?");
  $stmt->execute([$job_id]);

  // ลบงานหลัก
  $stmt = $pdo->prepare("DELETE FROM jobs WHERE id=?");
  $stmt->execute([$job_id]);

  $pdo->commit();
  ok([], "ลบงานสำเร็จ");
} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail("delete_job error: " . $e->getMessage());
}