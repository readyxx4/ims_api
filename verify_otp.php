<?php
require_once "db.php";

$data = input();

$user_id = (int)($data["user_id"] ?? 0);
$otp = trim($data["otp"] ?? "");

if ($user_id <= 0 || $otp === "") fail(message: "ข้อมูลไม่ครบ");

try {
  $stmt = $pdo->prepare("
    SELECT id, otp_hash, expires_at, is_used
    FROM password_otps
    WHERE user_id=? AND is_used=0
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) fail(message: "ไม่พบ OTP หรือ OTP ถูกใช้แล้ว");

  $now = new DateTime("now", new DateTimeZone("Asia/Bangkok"));
  $exp = new DateTime($row["expires_at"], new DateTimeZone("Asia/Bangkok"));
  if ($now > $exp) fail(message: "OTP หมดอายุแล้ว");

  if (!password_verify($otp, $row["otp_hash"])) {
    fail(message: "OTP ไม่ถูกต้อง");
  }

  ok(data: ["otp_id" => (int)$row["id"]], message: "OTP ถูกต้อง");
} catch (PDOException $e) {
  fail(message: $e->getMessage());
}