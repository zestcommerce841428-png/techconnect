<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webauthn.php';

use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf_token'] ?? '', $body['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid session, please refresh and try again.']);
    exit;
}

$challengeB64 = $_SESSION['passkey_register_challenge'] ?? null;
if (!$challengeB64) {
    http_response_code(400);
    echo json_encode(['error' => 'No registration in progress. Please try again.']);
    exit;
}

try {
    $credential = webauthn_credential_loader()->load(json_encode($body['credential']));
    $response = $credential->getResponse();
    if (!$response instanceof AuthenticatorAttestationResponse) {
        throw new RuntimeException('Unexpected response type.');
    }

    $userEntity = PublicKeyCredentialUserEntity::create($user['username'], (string) $user['id'], $user['username']);
    $options = PublicKeyCredentialCreationOptions::create(
        webauthn_rp_entity(),
        $userEntity,
        base64_decode($challengeB64),
        webauthn_pubkey_params()
    );

    $source = webauthn_attestation_validator()->check($response, $options, webauthn_current_request());
    webauthn_credential_repository()->saveCredentialSource($source);

    $label = trim($body['label'] ?? '') ?: 'Passkey';
    db()->prepare('UPDATE webauthn_credentials SET label = ? WHERE credential_id = ?')
        ->execute([mb_substr($label, 0, 100), $source->getPublicKeyCredentialId()]);

    unset($_SESSION['passkey_register_challenge']);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    unset($_SESSION['passkey_register_challenge']);
    http_response_code(422);
    echo json_encode(['error' => 'Could not register this passkey: ' . $e->getMessage()]);
}
