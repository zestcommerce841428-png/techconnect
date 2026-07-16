document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-follow]');
  if (!btn) return;
  const type = btn.dataset.type;
  const id = btn.dataset.id;
  btn.disabled = true;
  try {
    const res = await fetch('/api/follow.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id, csrf_token: window.CSRF_TOKEN }),
    });
    const data = await res.json();
    if (!res.ok) {
      alert(data.error || 'Action failed.');
      return;
    }
    btn.textContent = data.following ? 'Following' : 'Follow';
    btn.classList.toggle('bg-indigo-600', data.following);
    btn.classList.toggle('text-white', data.following);
    btn.classList.toggle('bg-slate-100', !data.following);
  } catch (err) {
    alert('Network error, please try again.');
  } finally {
    btn.disabled = false;
  }
});
