<?php
declare(strict_types=1);

/**
 * Ujian sendiri sambungan Firestore — jalankan melalui CLI selepas mengisi
 * config/firebase.php, untuk sahkan kelayakan & sambungan berfungsi sebelum
 * bergantung padanya dalam aplikasi sebenar.
 *
 *   php scripts/firestore-selftest.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya untuk CLI.');
}

require __DIR__ . '/../lib/Db.php';

function line(string $s): void
{
    echo $s . PHP_EOL;
}

line('== Ujian Sendiri Sambungan Firestore ==');

$configFile = __DIR__ . '/../config/firebase.php';
if (!file_exists($configFile)) {
    line('✕ config/firebase.php tidak dijumpai.');
    line('  Salin config/firebase.example.php → config/firebase.php dan isikan project_id + credentials_file.');
    exit(1);
}
line('✓ config/firebase.php dijumpai.');

$config = require $configFile;
if (empty($config['project_id']) || empty($config['credentials_file'])) {
    line('✕ project_id atau credentials_file kosong dalam config/firebase.php.');
    exit(1);
}
line('  project_id: ' . $config['project_id']);
line('  credentials_file: ' . $config['credentials_file']);

if (!is_file($config['credentials_file'])) {
    line('✕ Fail kelayakan tidak dijumpai pada laluan di atas.');
    line('  Muat turun daripada Firebase Console → Project Settings → Service Accounts → Generate new private key.');
    exit(1);
}
line('✓ Fail kelayakan dijumpai.');

try {
    $t0 = microtime(true);
    $db = new Db(__DIR__ . '/../data/db.json'); // laluan tempatan hanya sandaran jika Firestore gagal dikonfigur

    $backendClass = (new ReflectionClass($db))->getProperty('backend');
    $backendClass->setAccessible(true);
    $backend = $backendClass->getValue($db);

    if (!($backend instanceof FirestoreBackend)) {
        line('✕ Db memilih ' . get_class($backend) . ', BUKAN Firestore.');
        line('  Semak semula config/firebase.php — kemungkinan project_id/credentials_file dianggap kosong.');
        exit(1);
    }
    line('✓ Backend aktif: FirestoreBackend.');

    line('→ Membaca dokumen (akan mencipta + benih data demo jika belum wujud)...');
    $data = $db->all();
    line('✓ Baca berjaya — ' . count($data['players'] ?? []) . ' pemain, '
        . count($data['sessions'] ?? []) . ' sesi, '
        . count($data['attendance'] ?? []) . ' rekod kehadiran, '
        . count($data['scores'] ?? []) . ' rekod skor.');

    line('→ Menguji tulisan (mutate) dengan penanda ujian sementara...');
    $marker = 'selftest-' . date('Y-m-d H:i:s');
    $db->mutate(function (array &$d) use ($marker) {
        $d['meta']['_selftest'] = $marker;
    });

    // Baksa semula dari kosong untuk pastikan ia BENAR-BENAR baca dari Firestore, bukan cache dalam proses.
    $db2 = new Db(__DIR__ . '/../data/db.json');
    $confirm = $db2->all();
    if (($confirm['meta']['_selftest'] ?? null) === $marker) {
        line('✓ Tulis & baca semula berjaya — pusingan penuh Firestore sah.');
    } else {
        line('✕ Penanda ujian tidak ditemui semula — tulisan mungkin gagal senyap.');
        exit(1);
    }

    $ms = round((microtime(true) - $t0) * 1000);
    line('');
    line("SEMUA UJIAN LULUS ({$ms}ms). Firestore sedia digunakan oleh aplikasi.");
} catch (Throwable $e) {
    line('');
    line('✕ RALAT: ' . $e->getMessage());
    exit(1);
}
