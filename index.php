<?php
declare(strict_types=1);

/**
 * PIKO TAZ — Sistem Kehadiran Latihan Boling
 * Pengawal hadapan: satu titik masuk, semua paparan dijana oleh PHP.
 */

session_start();

require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/Repo.php';
require __DIR__ . '/lib/Support.php';
require __DIR__ . '/lib/Qr.php';

$repo = new Repo(new Db(__DIR__ . '/data/db.json'));

$route = (string)($_GET['r'] ?? 'dashboard');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$user = $_SESSION['user'] ?? null;

/** Sesi yang sedang dipilih untuk paparan kehadiran / QR. */
function chosen_session(Repo $repo): ?array
{
    $id = (int)($_GET['s'] ?? $_SESSION['view_session'] ?? 0);
    $s = $id > 0 ? $repo->session($id) : null;
    if ($s) {
        $_SESSION['view_session'] = (int)$s['id'];
        return $s;
    }
    $s = $repo->activeSession();
    if ($s) {
        return $s;
    }
    $all = $repo->sessions();
    return $all[0] ?? null;
}

// ══ Laluan awam: check-in melalui imbasan QR ══

if ($route === 'checkin-public') {
    $pin = preg_replace('/\D/', '', (string)($_GET['checkin'] ?? $_POST['pin'] ?? ''));
    $session = $pin !== '' ? $repo->sessionByPin($pin) : null;
    $result = null;
    $error = null;

    if ($isPost) {
        csrf_check();
        try {
            $result = $repo->checkin($pin, post_int('player_id'));
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }

    $players = $repo->players(true);
    require __DIR__ . '/views/checkin-public.php';
    exit;
}

// ══ Log masuk ══

if ($route === 'login' || !$user) {
    $error = null;
    if ($isPost && $route === 'login') {
        csrf_check();
        $found = $repo->authenticate(post_str('username'), post_str('password'));
        if ($found) {
            $_SESSION['user'] = $found;
            session_regenerate_id(true);
            flash('Selamat kembali, ' . $found['name'] . '.');
            redirect('dashboard');
        }
        $error = 'ID pengguna atau kata laluan tidak sah.';
    }
    require __DIR__ . '/views/login.php';
    exit;
}

// ══ Tindakan POST (pola post-redirect-get) ══

if ($isPost) {
    csrf_check();

    if (($user['role'] ?? '') === 'Pemain' && in_array($route, PEMAIN_DENIED_ACTIONS, true)) {
        flash('Anda tidak mempunyai kebenaran untuk tindakan ini.', 'bad');
        redirect('dashboard');
    }

    try {
        switch ($route) {

            case 'player.save':
                $id = post_int('id');
                $name = post_str('name');
                if ($name === '') {
                    throw new RuntimeException('Nama pemain diperlukan.');
                }
                $repo->savePlayer($id, [
                    'name' => $name,
                    'category' => post_str('category', 'Senior'),
                    'phone' => post_str('phone'),
                    'average' => max(0, min(300, post_int('average'))),
                    'active' => isset($_POST['active']),
                ]);
                flash($id > 0 ? 'Maklumat pemain dikemas kini.' : 'Pemain baharu ditambah.');
                redirect('players');

            case 'player.delete':
                $repo->deletePlayer(post_int('id'));
                flash('Pemain dan rekod kehadirannya dibuang.');
                redirect('players');

            case 'session.save':
                $id = post_int('id');
                $title = post_str('title');
                $date = post_str('date');
                if ($title === '' || $date === '') {
                    throw new RuntimeException('Nama latihan dan tarikh diperlukan.');
                }
                $activate = post_str('mode') === 'activate';
                $saved = $repo->saveSession($id, [
                    'title' => $title,
                    'date' => $date,
                    'start' => post_str('start', '20:00'),
                    'end' => post_str('end', '22:00'),
                    'venue' => post_str('venue', 'Lite Superbowl'),
                    'coach' => post_str('coach'),
                    'note' => post_str('note'),
                ], $activate);
                $_SESSION['view_session'] = (int)$saved['id'];
                flash($activate ? 'Sesi disimpan dan diaktifkan untuk check-in.' : 'Sesi disimpan dalam jadual.');
                redirect($activate ? 'checkin' : 'sessions');

            case 'session.status':
                $repo->setSessionStatus(post_int('id'), post_str('status'));
                $_SESSION['view_session'] = post_int('id');
                flash(post_str('status') === 'aktif' ? 'Sesi diaktifkan.' : 'Sesi ditutup.');
                redirect(post_str('back', 'sessions'));

            case 'session.delete':
                $repo->deleteSession(post_int('id'));
                unset($_SESSION['view_session']);
                flash('Sesi dan rekod kehadirannya dibuang.');
                redirect('sessions');

            case 'attendance.save':
                $sid = post_int('session_id');
                $marks = (array)($_POST['status'] ?? []);
                $n = 0;
                foreach ($marks as $pid => $status) {
                    if (!array_key_exists((string)$status, STATUS_LABEL)) {
                        continue;
                    }
                    $repo->mark($sid, (int)$pid, (string)$status, 'manual');
                    $n++;
                }
                flash($n . ' rekod kehadiran disimpan.');
                redirect('attendance');

            case 'attendance.bulk':
                $sid = post_int('session_id');
                $mode = post_str('mode');
                $map = $repo->attendanceMap($sid);
                $n = 0;
                foreach ($repo->players(true) as $p) {
                    $pid = (int)$p['id'];
                    $marked = isset($map[$pid]);
                    // 'hadir' menimpa semua; 'tidak_hadir' hanya yang belum ditanda;
                    // 'belum' mengosongkan yang sudah ditanda.
                    if ($mode === 'tidak_hadir' && $marked) {
                        continue;
                    }
                    if ($mode === 'belum' && !$marked) {
                        continue;
                    }
                    $repo->mark($sid, $pid, $mode, 'manual');
                    $n++;
                }
                flash($n . ' rekod dikemas kini.');
                redirect('attendance');

            case 'attendance.checkin':
                $s = $repo->session(post_int('session_id'));
                if (!$s) {
                    throw new RuntimeException('Sesi tidak dijumpai.');
                }
                $r = $repo->checkin((string)$s['pin'], post_int('player_id'));
                flash($r['player'] . ' direkod sebagai ' . STATUS_LABEL[$r['status']] . '.');
                redirect('checkin');

            case 'score.save':
                $sid = post_int('session_id');
                if (!$repo->session($sid)) {
                    throw new RuntimeException('Sesi tidak dijumpai.');
                }
                $games = (array)($_POST['pins'] ?? []); // pins[player_id][game_no] = nilai
                $n = 0;
                foreach ($games as $pid => $byGame) {
                    foreach ((array)$byGame as $gameNo => $val) {
                        $val = trim((string)$val);
                        $pins = $val === '' ? null : (int)$val;
                        $repo->saveScore($sid, (int)$pid, (int)$gameNo, $pins);
                        if ($pins !== null) {
                            $n++;
                        }
                    }
                }
                flash($n . ' keputusan game disimpan.');
                redirect('scores');

            case 'data.reset':
                $repo->reset();
                unset($_SESSION['view_session']);
                flash('Pangkalan data dipulihkan kepada data demo.');
                redirect('reports');

            case 'logout':
                session_destroy();
                header('Location: index.php?r=login');
                exit;

            default:
                redirect('dashboard');
        }
    } catch (Throwable $ex) {
        flash($ex->getMessage(), 'bad');
        $fallbacks = [
            'player.save' => 'players',
            'session.save' => 'sessions',
            'score.save' => 'scores',
        ];
        redirect($fallbacks[$route] ?? 'dashboard');
    }
}

// ══ Eksport CSV ══

if ($route === 'export') {
    if (($user['role'] ?? '') === 'Pemain') {
        flash('Anda tidak mempunyai kebenaran untuk tindakan ini.', 'bad');
        redirect('dashboard');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kehadiran-piko-taz-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
    fputcsv($out, ['Tarikh', 'Sesi', 'Lokasi', 'No Ahli', 'Nama', 'Kategori', 'Status', 'Kaedah', 'Masa Direkod']);
    foreach ($repo->exportRows() as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ══ Paparan (GET) ══

$pages = ['dashboard', 'players', 'sessions', 'checkin', 'attendance', 'scores', 'reports'];
if (!in_array($route, $pages, true)) {
    $route = 'dashboard';
}
if (!role_can_view((string)($user['role'] ?? ''), $route)) {
    flash('Anda tidak mempunyai akses ke halaman itu.', 'bad');
    redirect('dashboard');
}
$canEdit = role_can_edit((string)($user['role'] ?? ''));

// Data yang diperlukan oleh setiap paparan.
switch ($route) {
    case 'dashboard':
        $active = $repo->activeSession();
        $sessions = $repo->sessions();
        // Hero: sesi aktif, jika tiada sesi dijadualkan terdekat, jika tiada sesi terkini.
        $hero = $active;
        if (!$hero) {
            $upcoming = array_values(array_filter($sessions, fn($s) => $s['status'] === 'dijadualkan'));
            $hero = $upcoming ? end($upcoming) : ($sessions[0] ?? null);
        }
        $current = $active ?? ($sessions[0] ?? null);
        $tally = $current ? $repo->tally((int)$current['id']) : null;
        $roster = $repo->players(true);
        $trend = $repo->trend();
        break;

    case 'players':
        $players = $repo->players();
        $editing = isset($_GET['edit']) ? $repo->player((int)$_GET['edit']) : null;
        $rates = [];
        $scoreStats = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            $rates[$pid] = $repo->playerRate($pid);
            $scoreStats[$pid] = $repo->playerScoreStats($pid);
        }
        break;

    case 'sessions':
        $sessions = $repo->sessions();
        $editing = isset($_GET['edit']) ? $repo->session((int)$_GET['edit']) : null;
        $tallies = [];
        foreach ($sessions as $s) {
            $tallies[(int)$s['id']] = $repo->tally((int)$s['id']);
        }
        break;

    case 'checkin':
        $session = $repo->activeSession();
        if ($session) {
            $tally = $repo->tally((int)$session['id']);
            $map = $repo->attendanceMap((int)$session['id']);
            $log = $repo->attendanceLog((int)$session['id']);
            $pending = array_values(array_filter(
                $repo->players(true),
                fn($p) => !isset($map[(int)$p['id']])
            ));
            $qrUrl = base_url() . 'index.php?r=checkin-public&checkin=' . $session['pin'];
            $qrSvg = Qr::svg($qrUrl);
        }
        break;

    case 'attendance':
        $sessions = $repo->sessions();
        $session = chosen_session($repo);
        if ($session) {
            $tally = $repo->tally((int)$session['id']);
            $map = $repo->attendanceMap((int)$session['id']);
            $roster = $repo->players(true);
        }
        break;

    case 'scores':
        $sessions = $repo->sessions();
        $session = chosen_session($repo);
        if ($session) {
            $roster = $repo->players(true);
            $scores = $repo->scoresBySession((int)$session['id']);
            $sessionAvg = $repo->sessionScoreAverage((int)$session['id']);
        }
        break;

    case 'reports':
        $sessions = $repo->sessions();
        $tallies = [];
        foreach ($sessions as $s) {
            $tallies[(int)$s['id']] = $repo->tally((int)$s['id']);
        }
        $byPlayer = $repo->playerReport();
        $held = $repo->heldSessionCount();
        $leaderboard = $repo->scoreLeaderboard();
        break;
}

$flashes = take_flash();
require __DIR__ . '/views/layout.php';
