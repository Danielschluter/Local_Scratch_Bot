<?php
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['message_id'], $input['score'])) {
  http_response_code(400);
  echo json_encode(["error" => "Bad request"]);
  exit;
}

$db = db();
$stmt = $db->prepare("INSERT INTO feedback(message_id, score) VALUES(?,?)");
$stmt->execute([(int)$input['message_id'], (int)$input['score']]);

echo json_encode(["ok" => true]);
