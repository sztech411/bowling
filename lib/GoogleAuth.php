<?php
declare(strict_types=1);

/**
 * Log masuk akaun perkhidmatan Google (service account) tanpa SDK/Composer.
 * Tandatangan JWT RS256 sendiri, tukar dengan token akses OAuth2, cache token
 * ke fail tempatan supaya tidak perlu tandatangan semula pada setiap permintaan.
 */
final class GoogleAuth
{
    private array $credentials;
    private string $cacheFile;

    public function __construct(string $credentialsFile, string $cacheFile)
    {
        $raw = @file_get_contents($credentialsFile);
        if ($raw === false) {
            throw new RuntimeException('Fail kelayakan Firebase tidak dijumpai: ' . $credentialsFile);
        }
        $creds = json_decode($raw, true);
        if (!is_array($creds) || empty($creds['client_email']) || empty($creds['private_key'])) {
            throw new RuntimeException('Fail kelayakan Firebase tidak sah (client_email/private_key hilang).');
        }
        $this->credentials = $creds;
        $this->cacheFile = $cacheFile;
    }

    /** Dapatkan token akses sah — guna cache jika belum tamat tempoh. */
    public function accessToken(): string
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached;
        }

        $jwt = $this->signedAssertion();
        $tokenUri = $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        [$status, $respBody] = self::httpPost($tokenUri, $body, 'application/x-www-form-urlencoded');
        $decoded = json_decode($respBody, true);

        if ($status !== 200 || empty($decoded['access_token'])) {
            $reason = $decoded['error_description'] ?? $decoded['error'] ?? $respBody;
            throw new RuntimeException('Gagal dapatkan token akses Google (HTTP ' . $status . '): ' . $reason);
        }

        $token = (string)$decoded['access_token'];
        $expiresIn = (int)($decoded['expires_in'] ?? 3600);
        $this->writeCache($token, time() + $expiresIn - 60); // sisih 60s sebagai penampan

        return $token;
    }

    /** Bina & tandatangan JWT RS256 bagi permohonan token (grant assertion). */
    private function signedAssertion(): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $signingInput = self::b64url(json_encode($header)) . '.' . self::b64url(json_encode($claims));

        $ok = openssl_sign($signingInput, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Gagal menandatangani JWT — kunci peribadi mungkin tidak sah.');
        }

        return $signingInput . '.' . self::b64url($signature);
    }

    private function readCache(): ?string
    {
        $raw = @file_get_contents($this->cacheFile);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['token']) || empty($data['expires_at'])) {
            return null;
        }
        if ((int)$data['expires_at'] <= time()) {
            return null;
        }
        return (string)$data['token'];
    }

    private function writeCache(string $token, int $expiresAt): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents(
            $this->cacheFile,
            json_encode(['token' => $token, 'expires_at' => $expiresAt]),
            LOCK_EX
        );
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** @return array{0:int,1:string} [status HTTP, badan respons] */
    private static function httpPost(string $url, string $body, string $contentType): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: ' . $contentType],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Ralat rangkaian semasa hubungi Google OAuth2: ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $resp];
    }
}
