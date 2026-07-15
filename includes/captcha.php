<?php
/** Renders the configured captcha's field/widget, or nothing if captcha is off. */
function captcha_field(string $action = 'submit'): string
{
    $provider = setting('captcha_provider', 'none');
    if ($provider === 'recaptcha' && setting('recaptcha_site_key')) {
        $siteKey = e(setting('recaptcha_site_key'));
        return "<input type=\"hidden\" name=\"recaptcha_token\" data-recaptcha-token>"
            . "<script>grecaptcha.ready(function(){grecaptcha.execute('{$siteKey}',{action:'{$action}'}).then(function(t){"
            . "document.querySelectorAll('[data-recaptcha-token]').forEach(function(el){el.value=t;});});});</script>";
    }
    if ($provider === 'turnstile' && setting('turnstile_site_key')) {
        return '<div class="cf-turnstile" data-sitekey="' . e(setting('turnstile_site_key')) . '"></div>';
    }
    return '';
}

/** Server-side verification; returns true if captcha is disabled (nothing to check) or passes. */
function captcha_verify(): bool
{
    $provider = setting('captcha_provider', 'none');
    if ($provider === 'recaptcha') {
        $secret = setting('recaptcha_secret_key');
        $token = $_POST['recaptcha_token'] ?? '';
        if (!$secret || !$token) return $secret === '';
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = json_decode((string) curl_exec($ch), true);
        curl_close($ch);
        return !empty($result['success']) && ($result['score'] ?? 1) >= 0.5;
    }
    if ($provider === 'turnstile') {
        $secret = setting('turnstile_secret_key');
        $token = $_POST['cf-turnstile-response'] ?? '';
        if (!$secret || !$token) return $secret === '';
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = json_decode((string) curl_exec($ch), true);
        curl_close($ch);
        return !empty($result['success']);
    }
    return true;
}
