<?php
/**
 * Standard OAuth2 (authorization-code) provider definitions. Each entry gives
 * everything the generic engine (oauth.php) needs: where to send the user,
 * where to exchange the code, where to fetch the profile, and how to read
 * a stable user id/email/name out of that profile response.
 *
 * Telegram isn't standard OAuth2 (it uses the "Telegram Login Widget" —
 * a signed-payload redirect, no code exchange) so it's handled separately
 * in telegram_login.php rather than through this table.
 */
function oauth_provider_defs(): array
{
    return [
        'google' => [
            'label' => 'Google',
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope' => 'openid email profile',
            'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => $p['sub'] ?? null, 'email' => $p['email'] ?? null, 'name' => $p['name'] ?? null],
        ],
        'facebook' => [
            'label' => 'Facebook',
            'authorize_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'profile_url' => 'https://graph.facebook.com/me?fields=id,name,email',
            'scope' => 'email public_profile',
            'fields' => ['client_id' => 'App ID', 'client_secret' => 'App Secret'],
            'map' => fn($p) => ['id' => $p['id'] ?? null, 'email' => $p['email'] ?? null, 'name' => $p['name'] ?? null],
        ],
        'github' => [
            'label' => 'GitHub',
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'profile_url' => 'https://api.github.com/user',
            'scope' => 'read:user user:email',
            'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => (string) ($p['id'] ?? ''), 'email' => $p['email'] ?? null, 'name' => $p['name'] ?? $p['login'] ?? null],
        ],
        'microsoft' => [
            'label' => 'Microsoft',
            'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'profile_url' => 'https://graph.microsoft.com/oidc/userinfo',
            'scope' => 'openid email profile',
            'fields' => ['client_id' => 'Application (client) ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => $p['sub'] ?? null, 'email' => $p['email'] ?? null, 'name' => $p['name'] ?? null],
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'profile_url' => 'https://api.linkedin.com/v2/userinfo',
            'scope' => 'openid email profile',
            'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => $p['sub'] ?? null, 'email' => $p['email'] ?? null, 'name' => $p['name'] ?? null],
        ],
        'twitter' => [
            'label' => 'X (Twitter)',
            'authorize_url' => 'https://twitter.com/i/oauth2/authorize',
            'token_url' => 'https://api.twitter.com/2/oauth2/token',
            'profile_url' => 'https://api.twitter.com/2/users/me?user.fields=name,username',
            'scope' => 'tweet.read users.read',
            'pkce' => true,
            'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => $p['data']['id'] ?? null, 'email' => null, 'name' => $p['data']['name'] ?? $p['data']['username'] ?? null],
        ],
        'zoho' => [
            'label' => 'Zoho',
            'authorize_url' => 'https://accounts.zoho.com/oauth/v2/auth',
            'token_url' => 'https://accounts.zoho.com/oauth/v2/token',
            'profile_url' => 'https://accounts.zoho.com/oauth/user/info',
            'scope' => 'AaaServer.profile.READ',
            'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client Secret'],
            'map' => fn($p) => ['id' => $p['ZUID'] ?? null, 'email' => $p['Email'] ?? null, 'name' => trim(($p['First_Name'] ?? '') . ' ' . ($p['Last_Name'] ?? ''))],
        ],
    ];
}
