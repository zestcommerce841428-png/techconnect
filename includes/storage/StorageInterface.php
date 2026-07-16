<?php
/**
 * Common interface every storage backend implements, so upload code never
 * branches on which provider is configured — same pattern as PaymentGatewayInterface.
 */
interface StorageInterface
{
    /** True once an admin has entered valid-looking config for this backend. */
    public function isConfigured(): bool;

    /**
     * Stores file contents at $path (e.g. "2026/07/abc.jpg").
     * Returns the publicly reachable URL, or throws RuntimeException on failure.
     */
    public function put(string $path, string $contents, string $mime): string;

    /** Removes a stored object. Returns false if it could not be deleted. */
    public function delete(string $path): bool;

    /** Public URL for an already-stored path. */
    public function url(string $path): string;

    /** Round-trips a small test object; returns [ok, message] for the admin UI. */
    public function test(): array;
}
