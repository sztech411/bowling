<?php
declare(strict_types=1);

/**
 * Persediaan pertama kali — satu fail, dijalankan sekali sahaja.
 *
 * Buat: semak persekitaran (PHP/sambungan/kebenaran fail), kesan/sedia storan
 * (Firestore jika config/firebase.php wujud, jika tidak tawar pilihan), dan
 * tetapkan kata laluan admin SEBENAR (hash bcrypt) menggantikan demo admin123.
 *
 * Selepas berjaya, fail penanda data/.installed dicipta — pelawat seterusnya
 * ke setup.php akan nampak skrin "sudah dipasang" dan tidak boleh tetapkan
 * semula kata laluan admin tanpa padam penanda itu secara manual di pelayan.
 * Ini penting: setup.php boleh dicapai tanpa log masuk (ia BERADA sebelum
 * log masuk), jadi ia mesti kunci diri selepas selesai.
 */

session_start();

require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/Repo.php';
require __DIR__ . '/lib/Support.php';

const MARKER_FILE = __DIR__ . '/data/.installed';
const LOCAL_DB_FILE = __DIR__ . '/data/db.json';
const FIREBASE_CONFIG_FILE = __DIR__ . '/config/firebase.php';
const FIREBASE_CREDS_FILE = __DIR__ . '/config/firebase-service-account.json';

$alreadyInstalled = file_exists(MARKER_FILE);
$firestoreConfigured = file_exists(FIREBASE_CONFIG_FILE);

$errors = [];
$success = null;

// ── Semakan persekitaran (selalu jalan, walaupun sudah dipasang — berguna
//    untuk diagnostik jika sesuatu rosak selepas kemas kini pelayan). ──────

function checkEnv(): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
    $checks[] = ['label' => 'Versi PHP (' . PHP_VERSION . ')', 'ok' => $phpOk, 'blocking' => true];

    foreach (['json', 'curl', 'openssl'] as $ext) {
        $checks[] = ['label' => 'Sambungan PHP: ' . $ext, 'ok' => extension_loaded($ext), 'blocking' => true];
    }
    $checks[] = ['label' => 'Sambungan PHP: gd (pilihan — ikon PWA sahaja)', 'ok' => extension_loaded('gd'), 'blocking' => false];

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }
    $checks[] = ['label' => 'Folder data/ boleh ditulis', 'ok' => is_writable($dataDir), 'blocking' => true];

    $configDir = __DIR__ . '/config';
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0777, true);
    }
    $checks[] = ['label' => 'Folder config/ boleh ditulis', 'ok' => is_writable($configDir), 'blocking' => true];

    return $checks;
}

$envChecks = checkEnv();
$envBlocking = array_filter($envChecks, fn($c) => $c['blocking'] && !$c['ok']);
$envOk = count($envBlocking) === 0;

// ── Proses borang (hanya jika belum dipasang) ───────────────────────────

if (!$alreadyInstalled && $envOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = post_str('name');
    $username = post_str('username', 'admin');
    $password = post_str('password');
    $confirm = post_str('confirm');
    $storage = post_str('storage', 'local');

    if ($name === '') {
        $errors[] = 'Nama penuh diperlukan.';
    }
    if ($username === '' || preg_match('/\s/', $username)) {
        $errors[] = 'ID pengguna diperlukan dan tidak boleh mengandungi ruang.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Kata laluan mesti sekurang-kurangnya 6 aksara.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Pengesahan kata laluan tidak sepadan.';
    }

    $writeFirestore = !$firestoreConfigured && $storage === 'firestore';
    if ($writeFirestore) {
        $projectId = post_str('project_id');
        $credsJson = post_str('credentials_json');

        if ($projectId === '') {
            $errors[] = 'Project ID Firebase diperlukan.';
        }
        $decoded = json_decode($credsJson, true);
        if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            $errors[] = 'Kandungan JSON kunci akaun perkhidmatan tidak sah (client_email/private_key hilang).';
        }
    }

    if (!$errors) {
        try {
            if ($writeFirestore) {
                file_put_contents(FIREBASE_CREDS_FILE, $credsJson, LOCK_EX);
                if (!file_exists(__DIR__ . '/config/.htaccess')) {
                    file_put_contents(
                        __DIR__ . '/config/.htaccess',
                        "Require all denied\n"
                    );
                }
                $configPhp = "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
                    . "    'project_id' => " . var_export($projectId, true) . ",\n"
                    . "    'credentials_file' => __DIR__ . '/firebase-service-account.json',\n"
                    . "    'document_path' => 'piko_taz/state',\n"
                    . "    'token_cache_file' => __DIR__ . '/../data/.firestore-token-cache.json',\n"
                    . "];\n";
                file_put_contents(FIREBASE_CONFIG_FILE, $configPhp, LOCK_EX);
            }

            // new Db() akan auto-kesan config Firestore yang baru ditulis (jika ada),
            // atau guna data/db.json tempatan — sama seperti seluruh aplikasi.
            $db = new Db(LOCAL_DB_FILE);
            $db->all(); // paksa baca/benih dahulu supaya ralat sambungan timbul di sini, bukan senyap

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $updated = false;
            $db->mutate(function (array &$data) use ($name, $username, $hash, &$updated) {
                foreach ($data['users'] as $i => $u) {
                    if (($u['role'] ?? '') === 'Admin') {
                        $data['users'][$i]['name'] = $name;
                        $data['users'][$i]['username'] = $username;
                        $data['users'][$i]['password'] = $hash;
                        $updated = true;
                        break;
                    }
                }
            });

            if (!$updated) {
                throw new RuntimeException('Akaun Admin tidak dijumpai dalam data sedia ada.');
            }

            file_put_contents(MARKER_FILE, json_encode([
                'installed_at' => date('c'),
                'admin_username' => $username,
            ], JSON_PRETTY_PRINT));

            $success = $username;
            $alreadyInstalled = true;
        } catch (Throwable $e) {
            $errors[] = 'Gagal memasang: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Persediaan Pertama Kali · PIKO TAZ Boling</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..900;1,9..144,400..700&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
<style>
  .setupwrap { max-width: 720px; margin: 0 auto; padding: clamp(2rem,5vw,3.5rem) 1.25rem 5rem; }
  .setupbrand { display: flex; align-items: center; gap: .6rem; margin-bottom: 2rem; }
  .setuppin { width: 11px; height: 16px; flex: none; border-radius: 50% 50% 42% 42% / 62% 62% 38% 38%; background: var(--paper-warm); border: 1.2px solid var(--ink); box-shadow: inset 0 -4px 0 var(--oxblood); }
  .setupbrand b { font-family: var(--display); font-weight: 600; font-size: 18px; }
  .setupbrand b em { font-style: normal; color: var(--oxblood); }
  .checklist { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
  .checklist li { display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-size: 13.5px; padding: .1rem 0; }
  .radiofield { display: flex; align-items: flex-start; gap: .6rem; padding: .85rem; border: 1px solid var(--line); border-radius: var(--r); margin-bottom: .7rem; cursor: pointer; }
  .radiofield input { width: auto; margin: .2rem 0 0; }
  .radiofield b { display: block; font-size: 13.5px; }
  .radiofield span { display: block; font-size: 12.5px; color: var(--ink-soft); margin-top: .15rem; }
</style>
</head>
<body>
<div class="setupwrap">

  <div class="setupbrand">
    <span class="setuppin" aria-hidden="true"></span>
    <b>PIKO<em>TAZ</em> — Persediaan Pertama Kali</b>
  </div>

  <?php if ($success): ?>

    <article class="card">
      <div class="notice notice--ok">
        <b>✓ Pemasangan selesai.</b>
      </div>
      <p class="cardtext">
        Akaun admin telah ditetapkan dengan kata laluan hash selamat. Log masuk
        menggunakan ID pengguna <b class="code"><?= e($success) ?></b> dan kata
        laluan yang baru sahaja anda tetapkan.
      </p>
      <p class="cardtext">
        Akaun demo <b class="code">coach</b> dan <b class="code">pemain</b> masih
        kekal dengan kata laluan asal (untuk ujian) — tiada skrin urus pengguna
        dalam aplikasi lagi untuk menukarnya; edit terus <code>data/db.json</code>
        atau dokumen Firestore jika perlu.
      </p>
      <a class="btn btn--primary" href="index.php?r=login">Pergi ke Log Masuk →</a>
    </article>

  <?php elseif ($alreadyInstalled): ?>

    <article class="card">
      <div class="notice">
        <b>Sistem ini sudah dipasang.</b>
      </div>
      <p class="cardtext">
        <code>data/.installed</code> wujud, jadi setup.php tidak akan menetapkan
        semula kata laluan admin secara automatik — ini sengaja, supaya sesiapa
        yang menemui URL ini selepas sistem digunakan tidak boleh rampas akaun admin.
      </p>
      <p class="cardtext">
        Untuk jalankan semula persediaan (cth. tetapkan semula kata laluan admin),
        padam fail <code>data/.installed</code> pada pelayan secara manual, kemudian
        muat semula halaman ini.
      </p>
      <a class="btn btn--ghost" href="index.php?r=login">Pergi ke Log Masuk →</a>
    </article>

  <?php else: ?>

    <article class="card">
      <h3 class="card__title">1. Semakan Persekitaran</h3>
      <ul class="checklist">
        <?php foreach ($envChecks as $c): ?>
          <li>
            <span><?= e($c['label']) ?></span>
            <?= $c['ok'] ? '<span class="tag tag--hadir">OK</span>' : '<span class="tag tag--tidak">' . ($c['blocking'] ? 'Gagal' : 'Tiada') . '</span>' ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$envOk): ?>
        <div class="notice notice--bad">
          Terdapat keperluan asas yang belum dipenuhi pelayan ini. Betulkan perkara
          di atas (cth. aktifkan sambungan PHP yang hilang, betulkan kebenaran folder)
          kemudian muat semula halaman.
        </div>
      <?php endif; ?>
    </article>

    <?php if ($envOk): ?>
      <form method="post" action="setup.php">
        <?= csrf_field() ?>

        <?php if ($errors): ?>
          <div class="notice notice--bad">
            <b>Tidak dapat teruskan:</b>
            <ul style="margin:.5rem 0 0 1.1rem;padding:0">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <article class="card">
          <h3 class="card__title">2. Storan Data</h3>
          <?php if ($firestoreConfigured): ?>
            <div class="notice notice--ok">
              ✓ Firestore sudah disambungkan (<code>config/firebase.php</code> dijumpai).
              Tiada tindakan diperlukan di sini.
            </div>
          <?php else: ?>
            <label class="radiofield">
              <input type="radio" name="storage" value="local" checked>
              <span><b>Fail JSON tempatan (disyorkan untuk mula)</b>
                <span>Data disimpan dalam <code>data/db.json</code> di pelayan ini. Boleh tukar ke Firestore kemudian.</span>
              </span>
            </label>
            <label class="radiofield">
              <input type="radio" name="storage" value="firestore">
              <span><b>Firestore (Firebase)</b>
                <span>Tampal Project ID dan kandungan fail JSON kunci akaun perkhidmatan di bawah.</span>
              </span>
            </label>
            <div class="formgrid" style="margin-top:.75rem">
              <label class="field field--wide">
                <span class="field__label">Firebase Project ID</span>
                <input name="project_id" placeholder="cth. piko-taz-bowling-a1b2c">
              </label>
              <label class="field field--wide">
                <span class="field__label">Kandungan fail JSON kunci akaun perkhidmatan</span>
                <textarea name="credentials_json" rows="5" placeholder='{"type": "service_account", ...}'></textarea>
              </label>
            </div>
          <?php endif; ?>
        </article>

        <article class="card">
          <h3 class="card__title">3. Akaun Admin</h3>
          <div class="formgrid">
            <label class="field field--wide">
              <span class="field__label">Nama penuh</span>
              <input name="name" required placeholder="cth. Shaharizuan">
            </label>
            <label class="field">
              <span class="field__label">ID Pengguna</span>
              <input name="username" required value="admin">
            </label>
            <label class="field">
              <span class="field__label">Kata Laluan</span>
              <input name="password" type="password" required minlength="6">
            </label>
            <label class="field field--wide">
              <span class="field__label">Sahkan Kata Laluan</span>
              <input name="confirm" type="password" required minlength="6">
            </label>
          </div>
        </article>

        <button class="btn btn--primary btn--block" type="submit">Pasang Sistem</button>
      </form>
    <?php endif; ?>

  <?php endif; ?>

</div>
</body>
</html>
