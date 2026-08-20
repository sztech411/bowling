<?php
declare(strict_types=1);

/** Pembantu umum: lepasan HTML, format tarikh/masa BM, flash, CSRF. */

const STATUS_LABEL = [
    'hadir'       => 'Hadir',
    'lewat'       => 'Lewat',
    'tidak_hadir' => 'Tidak Hadir',
    'belum'       => 'Belum Ditanda',
];

const STATUS_CLASS = [
    'hadir'       => 'hadir',
    'lewat'       => 'lewat',
    'tidak_hadir' => 'tidak',
    'belum'       => 'belum',
];

const BULAN = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
const HARI  = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];

/** Kawalan akses ikut peranan. Admin & Jurulatih ada akses penuh; Pemain hanya lihat. */
const ROLE_PAGES = [
    'Admin'     => ['dashboard', 'players', 'sessions', 'checkin', 'attendance', 'scores', 'reports', 'settings', 'profile'],
    'Jurulatih' => ['dashboard', 'players', 'sessions', 'checkin', 'attendance', 'scores', 'reports', 'settings', 'profile'],
    'Pemain'    => ['dashboard', 'attendance', 'scores', 'profile'],
];

/** Tindakan POST yang disekat daripada peranan Pemain (lihat sahaja, tiada suntingan). */
const PEMAIN_DENIED_ACTIONS = [
    'player.save', 'player.delete',
    'session.save', 'session.status', 'session.delete',
    'attendance.save', 'attendance.bulk', 'attendance.checkin',
    'score.save', 'data.reset', 'settings.save',
];

function role_can_view(string $role, string $route): bool
{
    return in_array($route, ROLE_PAGES[$role] ?? ['dashboard'], true);
}

function role_can_edit(string $role): bool
{
    return $role !== 'Pemain';
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_date(string $iso): string
{
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    return date('j', $ts) . ' ' . BULAN[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function fmt_day(string $iso): string
{
    $ts = strtotime($iso);
    return $ts === false ? '' : HARI[(int)date('w', $ts)];
}

function fmt_time(?string $hhmm): string
{
    if (!$hhmm) {
        return '';
    }
    $ts = strtotime('2000-01-01 ' . $hhmm);
    return $ts === false ? $hhmm : date('g:i A', $ts);
}

/** Lencana status berwarna. */
function status_tag(string $status): string
{
    $cls = STATUS_CLASS[$status] ?? 'belum';
    $txt = STATUS_LABEL[$status] ?? $status;
    return '<span class="tag tag--' . $cls . '">' . e($txt) . '</span>';
}

function session_tag(string $status): string
{
    if ($status === 'aktif') {
        return '<span class="tag tag--aktif">Aktif</span>';
    }
    if ($status === 'dijadualkan') {
        return '<span class="tag tag--jadual">Dijadualkan</span>';
    }
    return '<span class="tag tag--belum">Selesai</span>';
}

// ── Flash ───────────────────────────────────────────────────

function flash(string $msg, string $kind = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'kind' => $kind];
}

/** @return array<int, array{msg:string,kind:string}> */
function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ── CSRF ────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string)($_POST['_token'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        exit('Sesi telah tamat tempoh. <a href="index.php">Muat semula halaman</a> dan cuba lagi.');
    }
}

// ── Navigasi ────────────────────────────────────────────────

function redirect(string $route = 'dashboard'): never
{
    header('Location: index.php?r=' . urlencode($route));
    exit;
}

/** URL asas aplikasi, digunakan untuk membina pautan QR. */
function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir . '/';
}

/**
 * URL aset dengan versi cache-bust berdasarkan masa fail diubah suai — pelayar
 * akan sentiasa muat turun semula sebaik fail berubah, tanpa perlu segar semula
 * secara manual (elak isu CSS/JS lama tersimpan dalam cache pelayar).
 */
function asset_url(string $relativePath): string
{
    $full = __DIR__ . '/../' . $relativePath;
    $v = @filemtime($full);
    return e($relativePath) . ($v ? '?v=' . $v : '');
}

function post_str(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}
