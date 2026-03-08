<?php
require_once "db.php";

$data = input();

$name = trim($data["name"] ?? "");
$email = strtolower(trim($data["email"] ?? ""));
$phone = trim($data["phone"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "technician");

if ($name === "" || $email === "" || $phone === "" || $password === "") {
  fail(message: "กรอกข้อมูลไม่ครบ");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  fail(message: "รูปแบบอีเมลไม่ถูกต้อง");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
  fail(message: "กรุณากรอกเบอร์โทร 10 หลัก");
}

if (strlen($password) < 6) {
  fail(message: "รหัสผ่านต้องมีอย่างน้อย 6 ตัว");
}

try {
  // กัน email ซ้ำ
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    fail(message: "อีเมลนี้ถูกใช้แล้ว");
  }

  // กันเบอร์โทรซ้ำ
  $stmt = $pdo->prepare("SELECT id FROM users WHERE phone=? LIMIT 1");
  $stmt->execute([$phone]);
  if ($stmt->fetch()) {
    fail(message: "เบอร์โทรนี้ถูกใช้แล้ว");
  }

  $hash = password_hash($password, PASSWORD_BCRYPT);

  $stmt = $pdo->prepare("
    INSERT INTO users(name,email,phone,username,password,role)
    VALUES(?,?,?,?,?,?)
  ");
  $stmt->execute([$name, $email, $phone, $email, $hash, $role]);

  ok(data: null, message: "สมัครสมาชิกสำเร็จ");
} catch (PDOException $e) {
  fail(message: $e->getMessage());
}