<?php
/**
 * Branded, responsive HTML email templates.
 *
 * Email clients are ~2003-era renderers: Outlook uses Word's engine, Gmail
 * strips <style> blocks and unsupported CSS. So everything here is table-based
 * layout with inline styles only — no flexbox, no grid, no external CSS.
 * Dark mode is signalled via color-scheme; clients that ignore it still render
 * the light palette correctly.
 *
 * Usage:
 *   email_layout('Verify your email', [
 *       email_paragraph('Hi Naushad,'),
 *       email_paragraph('Confirm your address to finish signing up.'),
 *       email_button('Verify my email', $link),
 *       email_muted('This link expires in 48 hours.'),
 *   ]);
 */

/** Wraps content blocks in the branded shell. $blocks is an array of HTML strings. */
function email_layout(string $heading, array $blocks, ?string $preheader = null): string
{
    $siteName = setting('site_name', SITE_NAME);
    $siteUrl = SITE_URL;
    $year = date('Y');
    $body = implode("\n", $blocks);
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    // Preheader: the grey preview text next to the subject in most inboxes.
    // Hidden in the body itself, so it must be zero-height rather than absent.
    $pre = '';
    if ($preheader !== null) {
        $pre = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">'
            . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8')
            . str_repeat('&#8199;&#65279;', 60) . '</div>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{$safeHeading}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
{$pre}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr>
          <td style="background-color:#0f172a;padding:20px 28px;">
            <a href="{$siteUrl}" style="color:#818cf8;font-size:18px;font-weight:bold;text-decoration:none;">{$safeName}</a>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#0f172a;font-weight:600;">{$safeHeading}</h1>
            {$body}
          </td>
        </tr>
        <tr>
          <td style="padding:18px 28px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">
            <p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;">
              &copy; {$year} {$safeName} &middot;
              <a href="{$siteUrl}" style="color:#4f46e5;text-decoration:none;">{$siteUrl}</a>
            </p>
            <p style="margin:6px 0 0;font-size:11px;line-height:1.5;color:#94a3b8;">
              You received this email because you have an account on {$safeName}.
              <a href="{$siteUrl}/notification_settings" style="color:#64748b;text-decoration:underline;">Manage email preferences</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
}

function email_paragraph(string $text, bool $escape = true): string
{
    $content = $escape ? nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) : $text;
    return '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">' . $content . '</p>';
}

function email_muted(string $text): string
{
    return '<p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#94a3b8;">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
}

/** Bulletproof-ish CTA button. Also prints the raw URL, since some clients strip links. */
function email_button(string $label, string $url): string
{
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
  <tr>
    <td align="center" bgcolor="#4f46e5" style="border-radius:8px;">
      <a href="{$safeUrl}" style="display:inline-block;padding:12px 26px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">{$safeLabel}</a>
    </td>
  </tr>
</table>
<p style="margin:0 0 14px;font-size:12px;line-height:1.5;color:#94a3b8;word-break:break-all;">
  If the button doesn't work, paste this into your browser:<br>{$safeUrl}
</p>
HTML;
}

/** Large monospace code block for OTPs — the focal point of the email. */
function email_code(string $code): string
{
    $safe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;width:100%;">'
        . '<tr><td align="center" style="background-color:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:18px;">'
        . '<div style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:30px;font-weight:700;'
        . 'letter-spacing:8px;color:#0f172a;">' . $safe . '</div>'
        . '</td></tr></table>';
}

/** Simple list of link items, e.g. digest emails. $items = [['title' => ..., 'url' => ..., 'meta' => ...]] */
function email_list(array $items): string
{
    $rows = '';
    foreach ($items as $item) {
        $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $meta = isset($item['meta']) ? htmlspecialchars((string) $item['meta'], ENT_QUOTES, 'UTF-8') : '';
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;">'
            . '<a href="' . $url . '" style="font-size:15px;font-weight:500;color:#4f46e5;text-decoration:none;">' . $title . '</a>'
            . ($meta !== '' ? '<div style="font-size:12px;color:#94a3b8;margin-top:3px;">' . $meta . '</div>' : '')
            . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 14px;">' . $rows . '</table>';
}

/** Coloured callout for security-sensitive notices. $tone: info|warning|danger */
function email_alert(string $text, string $tone = 'info'): string
{
    [$bg, $border, $color] = match ($tone) {
        'warning' => ['#fffbeb', '#fcd34d', '#92400e'],
        'danger' => ['#fef2f2', '#fca5a5', '#991b1b'],
        default => ['#eef2ff', '#c7d2fe', '#3730a3'],
    };
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:14px 0;">'
        . '<tr><td style="background-color:' . $bg . ';border:1px solid ' . $border . ';border-radius:8px;padding:12px 14px;'
        . 'font-size:13px;line-height:1.5;color:' . $color . ';">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td></tr></table>';
}
