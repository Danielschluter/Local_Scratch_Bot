<?php
require_once __DIR__ . '/tokenize.php';

function tfidf_index_document(PDO $db, int $docId, string $content): void {
  $toks = tokenize($content);
  if (!$toks) return;

  $tf = [];
  foreach ($toks as $t) {
    if (mb_strlen($t) < 2) continue;
    $tf[$t] = ($tf[$t] ?? 0) + 1;
  }
  if (!$tf) return;

  $db->beginTransaction();

  $stmtIns = $db->prepare("INSERT INTO doc_term_freq(doc_id, term, tf) VALUES(?,?,?)
                           ON CONFLICT(doc_id,term) DO UPDATE SET tf=excluded.tf");
  foreach ($tf as $term => $count) {
    $stmtIns->execute([$docId, $term, $count]);
  }

  $stmtDf = $db->prepare("INSERT INTO term_doc_freq(term, df) VALUES(?,1)
                          ON CONFLICT(term) DO UPDATE SET df=df+1");
  foreach (array_keys($tf) as $term) {
    $stmtDf->execute([$term]);
  }

  $db->commit();
}

function tfidf_search(PDO $db, string $query, int $k=5): array {
  $qtoks = tokenize($query);
  $qtf = [];
  foreach ($qtoks as $t) {
    if (mb_strlen($t) < 2) continue;
    $qtf[$t] = ($qtf[$t] ?? 0) + 1;
  }
  if (!$qtf) return [];

  $N = (int)$db->query("SELECT COUNT(*) AS n FROM documents")->fetch()['n'];
  if ($N <= 0) return [];

  $in = implode(',', array_fill(0, count($qtf), '?'));
  $stmt = $db->prepare("SELECT term, df FROM term_doc_freq WHERE term IN ($in)");
  $stmt->execute(array_keys($qtf));
  $dfRows = $stmt->fetchAll();

  $idf = [];
  foreach ($dfRows as $r) {
    $df = max(1, (int)$r['df']);
    $idf[$r['term']] = log(($N + 1) / ($df + 1)) + 1.0;
  }

  $scores = [];
  $stmtTf = $db->prepare("SELECT doc_id, tf FROM doc_term_freq WHERE term=?");
  foreach ($qtf as $term => $qcount) {
    if (!isset($idf[$term])) continue;
    $stmtTf->execute([$term]);
    while ($row = $stmtTf->fetch()) {
      $docId = (int)$row['doc_id'];
      $scores[$docId] = ($scores[$docId] ?? 0.0) + ((int)$row['tf']) * $idf[$term] * $qcount;
    }
  }
  if (!$scores) return [];

  arsort($scores);
  $topDocIds = array_slice(array_keys($scores), 0, $k);

  $stmtDoc = $db->prepare("SELECT id, content FROM documents WHERE id=?");
  $snips = [];
  foreach ($topDocIds as $docId) {
    $stmtDoc->execute([$docId]);
    $row = $stmtDoc->fetch();
    if (!$row) continue;
    $snips[] = trim(mb_substr($row['content'], 0, 400));
  }
  return $snips;
}

function tfidf_best_score(PDO $db, string $query): float {
  $qtoks = tokenize($query);
  $qtf = [];
  foreach ($qtoks as $t) { if (mb_strlen($t) >= 2) $qtf[$t] = ($qtf[$t] ?? 0) + 1; }
  if (!$qtf) return 0.0;

  $N = (int)$db->query("SELECT COUNT(*) AS n FROM documents")->fetch()['n'];
  if ($N <= 0) return 0.0;

  $in = implode(',', array_fill(0, count($qtf), '?'));
  $stmt = $db->prepare("SELECT term, df FROM term_doc_freq WHERE term IN ($in)");
  $stmt->execute(array_keys($qtf));
  $dfRows = $stmt->fetchAll();

  $idf = [];
  foreach ($dfRows as $r) {
    $df = max(1, (int)$r['df']);
    $idf[$r['term']] = log(($N + 1) / ($df + 1)) + 1.0;
  }

  $best = 0.0;
  $stmtTf = $db->prepare("SELECT doc_id, tf FROM doc_term_freq WHERE term=?");
  foreach ($qtf as $term => $qcount) {
    if (!isset($idf[$term])) continue;
    $stmtTf->execute([$term]);
    while ($row = $stmtTf->fetch()) {
      $score = ((int)$row['tf']) * $idf[$term] * $qcount;
      if ($score > $best) $best = $score;
    }
  }
  return $best;
}
