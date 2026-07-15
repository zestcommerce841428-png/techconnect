<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webauthn.php';

use Webauthn\PublicKeyCredentialRequestOptions;

header('Content-Type: application/json');

// Discoverable/"usernameless" flow: the browser's own passkey picker handles
// identifying which credential to use, so no allowCredentials list is needed.
$challenge = random_bytes(32);
$options = PublicKeyCredentialRequestOptions::create($challenge)
    ->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED)
    ->setTimeout(60000);

$_SESSION['passkey_auth_challenge'] = base64_encode($challenge);

echo json_encode($options);
