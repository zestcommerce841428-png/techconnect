<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webauthn.php';

use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialUserEntity;

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$existing = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
$existing->execute([$user['id']]);
$excludeCredentials = array_map(
    fn($id) => new PublicKeyCredentialDescriptor('public-key', $id),
    $existing->fetchAll(PDO::FETCH_COLUMN)
);

$userEntity = PublicKeyCredentialUserEntity::create($user['username'], (string) $user['id'], $user['username']);
$challenge = random_bytes(32);

$options = PublicKeyCredentialCreationOptions::create(
    webauthn_rp_entity(),
    $userEntity,
    $challenge,
    webauthn_pubkey_params()
)->setAuthenticatorSelection(
    AuthenticatorSelectionCriteria::create()->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED)
)->setTimeout(60000);

foreach ($excludeCredentials as $cred) {
    $options->excludeCredential($cred);
}

$_SESSION['passkey_register_challenge'] = base64_encode($challenge);

echo json_encode($options);
