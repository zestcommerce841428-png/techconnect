<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webauthn.php';

use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredentialRequestOptions;

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $body['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid session, please refresh and try again.']);
    exit;
}
if (!rate_limit('passkey_login', 10, 300)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts. Please wait a few minutes.']);
    exit;
}

$challengeB64 = $_SESSION['passkey_auth_challenge'] ?? null;
if (!$challengeB64) {
    http_response_code(400);
    echo json_encode(['error' => 'No login attempt in progress. Please try again.']);
    exit;
}

try {
    $credential = webauthn_credential_loader()->load(json_encode($body['credential']));
    $response = $credential->getResponse();
    if (!$response instanceof AuthenticatorAssertionResponse) {
        throw new RuntimeException('Unexpected response type.');
    }

    $options = PublicKeyCredentialRequestOptions::create(base64_decode($challengeB64))
        ->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED);

    $source = webauthn_assertion_validator()->check(
        $credential->getRawId(),
        $response,
        $options,
        webauthn_current_request(),
        null
    );

    unset($_SESSION['passkey_auth_challenge']);

    $userId = (int) $source->getUserHandle();
    db()->prepare('UPDATE webauthn_credentials SET last_used_at = NOW() WHERE credential_id = ?')
        ->execute([$source->getPublicKeyCredentialId()]);

    login_user($userId, !empty($body['remember']));
    echo json_encode(['success' => true, 'redirect' => '/']);
} catch (Throwable $e) {
    unset($_SESSION['passkey_auth_challenge']);
    http_response_code(422);
    echo json_encode(['error' => 'Passkey sign-in failed: ' . $e->getMessage()]);
}
