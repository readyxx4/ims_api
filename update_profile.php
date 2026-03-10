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
  $isMultipart = isset($_POST["user_id"]);

  if ($isMultipart) {
    $user_id = (int)($_POST["user_id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $new_password = trim($_POST["new_password"] ?? "");
  } else {
    $raw = json_decode(file_get_contents("php://input"), true);
    $user_id = (int)($raw["user_id"] ?? 0);
    $name = trim($raw["name"] ?? "");
    $email = trim($raw["email"] ?? "");
    $phone = trim($raw["phone"] ?? "");
    $new_password = trim($raw["new_password"] ?? "");
  }

  if ($user_id <= 0 || $name === "" || $email === "" || $phone === "") {
    json_response(false, "ข้อมูลไม่ครบ");
  }

  if (!preg_match('/^[0-9]{10}$/', $phone)) {
    json_response(false, "กรุณากรอกเบอร์โทร 10 หลัก");
  }

  // เช็กว่า user มีจริงไหม
  $stmt = $pdo->prepare("SELECT id, profile_image FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    json_response(false, "ไม่พบผู้ใช้งาน");
  }

  // กัน email ซ้ำ
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
  $stmt->execute([$email, $user_id]);
  if ($stmt->fetch()) {
    json_response(false, "อีเมลนี้ถูกใช้งานแล้ว");
  }

  $profile_image = $user["profile_image"] ?? null;

  // ===== upload profile image =====
  if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/profiles/";
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    $tmp = $_FILES["profile_image"]["tmp_name"];
    $originalName = $_FILES["profile_image"]["name"];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($ext, $allowed)) {
      json_response(false, "รองรับเฉพาะไฟล์ jpg, jpeg, png, webp");
    }

    $newFileName = "user_" . $user_id . "_" . time() . "." . $ext;
    $targetPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($tmp, $targetPath)) {
      json_response(false, "อัปโหลดรูปไม่สำเร็จ");
    }

    $profile_image = "uploads/profiles/" . $newFileName;
  }

  // ===== update sql =====
  if ($new_password !== "") {
    if (mb_strlen($new_password) < 6) {
      json_response(false, "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัว");
    }

    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
      UPDATE users
      SET name = ?, email = ?, phone = ?, password = ?, profile_image = ?
      WHERE id = ?
    ");
    $stmt->execute([$name, $email, $phone, $password_hash, $profile_image, $user_id]);
  } else {
    $stmt = $pdo->prepare("
      UPDATE users
      SET name = ?, email = ?, phone = ?, profile_image = ?
      WHERE id = ?
    ");
    $stmt->execute([$name, $email, $phone, $profile_image, $user_id]);
  }

  // ===== response data =====
  $stmt = $pdo->prepare("
    SELECT id, name, email, phone, role, profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $updated = $stmt->fetch(PDO::FETCH_ASSOC);

  json_response(true, "อัปเดตโปรไฟล์สำเร็จ", $updated);

} catch (Throwable $e) {
  json_response(false, "Server error: " . $e->getMessage());
}