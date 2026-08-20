<?php
declare(strict_types=1);

/** Dilemparkan apabila tulisan Firestore berlanggar dengan prasyarat updateTime (konflik serentak). */
final class FirestorePreconditionException extends RuntimeException
{
}

/**
 * Klien REST Firestore yang ringkas — baca/tulis SATU dokumen sepenuhnya.
 * Tiada SDK/Composer; guna cURL terus mengikut format REST API v1 Firestore.
 */
final class Firestore
{
    private string $projectId;
    private GoogleAuth $auth;

    public function __construct(string $projectId, GoogleAuth $auth)
    {
        $this->projectId = $projectId;
        $this->auth = $auth;
    }

    private function baseUrl(): string
    {
        return 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($this->projectId)
            . '/databases/(default)/documents/';
    }

    /**
     * Baca satu dokumen penuh.
     * @return array{data: array, updateTime: string}|null null jika dokumen tiada.
     */
    public function getDocument(string $docPath): ?array
    {
        [$status, $body] = $this->request('GET', $this->baseUrl() . $docPath);

        if ($status === 404) {
            return null;
        }
        if ($status !== 200) {
            throw new RuntimeException('Firestore GET gagal (HTTP ' . $status . '): ' . $body);
        }

        $json = json_decode($body, true);
        return [
            'data' => self::decodeFields($json['fields'] ?? []),
            'updateTime' => (string)($json['updateTime'] ?? ''),
        ];
    }

    /**
     * Tulis (ganti sepenuhnya) satu dokumen. Jika dokumen tiada, ia dicipta.
     * $expectUpdateTime — jika diberi, tulisan akan ditolak (412) jika dokumen
     * telah diubah oleh proses lain sejak masa itu (kawalan konkurensi optimistik).
     */
    public function setDocument(string $docPath, array $data, ?string $expectUpdateTime = null): void
    {
        $url = $this->baseUrl() . $docPath;
        if ($expectUpdateTime !== null && $expectUpdateTime !== '') {
            $url .= '?currentDocument.updateTime=' . rawurlencode($expectUpdateTime);
        }

        $payload = json_encode(['fields' => self::encodeFields($data)], JSON_UNESCAPED_UNICODE);
        [$status, $body] = $this->request('PATCH', $url, $payload);

        if ($status === 400 || $status === 409) {
            throw new FirestorePreconditionException('Percanggahan tulisan serentak Firestore: ' . $body);
        }
        if ($status !== 200) {
            throw new RuntimeException('Firestore PATCH gagal (HTTP ' . $status . '): ' . $body);
        }
    }

    // ── Penukaran nilai PHP <-> format bertaip Firestore ────────

    private static function encodeFields(array $assoc): array
    {
        $out = [];
        foreach ($assoc as $key => $value) {
            $out[(string)$key] = self::encodeValue($value);
        }
        return $out;
    }

    private static function encodeValue($value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string)$value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            if (self::isList($value)) {
                return ['arrayValue' => ['values' => array_map([self::class, 'encodeValue'], array_values($value))]];
            }
            return ['mapValue' => ['fields' => self::encodeFields($value)]];
        }
        throw new RuntimeException('Jenis nilai tidak disokong untuk Firestore: ' . gettype($value));
    }

    private static function decodeFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[$key] = self::decodeValue($value);
        }
        return $out;
    }

    /** @return mixed */
    private static function decodeValue(array $value)
    {
        if (array_key_exists('nullValue', $value)) {
            return null;
        }
        if (array_key_exists('booleanValue', $value)) {
            return (bool)$value['booleanValue'];
        }
        if (array_key_exists('integerValue', $value)) {
            return (int)$value['integerValue'];
        }
        if (array_key_exists('doubleValue', $value)) {
            return (float)$value['doubleValue'];
        }
        if (array_key_exists('stringValue', $value)) {
            return (string)$value['stringValue'];
        }
        if (array_key_exists('timestampValue', $value)) {
            return (string)$value['timestampValue'];
        }
        if (array_key_exists('arrayValue', $value)) {
            $items = $value['arrayValue']['values'] ?? [];
            return array_map([self::class, 'decodeValue'], $items);
        }
        if (array_key_exists('mapValue', $value)) {
            return self::decodeFields($value['mapValue']['fields'] ?? []);
        }
        return null;
    }

    /** Array tersenarai (0,1,2,...) dianggap senarai; sebaliknya peta. Array kosong = senarai. */
    private static function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /** @return array{0:int,1:string} */
    private function request(string $method, string $url, ?string $jsonBody = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->auth->accessToken()];
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $jsonBody;
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Ralat rangkaian semasa hubungi Firestore: ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $resp];
    }
}
