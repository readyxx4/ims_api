<?php
require_once "db.php";

$data = input();
$email = strtolower(trim($data["email"] ?? ""));
$password = trim($data["password"] ?? "");

if ($email === "" || $password === "") {
  fail("กรอก email/password");
}

try {
  $stmt = $pdo->prepare("SELECT id, name, role, password, email FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u) fail("ไม่พบบัญชีผู้ใช้");
  if (!password_verify($password, $u["password"])) fail("รหัสผ่านไม่ถูกต้อง");

  ok([
    "id" => (int)$u["id"],
    "name" => $u["name"],
    "role" => $u["role"],
    "email" => $u["email"],
  ], "เข้าสู่ระบบสำเร็จ");

} catch (PDOException $e) {
  fail($e->getMessage());
}