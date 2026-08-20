<?php
declare(strict_types=1);

/**
 * Konfigurasi Firestore. Salin fail ini kepada `firebase.php` dalam folder
 * yang sama, kemudian isikan nilai di bawah. Selagi `firebase.php` tiada,
 * aplikasi terus guna data/db.json seperti biasa — tiada apa yang pecah.
 *
 * Langkah persediaan penuh: lihat bahagian "Firestore" dalam README.md.
 */
return [
    // ID projek Firebase anda (Project Settings → General → Project ID).
    'project_id' => 'GANTIKAN-DENGAN-PROJECT-ID-ANDA',

    // Laluan ke fail JSON kunci akaun perkhidmatan yang dimuat turun daripada
    // Project Settings → Service Accounts → Generate new private key.
    // JANGAN letak fail ini di dalam folder yang boleh dicapai terus oleh
    // pelayar (folder config/ ini sudah dilindungi oleh config/.htaccess).
    'credentials_file' => __DIR__ . '/firebase-service-account.json',

    // Laluan dokumen Firestore tempat seluruh data aplikasi disimpan.
    // Tidak perlu diubah melainkan anda mahu berkongsi projek Firebase yang
    // sama untuk beberapa kelab/tenant berasingan.
    'document_path' => 'piko_taz/state',

    // Lokasi cache token akses (dijana automatik, tidak perlu disentuh).
    'token_cache_file' => __DIR__ . '/../data/.firestore-token-cache.json',
];
