<?php
/** Shared "Report" control usable on questions, answers, comments, and user profiles. */
function report_reasons(): array
{
    return ['spam' => 'Spam or advertising', 'harassment' => 'Harassment or hate speech',
            'inappropriate' => 'Inappropriate content', 'off_topic' => 'Off-topic / low quality',
            'other' => 'Something else'];
}

function render_report_form(string $targetType, int $targetId, ?array $user): void
{
    if (!$user) return;
    echo '<details class="inline-block ml-2">
            <summary class="text-xs text-slate-400 hover:text-red-600 hover:underline cursor-pointer inline">Report</summary>
            <form method="post" action="/api/report.php" class="mt-1 flex flex-wrap items-center gap-1.5 bg-slate-50 dark:bg-slate-800 border rounded p-2">'
        . csrf_field()
        . '<input type="hidden" name="target_type" value="' . e($targetType) . '">
           <input type="hidden" name="target_id" value="' . $targetId . '">
           <input type="hidden" name="redirect" value="' . e($_SERVER['REQUEST_URI'] ?? '/') . '">
           <select name="reason_key" required class="border rounded px-2 py-1 text-xs">
             <option value="">Reason&hellip;</option>';
    foreach (report_reasons() as $key => $label) {
        echo '<option value="' . e($key) . '">' . e($label) . '</option>';
    }
    echo '   </select>
           <button type="submit" class="text-xs bg-red-600 hover:bg-red-500 text-white px-2 py-1 rounded">Submit</button>
         </form>
       </details>';
}
