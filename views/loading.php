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
<?php require __DIR__ . '/_pwa-head.php'; ?>
</head>
<body>

<div class="loadscreen">
  <div class="loadscreen__glow" aria-hidden="true"></div>
  <img class="loadscreen__logo" src="<?= asset_url('assets/logo-bowling.jpeg') ?>" alt="PIKO TAZ">
  <p class="loadscreen__title">PIKO<em>TAZ</em></p>
  <div class="loadscreen__bar"><span></span></div>
  <p class="loadscreen__hint">Menyediakan papan pemuka…</p>
</div>

<script>
setTimeout(function () {
  window.location.href = 'index.php?r=dashboard';
}, <?= (int)$loadingMs ?>);
document.querySelector('.loadscreen__bar span').style.animationDuration = <?= (int)$loadingMs ?> + 'ms';
</script>
</body>
</html>
