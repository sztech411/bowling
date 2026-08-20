<?php /** @var array $user */ ?>

<header class="phead">
  <p class="eyebrow">Akaun</p>
  <h1>Profil Saya</h1>
  <p class="phead__sub">Kemas kini nama, ID pengguna, dan kata laluan akaun anda.</p>
</header>

<article class="card">
  <form method="post" action="index.php?r=profile.save" class="formgrid">
    <?= csrf_field() ?>

    <label class="field field--wide">
      <span class="field__label">Nama penuh</span>
      <input name="name" required value="<?= e($user['name']) ?>">
    </label>

    <label class="field">
      <span class="field__label">ID Pengguna</span>
      <input name="username" required value="<?= e($user['username']) ?>">
    </label>

    <label class="field">
      <span class="field__label">Peranan</span>
      <input value="<?= e($user['role']) ?>" disabled>
    </label>

    <label class="field">
      <span class="field__label">Kata Laluan Baharu</span>
      <input name="new_password" type="password" autocomplete="new-password" placeholder="Kosongkan jika tidak berubah">
    </label>

    <label class="field">
      <span class="field__label">Sahkan Kata Laluan Baharu</span>
      <input name="confirm_password" type="password" autocomplete="new-password">
    </label>

    <div class="formgrid__actions">
      <button class="btn btn--primary" type="submit">Simpan Profil</button>
    </div>
  </form>
</article>
