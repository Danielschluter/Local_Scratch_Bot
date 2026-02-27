const http = require('http');
const fs = require('fs');
const path = require('path');
const { tokenize } = require('./tokenize');
const { TinyLM } = require('./model');

const modelDir = path.join(__dirname, '../data/model');
const vocabPath = path.join(modelDir, 'vocab.json');
const weightsPath = path.join(modelDir, 'weights.json');

function loadModel() {
  if (!fs.existsSync(vocabPath) || !fs.existsSync(weightsPath)) return null;
  const vocab = JSON.parse(fs.readFileSync(vocabPath, 'utf8'));
  const w = JSON.parse(fs.readFileSync(weightsPath, 'utf8'));
  const stoi = new Map(vocab.map((t,i)=>[t,i]));
  const itos = vocab;
  const model = TinyLM.fromJSON(w);
  return { model, stoi, itos };
}

let state = loadModel();
function reload() { state = loadModel(); }

function sampleFromProbs(probs, topK=40, temperature=1.0) {
  const pairs = probs.map((p,i)=>[i, Math.log(Math.max(1e-12,p))/Math.max(1e-6,temperature)]);
  pairs.sort((a,b)=>b[1]-a[1]);
  const top = pairs.slice(0, topK);

  let max = -Infinity;
  for (const [,l] of top) if (l > max) max = l;
  const exps = top.map(([i,l])=>[i, Math.exp(l-max)]);
  const sum = exps.reduce((a, [,e])=>a+e,0);

  let r = Math.random()*sum;
  for (const [i,e] of exps) { r -= e; if (r <= 0) return i; }
  return exps[0][0];
}

function idsFromTokens(tokens, stoi) {
  const unk = stoi.get("<UNK>") ?? 1;
  return tokens.map(t => stoi.has(t) ? stoi.get(t) : unk);
}

function detok(tokens) {
  const noSpaceBefore = new Set([".",",","!","?",";",":",")"]);
  const noSpaceAfter = new Set(["("]);
  let out = "";
  for (const t of tokens) {
    if (!out) { out = t; continue; }
    const last = out[out.length-1];
    if (noSpaceBefore.has(t) || noSpaceAfter.has(last)) out += t;
    else out += " " + t;
  }
  return out;
}

const server = http.createServer((req, res) => {
  if (req.method !== 'POST' || req.url !== '/infer') {
    res.writeHead(404); return res.end();
  }

  let body = "";
  req.on('data', chunk => body += chunk);
  req.on('end', () => {
    reload();
    if (!state) {
      res.writeHead(503, {'Content-Type':'application/json'});
      return res.end(JSON.stringify({ error: "Model not found. Train first." }));
    }

    let json;
    try { json = JSON.parse(body); } catch {
      res.writeHead(400, {'Content-Type':'application/json'});
      return res.end(JSON.stringify({ error: "Bad JSON" }));
    }

    const context = String(json.context || "");
    const maxTokens = Math.max(1, Math.min(200, Number(json.max_tokens || 140)));
    const temperature = Math.max(0.2, Math.min(2.0, Number(json.temperature || 0.9)));
    const topK = Math.max(5, Math.min(200, Number(json.top_k || 40)));

    const toks = ["<BOS>", ...tokenize(context), "<EOS>"];
    const ids = idsFromTokens(toks, state.stoi);

    const ctxLen = state.model.ctxLen;
    const bosId = state.stoi.get("<BOS>") ?? 2;

    let ctx = [];
    for (let i=ids.length-1-ctxLen; i<ids.length-1; i++) {
      ctx.push(i < 0 ? bosId : ids[i]);
    }

    const out = [];
    for (let t=0; t<maxTokens; t++) {
      const probs = state.model.predictProbs(ctx);
      const nextId = sampleFromProbs(probs, topK, temperature);
      const tok = state.itos[nextId] || "<UNK>";
      if (tok === "<EOS>") break;
      if (tok !== "<BOS>" && tok !== "<PAD>") out.push(tok);

      ctx = ctx.slice(1);
      ctx.push(nextId);
    }

    res.writeHead(200, {'Content-Type':'application/json'});
    res.end(JSON.stringify({ text: detok(out) }));
  });
});

server.listen(3030, '0.0.0.0', () => {
  console.log("Inference server listening on :3030");
});
