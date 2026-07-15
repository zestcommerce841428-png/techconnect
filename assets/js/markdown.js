/**
 * Lightweight markdown editor: toolbar buttons wrap the current textarea selection,
 * plus a debounced preview pane rendered via /api/render_markdown.php.
 * Attach with: initMarkdownEditor(document.querySelector('[data-markdown-editor]'))
 */
function initMarkdownEditor(root) {
  if (!root) return;
  const textarea = root.querySelector('textarea');
  const preview = root.querySelector('[data-md-preview]');
  const toolbar = root.querySelector('[data-md-toolbar]');
  if (!textarea) return;

  const wrap = (before, after = before) => {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.slice(start, end) || 'text';
    textarea.setRangeText(before + selected + after, start, end, 'end');
    textarea.focus();
  };

  const actions = {
    bold: () => wrap('**'),
    italic: () => wrap('*'),
    code: () => wrap('`'),
    codeblock: () => wrap('```\n', '\n```'),
    link: () => wrap('[', '](https://)'),
    list: () => wrap('- ', ''),
  };

  if (toolbar) {
    toolbar.querySelectorAll('[data-md-action]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const action = actions[btn.dataset.mdAction];
        if (action) action();
        schedulePreview();
      });
    });
  }

  let timer = null;
  function schedulePreview() {
    if (!preview) return;
    clearTimeout(timer);
    timer = setTimeout(async () => {
      try {
        const res = await fetch('/api/render_markdown.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ body: textarea.value, csrf_token: window.CSRF_TOKEN }),
        });
        const data = await res.json();
        preview.innerHTML = data.html || '';
      } catch (err) {
        // Preview is best-effort; ignore network errors silently.
      }
    }, 400);
  }

  textarea.addEventListener('input', schedulePreview);

  const uploadInput = root.querySelector('[data-md-upload]');
  const uploadStatus = root.querySelector('[data-upload-status]');
  if (uploadInput) {
    uploadInput.addEventListener('change', async () => {
      const file = uploadInput.files[0];
      if (!file) return;
      if (uploadStatus) uploadStatus.textContent = 'Uploading...';
      const formData = new FormData();
      formData.append('file', file);
      formData.append('csrf_token', window.CSRF_TOKEN);
      try {
        const res = await fetch('/api/upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (!res.ok) {
          if (uploadStatus) uploadStatus.textContent = data.error || 'Upload failed.';
          return;
        }
        const pos = textarea.selectionStart;
        textarea.setRangeText(data.markdown + '\n', pos, pos, 'end');
        if (uploadStatus) uploadStatus.textContent = 'Attached: ' + file.name;
        schedulePreview();
      } catch (err) {
        if (uploadStatus) uploadStatus.textContent = 'Network error, please retry.';
      } finally {
        uploadInput.value = '';
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-markdown-editor]').forEach(initMarkdownEditor);
});
