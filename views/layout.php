<?php
/**
 * Rangka utama aplikasi.
 * Pemboleh ubah datang dari index.php: $route, $user, $flashes, dan data setiap paparan.
 */
// [label penuh (topbar desktop), label ringkas (tab bawah mobile), ikon]
$navItems = [
    'dashboard'  => ['Dashboard', 'Utama', '◱'],
    'players'    => ['Pemain', 'Pemain', '◍'],
    'sessions'   => ['Sesi Latihan', 'Sesi', '▤'],
    'checkin'    => ['QR Check-in', 'Check-in', '⬚'],
    'attendance' => ['Kehadiran', 'Hadir', '✓'],
    'scores'     => ['Skor & Prestasi', 'Skor', '◈'],
    'reports'    => ['Laporan', 'Laporan', '◫'],
];
$navItems = array_filter($navItems, fn($k) => role_can_view((string)($user['role'] ?? ''), $k), ARRAY_FILTER_USE_KEY);
?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($navItems[$route][0] ?? 'Dashboard') ?> · PIKO TAZ Boling</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..900;1,9..144,400..700&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
<?php require __DIR__ . '/_pwa-head.php'; ?>
</head>
<body>

<div class="shell">

  <header class="topbar">
    <a class="topbar__brand" href="index.php?r=dashboard">
      <span class="topbar__pin" aria-hidden="true"></span>
      <span class="topbar__name">PIKO<em>TAZ</em></span>
    </a>

    <nav class="topbar__nav">
      <?php foreach ($navItems as $key => [$label, $short, $icon]): ?>
        <a href="index.php?r=<?= e($key) ?>"<?= $route === $key ? ' class="is-active" aria-current="page"' : '' ?>>
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="topbar__user">
      <div class="who">
        <span class="who__name"><?= e($user['name']) ?></span>
        <span class="who__role"><?= e($user['role']) ?></span>
      </div>
      <form method="post" action="index.php?r=logout">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm" type="submit">Keluar</button>
      </form>
    </div>
  </header>

  <main class="main">
    <?php if ($flashes): ?>
      <div class="flashes">
        <?php foreach ($flashes as $f): ?>
          <div class="flash flash--<?= e($f['kind']) ?>"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php require __DIR__ . '/' . $route . '.php'; ?>
  </main>

  <nav class="tabbar" aria-label="Navigasi utama">
    <?php foreach ($navItems as $key => [$label, $short, $icon]): ?>
      <a href="index.php?r=<?= e($key) ?>"<?= $route === $key ? ' class="is-active" aria-current="page"' : '' ?>>
        <i aria-hidden="true"><?= $icon ?></i><span><?= e($short) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="grain" aria-hidden="true"></div>

<script>
/* Peningkatan progresif sahaja — semua borang berfungsi tanpa JavaScript. */
document.addEventListener('DOMContentLoaded', function () {
  // Hantar borang penukar sesi sebaik pilihan berubah.
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
  // Minta kepastian sebelum tindakan yang memusnahkan data.
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
  // Sembunyikan mesej flash selepas beberapa saat.
  var flashes = document.querySelector('.flashes');
  if (flashes) setTimeout(function () { flashes.classList.add('is-gone'); }, 4200);
});
</script>
<?php require __DIR__ . '/_pwa-register.php'; ?>
</body>
</html>
