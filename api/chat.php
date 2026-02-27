<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/agent.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['message'])) {
  http_response_code(400);
  echo json_encode(["error" => "Bad request"]);
  exit;
}

$db = db();

$convId = isset($input['conversation_id']) && $input['conversation_id']
  ? (int)$input['conversation_id']
  : create_conversation($db);

[$reply, $assistantMsgId, $citations] = agent_reply($db, $convId, (string)$input['message']);

echo json_encode([
  "conversation_id" => $convId,
  "assistant_message_id" => $assistantMsgId,
  "reply" => $reply,
  "citations" => $citations
]);
