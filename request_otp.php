<?php
require_once "db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";

$data = input();
$email = trim($data["email"] ?? "");
if ($email === "") fail(message: "กรุณากรอกอีเมล");

try {
  // หา user จาก email
  $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$u) fail(message: "ไม่พบอีเมลนี้ในระบบ");

  // สร้าง OTP 6 หลัก
  $otp = strval(random_int(100000, 999999));
  $otp_hash = password_hash($otp, PASSWORD_BCRYPT);

  // หมดอายุใน 5 นาที
  $expires = (new DateTime("now", new DateTimeZone("Asia/Bangkok")))
    ->add(new DateInterval("PT5M"))
    ->format("Y-m-d H:i:s");

  // ยกเลิก OTP เก่า
  $pdo->prepare("UPDATE password_otps SET is_used=1 WHERE user_id=? AND is_used=0")
      ->execute([$u["id"]]);

  // บันทึก OTP ใหม่
  $stmt = $pdo->prepare("INSERT INTO password_otps(user_id, otp_hash, expires_at) VALUES(?,?,?)");
  $stmt->execute([$u["id"], $otp_hash, $expires]);

  // ====== ส่ง Email ======
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = "smtp.gmail.com";
  $mail->SMTPAuth = true;

  // ❗แนะนำ: ย้ายค่าเหล่านี้ไปไว้ใน db.php เป็นตัวแปร config
  $mail->Username = "aphiwat.chamnanrob@gmail.com";
  $mail->Password = "iogqfqmxaserdcwb";

  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->CharSet = "UTF-8";
  $mail->setFrom($mail->Username, "IMS System");
  $mail->addAddress($u["email"], $u["name"]);

  $mail->Subject = "รหัส OTP รีเซ็ตรหัสผ่าน";
  $mail->Body = "รหัส OTP ของคุณคือ: $otp\n\nหมดอายุภายใน 5 นาที";

  $mail->send();

  // ✅ ส่ง user_id กลับไปให้ Flutter ใช้ยิง verify_otp ต่อ
  ok(data: ["user_id" => (int)$u["id"]], message: "ส่ง OTP ไปที่อีเมลแล้ว");
} catch (Exception $e) {
  fail(message: "ส่งอีเมลไม่สำเร็จ: " . $e->getMessage());
} catch (PDOException $e) {
  fail(message: $e->getMessage());
}