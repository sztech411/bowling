<?php /** @var array|null $hero @var array|null $current @var array|null $tally @var array $roster @var array $trend @var bool $canEdit */ ?>

<header class="phead">
  <p class="eyebrow">Ringkasan</p>
  <h1>Dashboard</h1>
  <p class="phead__sub">Keadaan latihan dan kehadiran semasa kelab.</p>
</header>

<div class="scorerow">
  <div class="score">
    <div class="score__label">Jumlah Pemain</div>
    <div class="score__value"><?= count($roster) ?></div>
    <div class="score__foot">ahli aktif berdaftar</div>
  </div>
  <div class="score score--pine">
    <div class="score__label">Hadir</div>
    <div class="score__value"><?= $tally ? $tally['hadir'] : 0 ?></div>
    <div class="score__foot"><?= $current ? e(fmt_date($current['date'])) : 'tiada sesi' ?></div>
  </div>
  <div class="score score--amber">
    <div class="score__label">Lewat</div>
    <div class="score__value"><?= $tally ? $tally['lewat'] : 0 ?></div>
    <div class="score__foot">lebih 15 min selepas mula</div>
  </div>
  <div class="score score--accent">
    <div class="score__label">Kadar Kehadiran</div>
    <div class="score__value"><?= $tally ? $tally['rate'] : 0 ?><small>%</small></div>
    <div class="score__foot">
      <?= $tally ? $tally['hadir'] + $tally['lewat'] : 0 ?> daripada <?= $tally ? $tally['total'] : 0 ?> pemain
    </div>
  </div>
</div>

<div class="split">

  <article class="card card--hero">
    <?php if (!$hero): ?>
      <div class="empty">
        <strong>Belum ada sesi latihan</strong>
        Cipta sesi pertama anda untuk mula merekod kehadiran.
        <p><a class="btn btn--primary" href="index.php?r=sessions">Cipta Sesi Latihan</a></p>
      </div>
    <?php else: ?>
      <p class="eyebrow">
        <?php if ($hero['status'] === 'aktif'): ?>Sesi Aktif Sekarang
        <?php elseif ($hero['status'] === 'dijadualkan'): ?>Sesi Akan Datang
        <?php else: ?>Sesi Terkini<?php endif; ?>
      </p>
      <h2 class="hero__title"><?= e($hero['title']) ?></h2>

      <dl class="hero__meta">
        <div><dt aria-hidden="true">📅</dt><dd><b><?= e(fmt_day($hero['date'])) ?>, <?= e(fmt_date($hero['date'])) ?></b></dd></div>
        <div><dt aria-hidden="true">⏰</dt><dd><b><?= e(fmt_time($hero['start'])) ?> – <?= e(fmt_time($hero['end'])) ?></b></dd></div>
        <div><dt aria-hidden="true">📍</dt><dd><b><?= e($hero['venue']) ?></b><?= $hero['coach'] ? ' · ' . e($hero['coach']) : '' ?></dd></div>
        <?php if (!empty($hero['note'])): ?>
          <div><dt aria-hidden="true">🎯</dt><dd><?= e($hero['note']) ?></dd></div>
        <?php endif; ?>
      </dl>

      <?php if ($canEdit): ?>
      <div class="hero__actions">
        <?php if ($hero['status'] === 'aktif'): ?>
          <a class="btn btn--primary" href="index.php?r=checkin">Buka QR Check-in</a>
          <a class="btn btn--ghost" href="index.php?r=attendance&s=<?= (int)$hero['id'] ?>">Tanda Kehadiran</a>
        <?php else: ?>
          <form method="post" action="index.php?r=session.status" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$hero['id'] ?>">
            <input type="hidden" name="status" value="aktif">
            <input type="hidden" name="back" value="checkin">
            <button class="btn btn--primary" type="submit">Aktifkan Sesi Ini</button>
          </form>
          <a class="btn btn--ghost" href="index.php?r=sessions">Urus Sesi</a>
        <?php endif; ?>
      </div>
      <?php elseif ($hero['status'] === 'aktif'): ?>
      <div class="hero__actions">
        <a class="btn btn--ghost" href="index.php?r=attendance&s=<?= (int)$hero['id'] ?>">Lihat Kehadiran</a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </article>

  <article class="card">
    <h3 class="card__title">Pecahan Kehadiran</h3>
    <?php if (!$current || !$tally): ?>
      <div class="empty">Tiada data untuk dipaparkan.</div>
    <?php else: ?>
      <div class="ring">
        <div class="ring__dial" style="--pct:<?= $tally['rate'] ?>">
          <span class="ring__num"><?= $tally['rate'] ?>%</span>
        </div>
        <div class="ring__text">
          <b><?= e($current['title']) ?></b>
          <?= e(fmt_date($current['date'])) ?><br>
          <?= $tally['hadir'] + $tally['lewat'] ?> / <?= $tally['total'] ?> pemain berada di lorong
        </div>
      </div>
      <div class="bars">
        <?php foreach (['hadir', 'lewat', 'tidak_hadir', 'belum'] as $st): ?>
          <div>
            <div class="bar__head">
              <b><?= e(STATUS_LABEL[$st]) ?></b>
              <span><?= $tally[$st] ?> pemain</span>
            </div>
            <div class="bar__track">
              <div class="bar__fill bar__fill--<?= e(STATUS_CLASS[$st]) ?>"
                   style="width:<?= $tally['total'] ? round($tally[$st] / $tally['total'] * 100, 1) : 0 ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</div>

<article class="card">
  <h3 class="card__title">Trend Sesi Terkini</h3>
  <?php if (!$trend): ?>
    <div class="empty">Belum ada sesi selesai untuk dianalisis.</div>
  <?php else: ?>
    <div class="trend">
      <?php foreach ($trend as $row): ?>
        <?php $t = $row['tally']; $h = fn(int $n) => $t['total'] ? max($n ? 4 : 0, round($n / $t['total'] * 140)) : 0; ?>
        <div class="tcol" title="<?= e($row['session']['title']) ?>">
          <div class="tcol__stack">
            <div class="tcol__seg tcol__seg--tidak" style="height:<?= $h($t['tidak_hadir']) ?>px"></div>
            <div class="tcol__seg tcol__seg--lewat" style="height:<?= $h($t['lewat']) ?>px"></div>
            <div class="tcol__seg tcol__seg--hadir" style="height:<?= $h($t['hadir']) ?>px"></div>
          </div>
          <div class="tcol__pct"><?= $t['rate'] ?>%</div>
          <div class="tcol__date">
            <?= e(date('j', strtotime($row['session']['date']))) ?>
            <?= e(BULAN[(int)date('n', strtotime($row['session']['date'])) - 1]) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="legend">
      <span><i style="background:var(--pine)"></i>Hadir</span>
      <span><i style="background:var(--gold)"></i>Lewat</span>
      <span><i style="background:var(--clay)"></i>Tidak hadir</span>
    </div>
  <?php endif; ?>
</article>
