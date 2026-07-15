document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-vote]');
  if (!btn) return;
  const type = btn.dataset.type;
  const id = btn.dataset.id;
  const value = parseInt(btn.dataset.vote, 10);
  const scoreEl = document.querySelector(`[data-score="${type}-${id}"]`);

  try {
    const res = await fetch('/api/vote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id, value, csrf_token: window.CSRF_TOKEN }),
    });
    const data = await res.json();
    if (!res.ok) {
      alert(data.error || 'Vote failed.');
      return;
    }
    if (scoreEl) scoreEl.textContent = data.score;
  } catch (err) {
    alert('Network error, please try again.');
  }
});
