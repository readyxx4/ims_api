<?php
require_once "db.php";

$data = input();

$otp_id = (int)($data["otp_id"] ?? 0);
$new_password = trim($data["new_password"] ?? "");

if ($otp_id <= 0 || $new_password == "") fail("ข้อมูลไม่ครบ");
if (strlen($new_password) < 6) fail("รหัสผ่านต้องมีอย่างน้อย 6 ตัว");

try {
  // หา otp record
  $stmt = $pdo->prepare("SELECT user_id, expires_at, is_used FROM password_otps WHERE id=? LIMIT 1");
  $stmt->execute([$otp_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) fail("ไม่พบ OTP");
  if ((int)$row["is_used"] === 1) fail("OTP ถูกใช้แล้ว");

  $now = new DateTime("now", new DateTimeZone("Asia/Bangkok"));
  $exp = new DateTime($row["expires_at"], new DateTimeZone("Asia/Bangkok"));
  if ($now > $exp) fail("OTP หมดอายุแล้ว");

  $hash = password_hash($new_password, PASSWORD_BCRYPT);

  // อัปเดตรหัสผ่าน
  $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
  $stmt->execute([$hash, (int)$row["user_id"]]);

  // ใช้ OTP แล้ว
  $pdo->prepare("UPDATE password_otps SET is_used=1 WHERE id=?")->execute([$otp_id]);

  ok(null, "เปลี่ยนรหัสผ่านสำเร็จ");
} catch (PDOException $e) {
  fail($e->getMessage());
}