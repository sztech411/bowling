<?php /** @var array|null $session @var array $players @var array|null $result @var string|null $error @var string $pin */ ?>
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check-in Latihan · PIKO TAZ Boling</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..900;1,9..144,400..700&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
<?php require __DIR__ . '/_pwa-head.php'; ?>
</head>
<body>

<div class="gate">
  <div class="gate__lanes" aria-hidden="true"></div>

  <div class="gate__inner gate__inner--single">
    <div class="gate__form">
      <img class="gate__logo" src="<?= asset_url('assets/logo-bowling.jpeg') ?>" alt="PIKO TAZ">
      <p class="eyebrow">Check-in Latihan</p>

      <?php if ($session): ?>
        <h1 class="gate__title gate__title--sm"><?= e($session['title']) ?></h1>
        <p class="gate__tag gate__tag--sm">
          <?= e(fmt_day($session['date'])) ?>, <?= e(fmt_date($session['date'])) ?>
          · <?= e(fmt_time($session['start'])) ?> · <?= e($session['venue']) ?>
        </p>
      <?php else: ?>
        <h1 class="gate__title gate__title--sm">Kod tidak dikenali</h1>
        <p class="gate__tag gate__tag--sm">Sahkan kod check-in dengan jurulatih anda.</p>
      <?php endif; ?>

      <?php if ($result): ?>
        <div class="notice notice--ok">
          <b><?= e($result['player']) ?></b> — kehadiran direkod sebagai
          <b><?= e(STATUS_LABEL[$result['status']]) ?></b>.
          <?php if ($result['already']): ?><br>Rekod terdahulu telah dikemas kini.<?php endif; ?>
        </div>
        <p class="gate__done">Terima kasih. Anda boleh menutup halaman ini.</p>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="notice notice--bad"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($session && $session['status'] !== 'aktif'): ?>
          <div class="notice notice--bad">Sesi ini tidak lagi dibuka untuk check-in.</div>
        <?php endif; ?>

        <form method="post" action="index.php?r=checkin-public">
          <?= csrf_field() ?>

          <label class="field">
            <span class="field__label">Pilih nama anda</span>
            <select name="player_id" required>
              <?php foreach ($players as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= e($p['category']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span class="field__label">No. Ahli</span>
            <input name="no_ahli" class="mono" maxlength="20" required
                   placeholder="MSN-1001" autocomplete="off">
          </label>

          <label class="field">
            <span class="field__label">Kod sesi</span>
            <input name="pin" class="mono" inputmode="numeric" maxlength="6" required
                   value="<?= e($pin) ?>" placeholder="000000">
          </label>

          <button class="btn btn--primary btn--block" type="submit">Rekod Kehadiran</button>
        </form>
      <?php endif; ?>

      <a class="gate__link" href="index.php?r=login">Log masuk sebagai pengurus →</a>
    </div>
  </div>
</div>

<div class="grain" aria-hidden="true"></div>
<?php require __DIR__ . '/_pwa-register.php'; ?>
</body>
</html>
