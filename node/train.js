const fs = require('fs');
const path = require('path');
const { tokenize } = require('./tokenize');
const { TinyLM } = require('./model');

function buildVocab(tokens, maxVocab=10000) {
  const freq = new Map();
  for (const t of tokens) freq.set(t, (freq.get(t)||0)+1);

  const specials = ["<PAD>","<UNK>","<BOS>","<EOS>"];
  const sorted = [...freq.entries()].sort((a,b)=>b[1]-a[1]).map(x=>x[0]);
  const vocab = specials.concat(sorted.filter(t=>!specials.includes(t)).slice(0, maxVocab - specials.length));

  const stoi = new Map(vocab.map((t,i)=>[t,i]));
  return { vocab, stoi };
}

function idsFromTokens(tokens, stoi) {
  const unk = stoi.get("<UNK>");
  return tokens.map(t => stoi.has(t) ? stoi.get(t) : unk);
}

function main() {
  const dataPath = path.join(__dirname, 'user_corpus.txt');
  if (!fs.existsSync(dataPath)) {
    console.error("Missing user_corpus.txt. Run export_from_sqlite.js first.");
    process.exit(1);
  }

  const text = fs.readFileSync(dataPath, 'utf8');
  const toks = tokenize(text);
  const all = ["<BOS>", ...toks, "<EOS>"];

  const { vocab, stoi } = buildVocab(all, 10000);
  fs.mkdirSync(path.join(__dirname, '../data/model'), { recursive: true });
  fs.writeFileSync(path.join(__dirname, '../data/model/vocab.json'), JSON.stringify(vocab));

  const ids = idsFromTokens(all, stoi);

  const ctxLen = 8, embDim = 32, hiddenDim = 128;
  const model = new TinyLM(vocab.length, ctxLen, embDim, hiddenDim);

  const epochs = 2;
  const lr = 0.03;

  const bos = stoi.get("<BOS>");

  function getCtx(i) {
    const ctx = [];
    for (let j=i-ctxLen; j<i; j++) {
      if (j < 0) ctx.push(bos);
      else ctx.push(ids[j]);
    }
    return ctx;
  }

  let steps = 0;
  for (let e=0; e<epochs; e++) {
    let totalLoss = 0;
    let count = 0;

    for (let i=1; i<ids.length; i++) {
      const ctx = getCtx(i);
      const target = ids[i];
      const loss = model.trainStep(ctx, target, lr);
      totalLoss += loss;
      count++;
      steps++;

      if (steps % 5000 === 0) {
        console.log(`epoch ${e+1}/${epochs} step ${steps} avg_loss ${(totalLoss/count).toFixed(4)}`);
      }
    }
    console.log(`epoch ${e+1} done avg_loss ${(totalLoss/count).toFixed(4)}`);
  }

  fs.writeFileSync(path.join(__dirname, '../data/model/weights.json'), JSON.stringify(model.toJSON()));
  console.log("Saved model to /data/model/{vocab.json,weights.json}");
}

main();
