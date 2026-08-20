<?php /** @var array|null $session @var array $tally @var array $log @var array $pending @var string $qrUrl @var string $qrSvg */ ?>

<header class="phead">
  <p class="eyebrow">Kehadiran Automatik</p>
  <h1>QR Check-in</h1>
  <p class="phead__sub">Pemain mengimbas kod ini dengan kamera telefon untuk merekod kehadiran sendiri.</p>
</header>

<?php if (!$session): ?>
  <div class="card">
    <div class="empty">
      <strong>Tiada sesi aktif</strong>
      Aktifkan satu sesi latihan sebelum membuka check-in.
      <p><a class="btn btn--primary" href="index.php?r=sessions">Pergi ke Sesi Latihan</a></p>
    </div>
  </div>
<?php else: ?>

  <div class="qrwrap">

    <div class="qrpanel">
      <p class="eyebrow"><?= e($session['title']) ?></p>
      <div class="qrbox"><?= $qrSvg /* SVG dijana oleh Qr::svg, sudah bersih */ ?></div>

      <div class="pin__label">Kod Sesi</div>
      <div class="pin"><?= e($session['pin']) ?></div>
      <p class="qrhint">Imbas kod, atau masukkan enam angka di atas pada halaman check-in.</p>
      <div class="qrurl"><?= e($qrUrl) ?></div>
    </div>

    <div>
      <article class="card">
        <h3 class="card__title">
          Check-in Kaunter
          <span class="tag tag--aktif"><?= $tally['hadir'] + $tally['lewat'] ?>/<?= $tally['total'] ?> masuk</span>
        </h3>

        <?php if (!$pending): ?>
          <div class="notice notice--ok">Semua pemain aktif sudah direkod untuk sesi ini.</div>
        <?php else: ?>
          <form method="post" action="index.php?r=attendance.checkin" class="formgrid">
            <?= csrf_field() ?>
            <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
            <label class="field field--wide">
              <span class="field__label">Pemain belum check-in (<?= count($pending) ?>)</span>
              <select name="player_id">
                <?php foreach ($pending as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= e($p['category']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="formgrid__actions">
              <button class="btn btn--pine" type="submit">✓ Rekod Check-in</button>
              <a class="btn btn--ghost" href="index.php?r=attendance&s=<?= (int)$session['id'] ?>">Buka Senarai Kehadiran</a>
            </div>
          </form>
        <?php endif; ?>
      </article>

      <article class="card">
        <h3 class="card__title">Log Check-in Terkini</h3>
        <?php if (!$log): ?>
          <div class="empty">Belum ada check-in untuk sesi ini.</div>
        <?php else: ?>
          <div class="feed">
            <?php foreach ($log as $a): ?>
              <div class="feed__row">
                <span>
                  <b><?= e($a['player_name']) ?></b>
                  <?= status_tag($a['status']) ?>
                </span>
                <span class="feed__time">
                  <?= e(substr((string)$a['marked_at'], 11, 5)) ?> · <?= e(strtoupper((string)$a['method'])) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </div>
  </div>

<?php endif; ?>
