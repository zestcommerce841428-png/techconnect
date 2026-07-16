<?php
/**
 * Word-level diff for revision history. Tokenizes on whitespace boundaries
 * (keeping the whitespace itself as a token) and finds the longest common
 * subsequence, so unchanged words don't shift around visually.
 *
 * Guarded against pathological input: LCS is O(n*m) token pairs, so very
 * long texts fall back to a plain "before/after" pair instead of diffing.
 */
function text_diff(string $old, string $new): string
{
    if ($old === $new) {
        return '<span>' . e($new) . '</span>';
    }

    $oldTokens = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    $newTokens = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

    if (count($oldTokens) * count($newTokens) > 400000) {
        return '<div class="text-xs text-red-600 line-through mb-1">' . nl2br(e($old)) . '</div>'
             . '<div class="text-xs text-green-700">' . nl2br(e($new)) . '</div>';
    }

    $m = count($oldTokens);
    $n = count($newTokens);
    $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($i = $m - 1; $i >= 0; $i--) {
        for ($j = $n - 1; $j >= 0; $j--) {
            $lcs[$i][$j] = $oldTokens[$i] === $newTokens[$j]
                ? $lcs[$i + 1][$j + 1] + 1
                : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
        }
    }

    $html = '';
    $i = 0; $j = 0;
    while ($i < $m && $j < $n) {
        if ($oldTokens[$i] === $newTokens[$j]) {
            $html .= e($oldTokens[$i]);
            $i++; $j++;
        } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $html .= '<del class="bg-red-100 text-red-700 line-through">' . e($oldTokens[$i]) . '</del>';
            $i++;
        } else {
            $html .= '<ins class="bg-green-100 text-green-800 no-underline">' . e($newTokens[$j]) . '</ins>';
            $j++;
        }
    }
    while ($i < $m) { $html .= '<del class="bg-red-100 text-red-700 line-through">' . e($oldTokens[$i]) . '</del>'; $i++; }
    while ($j < $n) { $html .= '<ins class="bg-green-100 text-green-800 no-underline">' . e($newTokens[$j]) . '</ins>'; $j++; }

    return nl2br($html);
}
