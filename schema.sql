PRAGMA journal_mode=WAL;

CREATE TABLE IF NOT EXISTS conversations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  conversation_id INTEGER NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('user','assistant','system')),
  content TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(conversation_id) REFERENCES conversations(id)
);

CREATE TABLE IF NOT EXISTS documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source TEXT NOT NULL,
  ref_id INTEGER,
  content TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS term_doc_freq (
  term TEXT PRIMARY KEY,
  df INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS doc_term_freq (
  doc_id INTEGER NOT NULL,
  term TEXT NOT NULL,
  tf INTEGER NOT NULL,
  PRIMARY KEY(doc_id, term),
  FOREIGN KEY(doc_id) REFERENCES documents(id)
);

CREATE TABLE IF NOT EXISTS feedback (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  message_id INTEGER NOT NULL,
  score INTEGER NOT NULL CHECK(score IN (-1, 1)),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY(message_id) REFERENCES messages(id)
);

CREATE TABLE IF NOT EXISTS web_cache (
  url TEXT PRIMARY KEY,
  title TEXT,
  snippet TEXT,
  fetched_at TEXT NOT NULL,
  ttl_hours INTEGER NOT NULL DEFAULT 168
);

CREATE TABLE IF NOT EXISTS web_pages (
  url TEXT PRIMARY KEY,
  content TEXT NOT NULL,
  fetched_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS web_citations (
  conversation_id INTEGER NOT NULL,
  user_message_id INTEGER NOT NULL,
  url TEXT NOT NULL,
  title TEXT,
  used_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS web_queries (
  qhash TEXT PRIMARY KEY,
  query TEXT NOT NULL,
  json TEXT NOT NULL,
  fetched_at TEXT NOT NULL,
  ttl_hours INTEGER NOT NULL DEFAULT 24
);
