<?php /** @var int $loadingMs */ ?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="<?= max(0.1, $loadingMs / 1000) ?>;url=index.php?r=dashboard">
<title>Memuatkan · PIKO TAZ Boling</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..900;1,9..144,400..700&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
<?php /* Panaskan cache papan pemuka supaya peralihan terasa serta-merta. */ ?>
<link rel="prefetch" href="index.php?r=dashboard">
<?php require __DIR__ . '/_pwa-head.php'; ?>
</head>
<body>

<div class="loadscreen">
  <div class="loadscreen__glow" aria-hidden="true"></div>

  <div class="loadscreen__stage">
    <div class="loadscreen__badge">
      <span class="loadscreen__ring" aria-hidden="true"></span>
      <img class="loadscreen__logo" src="<?= asset_url('assets/logo-bowling.jpeg') ?>" alt="PIKO TAZ">
    </div>

    <p class="loadscreen__title">PIKO<em>TAZ</em></p>
    <p class="loadscreen__hint">Menyediakan papan pemuka…</p>

    <div class="loadscreen__bar" role="progressbar" aria-label="Memuatkan"><span></span></div>
  </div>
</div>

<script>
(function () {
  var total = <?= (int)$loadingMs ?>;
  var screen = document.querySelector('.loadscreen');
  var fill = document.querySelector('.loadscreen__bar span');

  // Palang mengisi secara linear sepanjang tempoh sebenar — pergerakan
  // sekata, bukan tersentak habis di awal.
  fill.style.animationDuration = total + 'ms';

  // Pudar keluar tepat sebelum navigasi supaya tiada lompatan mengejut.
  var fade = Math.min(340, Math.round(total / 3));

  setTimeout(function () {
    screen.classList.add('is-leaving');
  }, total - fade);

  setTimeout(function () {
    window.location.href = 'index.php?r=dashboard';
  }, total);
})();
</script>
</body>
</html>
