<?php /** @var array $settings */ ?>

<header class="phead">
  <p class="eyebrow">Tetapan</p>
  <h1>Tetapan Sesi Latihan</h1>
  <p class="phead__sub">Urus senarai jurulatih dan lokasi yang dipaparkan semasa mencipta sesi, serta pilihan lalai.</p>
</header>

<article class="card">
  <form method="post" action="index.php?r=settings.save" class="formgrid">
    <?= csrf_field() ?>

    <label class="field field--wide">
      <span class="field__label">Senarai Jurulatih (satu setiap baris)</span>
      <textarea name="coaches" rows="4" placeholder="Coach Rizal&#10;Coach Aiman"><?= e(implode("\n", $settings['coaches'])) ?></textarea>
    </label>

    <label class="field field--wide">
      <span class="field__label">Senarai Lokasi (satu setiap baris)</span>
      <textarea name="venues" rows="4" placeholder="Lite Superbowl&#10;Sunway Pyramid Bowl"><?= e(implode("\n", $settings['venues'])) ?></textarea>
    </label>

    <label class="field">
      <span class="field__label">Jurulatih Lalai</span>
      <select name="default_coach">
        <option value="">— Tiada —</option>
        <?php foreach ($settings['coaches'] as $c): ?>
          <option<?= $c === $settings['default_coach'] ? ' selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="field">
      <span class="field__label">Lokasi Lalai</span>
      <select name="default_venue">
        <option value="">— Tiada —</option>
        <?php foreach ($settings['venues'] as $v): ?>
          <option<?= $v === $settings['default_venue'] ? ' selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <p class="phead__sub" style="margin:0 0 -.4rem">Nota: jurulatih/lokasi lalai akan ditambah automatik ke senarai di atas jika belum wujud.</p>

    <div class="formgrid__actions">
      <button class="btn btn--primary" type="submit">Simpan Tetapan</button>
    </div>
  </form>
</article>
