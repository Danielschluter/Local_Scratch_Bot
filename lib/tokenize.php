<?php
function tokenize(string $text): array {
  $text = mb_strtolower($text);
  preg_match_all("/[a-z0-9]+|[^\s\p{L}\p{N}]/u", $text, $m);
  return $m[0] ?? [];
}
