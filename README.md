# 🎳 PIKO TAZ — Sistem Kehadiran Latihan Boling

Demo sistem pengurusan kehadiran latihan boling. **PHP tulen, dijana di pelayan**, dengan
fail JSON sebagai pangkalan data. Tiada MySQL, tiada Composer, tiada rangka kerja, dan
tiada kebergantungan JavaScript untuk apa-apa fungsi.

## Menjalankan

Letakkan folder ini di `C:\laragon\www\`, kemudian buka:

    http://localhost/bowling/

Tanpa Laragon:

```bash
php -S 127.0.0.1:8000 -t .
```

Memerlukan **PHP 7.4 atau lebih baharu** (disahkan berjalan di bawah Apache/Laragon
PHP 7.4.19 — tiada sintaks khusus PHP 8 seperti `match`/`enum`/`readonly` digunakan).

## Persediaan pertama kali

Sebelum guna sistem sebenar (bukan sekadar demo), buka **`setup.php`** dalam pelayar
untuk semak persekitaran pelayan dan tetapkan **kata laluan admin sebenar** (hash
bcrypt, gantikan `admin123` demo). Lihat bahagian [Persediaan pertama kali](#persediaan-pertama-kali-1)
di bawah untuk butiran penuh. Sehingga `setup.php` dijalankan, akaun demo di bawah
kekal aktif.

## Akaun demo

| ID       | Kata laluan | Peranan   |
|----------|-------------|-----------|
| `admin`  | `admin123`  | Admin     |
| `coach`  | `coach123`  | Jurulatih |
| `pemain` | `pemain123` | Pemain    |

Selepas `setup.php` dijalankan, kata laluan **admin** ditukar kepada yang anda
tetapkan sendiri — `coach`/`pemain` kekal seperti di atas (untuk ujian).

## Ciri

- **Dashboard** — KPI, kad sesi aktif, cincin peratusan, trend enam sesi terakhir.
- **Pemain** — tambah, sunting, buang, tanda aktif/tidak aktif; kadar kehadiran automatik.
- **Sesi Latihan** — cipta, jadualkan, aktifkan, tutup, buang. Hanya satu sesi aktif
  pada satu masa; mengaktifkan sesi baharu menutup sesi sebelumnya.
- **QR Check-in** — QR sebenar yang boleh diimbas, **dijana oleh PHP** sebagai SVG.
  Check-in lebih 15 minit selepas waktu mula ditanda **Lewat** secara automatik.
- **Kehadiran** — satu borang untuk seluruh senarai, plus tindakan pukal.
- **Laporan** — ringkasan sesi dan pemain, eksport CSV dengan BOM UTF-8 untuk Excel.

### Check-in melalui imbasan telefon

QR mengandungi URL `index.php?r=checkin-public&checkin=<kod>` yang membuka borang
check-in **tanpa perlu log masuk**. Supaya telefon boleh mencapainya, layan projek pada
alamat LAN mesin anda (cth. `http://192.168.0.10/bowling/`), bukan `localhost`.

## Struktur

```
setup.php                       persediaan pertama kali — jalankan sekali sahaja
index.php                       pengawal hadapan — routing, POST, redirect
views/layout.php                rangka: topbar (desktop) + tabbar (mudah alih), flash
views/dashboard.php             ┐
views/players.php               │
views/sessions.php              │
views/checkin.php               ├ satu fail bagi setiap paparan
views/attendance.php            │
views/scores.php                │
views/reports.php               ┘
views/login.php                 skrin log masuk (rangka sendiri)
views/checkin-public.php        check-in awam melalui QR (rangka sendiri)
views/_pwa-head.php             partial: tag manifest/ikon/tema (dikongsi 3 rangka)
views/_pwa-register.php         partial: pendaftaran service worker
lib/Db.php                      titik masuk data — pilih Firestore atau JSON automatik
lib/DbBackendInterface.php      kontrak storan (all/mutate)
lib/JsonFileBackend.php         storan lalai: fail JSON, kunci fail + tulis atomik
lib/FirestoreBackend.php        storan pilihan: satu dokumen Firestore + kawalan konkurensi
lib/GoogleAuth.php              log masuk akaun perkhidmatan Google (JWT RS256 tanpa SDK)
lib/Firestore.php               klien REST Firestore + penukaran nilai bertaip
lib/Repo.php                    lapisan domain — semua bacaan/tulisan melalui sini
lib/Support.php                 pembantu: lepasan HTML, tarikh BM, flash, CSRF, asset_url()
lib/Qr.php                      penjana QR dalam PHP (mod byte, EC tahap M, versi 1–10)
assets/style.css                tema kertas krim / dakwat oxblood
assets/icons/                   ikon PWA (PNG, dijana melalui GD)
manifest.webmanifest            manifest PWA
sw.js                           service worker (cache aset statik sahaja)
offline.html                    fallback statik apabila luar talian
config/firebase.example.php     templat konfigurasi Firestore — salin → firebase.php
scripts/firestore-selftest.php  ujian sendiri sambungan Firestore (CLI)
scripts/migrate-to-firestore.php pindah data/db.json sedia ada ke Firestore (CLI)
data/db.json                    PANGKALAN DATA lalai — dijana automatik pada larian pertama
.htaccess                       MIME .webmanifest + no-cache sw.js
data/.htaccess                  ┐
lib/.htaccess                   │
views/.htaccess                 ├ halang akses terus melalui pelayar (403)
config/.htaccess                │
scripts/.htaccess                ┘
```

## Reka bentuk

Tema "almanak sukan bercetak": kertas krim hangat dengan garisan halus, dakwat oxblood,
aksen kuning kunyit, dan sidebar gelap bermotif papan lorong. Fraunces (paparan) +
Archivo (teks) + JetBrains Mono (kod dan angka). Nombor menggunakan angka tabular supaya
lajur statistik berbaris tepat.

## Pangkalan data

Lalai: semua data dalam `data/db.json` — jadual `users`, `players`, `sessions`,
`attendance`, `scores`. Setiap tulisan mengambil kunci eksklusif (`flock`) sebelum
menulis semula keseluruhan fail, jadi dua check-in serentak tidak akan merosakkan data.

Untuk mula semula: **Laporan → Set Semula Data Demo**, atau padam `data/db.json`.

Boleh juga tukar kepada **Firestore** (Firebase) — lihat bahagian seterusnya.

## Persediaan pertama kali

Buka **`http://localhost/bowling/setup.php`** dalam pelayar. Ia akan:

1. **Semak persekitaran** — versi PHP, sambungan (`json`/`curl`/`openssl` wajib,
   `gd` pilihan), dan kebenaran tulis pada folder `data/` dan `config/`.
2. **Kesan/sedia storan** — jika `config/firebase.php` sudah wujud (lihat bahagian
   Firestore di bawah), ia terus guna itu. Jika tidak, anda pilih fail JSON tempatan
   (lalai, mudah) atau tampal terus Project ID + kandungan JSON kunci akaun
   perkhidmatan Firestore untuk sambung terus dari sini.
3. **Tetapkan akaun admin sebenar** — nama, ID pengguna, dan kata laluan (disimpan
   sebagai hash `bcrypt`, bukan teks biasa). Ini gantikan akaun demo `admin`/`admin123`.

Selepas berjaya, fail penanda `data/.installed` dicipta dan `setup.php` **mengunci
diri** — pelawat seterusnya ke URL itu cuma nampak mesej "sudah dipasang", tidak
boleh tetapkan semula kata laluan admin. Ini penting kerana `setup.php` boleh
dicapai **tanpa log masuk** (ia berada sebelum log masuk dalam aliran aplikasi),
jadi ia mesti menutup diri secara automatik supaya sesiapa yang jumpa URL itu
selepas sistem digunakan sebenar tidak boleh rampas akaun admin.

Untuk jalankan `setup.php` semula (cth. tetapkan semula kata laluan admin), padam
fail `data/.installed` pada pelayan secara manual, kemudian muat semula halaman.

Akaun demo `coach`/`pemain` **tidak** disentuh oleh `setup.php` — kekal kata laluan
teks biasa asal untuk ujian. Tiada skrin urus pengguna dalam aplikasi buat masa ini;
untuk tukar/buang akaun demo, edit terus `data/db.json` atau dokumen Firestore.

## Firestore (pilihan — pangkalan data awan)

Aplikasi boleh guna **Firestore** sebagai ganti `data/db.json`, tanpa mengubah satu
baris pun kod PHP dalam `index.php`, `Repo.php`, atau `views/`. Ini kerana `lib/Db.php`
memilih storan secara automatik: jika `config/firebase.php` wujud dan lengkap, ia guna
Firestore; jika tidak, ia terus guna fail JSON tempatan seperti biasa.

**Penting:** Firebase Hosting sendiri **tidak boleh** menjalankan PHP secara terus —
ia hanya hosting statik + Cloud Functions/Cloud Run. Persediaan ini **hanya** memindahkan
pangkalan data ke awan; aplikasi PHP tetap dijalankan melalui Apache/Laragon (atau mana-mana
pelayan berkemampuan PHP) seperti sekarang.

### Cara kerja

Seluruh keadaan aplikasi (`users`/`players`/`sessions`/`attendance`/`scores`) disimpan
sebagai **satu dokumen Firestore** (`piko_taz/state`) — sama seperti fail JSON tunggal,
cuma dipindah ke awan. Sambungan diautentikasi melalui akaun perkhidmatan (service
account) menggunakan JWT RS256 yang ditandatangani sendiri (`lib/GoogleAuth.php`) dan
klien REST Firestore ringkas (`lib/Firestore.php`) — **tiada Firebase SDK atau Composer**,
konsisten dengan seluruh projek ini.

### Persediaan

1. **Cipta projek Firebase** — [console.firebase.google.com](https://console.firebase.google.com)
   → Tambah Projek.
2. **Aktifkan Firestore** — menu kiri → Firestore Database → Cipta pangkalan data
   (mod Native, pilih lokasi berdekatan).
3. **Jana akaun perkhidmatan** — ⚙️ Project Settings → Service Accounts → **Generate
   new private key**. Ini memuat turun satu fail JSON.
4. **Letak fail kunci** — simpan fail yang dimuat turun sebagai
   `config/firebase-service-account.json` (folder `config/` sudah dilindungi
   `.htaccess` — tidak boleh dicapai terus melalui pelayar).
5. **Salin konfigurasi**:
   ```bash
   cp config/firebase.example.php config/firebase.php
   ```
   Kemudian buka `config/firebase.php` dan isikan `project_id` (dari Project
   Settings → General → Project ID).
6. **Uji sambungan** sebelum bergantung padanya:
   ```bash
   php scripts/firestore-selftest.php
   ```
   Skrip ini akan sahkan kelayakan, baca (atau cipta + benih data demo jika
   dokumen belum wujud), tulis penanda ujian, dan baca semula untuk sahkan
   pusingan penuh berfungsi.
7. **(Pilihan) Pindahkan data sedia ada** — jika `data/db.json` anda sudah ada
   data sebenar (bukan sekadar demo), jalankan sebelum langkah 6 supaya ia
   tidak digantikan set data awal:
   ```bash
   php scripts/migrate-to-firestore.php
   ```

Selepas `config/firebase.php` wujud dan sah, aplikasi web akan terus guna Firestore
secara automatik — tiada perubahan lain diperlukan. Untuk kembali ke fail JSON
tempatan, padam atau namakan semula `config/firebase.php`.

### Nota

- **Kawalan akses**: akaun perkhidmatan mempunyai akses penuh (admin) kepada Firestore
  dan **memintas** Peraturan Keselamatan (Security Rules) yang ditetapkan dalam Firebase
  Console sepenuhnya — peraturan itu hanya terpakai untuk capaian terus SDK klien/Auth,
  bukan panggilan REST API berautentikasi akaun perkhidmatan seperti di sini. Kawalan
  akses sebenar kekal di peringkat aplikasi PHP (log masuk + CSRF), sama seperti sekarang.
- **Konkurensi**: setiap `mutate()` baca `updateTime` dokumen terkini dan hantar semula
  sebagai prasyarat semasa tulis; jika berlanggar dengan tulisan lain (jarang berlaku
  untuk trafik kelab kecil), ia cuba sekali lagi secara automatik di atas data terkini.
- **Fail kunci ialah rahsia sensitif** — ia memberi akses penuh baca/tulis Firestore.
  Jangan kongsi, jangan letak dalam folder web-accessible, dan jangan hantar melalui
  emel/mesej biasa.
- Token akses OAuth2 dicache dalam `data/.firestore-token-cache.json` (± 1 jam) supaya
  tidak perlu tandatangan JWT baharu pada setiap permintaan.

## Keselamatan

Yang **ada**: sesi PHP untuk log masuk, `session_regenerate_id()` selepas log masuk,
token CSRF pada setiap borang POST, pola post-redirect-get, lepasan HTML pada semua
output, `.htaccess` yang menghalang akses terus kepada `lib/`, `views/`, `data/`,
`config/`, dan `scripts/`. Kata laluan disokong dalam **dua bentuk**: hash `bcrypt`
(akaun yang ditetapkan melalui `setup.php`) dan teks biasa (akaun demo lama) —
`Repo::authenticate()` cuba `password_verify()` dahulu, jatuh kembali kepada
perbandingan teks biasa hanya jika nilai tersimpan bukan hash yang sah.

Yang **tiada** (kerana ini demo): peranan tidak dikuatkuasakan — ketiga-tiga akaun
melihat paparan yang sama (semakan peranan perlu ditambah pada setiap tindakan dalam
`index.php` untuk penggunaan sebenar), dan akaun demo `coach`/`pemain` kekal teks
biasa selagi tiada skrin urus pengguna dalam aplikasi.

## Nota

JavaScript digunakan **hanya** untuk peningkatan progresif (hantar automatik penukar
sesi, dialog kepastian sebelum membuang, sembunyikan mesej flash). Matikan JavaScript
dan setiap fungsi masih berjalan melalui borang HTML biasa.

## PWA (Progressive Web App)

Aplikasi boleh dipasang ke skrin utama telefon/desktop dan berfungsi seperti apl asli.

- `manifest.webmanifest` — nama, ikon, warna tema, `display: standalone`, pintasan
  (shortcut) terus ke QR Check-in / Kehadiran / Skor.
- `sw.js` — service worker. **Halaman HTML tidak dicache** kerana setiap muatan
  membawa token CSRF sesi semasa; hanya aset statik (`assets/`, ikon, manifest)
  dicache. Navigasi sentiasa cuba rangkaian dahulu dan jatuh kembali ke
  `offline.html` hanya apabila benar-benar tiada sambungan.
- `assets/icons/` — ikon PNG dijana melalui GD PHP (192, 512, versi maskable 512,
  apple-touch-icon, favicon). Skrip penjana tidak disertakan dalam projek —
  jalankan semula secara berasingan jika ikon perlu diubah.
- `.htaccess` (root) — jenis MIME `application/manifest+json` untuk
  `.webmanifest`, dan `Cache-Control: no-cache` pada `sw.js` supaya kemas kini
  service worker sentiasa sampai.

**Pasang:** buka `http://<alamat-lan-anda>/bowling/` dalam Chrome/Edge/Safari
mudah alih → menu pelayar → "Tambah ke Skrin Utama" / "Pasang Apl". PWA
memerlukan HTTPS **kecuali** `localhost`, yang dikecualikan khas untuk
pembangunan — jadi ujian di `http://localhost/bowling/` berfungsi tanpa
sijil TLS.

Nota alat: pratonton dalam sesetengah pelayar automatik/sandboxed (termasuk
panel pratonton Claude Code) menyekat pendaftaran service worker walaupun
skrip dan manifest sah — sahkan pemasangan sebenar dalam Chrome/Edge biasa.
