function randn() {
  return (Math.random() * 2 - 1) * 0.02;
}

function softmax(logits) {
  let max = -Infinity;
  for (const x of logits) if (x > max) max = x;
  const exps = logits.map(x => Math.exp(x - max));
  const sum = exps.reduce((a,b)=>a+b,0);
  return exps.map(x => x / sum);
}

function relu(x) { return x > 0 ? x : 0; }

class TinyLM {
  constructor(vocabSize, ctxLen=8, embDim=32, hiddenDim=128) {
    this.vocabSize = vocabSize;
    this.ctxLen = ctxLen;
    this.embDim = embDim;
    this.hiddenDim = hiddenDim;

    this.E = Array.from({length: vocabSize}, () => Array.from({length: embDim}, randn));
    this.W1 = Array.from({length: ctxLen*embDim}, () => Array.from({length: hiddenDim}, randn));
    this.b1 = Array.from({length: hiddenDim}, () => 0);
    this.W2 = Array.from({length: hiddenDim}, () => Array.from({length: vocabSize}, randn));
    this.b2 = Array.from({length: vocabSize}, () => 0);
  }

  forward(ctxIds) {
    const x = [];
    for (let i=0;i<this.ctxLen;i++) {
      const id = ctxIds[i];
      const emb = this.E[id] || this.E[0];
      for (let j=0;j<this.embDim;j++) x.push(emb[j]);
    }

    const h = Array(this.hiddenDim).fill(0);
    for (let j=0;j<this.hiddenDim;j++) {
      let s = this.b1[j];
      for (let i=0;i<x.length;i++) s += x[i] * this.W1[i][j];
      h[j] = relu(s);
    }

    const logits = Array(this.vocabSize).fill(0);
    for (let k=0;k<this.vocabSize;k++) {
      let s = this.b2[k];
      for (let j=0;j<this.hiddenDim;j++) s += h[j] * this.W2[j][k];
      logits[k] = s;
    }

    return { x, h, logits };
  }

  predictProbs(ctxIds) {
    const { logits } = this.forward(ctxIds);
    return softmax(logits);
  }

  trainStep(ctxIds, targetId, lr=0.03) {
    const { x, h, logits } = this.forward(ctxIds);
    const probs = softmax(logits);
    const loss = -Math.log(Math.max(1e-12, probs[targetId]));

    const dlogits = probs.slice();
    dlogits[targetId] -= 1;

    for (let j=0;j<this.hiddenDim;j++) {
      const hj = h[j];
      if (hj === 0) continue;
      for (let k=0;k<this.vocabSize;k++) {
        this.W2[j][k] -= lr * (hj * dlogits[k]);
      }
    }
    for (let k=0;k<this.vocabSize;k++) this.b2[k] -= lr * dlogits[k];

    const dh = Array(this.hiddenDim).fill(0);
    for (let j=0;j<this.hiddenDim;j++) {
      let s = 0;
      for (let k=0;k<this.vocabSize;k++) s += this.W2[j][k] * dlogits[k];
      dh[j] = (h[j] > 0) ? s : 0;
    }

    for (let j=0;j<this.hiddenDim;j++) {
      const g = dh[j];
      if (g === 0) continue;
      this.b1[j] -= lr * g;
      for (let i=0;i<x.length;i++) {
        this.W1[i][j] -= lr * (x[i] * g);
      }
    }

    const dx = Array(x.length).fill(0);
    for (let i=0;i<x.length;i++) {
      let s = 0;
      for (let j=0;j<this.hiddenDim;j++) s += this.W1[i][j] * dh[j];
      dx[i] = s;
    }

    for (let pos=0;pos<this.ctxLen;pos++) {
      const id = ctxIds[pos];
      const base = pos*this.embDim;
      for (let d=0;d<this.embDim;d++) {
        this.E[id][d] -= lr * dx[base + d];
      }
    }

    return loss;
  }

  toJSON() {
    return {
      vocabSize: this.vocabSize,
      ctxLen: this.ctxLen,
      embDim: this.embDim,
      hiddenDim: this.hiddenDim,
      E: this.E, W1: this.W1, b1: this.b1, W2: this.W2, b2: this.b2
    };
  }

  static fromJSON(obj) {
    const m = new TinyLM(obj.vocabSize, obj.ctxLen, obj.embDim, obj.hiddenDim);
    m.E = obj.E; m.W1 = obj.W1; m.b1 = obj.b1; m.W2 = obj.W2; m.b2 = obj.b2;
    return m;
  }
}

module.exports = { TinyLM };
