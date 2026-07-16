<?php
/**
 * Common interface every payment gateway adapter implements, so checkout flows
 * (job_post.php, pro membership, consultations) never branch on gateway name.
 */
interface PaymentGatewayInterface
{
    /** True once an admin has entered valid-looking config for this gateway. */
    public function isConfigured(): bool;

    /**
     * Start a checkout for the given order. Returns an array with either:
     * ['redirect_url' => string] to send the browser to a hosted checkout page, or
     * ['client_config' => array] for a JS SDK (e.g. Stripe Elements) to consume.
     */
    public function createCheckout(array $order, array $config): array;

    /** Verify an incoming webhook payload's authenticity for this gateway. */
    public function verifyWebhook(string $rawBody, array $headers, array $config): bool;
}
