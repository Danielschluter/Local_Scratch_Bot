# Local Assistant (PHP + SQLite + Vanilla JS) with SearXNG + Tiny Neural LM

## What this is
- Vanilla JS frontend chat UI
- PHP backend with SQLite memory (TF-IDF retrieval)
- Optional web search via **self-hosted SearXNG** (no paid APIs)
- Tiny self-trained neural next-token model (Embedding + MLP) running on local Node server

## Quick start

### 1) Initialize SQLite
```bash
mkdir -p data
sqlite3 data/assistant.sqlite < schema.sql
```

### 2) Start SearXNG + Node inference
```bash
docker compose up -d
```

### 3) Serve PHP
Use any web server that can serve `/public` and route `/api/*` to PHP.
For quick local dev:
```bash
php -S 0.0.0.0:8000 -t public
```
Then ensure `/api` is reachable (if using built-in server, you may prefer putting `public` and `api` under same docroot or configure rewrites).

## Train the model (user messages only)
After chatting a bit:

```bash
cd node
npm init -y
npm i sqlite3
node export_from_sqlite.js
node train.js
```

The Node server will load weights from `data/model/`.
