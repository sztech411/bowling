<?php /** @var array $players @var array|null $editing @var array $rates @var array $scoreStats */ ?>

<header class="phead">
  <p class="eyebrow">Pendaftaran</p>
  <h1>Senarai Pemain</h1>
  <p class="phead__sub">Urus maklumat ahli kelab, kategori pertandingan, dan purata mata.</p>
</header>

<article class="card" id="borang">
  <h3 class="card__title">
    <?= $editing ? 'Sunting: ' . e($editing['name']) : 'Tambah Pemain' ?>
    <?php if ($editing): ?>
      <a class="btn btn--ghost btn--sm" href="index.php?r=players">Batal</a>
    <?php endif; ?>
  </h3>

  <form method="post" action="index.php?r=player.save" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

    <label class="field field--wide">
      <span class="field__label">Nama penuh</span>
      <input name="name" required placeholder="cth. Ahmad Zulkarnain"
             value="<?= e($editing['name'] ?? '') ?>">
    </label>

    <label class="field">
      <span class="field__label">Kategori</span>
      <select name="category">
        <?php foreach (['Senior', 'Youth', 'Junior', 'Ladies', 'Veteran'] as $c): ?>
          <option<?= ($editing['category'] ?? '') === $c ? ' selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="field">
      <span class="field__label">No. telefon</span>
      <input name="phone" placeholder="013-0000000" value="<?= e($editing['phone'] ?? '') ?>">
    </label>

    <label class="field">
      <span class="field__label">No. Kad Pengenalan (IC)</span>
      <input name="ic" class="mono" inputmode="numeric" maxlength="14" placeholder="000000-00-0000"
             value="<?= e($editing['ic'] ?? '') ?>">
    </label>

    <label class="field">
      <span class="field__label">Purata (average)</span>
      <input name="average" type="number" min="0" max="300" value="<?= (int)($editing['average'] ?? 150) ?>">
    </label>

    <label class="check field">
      <input type="checkbox" name="active" value="1"<?= (!$editing || !empty($editing['active'])) ? ' checked' : '' ?>>
      <span>Ahli aktif (dikira dalam senarai kehadiran)</span>
    </label>

    <div class="formgrid__actions">
      <button class="btn btn--primary" type="submit"><?= $editing ? 'Kemas Kini Pemain' : 'Tambah Pemain' ?></button>
    </div>
  </form>
</article>

<article class="card card--flush">
  <div class="tablewrap">
    <table class="sheet">
      <thead>
        <tr>
          <th>No. Ahli</th><th>Nama</th><th>Kategori</th><th>Telefon</th><th>IC</th>
          <th class="num">Kehadiran</th><th class="num">Purata Skor</th><th class="num">Tertinggi</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$players): ?>
          <tr><td colspan="9">
            <div class="empty"><strong>Senarai kosong</strong>Tambah pemain pertama menggunakan borang di atas.</div>
          </td></tr>
        <?php else: foreach ($players as $p): $id = (int)$p['id']; $ss = $scoreStats[$id] ?? ['games' => 0, 'avg' => 0, 'high' => 0]; ?>
          <tr>
            <td><span class="code"><?= e($p['no_ahli']) ?></span></td>
            <td>
              <div class="cell-name"><?= e($p['name']) ?></div>
              <?php if (empty($p['active'])): ?><div class="cell-sub">tidak aktif</div><?php endif; ?>
            </td>
            <td><?= e($p['category']) ?></td>
            <td><?= e($p['phone'] ?: '—') ?></td>
            <td>
              <?php $ic = preg_replace('/\D/', '', (string)($p['ic'] ?? '')); ?>
              <?= $ic !== '' ? '<span class="code">••••••-••-' . e(substr($ic, -4)) . '</span>' : '—' ?>
            </td>
            <td class="num">
              <?php $r = $rates[$id] ?? null; ?>
              <?php if ($r === null): ?>—<?php else: ?>
                <span class="rate rate--<?= $r >= 80 ? 'hi' : ($r >= 50 ? 'mid' : 'lo') ?>"><?= $r ?>%</span>
              <?php endif; ?>
            </td>
            <td class="num"><?= $ss['games'] ? $ss['avg'] : '—' ?></td>
            <td class="num"><?= $ss['games'] ? '<b>' . $ss['high'] . '</b>' : '—' ?></td>
            <td class="actions">
              <a class="btn btn--ghost btn--icon" href="index.php?r=players&edit=<?= $id ?>#borang" title="Sunting">✎</a>
              <form method="post" action="index.php?r=player.delete" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn--ghost btn--icon" type="submit" title="Buang"
                        data-confirm="Buang <?= e($p['name']) ?> berserta semua rekod kehadiran dan skornya?">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</article>
