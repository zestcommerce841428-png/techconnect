document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-save-question]');
  if (!btn) return;
  const id = btn.dataset.id;
  btn.disabled = true;
  try {
    const res = await fetch('/api/save_question.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question_id: id, csrf_token: window.CSRF_TOKEN }),
    });
    const data = await res.json();
    if (!res.ok) {
      alert(data.error || 'Action failed.');
      return;
    }
    btn.textContent = data.saved ? '★ Saved' : '☆ Save';
  } catch (err) {
    alert('Network error, please try again.');
  } finally {
    btn.disabled = false;
  }
});
