<?php
require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\Providers\Qr\IQRCodeProvider;

/**
 * TwoFactorAuth (v3) requires a QR code provider even though we only use
 * manual secret-key entry (no QR image, keeps this self-hosted with zero
 * external calls). This satisfies the interface without ever being invoked.
 */
class NullQrCodeProvider implements IQRCodeProvider
{
    public function getQRCodeImage(string $qrText, int $size): string
    {
        throw new \RuntimeException('QR code image generation is not supported; use manual secret entry.');
    }

    public function getMimeType(): string
    {
        return 'image/png';
    }
}
