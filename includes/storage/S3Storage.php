<?php
require_once __DIR__ . '/StorageInterface.php';

/**
 * S3-compatible object storage.
 *
 * One driver, many providers — AWS S3, Cloudflare R2, DigitalOcean Spaces,
 * Backblaze B2, Wasabi and MinIO all speak the same S3 REST API and the same
 * AWS Signature V4 auth. Only endpoint/region/bucket differ, so they are config,
 * not code.
 *
 * Implemented with curl + hash_hmac directly rather than the AWS SDK: the SDK
 * pulls ~50 MB of Composer dependencies, which is hostile to shared hosting and
 * to FTP deploys. SigV4 is ~40 lines when you only need PUT/DELETE/HEAD.
 *
 * Config keys (site_settings):
 *   s3_endpoint    e.g. https://<account>.r2.cloudflarestorage.com
 *                       https://s3.ap-south-1.amazonaws.com
 *   s3_region      e.g. auto (R2) | ap-south-1 (AWS Mumbai)
 *   s3_bucket
 *   s3_access_key
 *   s3_secret_key
 *   s3_public_url  CDN/public base, e.g. https://cdn.puchonow.in
 */
class S3Storage implements StorageInterface
{
    private string $endpoint;
    private string $region;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;
    private string $publicUrl;

    public function __construct(?array $config = null)
    {
        $config ??= [
            'endpoint' => setting('s3_endpoint'),
            'region' => setting('s3_region', 'auto'),
            'bucket' => setting('s3_bucket'),
            'access_key' => setting('s3_access_key'),
            'secret_key' => setting('s3_secret_key'),
            'public_url' => setting('s3_public_url'),
        ];
        $this->endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $this->region = (string) ($config['region'] ?? 'auto') ?: 'auto';
        $this->bucket = trim((string) ($config['bucket'] ?? ''), '/');
        $this->accessKey = (string) ($config['access_key'] ?? '');
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->publicUrl = rtrim((string) ($config['public_url'] ?? ''), '/');
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->bucket !== ''
            && $this->accessKey !== '' && $this->secretKey !== '';
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        if ($this->publicUrl !== '') {
            return $this->publicUrl . '/' . $path;
        }
        // No CDN configured: fall back to the bucket URL (works when the bucket
        // itself is public — otherwise the admin must set s3_public_url).
        return $this->endpoint . '/' . $this->bucket . '/' . $path;
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $res = $this->request('PUT', $path, $contents, ['content-type' => $mime]);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new RuntimeException('S3 upload failed (HTTP ' . $res['status'] . '): ' . $this->errorText($res['body']));
        }
        return $this->url($path);
    }

    public function delete(string $path): bool
    {
        $res = $this->request('DELETE', $path);
        // S3 returns 204 on success and also on deleting a non-existent key.
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function test(): array
    {
        if (!$this->isConfigured()) {
            return [false, 'Endpoint, bucket, access key and secret key are all required.'];
        }
        $key = '_healthcheck/' . bin2hex(random_bytes(6)) . '.txt';
        try {
            $this->put($key, 'puchonow storage test ' . date('c'), 'text/plain');
        } catch (Throwable $e) {
            return [false, 'Write failed: ' . $e->getMessage()];
        }
        $read = $this->request('GET', $key);
        if ($read['status'] !== 200) {
            $this->delete($key);
            return [false, 'Wrote the object but could not read it back (HTTP ' . $read['status'] . ').'];
        }
        if (!$this->delete($key)) {
            return [false, 'Write and read worked, but delete failed — check DeleteObject permission.'];
        }
        return [true, 'Success — wrote, read and deleted a test object. Storage is working.'];
    }

    private function errorText(string $body): string
    {
        // S3 errors are XML; surface the useful part rather than a wall of markup.
        if (preg_match('#<Message>(.*?)</Message>#s', $body, $m)) {
            return trim($m[1]);
        }
        return mb_substr(trim(strip_tags($body)), 0, 200) ?: 'no response body';
    }

    /**
     * Signs and sends one S3 request using AWS Signature V4.
     * @return array{status:int, body:string}
     */
    private function request(string $method, string $path, string $body = '', array $extraHeaders = []): array
    {
        $path = ltrim($path, '/');
        // Each path segment is encoded separately: "/" must stay a delimiter, but
        // spaces and unicode in filenames must not.
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $canonicalUri = '/' . $this->bucket . '/' . $encodedPath;

        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: '';
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = array_change_key_case($extraHeaders, CASE_LOWER) + [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim((string) $v) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", [
            $method, $canonicalUri, '', $canonicalHeaders, $signedHeaders, $payloadHash,
        ]);

        $scope = $date . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256', $now, $scope, hash('sha256', $canonicalRequest),
        ]);

        // Derive the signing key: HMAC chain date -> region -> service -> request.
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = 'AWS4-HMAC-SHA256 '
            . 'Credential=' . $this->accessKey . '/' . $scope . ', '
            . 'SignedHeaders=' . $signedHeaders . ', '
            . 'Signature=' . $signature;

        $curlHeaders = ['Authorization: ' . $authorization];
        foreach ($headers as $k => $v) {
            if ($k === 'host') continue; // curl sets Host itself
            $curlHeaders[] = $k . ': ' . $v;
        }

        $ch = curl_init($this->endpoint . $canonicalUri);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== '' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Could not reach storage endpoint: ' . $curlError);
        }
        return ['status' => $status, 'body' => (string) $response];
    }
}
