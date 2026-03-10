<?php
require_once "db.php";

header("Content-Type: application/json; charset=utf-8");

function json_response($success, $message, $data = null) {
  echo json_encode([
    "success" => $success ? 1 : 0,
    "message" => $message,
    "data" => $data
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $raw = json_decode(file_get_contents("php://input"), true);
  $user_id = (int)($raw["user_id"] ?? 0);

  if ($user_id <= 0) {
    json_response(false, "ไม่พบรหัสผู้ใช้งาน");
  }

  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, role, profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    json_response(false, "ไม่พบข้อมูลผู้ใช้");
  }

  json_response(true, "success", $user);

} catch (Throwable $e) {
  json_response(false, "Server error: " . $e->getMessage());
}