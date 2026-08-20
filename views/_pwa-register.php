<script>
/* Daftar service worker untuk keupayaan PWA (ikon, "Add to Home Screen", fallback luar talian). */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () { /* diam-diam gagal, aplikasi tetap berjalan */ });
  });
}
</script>
