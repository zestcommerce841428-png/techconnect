<?php
/**
 * Passkey (WebAuthn/FIDO2) support. Uses the vetted web-auth/webauthn-lib
 * library for all cryptographic verification — attestation/assertion
 * signature checks, challenge validation, sign-count replay detection —
 * rather than hand-rolling any of that. This file only wires the library
 * into the app's session/DB/PSR-7 plumbing.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Cose\Algorithm\Manager as CoseAlgorithmManager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

function webauthn_rp_entity(): PublicKeyCredentialRpEntity
{
    $host = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    return new PublicKeyCredentialRpEntity(setting('site_name', SITE_NAME), $host);
}

function webauthn_current_request(): \Psr\Http\Message\ServerRequestInterface
{
    $psr17Factory = new Psr17Factory();
    $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    return $creator->fromGlobals();
}

/** DB-backed credential store implementing the library's repository interface. */
final class DbPublicKeyCredentialSourceRepository implements PublicKeyCredentialSourceRepository
{
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $stmt = db()->prepare('SELECT public_key_data FROM webauthn_credentials WHERE credential_id = ?');
        $stmt->execute([$publicKeyCredentialId]);
        $json = $stmt->fetchColumn();
        if (!$json) return null;
        return PublicKeyCredentialSource::createFromArray(json_decode($json, true));
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $stmt = db()->prepare('SELECT public_key_data FROM webauthn_credentials WHERE user_id = ?');
        $stmt->execute([(int) $publicKeyCredentialUserEntity->getId()]);
        $sources = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $sources[] = PublicKeyCredentialSource::createFromArray(json_decode($json, true));
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $userId = (int) $publicKeyCredentialSource->getUserHandle();
        $credentialId = $publicKeyCredentialSource->getPublicKeyCredentialId();
        $json = json_encode($publicKeyCredentialSource);
        $signCount = $publicKeyCredentialSource->getCounter();

        db()->prepare(
            'INSERT INTO webauthn_credentials (user_id, credential_id, public_key_data, sign_count) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE public_key_data = VALUES(public_key_data), sign_count = VALUES(sign_count)'
        )->execute([$userId, $credentialId, $json, $signCount]);
    }
}

function webauthn_credential_repository(): DbPublicKeyCredentialSourceRepository
{
    return new DbPublicKeyCredentialSourceRepository();
}

function webauthn_attestation_statement_manager(): AttestationStatementSupportManager
{
    $manager = AttestationStatementSupportManager::create();
    $manager->add(NoneAttestationStatementSupport::create());
    return $manager;
}

function webauthn_credential_loader(): PublicKeyCredentialLoader
{
    $attestationObjectLoader = AttestationObjectLoader::create(webauthn_attestation_statement_manager());
    return PublicKeyCredentialLoader::create($attestationObjectLoader);
}

function webauthn_algorithm_manager(): CoseAlgorithmManager
{
    return CoseAlgorithmManager::create()->add(ES256::create(), RS256::create());
}

function webauthn_attestation_validator(): AuthenticatorAttestationResponseValidator
{
    // Built via `new`, not ::create() — the static factory requires a non-null
    // TokenBindingHandler, which triggers a Symfony deprecation-contracts call
    // this project doesn't have installed. The constructor itself accepts null
    // fine (token binding is deprecated/unused by browsers), so bypass it.
    return new AuthenticatorAttestationResponseValidator(
        webauthn_attestation_statement_manager(),
        webauthn_credential_repository(),
        null,
        ExtensionOutputCheckerHandler::create(),
    );
}

function webauthn_assertion_validator(): AuthenticatorAssertionResponseValidator
{
    return new AuthenticatorAssertionResponseValidator(
        webauthn_credential_repository(),
        null,
        ExtensionOutputCheckerHandler::create(),
        webauthn_algorithm_manager(),
    );
}

/** The standard set of public-key algorithms browsers/authenticators support. */
function webauthn_pubkey_params(): array
{
    return [
        new PublicKeyCredentialParameters('public-key', -7),   // ES256
        new PublicKeyCredentialParameters('public-key', -257), // RS256
    ];
}
