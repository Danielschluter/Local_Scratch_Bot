<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tfidf.php';
require_once __DIR__ . '/web.php';

function agent_reply(PDO $db, int $conversationId, string $userText): array {
  $userMsgId = insert_message($db, $conversationId, 'user', $userText);

  $docId = insert_document($db, 'message', $userMsgId, $userText);
  tfidf_index_document($db, $docId, $userText);

  $mem = tfidf_search($db, $userText, 5);
  $bestScore = tfidf_best_score($db, $userText);

  $forceWeb = preg_match('/\b(latest|today|now|current|price|news|202\d)\b/i', $userText) === 1;
  $useWeb = $forceWeb || ($bestScore < 0.15);

  $citations = [];
  $webBlock = "";

  if ($useWeb) {
    $results = web_search_searxng($db, $userText, 5);

    $lines = [];
    foreach ($results as $i => $r) {
      $lines[] = "[" . ($i+1) . "] " . $r['title'] . " — " . $r['url'] . "\nSnippet: " . mb_substr($r['snippet'], 0, 280);

      $citations[] = ["title" => $r['title'], "url" => $r['url']];
      $stmt = $db->prepare("INSERT INTO web_citations(conversation_id, user_message_id, url, title) VALUES(?,?,?,?)");
      $stmt->execute([$conversationId, $userMsgId, $r['url'], $r['title']]);
    }
    $webBlock = implode("\n\n", $lines);

    if (!$forceWeb && $bestScore < 0.08 && count($results) > 0) {
      $u = $results[0]['url'];
      $page = web_pages_get($db, $u);
      if ($page === null) {
        $page = fetch_page_text($u, 8);
        if ($page !== "") web_pages_set($db, $u, $page);
      }
      if ($page) {
        $webBlock .= "\n\nPAGE EXTRACT (top result):\n" . mb_substr($page, 0, 1800);
      }
    }
  }

  $recent = get_recent_messages($db, $conversationId, 6);

  $context = "SYSTEM: You are a helpful personal assistant. Use MEMORY and WEB snippets.\n";
  $context .= "MEMORY:\n" . ($mem ? implode("\n---\n", $mem) : "(none)") . "\n\n";
  if ($webBlock) $context .= "WEB:\n" . $webBlock . "\n\n";
  $context .= "CHAT:\n" . implode("\n", $recent) . "\n\n";
  $context .= "USER:\n" . $userText . "\n\nASSISTANT:";

  $reply = node_infer($context);

  if ($reply === null || trim($reply) === "") {
    $reply = "I’m not able to generate a good answer right now. Try rephrasing, or ensure the Node inference server is running on port 3030.";
  }

  $assistantMsgId = insert_message($db, $conversationId, 'assistant', $reply);

  return [$reply, $assistantMsgId, $citations];
}

function node_infer(string $context): ?string {
  $url = "http://127.0.0.1:3030/infer";
  $payload = json_encode([
    "context" => $context,
    "max_tokens" => 140,
    "temperature" => 0.9,
    "top_k" => 40
  ]);

  $ctx = stream_context_create([
    "http" => [
      "method" => "POST",
      "timeout" => 6,
      "header" => "Content-Type: application/json\r\n",
      "content" => $payload
    ]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;

  $json = json_decode($raw, true);
  if (!is_array($json) || !isset($json['text'])) return null;
  return (string)$json['text'];
}
