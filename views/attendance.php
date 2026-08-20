<?php /** @var array $sessions @var array|null $session @var array $tally @var array $map @var array $roster */ ?>

<header class="phead">
  <p class="eyebrow">Rekod</p>
  <h1>Kehadiran Training</h1>
  <p class="phead__sub">Tandakan status setiap pemain, kemudian simpan. Rekod QR dipaparkan bersama waktu check-in.</p>
</header>

<?php if (!$session): ?>
  <div class="card">
    <div class="empty"><strong>Tiada sesi</strong>Cipta sesi latihan terlebih dahulu.
      <p><a class="btn btn--primary" href="index.php?r=sessions">Cipta Sesi</a></p>
    </div>
  </div>
<?php else: ?>

  <article class="card">
    <form method="get" action="index.php" class="formgrid">
      <input type="hidden" name="r" value="attendance">
      <label class="field field--wide">
        <span class="field__label">Sesi latihan</span>
        <select name="s" data-autosubmit>
          <?php foreach ($sessions as $x): ?>
            <option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === (int)$session['id'] ? ' selected' : '' ?>>
              <?= e($x['title']) ?> — <?= e(fmt_date($x['date'])) ?><?= $x['status'] === 'aktif' ? ' (aktif)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="formgrid__actions">
        <button class="btn btn--ghost btn--sm" type="submit">Tukar Sesi</button>
      </div>
    </form>

    <div class="scorerow scorerow--tight">
      <div class="score score--pine"><div class="score__label">Hadir</div><div class="score__value"><?= $tally['hadir'] ?></div></div>
      <div class="score score--amber"><div class="score__label">Lewat</div><div class="score__value"><?= $tally['lewat'] ?></div></div>
      <div class="score"><div class="score__label">Tidak Hadir</div><div class="score__value"><?= $tally['tidak_hadir'] ?></div></div>
      <div class="score score--accent"><div class="score__label">Belum Ditanda</div><div class="score__value"><?= $tally['belum'] ?></div></div>
    </div>
  </article>

  <form method="post" action="index.php?r=attendance.save">
    <?= csrf_field() ?>
    <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">

    <article class="card card--flush">
      <div class="tablewrap">
        <table class="sheet">
          <thead>
            <tr><th>Pemain</th><th>Kategori</th><th>Status Semasa</th><th>Kaedah</th><th>Masa</th><th>Tandakan</th></tr>
          </thead>
          <tbody>
            <?php foreach ($roster as $p): $id = (int)$p['id']; $a = $map[$id] ?? null; $st = $a['status'] ?? 'belum'; ?>
              <tr>
                <td>
                  <div class="cell-name"><?= e($p['name']) ?></div>
                  <div class="cell-sub"><?= e($p['no_ahli']) ?></div>
                </td>
                <td><?= e($p['category']) ?></td>
                <td><?= status_tag($st) ?></td>
                <td><?= $a ? '<span class="code">' . e(strtoupper((string)$a['method'])) . '</span>' : '—' ?></td>
                <td><span class="code"><?= $a ? e(substr((string)$a['marked_at'], 11, 5)) : '—' ?></span></td>
                <td>
                  <select name="status[<?= $id ?>]">
                    <?php foreach (STATUS_LABEL as $key => $label): ?>
                      <option value="<?= e($key) ?>"<?= $key === $st ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <div class="actionbar">
      <span class="actionbar__note">
        <?= $tally['belum'] ?> pemain masih belum ditanda untuk sesi ini.
      </span>
      <button class="btn btn--primary" type="submit">Simpan Kehadiran</button>
    </div>
  </form>

  <article class="card">
    <h3 class="card__title">Tindakan Pukal</h3>
    <div class="rowbtns">
      <?php
      $bulk = [
          'hadir' => ['Tandakan Semua Hadir', 'btn--pine'],
          'tidak_hadir' => ['Yang Belum → Tidak Hadir', 'btn--ghost'],
          'belum' => ['Kosongkan Semua Tanda', 'btn--ghost'],
      ];
      foreach ($bulk as $mode => [$label, $cls]): ?>
        <form method="post" action="index.php?r=attendance.bulk" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
          <input type="hidden" name="mode" value="<?= e($mode) ?>">
          <button class="btn <?= e($cls) ?>" type="submit"
                  <?= $mode === 'belum' ? 'data-confirm="Kosongkan semua tanda kehadiran untuk sesi ini?"' : '' ?>>
            <?= e($label) ?>
          </button>
        </form>
      <?php endforeach; ?>
      <a class="btn btn--gold" href="index.php?r=export">Muat Turun CSV</a>
    </div>
  </article>

<?php endif; ?>
