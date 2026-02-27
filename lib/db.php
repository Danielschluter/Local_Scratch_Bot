<?php
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $path = __DIR__ . '/../data/assistant.sqlite';
  $pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  $pdo->exec("PRAGMA foreign_keys = ON;");
  $pdo->exec("PRAGMA journal_mode = WAL;");
  return $pdo;
}

function create_conversation(PDO $db): int {
  $db->exec("INSERT INTO conversations DEFAULT VALUES");
  return (int)$db->lastInsertId();
}

function insert_message(PDO $db, int $convId, string $role, string $content): int {
  $stmt = $db->prepare("INSERT INTO messages(conversation_id, role, content) VALUES(?,?,?)");
  $stmt->execute([$convId, $role, $content]);
  return (int)$db->lastInsertId();
}

function get_recent_messages(PDO $db, int $convId, int $limit=6): array {
  $stmt = $db->prepare("SELECT role, content FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT ?");
  $stmt->execute([$convId, $limit]);
  $rows = array_reverse($stmt->fetchAll());
  $out = [];
  foreach ($rows as $r) {
    $out[] = strtoupper($r['role']) . ": " . $r['content'];
  }
  return $out;
}

function insert_document(PDO $db, string $source, ?int $refId, string $content): int {
  $stmt = $db->prepare("INSERT INTO documents(source, ref_id, content) VALUES(?,?,?)");
  $stmt->execute([$source, $refId, $content]);
  return (int)$db->lastInsertId();
}
