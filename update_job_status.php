<?php
require_once "db.php";

function request_data() {
  $contentType = $_SERVER["CONTENT_TYPE"] ?? $_SERVER["HTTP_CONTENT_TYPE"] ?? "";

  // ถ้าเป็น multipart/form-data หรือ x-www-form-urlencoded ใช้ $_POST
  if (
    stripos($contentType, "multipart/form-data") !== false ||
    stripos($contentType, "application/x-www-form-urlencoded") !== false
  ) {
    return $_POST;
  }

  // default ใช้ input() แบบเดิมสำหรับ JSON
  return input();
}

function save_report_image($job_id, $user_id) {
  if (!isset($_FILES["report_image"])) {
    return "";
  }

  if (!is_array($_FILES["report_image"])) {
    fail("ไฟล์รูปไม่ถูกต้อง");
  }

  if (($_FILES["report_image"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail("อัปโหลดรูปไม่สำเร็จ");
  }

  $tmpPath = $_FILES["report_image"]["tmp_name"] ?? "";
  $origName = $_FILES["report_image"]["name"] ?? "image.jpg";

  if ($tmpPath === "" || !is_uploaded_file($tmpPath)) {
    fail("ไม่พบไฟล์อัปโหลด");
  }

  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  $allow = ["jpg", "jpeg", "png", "webp"];

  if (!in_array($ext, $allow, true)) {
    fail("อนุญาตเฉพาะไฟล์ jpg, jpeg, png, webp");
  }

  $dir = __DIR__ . "/uploads/reports";
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
      fail("สร้างโฟลเดอร์เก็บรูปไม่สำเร็จ");
    }
  }

  $filename = "job_" . $job_id . "_tech_" . $user_id . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = $dir . "/" . $filename;

  if (!move_uploaded_file($tmpPath, $dest)) {
    fail("บันทึกไฟล์รูปไม่สำเร็จ");
  }

  // path สำหรับเก็บลงฐานข้อมูล / ใช้แสดงผล
  return "uploads/reports/" . $filename;
}

$data = request_data();

$job_id  = (int)($data["job_id"] ?? 0);
$user_id = (int)($data["user_id"] ?? 0);
$status  = (int)($data["status"] ?? -1);
$note    = trim($data["note"] ?? "");
$role    = trim($data["role"] ?? "technician");

if ($job_id <= 0 || $user_id <= 0) fail("ข้อมูลไม่ครบ (job_id/user_id)");

try {
  $pdo->beginTransaction();

  // =========================
  // กรณีช่างติดตั้ง
  // =========================
  if ($role === "technician") {

    if (!in_array($status, [2, 3], true)) {
      $pdo->rollBack();
      fail("status ไม่ถูกต้อง (ช่างอนุญาต 2 หรือ 3)");
    }

    // 1) เช็คผู้ใช้ต้องเป็น technician
    $stmt = $pdo->prepare("
      SELECT id
      FROM users
      WHERE id = ? AND role = 'technician'
      LIMIT 1
    ");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
      $pdo->rollBack();
      fail("สิทธิ์ไม่ถูกต้อง (ต้องเป็นช่าง)");
    }

    // 2) เช็คงาน + ต้องเป็นงานของช่างคนนี้
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

    if ((int)$job["technician_id"] !== $user_id) {
      $pdo->rollBack();
      fail("คุณไม่มีสิทธิ์อัปเดตงานนี้");
    }

    if ((int)$job["status"] >= 4) {
      $pdo->rollBack();
      fail("งานนี้ถูกปิดแล้ว ไม่สามารถอัปเดตต่อได้");
    }

    // 3) เช็ค assignment
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
      fail("คุณยังไม่ได้รับมอบหมายงานนี้");
    }

    if ((int)$as["status"] < 1) {
      $pdo->rollBack();
      fail("กรุณารับงานก่อนอัปเดตสถานะ");
    }

    // 4) ถ้า status = 3 ต้องมีรูป
    $imagePath = "";
    if ($status === 3) {
      $imagePath = save_report_image($job_id, $user_id);

      if ($imagePath === "") {
        $pdo->rollBack();
        fail("กรุณาอัปโหลดรูปก่อนยืนยันงานเสร็จ");
      }
    }

    // 5) log
    $logNote = $note;
    if ($status === 2 && $logNote === "") {
      $logNote = "ช่างเริ่มดำเนินการติดตั้ง";
    } else if ($status === 3 && $logNote === "") {
      $logNote = "ช่างส่งงานเรียบร้อย";
    }

    $stmt = $pdo->prepare("
      INSERT INTO job_logs(job_id, status, note, update_time)
      VALUES(?, ?, ?, NOW())
    ");
    $stmt->execute([$job_id, $status, $logNote]);

    // 6) อัปเดต jobs
    // status = 2 => กำลังติดตั้ง
    // status = 3 => งานเสร็จสิ้น รอหัวหน้าช่างปิดงาน
    $stmt = $pdo->prepare("
      UPDATE jobs
      SET
        status = ?,
        assign_status = 1,
        updated_at = NOW(),
        status_updated_at = NOW(),
        result_note = CASE WHEN ? <> '' THEN ? ELSE result_note END,
        result_image = CASE WHEN ? <> '' THEN ? ELSE result_image END
      WHERE id = ? AND technician_id = ?
    ");
    $stmt->execute([
      $status,
      $note, $note,
      $imagePath, $imagePath,
      $job_id, $user_id
    ]);

    // 7) ถ้าส่งงานแล้ว อัปเดต assignment เป็นเสร็จแล้ว
    if ($status === 3) {
      $stmt = $pdo->prepare("
        UPDATE assignments
        SET status = 2
        WHERE job_id = ? AND user_id = ?
      ");
      $stmt->execute([$job_id, $user_id]);
    }

    $pdo->commit();
    ok([
      "job_id" => $job_id,
      "status" => $status,
      "image_path" => $imagePath,
    ], "บันทึกสถานะสำเร็จ");
    exit;
  }

  // =========================
  // กรณีหัวหน้าช่างปิดงาน
  // =========================
  if ($role === "manager") {

    if ($status !== 4) {
      $pdo->rollBack();
      fail("manager อนุญาตเฉพาะ status = 4");
    }

    // เช็คว่าเป็น manager จริง
    $stmt = $pdo->prepare("
      SELECT id
      FROM users
      WHERE id = ? AND role = 'manager'
      LIMIT 1
    ");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
      $pdo->rollBack();
      fail("สิทธิ์ไม่ถูกต้อง (ต้องเป็นหัวหน้าช่าง)");
    }

    // เช็คว่างานนี้เป็นของ manager คนนี้ และช่างส่งงานแล้ว
    $stmt = $pdo->prepare("
      SELECT id, created_by, status, assign_status
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

    if ((int)$job["created_by"] !== $user_id) {
      $pdo->rollBack();
      fail("คุณไม่มีสิทธิ์ปิดงานนี้");
    }

    if ((int)$job["status"] !== 3) {
      $pdo->rollBack();
      fail("งานนี้ยังไม่อยู่ในสถานะรอหัวหน้าช่างปิดงาน");
    }

    $stmt = $pdo->prepare("
      INSERT INTO job_logs(job_id, status, note, update_time)
      VALUES(?, ?, ?, NOW())
    ");
    $stmt->execute([$job_id, $status, $note]);

    $stmt = $pdo->prepare("
      UPDATE jobs
      SET
        status = 4,
        updated_at = NOW(),
        status_updated_at = NOW()
      WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$job_id, $user_id]);

    $pdo->commit();
    ok(null, "หัวหน้าช่างปิดงานเรียบร้อย");
    exit;
  }

  $pdo->rollBack();
  fail("role ไม่ถูกต้อง");

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail($e->getMessage());
}