document.addEventListener('DOMContentLoaded', () => {
  const titleInput = document.getElementById('ask-title');
  const container = document.getElementById('related-questions');
  const list = document.getElementById('related-list');
  if (!titleInput || !container || !list) return;

  let timer = null;
  titleInput.addEventListener('input', () => {
    clearTimeout(timer);
    const value = titleInput.value.trim();
    if (value.length < 8) {
      container.classList.add('hidden');
      return;
    }
    timer = setTimeout(async () => {
      try {
        const res = await fetch('/api/related_questions.php?q=' + encodeURIComponent(value));
        const data = await res.json();
        if (!data.results || !data.results.length) {
          container.classList.add('hidden');
          return;
        }
        list.innerHTML = '';
        data.results.forEach((r) => {
          const a = document.createElement('a');
          a.href = '/question.php?slug=' + encodeURIComponent(r.slug);
          a.target = '_blank';
          a.className = 'block text-sm text-indigo-600 hover:underline';
          a.textContent = `${r.title} (${r.answer_count} answers)`;
          list.appendChild(a);
        });
        container.classList.remove('hidden');
      } catch (err) {
        container.classList.add('hidden');
      }
    }, 500);
  });
});
