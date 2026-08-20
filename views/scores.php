<?php
/** @var array $sessions @var array|null $session @var array $roster @var array $scores @var float|null $sessionAvg @var bool $canEdit */
$maxGames = Repo::MAX_GAMES_PER_SESSION;
?>

<header class="phead">
  <p class="eyebrow">Prestasi</p>
  <h1>Skor &amp; Prestasi Pemain</h1>
  <p class="phead__sub">Rekod keputusan setiap game (0–300 pin) untuk sesi latihan. Purata dan skor tertinggi dikira automatik.</p>
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
      <input type="hidden" name="r" value="scores">
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

    <?php
      $recorded = 0;
      $high = 0;
      foreach ($scores as $byGame) {
          foreach ($byGame as $rec) {
              $recorded++;
              $high = max($high, (int)$rec['pins']);
          }
      }
    ?>
    <div class="scorerow scorerow--tight">
      <div class="score score--accent"><div class="score__label">Game Direkod</div><div class="score__value"><?= $recorded ?></div></div>
      <div class="score score--pine"><div class="score__label">Purata Sesi</div><div class="score__value"><?= $sessionAvg !== null ? $sessionAvg : '—' ?></div></div>
      <div class="score score--amber"><div class="score__label">Skor Tertinggi</div><div class="score__value"><?= $recorded ? $high : '—' ?></div></div>
      <div class="score"><div class="score__label">Pemain</div><div class="score__value"><?= count($roster) ?></div></div>
    </div>
  </article>

  <?php $tag = $canEdit ? 'form' : 'div'; ?>
  <<?= $tag ?><?= $canEdit ? ' method="post" action="index.php?r=score.save"' : '' ?>>
    <?php if ($canEdit): ?><?= csrf_field() ?><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><?php endif; ?>

    <article class="card card--flush">
      <div class="tablewrap">
        <table class="sheet">
          <thead>
            <tr>
              <th>Pemain</th><th>Kategori</th>
              <?php for ($g = 1; $g <= $maxGames; $g++): ?><th>Game <?= $g ?></th><?php endfor; ?>
              <th class="num">Jumlah</th><th class="num">Purata</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roster as $p): $id = (int)$p['id']; $byGame = $scores[$id] ?? []; ?>
              <?php
                $sum = 0; $n = 0;
                foreach ($byGame as $rec) { $sum += (int)$rec['pins']; $n++; }
              ?>
              <tr>
                <td>
                  <div class="cell-name"><?= e($p['name']) ?></div>
                  <div class="cell-sub"><?= e($p['no_ahli']) ?></div>
                </td>
                <td><?= e($p['category']) ?></td>
                <?php for ($g = 1; $g <= $maxGames; $g++): ?>
                  <td>
                    <?php if ($canEdit): ?>
                    <input type="number" min="0" max="<?= Repo::MAX_PINS ?>" inputmode="numeric"
                           name="pins[<?= $id ?>][<?= $g ?>]"
                           value="<?= isset($byGame[$g]) ? (int)$byGame[$g]['pins'] : '' ?>"
                           placeholder="—" class="scoreinput">
                    <?php else: ?>
                      <?= isset($byGame[$g]) ? (int)$byGame[$g]['pins'] : '—' ?>
                    <?php endif; ?>
                  </td>
                <?php endfor; ?>
                <td class="num"><?= $n ? $sum : '—' ?></td>
                <td class="num"><?= $n ? round($sum / $n, 1) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <?php if ($canEdit): ?>
    <div class="actionbar">
      <span class="actionbar__note">Kosongkan medan untuk membuang rekod game tersebut. Skor sah: 0–<?= Repo::MAX_PINS ?>.</span>
      <button class="btn btn--primary" type="submit">Simpan Skor</button>
    </div>
    <?php endif; ?>
  </<?= $tag ?>>

<?php endif; ?>
