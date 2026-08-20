<?php /** @var string|null $error */ ?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log Masuk · PIKO TAZ Boling</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..900;1,9..144,400..700&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
<?php require __DIR__ . '/_pwa-head.php'; ?>
</head>
<body>

<div class="gate">
  <div class="gate__lanes" aria-hidden="true"></div>

  <div class="gate__inner">
    <div class="gate__brand">
      <img class="gate__logo" src="<?= asset_url('assets/logo-bowling.jpeg') ?>" alt="PIKO TAZ">
      <p class="eyebrow">Kelab Boling · Est. 2019</p>
      <h1 class="gate__title">PIKO<em>TAZ</em></h1>
      <p class="gate__tag">Sistem Kehadiran Latihan</p>
      <p class="gate__blurb">
        Rekod kehadiran latihan, imbas QR di lorong, dan jana laporan
        kehadiran pemain dalam satu tempat.
      </p>
    </div>

    <form class="gate__form" method="post" action="index.php?r=login" autocomplete="off">
      <?= csrf_field() ?>

      <?php if ($error): ?>
        <p class="gate__error"><?= e($error) ?></p>
      <?php endif; ?>

      <label class="field">
        <span class="field__label">ID Pengguna</span>
        <input name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </label>

      <label class="field">
        <span class="field__label">Kata Laluan</span>
        <input name="password" type="password" required>
      </label>

      <button class="btn btn--primary btn--block" type="submit">Log Masuk</button>
    </form>
  </div>
</div>

<div class="grain" aria-hidden="true"></div>
<?php require __DIR__ . '/_pwa-register.php'; ?>
</body>
</html>
