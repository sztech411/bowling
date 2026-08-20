<?php
declare(strict_types=1);

/**
 * Konfigurasi Firestore. Selagi fail ini wujud dan lengkap, aplikasi guna
 * Firestore sebagai pangkalan data. Padam/namakan semula fail ini untuk
 * kembali ke data/db.json tempatan.
 */
return [
    'project_id' => 'bowling-b58ea',

    'credentials_file' => __DIR__ . '/firebase-service-account.json',

    'document_path' => 'piko_taz/state',

    'token_cache_file' => __DIR__ . '/../data/.firestore-token-cache.json',
];
