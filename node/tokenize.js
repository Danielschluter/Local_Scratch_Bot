function tokenize(text) {
  const lower = (text || "").toLowerCase();
  const re = /[a-z0-9]+|[^\s\p{L}\p{N}]/gu;
  return lower.match(re) || [];
}

module.exports = { tokenize };
