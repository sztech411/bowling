<?php /** @var array $sessions @var array $tallies @var array $byPlayer @var int $held @var array $leaderboard */ ?>

<header class="phead">
  <p class="eyebrow">Analitik</p>
  <h1>Laporan &amp; Statistik</h1>
  <p class="phead__sub">Prestasi kehadiran mengikut sesi dan mengikut pemain.</p>
</header>

<?php
$sumHadir = $sumLewat = $sumTidak = 0;
foreach ($tallies as $t) {
    $sumHadir += $t['hadir'];
    $sumLewat += $t['lewat'];
    $sumTidak += $t['tidak_hadir'];
}
$marked = $sumHadir + $sumLewat + $sumTidak;
$overall = $marked > 0 ? (int)round(($sumHadir + $sumLewat) / $marked * 100) : 0;
?>

<div class="scorerow">
  <div class="score">
    <div class="score__label">Jumlah Sesi</div>
    <div class="score__value"><?= count($sessions) ?></div>
    <div class="score__foot"><?= $held ?> telah berjalan</div>
  </div>
  <div class="score score--pine">
    <div class="score__label">Rekod Hadir</div>
    <div class="score__value"><?= $sumHadir ?></div>
  </div>
  <div class="score score--amber">
    <div class="score__label">Rekod Lewat</div>
    <div class="score__value"><?= $sumLewat ?></div>
  </div>
  <div class="score score--accent">
    <div class="score__label">Kadar Keseluruhan</div>
    <div class="score__value"><?= $overall ?><small>%</small></div>
    <div class="score__foot"><?= $marked ?> rekod ditanda</div>
  </div>
</div>

<div class="split">

  <article class="card">
    <h3 class="card__title">Kadar Kehadiran Mengikut Pemain</h3>
    <?php if (!$byPlayer): ?>
      <div class="empty">Tiada pemain berdaftar.</div>
    <?php else: ?>
      <div class="bars">
        <?php foreach ($byPlayer as $r): ?>
          <div>
            <div class="bar__head">
              <b><?= e($r['player']['name']) ?></b>
              <span>
                <?= $r['rate'] ?>% ·
                <?= $r['counts']['hadir'] ?>H / <?= $r['counts']['lewat'] ?>L / <?= $r['counts']['tidak_hadir'] ?>T
              </span>
            </div>
            <div class="bar__track">
              <div class="bar__fill bar__fill--<?= $r['rate'] >= 80 ? 'hadir' : ($r['rate'] >= 50 ? 'lewat' : 'tidak') ?>"
                   style="width:<?= (int)$r['rate'] ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="footnote">H = hadir · L = lewat · T = tidak hadir. Kadar dikira daripada <?= $held ?> sesi yang telah berjalan.</p>
    <?php endif; ?>
  </article>

  <article class="card">
    <h3 class="card__title">Papan Pendahulu Skor</h3>
    <?php if (!$leaderboard): ?>
      <div class="empty">Belum ada skor direkod.</div>
    <?php else: ?>
      <div class="bars">
        <?php foreach ($leaderboard as $i => $row): ?>
          <div class="bar__head" style="margin-bottom:<?= $i === count($leaderboard) - 1 ? 0 : '.9rem' ?>">
            <b><?= $i + 1 ?>. <?= e($row['player']['name']) ?></b>
            <span><?= $row['pins'] ?> pin · Game <?= $row['game_no'] ?> · <?= e(fmt_date($row['session']['date'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</div>

<article class="card">
  <h3 class="card__title">Eksport &amp; Penyelenggaraan</h3>
  <p class="cardtext">
    Fail CSV disertakan tanda BOM UTF-8 supaya nama dan simbol dipaparkan betul dalam Excel.
  </p>
  <div class="rowbtns">
    <a class="btn btn--gold" href="index.php?r=export">Muat Turun CSV Penuh</a>
    <form method="post" action="index.php?r=data.reset" class="inline">
      <?= csrf_field() ?>
      <button class="btn btn--ghost" type="submit"
              data-confirm="Set semula seluruh pangkalan data kepada data demo asal? Semua perubahan akan hilang.">
        Set Semula Data Demo
      </button>
    </form>
  </div>
  <div class="notice">
    Data disimpan dalam <b>data/db.json</b> di dalam folder projek — tiada pelayan
    pangkalan data diperlukan.
  </div>
</article>

<article class="card card--flush">
  <div class="tablewrap">
    <table class="sheet">
      <thead>
        <tr>
          <th>Tarikh</th><th>Sesi</th><th>Lokasi</th>
          <th class="num">Hadir</th><th class="num">Lewat</th>
          <th class="num">Tidak Hadir</th><th class="num">Belum</th><th class="num">Kadar</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$sessions): ?>
          <tr><td colspan="8"><div class="empty">Tiada sesi untuk dilaporkan.</div></td></tr>
        <?php else: foreach ($sessions as $s): $t = $tallies[(int)$s['id']]; ?>
          <tr>
            <td><span class="code"><?= e($s['date']) ?></span></td>
            <td>
              <div class="cell-name"><?= e($s['title']) ?></div>
              <div class="cell-sub"><?= e($s['status']) ?></div>
            </td>
            <td><?= e($s['venue']) ?></td>
            <td class="num"><?= $t['hadir'] ?></td>
            <td class="num"><?= $t['lewat'] ?></td>
            <td class="num"><?= $t['tidak_hadir'] ?></td>
            <td class="num"><?= $t['belum'] ?></td>
            <td class="num"><b><?= $t['rate'] ?>%</b></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</article>
