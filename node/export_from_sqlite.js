const fs = require('fs');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

const dbPath = path.join(__dirname, '../data/assistant.sqlite');
const outPath = path.join(__dirname, 'user_corpus.txt');

const db = new sqlite3.Database(dbPath);
db.all("SELECT content FROM messages WHERE role='user' ORDER BY id ASC", [], (err, rows) => {
  if (err) throw err;
  const text = rows.map(r => r.content).join("\n");
  fs.writeFileSync(outPath, text, 'utf8');
  console.log(`Wrote ${rows.length} user messages to ${outPath}`);
  db.close();
});
