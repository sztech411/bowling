<?php /** @var array $sessions @var array|null $session @var array $tally @var array $map @var array $roster @var bool $canEdit */ ?>

<header class="phead">
  <p class="eyebrow">Rekod</p>
  <h1>Kehadiran Training</h1>
  <p class="phead__sub">Tick pemain yang hadir, kemudian simpan. Rekod QR dipaparkan bersama waktu check-in.</p>
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

  <?php $tag = $canEdit ? 'form' : 'div'; ?>
  <<?= $tag ?><?= $canEdit ? ' method="post" action="index.php?r=attendance.save"' : '' ?>>
    <?php if ($canEdit): ?><?= csrf_field() ?><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><?php endif; ?>

    <article class="card card--flush">
      <div class="tablewrap">
        <table class="sheet">
          <thead>
            <tr><th>Pemain</th><th>Kategori</th><th>Status Semasa</th><th>Kaedah</th><th>Masa</th><?php if ($canEdit): ?><th class="num">Hadir</th><?php endif; ?></tr>
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
                <?php if ($canEdit): ?>
                <td class="num">
                  <input type="hidden" name="present[<?= $id ?>]" value="0">
                  <input type="checkbox" class="tick" name="present[<?= $id ?>]" value="1"
                         <?= in_array($st, ['hadir', 'lewat'], true) ? 'checked' : '' ?>>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <?php if ($canEdit): ?>
    <div class="actionbar">
      <span class="actionbar__note">
        <?= $tally['belum'] ?> pemain masih belum ditanda untuk sesi ini.
      </span>
      <button class="btn btn--primary" type="submit">Simpan Kehadiran</button>
    </div>
    <?php endif; ?>
  </<?= $tag ?>>

  <?php if ($canEdit): ?>
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

<?php endif; ?>
