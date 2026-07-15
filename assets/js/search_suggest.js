// Live typeahead suggestions for the header search box.
(function () {
  var input = document.querySelector('[data-header-search]');
  var box = document.querySelector('[data-search-suggestions]');
  if (!input || !box) return;

  var timer = null;
  var activeIndex = -1;
  var items = [];

  function hide() {
    box.classList.add('hidden');
    box.innerHTML = '';
    activeIndex = -1;
    items = [];
  }

  function render(results, query) {
    items = results;
    activeIndex = -1;
    if (!results.length) { hide(); return; }
    box.innerHTML = results.map(function (r, i) {
      return '<a href="/q/' + encodeURIComponent(r.slug) + '" data-suggest-index="' + i +
        '" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 border-b border-slate-100 dark:border-slate-700 last:border-0">' +
        escapeHtml(r.title) + '<span class="block text-xs text-slate-400">' + r.answer_count + ' answer' + (r.answer_count === 1 ? '' : 's') + '</span></a>';
    }).join('') + '<a href="/search?q=' + encodeURIComponent(query) + '" class="block px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700">See all results for "' + escapeHtml(query) + '"</a>';
    box.classList.remove('hidden');
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function highlight() {
    var links = box.querySelectorAll('a');
    links.forEach(function (a, i) {
      a.classList.toggle('bg-slate-100', i === activeIndex);
      a.classList.toggle('dark:bg-slate-700', i === activeIndex);
    });
  }

  input.addEventListener('input', function () {
    var q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { hide(); return; }
    timer = setTimeout(function () {
      fetch('/api/search_suggest.php?q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) { render(data.results || [], q); })
        .catch(function () { hide(); });
    }, 200);
  });

  input.addEventListener('keydown', function (e) {
    var links = box.querySelectorAll('a');
    if (!links.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, links.length - 1);
      highlight();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, -1);
      highlight();
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      links[activeIndex].click();
    } else if (e.key === 'Escape') {
      hide();
    }
  });

  document.addEventListener('click', function (e) {
    if (!box.contains(e.target) && e.target !== input) hide();
  });
})();
