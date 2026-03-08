<?php
require_once "db.php";

$data = input();

$user_id = (int)($data["user_id"] ?? 0);
if ($user_id <= 0) fail("user_id ไม่ถูกต้อง");

try {
  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, username, role, created_at
    FROM users
    WHERE id=?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u) fail("ไม่พบผู้ใช้");

  ok($u, "profile");
} catch (PDOException $e) {
  fail($e->getMessage());
}