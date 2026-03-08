<?php
require_once "db.php";

$data = input();

$user_id = (int)($data["user_id"] ?? 0);
$name = trim($data["name"] ?? "");
$email = strtolower(trim($data["email"] ?? ""));
$phone = trim($data["phone"] ?? "");
$new_password = trim($data["new_password"] ?? "");

if ($user_id <= 0 || $name === "" || $email === "" || $phone === "") {
  fail(message: "กรอกข้อมูลไม่ครบ");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  fail(message: "รูปแบบอีเมลไม่ถูกต้อง");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
  fail(message: "กรุณากรอกเบอร์โทร 10 หลัก");
}

if ($new_password !== "" && strlen($new_password) < 6) {
  fail(message: "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัว");
}

try {
  // กัน email ซ้ำกับคนอื่น
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
  $stmt->execute([$email, $user_id]);
  if ($stmt->fetch()) {
    fail(message: "อีเมลนี้ถูกใช้แล้ว");
  }

  // กันเบอร์โทรซ้ำกับคนอื่น
  $stmt = $pdo->prepare("SELECT id FROM users WHERE phone=? AND id<>? LIMIT 1");
  $stmt->execute([$phone, $user_id]);
  if ($stmt->fetch()) {
    fail(message: "เบอร์โทรนี้ถูกใช้แล้ว");
  }

  if ($new_password !== "") {
    $hash = password_hash($new_password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
      UPDATE users
      SET name=?, email=?, phone=?, password=?
      WHERE id=?
    ");
    $stmt->execute([$name, $email, $phone, $hash, $user_id]);
  } else {
    $stmt = $pdo->prepare("
      UPDATE users
      SET name=?, email=?, phone=?
      WHERE id=?
    ");
    $stmt->execute([$name, $email, $phone, $user_id]);
  }

  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, role, created_at
    FROM users
    WHERE id=?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  ok(data: $user, message: "อัปเดตโปรไฟล์สำเร็จ");
} catch (PDOException $e) {
  fail(message: $e->getMessage());
}