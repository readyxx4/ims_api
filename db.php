<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$host = "localhost";
$dbname = "ims_db";
$user = "root";
$pass = "";

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  echo json_encode(["success" => 0, "message" => "DB connect error: " . $e->getMessage()]);
  exit;
}

function ok($data = null, $message = "ok") {
  echo json_encode(["success" => 1, "message" => $message, "data" => $data]);
  exit;
}

function fail($message = "fail") {
  echo json_encode(["success" => 0, "message" => $message]);
  exit;
}

function input() {
  $raw = file_get_contents("php://input");
  $json = json_decode($raw, true);
  if (is_array($json)) return $json;
  return $_POST;
}