<?php
declare(strict_types=1);

/**
 * Pindahkan data/db.json SEDIA ADA ke Firestore sekali sahaja — guna ini jika
 * anda sudah ada data sebenar (bukan sekadar data demo) sebelum menukar ke
 * Firestore, supaya ia tidak digantikan oleh set data awal (seed) yang baharu.
 *
 *   php scripts/migrate-to-firestore.php
 *
 * Selamat dijalankan berulang kali — ia menimpa dokumen Firestore dengan
 * kandungan data/db.json semasa setiap kali dijalankan.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya untuk CLI.');
}

require __DIR__ . '/../lib/Db.php';

function ln(string $s): void
{
    echo $s . PHP_EOL;
}

$localFile = __DIR__ . '/../data/db.json';
$configFile = __DIR__ . '/../config/firebase.php';

if (!file_exists($localFile)) {
    ln('✕ data/db.json tidak dijumpai — tiada apa untuk dipindahkan.');
    exit(1);
}
if (!file_exists($configFile)) {
    ln('✕ config/firebase.php tidak dijumpai. Salin daripada firebase.example.php dahulu.');
    exit(1);
}

$config = require $configFile;
if (empty($config['project_id']) || empty($config['credentials_file']) || !is_file($config['credentials_file'])) {
    ln('✕ config/firebase.php tidak lengkap atau fail kelayakan tidak dijumpai.');
    exit(1);
}

$raw = file_get_contents($localFile);
$data = json_decode($raw, true);
if (!is_array($data)) {
    ln('✕ data/db.json rosak / bukan JSON sah.');
    exit(1);
}

ln('Data tempatan: ' . count($data['players'] ?? []) . ' pemain, '
    . count($data['sessions'] ?? []) . ' sesi, '
    . count($data['attendance'] ?? []) . ' kehadiran, '
    . count($data['scores'] ?? []) . ' skor.');

echo 'Tulis data ini ke Firestore projek "' . $config['project_id'] . '"? '
    . 'Ini akan MENIMPA apa-apa yang ada di sana sekarang. Taip "ya" untuk teruskan: ';
$confirm = trim((string)fgets(STDIN));
if (strtolower($confirm) !== 'ya') {
    ln('Dibatalkan.');
    exit(0);
}

try {
    $auth = new GoogleAuth($config['credentials_file'], $config['token_cache_file'] ?? (__DIR__ . '/../data/.firestore-token-cache.json'));
    $client = new Firestore($config['project_id'], $auth);
    $docPath = $config['document_path'] ?? 'piko_taz/state';

    $existing = $client->getDocument($docPath);
    $client->setDocument($docPath, $data, $existing['updateTime'] ?? null);

    ln('✓ Berjaya ditulis ke Firestore (' . $docPath . ').');
    ln('  Jalankan scripts/firestore-selftest.php untuk sahkan, kemudian pastikan config/firebase.php kekal aktif.');
} catch (Throwable $e) {
    ln('✕ RALAT: ' . $e->getMessage());
    exit(1);
}
