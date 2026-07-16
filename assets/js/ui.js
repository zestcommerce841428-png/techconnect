// Shared UI behaviors: mobile nav, user dropdown, "/" search shortcut,
// copy-link buttons, and toast notifications.
(function () {
  // Mobile nav
  var navToggle = document.querySelector('[data-mobile-nav-toggle]');
  var mobileNav = document.querySelector('[data-mobile-nav]');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('hidden') === false;
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // User dropdown
  var menuToggle = document.querySelector('[data-user-menu-toggle]');
  var menuPanel = document.querySelector('[data-user-menu-panel]');
  if (menuToggle && menuPanel) {
    menuToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = menuPanel.classList.toggle('hidden') === false;
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function () {
      menuPanel.classList.add('hidden');
      menuToggle.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') menuPanel.classList.add('hidden');
    });
  }

  // "/" focuses the header search (unless already typing in a field)
  var headerSearch = document.querySelector('[data-header-search]');
  if (headerSearch) {
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
        e.preventDefault();
        headerSearch.focus();
      }
    });
  }

  // Toasts
  window.showToast = function (message, kind) {
    var wrap = document.querySelector('[data-toast-wrap]');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.setAttribute('data-toast-wrap', '');
      wrap.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.textContent = message;
    el.style.cssText = 'padding:.6rem 1rem;border-radius:.5rem;color:#fff;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.25);opacity:0;transition:opacity .2s;'
      + 'background:' + (kind === 'error' ? '#dc2626' : '#16a34a') + ';';
    wrap.appendChild(el);
    requestAnimationFrame(function () { el.style.opacity = '1'; });
    setTimeout(function () {
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 300);
    }, 3000);
  };

  // Copy-link buttons
  document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(btn.getAttribute('data-copy-link')).then(function () {
        window.showToast('Link copied to clipboard');
      }, function () {
        window.showToast('Could not copy link', 'error');
      });
    });
  });

  // Comment edit toggle (question.php)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-comment-edit-toggle]');
    if (!btn) return;
    var wrap = btn.closest('[data-comment-id]');
    if (!wrap) return;
    var view = wrap.querySelector('[data-comment-view]');
    var form = wrap.querySelector('[data-comment-edit-form]');
    if (!view || !form) return;
    var editing = !form.classList.contains('hidden');
    form.classList.toggle('hidden', editing);
    view.classList.toggle('hidden', !editing);
  });

  // Newsletter subscribe form (footer)
  var newsletterForm = document.querySelector('[data-newsletter-form]');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(newsletterForm);
      fetch('/api/newsletter_subscribe.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          window.showToast(data.message || data.error, data.success ? 'success' : 'error');
          if (data.success) newsletterForm.reset();
        })
        .catch(function () { window.showToast('Something went wrong.', 'error'); });
    });
  }
})();
