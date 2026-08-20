<?php
declare(strict_types=1);

/**
 * Fail-JSON tempatan sebagai pangkalan data. Semua tulisan guna kunci fail +
 * tulis atomik. Digunakan sebagai lalai apabila konfigurasi Firestore
 * (config/firebase.php) tiada — memudahkan pembangunan tanpa akaun Google.
 */
final class JsonFileBackend implements DbBackendInterface
{
    private string $file;
    private ?array $data = null;

    public function __construct(string $file)
    {
        $this->file = $file;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        if (!file_exists($file)) {
            $this->write(Db::seed());
        }
    }

    public function all(): array
    {
        if ($this->data === null) {
            $raw = file_get_contents($this->file);
            $this->data = json_decode($raw ?: '[]', true) ?: Db::seed();
        }
        return $this->data;
    }

    public function mutate(callable $fn)
    {
        $fh = fopen($this->file, 'c+');
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $data = json_decode($raw ?: '', true) ?: Db::seed();

        $result = $fn($data);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $json);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $this->data = $data;
        return $result;
    }

    private function write(array $data): void
    {
        file_put_contents(
            $this->file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        $this->data = $data;
    }
}
