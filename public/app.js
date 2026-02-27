async function sendMessage() {
  const input = document.getElementById('msg');
  const text = input.value.trim();
  if (!text) return;

  appendBubble('user', text);
  input.value = '';

  const res = await fetch('/api/chat.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ conversation_id: window.convId || null, message: text })
  });

  const data = await res.json();
  window.convId = data.conversation_id;

  appendBubble('assistant', data.reply, data.assistant_message_id, data.citations || []);
}

function appendBubble(role, text, assistantMessageId=null, citations=[]) {
  const chat = document.getElementById('chat');
  const wrap = document.createElement('div');
  wrap.className = `wrap ${role}`;

  const bubble = document.createElement('div');
  bubble.className = `bubble ${role}`;
  bubble.textContent = text;
  wrap.appendChild(bubble);

  if (role === 'assistant' && citations.length) {
    const cites = document.createElement('div');
    cites.className = 'citations';
    cites.innerHTML = citations.map((c, i) => {
      const safeUrl = c.url.replace(/"/g, '&quot;');
      const title = (c.title || c.url).replace(/</g,'&lt;');
      return `<div>[${i+1}] <a href="${safeUrl}" target="_blank" rel="noreferrer">${title}</a></div>`;
    }).join('');
    wrap.appendChild(cites);
  }

  if (role === 'assistant' && assistantMessageId) {
    const fb = document.createElement('div');
    fb.className = 'feedback';

    const up = document.createElement('button');
    up.textContent = '👍';
    up.onclick = () => sendFeedback(assistantMessageId, 1);

    const down = document.createElement('button');
    down.textContent = '👎';
    down.onclick = () => sendFeedback(assistantMessageId, -1);

    fb.appendChild(up); fb.appendChild(down);
    wrap.appendChild(fb);
  }

  chat.appendChild(wrap);
  chat.scrollTop = chat.scrollHeight;
}

async function sendFeedback(messageId, score) {
  await fetch('/api/feedback.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ message_id: messageId, score })
  });
}
