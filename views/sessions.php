<?php /** @var array $sessions @var array|null $editing @var array $tallies */ ?>

<header class="phead">
  <p class="eyebrow">Penjadualan</p>
  <h1>Sesi Latihan</h1>
  <p class="phead__sub">Cipta sesi, aktifkan untuk membuka check-in, dan tutup apabila selesai. Hanya satu sesi boleh aktif pada satu masa.</p>
</header>

<article class="card" id="borang">
  <h3 class="card__title">
    <?= $editing ? 'Sunting: ' . e($editing['title']) : 'Cipta Sesi Baharu' ?>
    <?php if ($editing): ?>
      <a class="btn btn--ghost btn--sm" href="index.php?r=sessions">Batal</a>
    <?php endif; ?>
  </h3>

  <form method="post" action="index.php?r=session.save" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

    <label class="field field--wide">
      <span class="field__label">Nama / jenis latihan</span>
      <input name="title" required value="<?= e($editing['title'] ?? 'Latihan Teknik & Konsistensi') ?>">
    </label>

    <label class="field">
      <span class="field__label">Tarikh</span>
      <input name="date" type="date" required value="<?= e($editing['date'] ?? date('Y-m-d')) ?>">
    </label>
    <label class="field">
      <span class="field__label">Mula</span>
      <input name="start" type="time" value="<?= e($editing['start'] ?? '20:00') ?>">
    </label>
    <label class="field">
      <span class="field__label">Tamat</span>
      <input name="end" type="time" value="<?= e($editing['end'] ?? '22:00') ?>">
    </label>
    <label class="field">
      <span class="field__label">Lokasi</span>
      <input name="venue" value="<?= e($editing['venue'] ?? 'Lite Superbowl') ?>">
    </label>
    <label class="field field--wide">
      <span class="field__label">Jurulatih</span>
      <input name="coach" value="<?= e($editing['coach'] ?? 'Coach Rizal') ?>">
    </label>
    <label class="field field--wide">
      <span class="field__label">Catatan</span>
      <textarea name="note" rows="2"><?= e($editing['note'] ?? 'Latihan asas, teknik dan konsistensi.') ?></textarea>
    </label>

    <div class="formgrid__actions">
      <button class="btn btn--primary" type="submit" name="mode" value="activate">Simpan &amp; Aktifkan</button>
      <button class="btn btn--ghost" type="submit" name="mode" value="draft">Simpan Sebagai Jadual</button>
    </div>
  </form>
</article>

<?php if (!$sessions): ?>
  <div class="card">
    <div class="empty"><strong>Tiada sesi</strong>Gunakan borang di atas untuk mencipta sesi latihan pertama.</div>
  </div>
<?php else: ?>
  <div class="sessionlist">
    <?php foreach ($sessions as $s): $id = (int)$s['id']; $t = $tallies[$id]; ?>
      <article class="scard<?= $s['status'] === 'aktif' ? ' scard--aktif' : '' ?>">
        <div class="scard__top">
          <h3 class="scard__title"><?= e($s['title']) ?></h3>
          <?= session_tag($s['status']) ?>
        </div>

        <div class="scard__meta">
          <span><?= e(fmt_day($s['date'])) ?>, <?= e(fmt_date($s['date'])) ?></span>
          <span><?= e(fmt_time($s['start'])) ?> – <?= e(fmt_time($s['end'])) ?> · <?= e($s['venue']) ?></span>
          <span>Kod check-in: <b class="code code--lg"><?= e($s['pin']) ?></b></span>
        </div>

        <div class="scard__mini">
          <span class="mini mini--hadir"><b><?= $t['hadir'] ?></b> hadir</span>
          <span class="mini mini--lewat"><b><?= $t['lewat'] ?></b> lewat</span>
          <span class="mini mini--tidak"><b><?= $t['tidak_hadir'] ?></b> tidak hadir</span>
          <span class="mini"><b><?= $t['rate'] ?>%</b> kadar</span>
        </div>

        <div class="scard__foot">
          <?php if ($s['status'] !== 'aktif'): ?>
            <form method="post" action="index.php?r=session.status" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="status" value="aktif">
              <input type="hidden" name="back" value="sessions">
              <button class="btn btn--primary btn--sm" type="submit">Aktifkan</button>
            </form>
          <?php else: ?>
            <form method="post" action="index.php?r=session.status" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="status" value="selesai">
              <input type="hidden" name="back" value="sessions">
              <button class="btn btn--pine btn--sm" type="submit">Tutup Sesi</button>
            </form>
          <?php endif; ?>

          <a class="btn btn--ghost btn--sm" href="index.php?r=attendance&s=<?= $id ?>">Kehadiran</a>
          <a class="btn btn--ghost btn--sm" href="index.php?r=sessions&edit=<?= $id ?>#borang">Sunting</a>

          <form method="post" action="index.php?r=session.delete" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn--ghost btn--sm" type="submit"
                    data-confirm="Buang sesi &quot;<?= e($s['title']) ?>&quot; berserta rekod kehadirannya?">Buang</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
