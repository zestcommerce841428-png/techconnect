<?php
/** @var string $textareaId id of the textarea this toolbar controls */
?>
<div data-md-toolbar class="flex flex-wrap gap-1 border border-b-0 rounded-t bg-slate-50 px-2 py-1.5">
  <button type="button" data-md-action="bold" class="px-2 py-1 text-xs font-bold hover:bg-slate-200 rounded" title="Bold" aria-label="Bold">B</button>
  <button type="button" data-md-action="italic" class="px-2 py-1 text-xs italic hover:bg-slate-200 rounded" title="Italic" aria-label="Italic">I</button>
  <button type="button" data-md-action="code" class="px-2 py-1 text-xs font-mono hover:bg-slate-200 rounded" title="Inline code" aria-label="Inline code">&lt;/&gt;</button>
  <button type="button" data-md-action="codeblock" class="px-2 py-1 text-xs font-mono hover:bg-slate-200 rounded" title="Code block" aria-label="Code block">{ }</button>
  <button type="button" data-md-action="link" class="px-2 py-1 text-xs hover:bg-slate-200 rounded" title="Link" aria-label="Insert link">🔗</button>
  <button type="button" data-md-action="list" class="px-2 py-1 text-xs hover:bg-slate-200 rounded" title="List" aria-label="Bulleted list">&bull; List</button>
  <label class="px-2 py-1 text-xs hover:bg-slate-200 rounded cursor-pointer" title="Upload image or file">
    📎 Attach
    <input type="file" data-md-upload data-target="<?= e($textareaId) ?>" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" class="hidden" aria-label="Upload image or file">
  </label>
  <span data-upload-status class="text-xs text-slate-400 self-center"></span>
</div>
