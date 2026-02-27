<?php

function web_query_cache_get(PDO $db, string $query): ?array {
  $qhash = hash('sha256', mb_strtolower(trim($query)));
  $stmt = $db->prepare("SELECT json, fetched_at, ttl_hours FROM web_queries WHERE qhash=?");
  $stmt->execute([$qhash]);
  $row = $stmt->fetch();
  if (!$row) return null;

  $fetched = strtotime($row['fetched_at']);
  $ttl = (int)$row['ttl_hours'] * 3600;
  if (time() - $fetched > $ttl) return null;

  $json = json_decode($row['json'], true);
  return is_array($json) ? $json : null;
}

function web_query_cache_set(PDO $db, string $query, array $json, int $ttlHours=24): void {
  $qhash = hash('sha256', mb_strtolower(trim($query)));
  $stmt = $db->prepare("INSERT INTO web_queries(qhash, query, json, fetched_at, ttl_hours)
                        VALUES(?,?,?,?,?)
                        ON CONFLICT(qhash) DO UPDATE SET json=excluded.json, fetched_at=excluded.fetched_at, ttl_hours=excluded.ttl_hours");
  $stmt->execute([$qhash, $query, json_encode($json), date('c'), $ttlHours]);
}

function web_search_searxng(PDO $db, string $q, int $limit = 5): array {
  $cached = web_query_cache_get($db, $q);
  if ($cached && isset($cached['results'])) {
    return array_slice(normalize_results($cached['results']), 0, $limit);
  }

  $url = "http://127.0.0.1:8080/search?" . http_build_query([
    "q" => $q,
    "format" => "json",
    "language" => "en",
    "safesearch" => 1
  ]);

  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "timeout" => 8,
      "header" => "User-Agent: LocalAssistant/1.0\r\n"
    ]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return [];

  $json = json_decode($raw, true);
  if (!is_array($json) || !isset($json["results"])) return [];

  web_query_cache_set($db, $q, $json, 24);
  return array_slice(normalize_results($json["results"]), 0, $limit);
}

function normalize_results(array $results): array {
  $out = [];
  foreach ($results as $r) {
    if (!isset($r["title"], $r["url"])) continue;
    $snippet = $r["content"] ?? "";
    $out[] = [
      "title" => (string)$r["title"],
      "url" => (string)$r["url"],
      "snippet" => trim(strip_tags((string)$snippet))
    ];
  }
  return $out;
}

function web_pages_get(PDO $db, string $url): ?string {
  $stmt = $db->prepare("SELECT content, fetched_at FROM web_pages WHERE url=?");
  $stmt->execute([$url]);
  $row = $stmt->fetch();
  if (!$row) return null;

  if (time() - strtotime($row['fetched_at']) > 7*24*3600) return null;
  return (string)$row['content'];
}

function web_pages_set(PDO $db, string $url, string $content): void {
  $stmt = $db->prepare("INSERT INTO web_pages(url, content, fetched_at)
                        VALUES(?,?,?)
                        ON CONFLICT(url) DO UPDATE SET content=excluded.content, fetched_at=excluded.fetched_at");
  $stmt->execute([$url, $content, date('c')]);
}

function fetch_page_text(string $url, int $timeout=8): string {
  $ctx = stream_context_create([
    "http" => ["timeout"=>$timeout, "header"=>"User-Agent: LocalAssistant/1.0\r\n"]
  ]);
  $html = @file_get_contents($url, false, $ctx);
  if ($html === false) return "";

  $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
  $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
  $html = preg_replace('/<(nav|footer|aside)\b[^>]*>.*?<\/\1>/is', ' ', $html);

  $text = strip_tags($html);
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace("/\s+/", " ", $text);
  return trim($text);
}
