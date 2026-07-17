<?php
/**
 * Single source of truth for admin destinations.
 *
 * Consumed by the command palette (Ctrl/Cmd+K) and the dashboard tool grid.
 * Before this file the two kept private lists and drifted apart: Trash and
 * Storage both shipped, reached the palette, and never appeared on the
 * dashboard — the grid covered 16 of 41 tools and nobody noticed, because
 * nothing fails when a link is merely absent. Add a tool here once and it
 * shows up in both surfaces, grouped and searchable.
 *
 * The sidebar in admin_header.php deliberately stays separate: it is
 * persistent chrome carrying live pending-work badges, not a directory.
 *
 * Item shape: [label, url, icon, description, keywords, adminOnly]
 * `adminOnly` items are hidden from moderators — the same RBAC rule the
 * sidebar and palette already apply.
 */

/** Grouped destinations, already filtered for the viewer's role. */
function admin_nav_groups(bool $isAdminRole): array
{
    $groups = [
        '👥 Community' => [
            ['Users', '/admin/users', '👥', 'Roles, bans, verification', 'members accounts ban suspend', false],
            ['Moderation', '/admin/moderation', '🛡️', 'Reported content queue', 'reports flags abuse', false],
            ['Feedback', '/admin/feedback', '📝', 'Bug reports & ideas', 'bugs suggestions', false],
            ['Groups', '/admin/groups', '🏘️', 'Communities & members', 'communities', false],
            ['Experts', '/admin/experts', '🎓', 'Applications & approvals', 'consultants', false],
            ['Jobs', '/admin/jobs', '💼', 'Job board moderation', 'careers vacancies', false],
            ['Badges', '/admin/badges', '🏅', 'Achievements & tiers', 'gamification rewards', true],
        ],
        '❓ Q&A content' => [
            ['Import Questions', '/admin/import_questions', '📥', 'Bulk CSV seeding', 'csv bulk seed content', true],
            ['Merge Questions', '/admin/merge_questions', '🔀', 'Combine duplicates', 'duplicate combine', false],
            ['Suggested Edits', '/admin/suggested_edits', '✏️', 'Community edit review', 'revisions review', false],
            ['Categories', '/admin/categories', '🗂️', 'Topics & bulk visibility', 'topics taxonomy', false],
            ['Tags', '/admin/tags', '🏷️', 'Tag catalog', 'labels taxonomy', false],
            ['Uploads', '/admin/uploads', '🖼️', 'Media files & images', 'media files', false],
            ['Trash', '/admin/trash', '🗑️', 'Restore deleted items', 'deleted recover undo bin', false],
        ],
        '📰 Publishing' => [
            ['Blog', '/admin/blog', '✍️', 'Posts & scheduling', 'articles writing', false],
            ['Pages', '/admin/pages', '📄', 'CMS pages', 'static content', false],
            ['Announcements', '/admin/announcements', '📢', 'Site-wide banner', 'notice broadcast', false],
            ['Testimonials', '/admin/testimonials', '💬', 'Reviews & quotes', 'quotes social proof', false],
            ['Changelog', '/admin/changelog', '📜', 'Public release notes', 'releases updates', false],
            ['Roadmap', '/admin/roadmap', '🗺️', 'Planned features & votes', 'planned voting', false],
            ['Community Polls', '/admin/site_polls', '📊', 'Site-wide polls', 'vote survey', true],
        ],
        '📈 Growth & SEO' => [
            ['Analytics', '/admin/analytics', '📊', 'Traffic & engagement', 'charts insights stats', false],
            ['Newsletter', '/admin/newsletter', '📧', 'Campaigns & subscribers', 'email broadcast', true],
            ['Redirects', '/admin/redirects', '↪️', '301 URL redirects', 'urls seo moved', true],
        ],
        '💳 Money' => [
            ['Orders', '/admin/orders', '💳', 'Payments & invoices', 'purchases billing', true],
            ['Payment Settings', '/admin/payment_settings', '🏦', 'Stripe, Razorpay, PayPal', 'gateway test live', true],
            ['Currencies', '/admin/currencies', '💱', 'Exchange rates', 'money conversion', true],
            ['Expert Payouts', '/admin/expert_payouts', '💰', 'Pay consultants', 'money payouts', true],
            ['Ad Slots', '/admin/ad_slots', '📣', 'Ad placements', 'ads monetization banners', true],
        ],
        '🛡️ Security' => [
            ['Security', '/admin/security', '🔒', 'Failed logins & lockouts', 'brute force attacks monitoring', true],
            ['IP Blocks', '/admin/ip_blocks', '🚫', 'Blocklist & firewall', 'ban firewall', true],
            ['Audit Log', '/admin/audit_log', '📋', 'Who changed what', 'history trail', true],
            ['Mod Permissions', '/admin/moderator_permissions', '🔑', 'Moderator rights matrix', 'rbac roles access', true],
            ['My 2FA', '/admin/security_2fa', '🔐', 'Your own two-factor', 'totp authenticator', false],
        ],
        '⚙️ System' => [
            ['Site Health', '/admin/site_health', '🩺', 'System checks & status', 'server diagnostics ip', true],
            ['Logs', '/admin/logs', '📃', 'Application error log', 'errors warnings debug', true],
            ['Storage', '/admin/storage_settings', '☁️', 'S3, R2 & CDN uploads', 'cloudflare aws bucket media', true],
            ['Integrations', '/admin/integrations', '🔌', 'Telegram, Tawk & more', 'api chat', true],
            ['Webhooks', '/admin/webhooks', '🪝', 'Outgoing event callbacks', 'events delivery', true],
            ['Social Login', '/admin/social_login_settings', '🔗', 'OAuth providers', 'google facebook', true],
            ['Branding', '/admin/site_settings', '🎨', 'Name, logo, tagline', 'theme identity', true],
            ['Settings', '/admin/settings', '⚙️', 'Global configuration', 'options config', true],
        ],
    ];

    foreach ($groups as $name => $items) {
        $visible = array_values(array_filter($items, fn($i) => !$i[5] || $isAdminRole));
        if ($visible) {
            $groups[$name] = $visible;
        } else {
            unset($groups[$name]); // a moderator sees no admin-only group header
        }
    }
    return $groups;
}

/**
 * Flat list for the command palette: [label, url, keywords, adminOnly].
 * Adds the two destinations that belong in search but not in a tool grid —
 * the dashboard itself, and the way back to the public site.
 */
function admin_nav_palette_items(bool $isAdminRole): array
{
    $items = [['Dashboard', '/admin/dashboard', 'home overview stats', false]];
    foreach (admin_nav_groups($isAdminRole) as $group) {
        foreach ($group as [$label, $url, $icon, $desc, $keywords, $adminOnly]) {
            // Description text joins the haystack so "bulk seed" finds Import
            // Questions even though the label says neither word.
            $items[] = [$label, $url, strtolower($keywords . ' ' . $desc), $adminOnly];
        }
    }
    $items[] = ['View public site', '/index', 'front end home', false];
    return $items;
}
