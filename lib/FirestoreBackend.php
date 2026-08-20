<?php
declare(strict_types=1);

/**
 * Seluruh keadaan aplikasi (users/players/sessions/attendance/scores) disimpan
 * sebagai SATU dokumen Firestore — sama seperti fail JSON tunggal, tetapi kini
 * di awan. Ini kekalkan corak mutate() yang sama seperti JsonFileBackend, jadi
 * Repo.php tidak perlu berubah langsung.
 *
 * Kawalan konkurensi: setiap mutate() baca updateTime terkini dan hantar semula
 * sebagai prasyarat semasa tulis; jika berlanggar dengan tulisan lain (jarang,
 * traffic kelab kecil), cuba sekali lagi di atas data terkini.
 */
final class FirestoreBackend implements DbBackendInterface
{
    private Firestore $client;
    private string $docPath;
    private ?array $cachedData = null;

    public function __construct(Firestore $client, string $docPath)
    {
        $this->client = $client;
        $this->docPath = $docPath;
    }

    public function all(): array
    {
        if ($this->cachedData === null) {
            $doc = $this->client->getDocument($this->docPath);
            if ($doc === null) {
                $seed = Db::seed();
                $this->client->setDocument($this->docPath, $seed);
                $this->cachedData = $seed;
            } else {
                $this->cachedData = $doc['data'];
            }
        }
        return $this->cachedData;
    }

    public function mutate(callable $fn)
    {
        $attemptsLeft = 2; // percubaan asal + satu ulangan jika berlanggar konkurensi
        $lastEx = null;

        while ($attemptsLeft-- > 0) {
            $doc = $this->client->getDocument($this->docPath);
            $data = $doc['data'] ?? Db::seed();
            $updateTime = $doc['updateTime'] ?? null;

            $result = $fn($data);

            try {
                $this->client->setDocument($this->docPath, $data, $updateTime);
                $this->cachedData = $data;
                return $result;
            } catch (FirestorePreconditionException $e) {
                $lastEx = $e; // pertindihan tulisan serentak — cuba sekali lagi di atas data terkini
            }
        }

        throw $lastEx ?? new RuntimeException('Gagal menulis ke Firestore selepas beberapa percubaan.');
    }
}
