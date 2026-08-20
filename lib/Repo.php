<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Lapisan domain di atas store JSON — semua bacaan dan tulisan aplikasi
 * melalui kelas ini supaya pengawal dan paparan kekal ringkas.
 */
final class Repo
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    // ── Pengguna ────────────────────────────────────────────

    /**
     * Sokong dua bentuk kata laluan: hash bcrypt (akaun yang ditetapkan
     * melalui setup.php) dan teks biasa (akaun demo lama). password_verify()
     * pulangkan false dengan selamat untuk rentetan bukan-hash, jadi susulan
     * hash_equals() bertindak sebagai jalan sandaran tanpa risiko keselamatan
     * tambahan untuk akaun yang sudah dinaik taraf.
     */
    public function authenticate(string $username, string $password): ?array
    {
        foreach ($this->db->table('users') as $u) {
            if (strcasecmp($u['username'], $username) !== 0) {
                continue;
            }
            $stored = (string)$u['password'];
            if (password_verify($password, $stored) || hash_equals($stored, $password)) {
                unset($u['password']);
                return $u;
            }
        }
        return null;
    }

    /**
     * Kemas kini profil pengguna sendiri (nama, ID pengguna, kata laluan
     * pilihan). Kata laluan baharu dihantar sebagai teks biasa dan
     * di-hash di sini; kosongkan untuk kekalkan kata laluan sedia ada.
     */
    public function updateProfile(int $id, string $name, string $username, string $newPassword = ''): array
    {
        if ($name === '') {
            throw new RuntimeException('Nama diperlukan.');
        }
        if ($username === '') {
            throw new RuntimeException('ID pengguna diperlukan.');
        }
        foreach ($this->db->table('users') as $u) {
            if ((int)$u['id'] !== $id && strcasecmp($u['username'], $username) === 0) {
                throw new RuntimeException('ID pengguna itu sudah digunakan.');
            }
        }

        return $this->db->mutate(function (array &$data) use ($id, $name, $username, $newPassword) {
            foreach ($data['users'] as $i => $u) {
                if ((int)$u['id'] === $id) {
                    $data['users'][$i]['name'] = $name;
                    $data['users'][$i]['username'] = $username;
                    if ($newPassword !== '') {
                        $data['users'][$i]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                    $safe = $data['users'][$i];
                    unset($safe['password']);
                    return $safe;
                }
            }
            throw new RuntimeException('Pengguna tidak dijumpai.');
        });
    }

    // ── Pemain ──────────────────────────────────────────────

    /** @return array<int, array> */
    public function players(bool $onlyActive = false): array
    {
        $rows = $this->db->table('players');
        if ($onlyActive) {
            $rows = array_values(array_filter($rows, fn($p) => !empty($p['active'])));
        }
        usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $rows;
    }

    public function player(int $id): ?array
    {
        foreach ($this->db->table('players') as $p) {
            if ((int)$p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    public function savePlayer(int $id, array $fields): array
    {
        return $this->db->mutate(function (array &$data) use ($id, $fields) {
            if ($id > 0) {
                foreach ($data['players'] as $i => $p) {
                    if ((int)$p['id'] === $id) {
                        $data['players'][$i] = array_merge($p, $fields);
                        return $data['players'][$i];
                    }
                }
                throw new RuntimeException('Pemain tidak dijumpai.');
            }
            $newId = Db::nextId($data['players']);
            $row = array_merge([
                'id' => $newId,
                'no_ahli' => 'MSN-' . (1000 + $newId),
                'joined_at' => date('Y-m-d'),
            ], $fields);
            $data['players'][] = $row;
            return $row;
        });
    }

    public function deletePlayer(int $id): void
    {
        $this->db->mutate(function (array &$data) use ($id) {
            $data['players'] = array_values(array_filter(
                $data['players'],
                fn($p) => (int)$p['id'] !== $id
            ));
            $data['attendance'] = array_values(array_filter(
                $data['attendance'],
                fn($a) => (int)$a['player_id'] !== $id
            ));
            $data['scores'] = array_values(array_filter(
                $data['scores'] ?? [],
                fn($s) => (int)$s['player_id'] !== $id
            ));
        });
    }

    // ── Sesi ────────────────────────────────────────────────

    /** Terkini dahulu. @return array<int, array> */
    public function sessions(): array
    {
        $rows = $this->db->table('sessions');
        usort($rows, fn($a, $b) => strcmp($b['date'] . $b['start'], $a['date'] . $a['start']));
        return $rows;
    }

    public function session(int $id): ?array
    {
        foreach ($this->db->table('sessions') as $s) {
            if ((int)$s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    public function activeSession(): ?array
    {
        foreach ($this->db->table('sessions') as $s) {
            if ($s['status'] === 'aktif') {
                return $s;
            }
        }
        return null;
    }

    public function sessionByPin(string $pin): ?array
    {
        foreach ($this->db->table('sessions') as $s) {
            if (hash_equals((string)$s['pin'], $pin)) {
                return $s;
            }
        }
        return null;
    }

    public function saveSession(int $id, array $fields, bool $activate): array
    {
        return $this->db->mutate(function (array &$data) use ($id, $fields, $activate) {
            // Hanya satu sesi boleh aktif pada satu masa.
            if ($activate) {
                foreach ($data['sessions'] as $i => $s) {
                    if ($s['status'] === 'aktif') {
                        $data['sessions'][$i]['status'] = 'selesai';
                    }
                }
            }
            if ($id > 0) {
                foreach ($data['sessions'] as $i => $s) {
                    if ((int)$s['id'] === $id) {
                        $data['sessions'][$i] = array_merge($s, $fields);
                        if ($activate) {
                            $data['sessions'][$i]['status'] = 'aktif';
                        }
                        return $data['sessions'][$i];
                    }
                }
                throw new RuntimeException('Sesi tidak dijumpai.');
            }
            $row = array_merge([
                'id' => Db::nextId($data['sessions']),
                'status' => $activate ? 'aktif' : 'dijadualkan',
                'pin' => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            ], $fields);
            $data['sessions'][] = $row;
            return $row;
        });
    }

    public function setSessionStatus(int $id, string $status): void
    {
        $this->db->mutate(function (array &$data) use ($id, $status) {
            foreach ($data['sessions'] as $i => $s) {
                if ($status === 'aktif' && $s['status'] === 'aktif') {
                    $data['sessions'][$i]['status'] = 'selesai';
                }
                if ((int)$s['id'] === $id) {
                    $data['sessions'][$i]['status'] = $status;
                }
            }
        });
    }

    public function deleteSession(int $id): void
    {
        $this->db->mutate(function (array &$data) use ($id) {
            $data['sessions'] = array_values(array_filter(
                $data['sessions'],
                fn($s) => (int)$s['id'] !== $id
            ));
            $data['attendance'] = array_values(array_filter(
                $data['attendance'],
                fn($a) => (int)$a['session_id'] !== $id
            ));
            $data['scores'] = array_values(array_filter(
                $data['scores'] ?? [],
                fn($s) => (int)$s['session_id'] !== $id
            ));
        });
    }

    // ── Kehadiran ───────────────────────────────────────────

    /** Peta player_id => rekod kehadiran untuk satu sesi. */
    public function attendanceMap(int $sessionId): array
    {
        $map = [];
        foreach ($this->db->table('attendance') as $a) {
            if ((int)$a['session_id'] === $sessionId) {
                $map[(int)$a['player_id']] = $a;
            }
        }
        return $map;
    }

    /** Log satu sesi dengan nama pemain, terbaharu dahulu. */
    public function attendanceLog(int $sessionId): array
    {
        $names = [];
        foreach ($this->db->table('players') as $p) {
            $names[(int)$p['id']] = $p['name'];
        }
        $rows = [];
        foreach ($this->db->table('attendance') as $a) {
            if ((int)$a['session_id'] !== $sessionId) {
                continue;
            }
            $a['player_name'] = $names[(int)$a['player_id']] ?? ('Pemain #' . $a['player_id']);
            $rows[] = $a;
        }
        usort($rows, fn($a, $b) => strcmp((string)$b['marked_at'], (string)$a['marked_at']));
        return $rows;
    }

    /** Status 'belum' membuang rekod. */
    public function mark(int $sessionId, int $playerId, string $status, string $method = 'manual'): void
    {
        $this->db->mutate(function (array &$data) use ($sessionId, $playerId, $status, $method) {
            foreach ($data['attendance'] as $i => $a) {
                if ((int)$a['session_id'] === $sessionId && (int)$a['player_id'] === $playerId) {
                    if ($status === 'belum') {
                        unset($data['attendance'][$i]);
                        $data['attendance'] = array_values($data['attendance']);
                        return;
                    }
                    $data['attendance'][$i]['status'] = $status;
                    $data['attendance'][$i]['method'] = $method;
                    $data['attendance'][$i]['marked_at'] = date('Y-m-d H:i:s');
                    return;
                }
            }
            if ($status === 'belum') {
                return;
            }
            $data['attendance'][] = [
                'id' => Db::nextId($data['attendance']),
                'session_id' => $sessionId,
                'player_id' => $playerId,
                'status' => $status,
                'method' => $method,
                'marked_at' => date('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Check-in melalui kod sesi. Lewat jika lebih 15 minit selepas waktu mula.
     * @return array{status:string, already:bool, player:string, session:string}
     */
    /**
     * $noAhliInput disahkan hanya apabila $verifyMember = true (laluan awam
     * QR self check-in). Check-in manual oleh jurulatih di panel admin
     * (identiti disahkan secara peribadi) tidak memerlukan pengesahan ini.
     */
    public function checkin(string $pin, int $playerId, string $noAhliInput = '', bool $verifyMember = false): array
    {
        $session = $this->sessionByPin($pin);
        if (!$session) {
            throw new RuntimeException('Kod check-in tidak sah.');
        }
        if ($session['status'] !== 'aktif') {
            throw new RuntimeException('Sesi ini tidak lagi dibuka untuk check-in.');
        }
        $player = $this->player($playerId);
        if (!$player) {
            throw new RuntimeException('Pemain tidak dijumpai.');
        }

        if ($verifyMember) {
            $onFile = strtoupper(trim((string)($player['no_ahli'] ?? '')));
            $typed = strtoupper(trim($noAhliInput));
            if ($typed === '' || $typed !== $onFile) {
                throw new RuntimeException('No. Ahli tidak sepadan dengan nama yang dipilih.');
            }
        }

        $grace = strtotime($session['date'] . ' ' . $session['start']) + 15 * 60;
        $status = time() > $grace ? 'lewat' : 'hadir';
        $already = isset($this->attendanceMap((int)$session['id'])[$playerId]);

        $this->mark((int)$session['id'], $playerId, $status, 'qr');

        return [
            'status' => $status,
            'already' => $already,
            'player' => $player['name'],
            'session' => $session['title'],
        ];
    }


    // ── Skor & Prestasi ─────────────────────────────────────

    public const MAX_GAMES_PER_SESSION = 6;
    public const MAX_PINS = 300;

    /**
     * Skor satu sesi, disusun mengikut pemain lalu nombor game.
     * @return array<int, array<int, array>> player_id => [game_no => rekod]
     */
    public function scoresBySession(int $sessionId): array
    {
        $map = [];
        foreach ($this->db->table('scores') as $s) {
            if ((int)$s['session_id'] === $sessionId) {
                $map[(int)$s['player_id']][(int)$s['game_no']] = $s;
            }
        }
        return $map;
    }

    /**
     * Simpan satu keputusan game (upsert). Pin 'null' membuang rekod itu.
     */
    public function saveScore(int $sessionId, int $playerId, int $gameNo, ?int $pins): void
    {
        if ($gameNo < 1 || $gameNo > self::MAX_GAMES_PER_SESSION) {
            throw new RuntimeException('Nombor game tidak sah.');
        }
        if ($pins !== null && ($pins < 0 || $pins > self::MAX_PINS)) {
            throw new RuntimeException('Skor mesti antara 0 dan ' . self::MAX_PINS . '.');
        }

        $this->db->mutate(function (array &$data) use ($sessionId, $playerId, $gameNo, $pins) {
            $data['scores'] = $data['scores'] ?? [];
            foreach ($data['scores'] as $i => $s) {
                if ((int)$s['session_id'] === $sessionId && (int)$s['player_id'] === $playerId && (int)$s['game_no'] === $gameNo) {
                    if ($pins === null) {
                        unset($data['scores'][$i]);
                        $data['scores'] = array_values($data['scores']);
                        return;
                    }
                    $data['scores'][$i]['pins'] = $pins;
                    $data['scores'][$i]['recorded_at'] = date('Y-m-d H:i:s');
                    return;
                }
            }
            if ($pins === null) {
                return;
            }
            $data['scores'][] = [
                'id' => Db::nextId($data['scores']),
                'session_id' => $sessionId,
                'player_id' => $playerId,
                'game_no' => $gameNo,
                'pins' => $pins,
                'recorded_at' => date('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Statistik prestasi seorang pemain merentas semua sesi.
     * @return array{games:int, avg:float, high:int, low:int}
     */
    public function playerScoreStats(int $playerId): array
    {
        $pins = [];
        foreach ($this->db->table('scores') as $s) {
            if ((int)$s['player_id'] === $playerId) {
                $pins[] = (int)$s['pins'];
            }
        }
        if (!$pins) {
            return ['games' => 0, 'avg' => 0.0, 'high' => 0, 'low' => 0];
        }
        return [
            'games' => count($pins),
            'avg' => round(array_sum($pins) / count($pins), 1),
            'high' => max($pins),
            'low' => min($pins),
        ];
    }

    /**
     * Papan pendahulu skor tertinggi merentas semua sesi, terbaik dahulu.
     * @return array<int, array> setiap baris: pins, game_no, player, session
     */
    public function scoreLeaderboard(int $limit = 8): array
    {
        $names = [];
        foreach ($this->db->table('players') as $p) {
            $names[(int)$p['id']] = $p;
        }
        $sessions = [];
        foreach ($this->db->table('sessions') as $s) {
            $sessions[(int)$s['id']] = $s;
        }

        $rows = [];
        foreach ($this->db->table('scores') as $s) {
            $p = $names[(int)$s['player_id']] ?? null;
            $sess = $sessions[(int)$s['session_id']] ?? null;
            if (!$p || !$sess) {
                continue;
            }
            $rows[] = [
                'pins' => (int)$s['pins'],
                'game_no' => (int)$s['game_no'],
                'player' => $p,
                'session' => $sess,
            ];
        }
        usort($rows, fn($a, $b) => $b['pins'] <=> $a['pins']);
        return array_slice($rows, 0, $limit);
    }

    /** Purata skor keseluruhan satu sesi (semua game yang direkod). */
    public function sessionScoreAverage(int $sessionId): ?float
    {
        $pins = [];
        foreach ($this->db->table('scores') as $s) {
            if ((int)$s['session_id'] === $sessionId) {
                $pins[] = (int)$s['pins'];
            }
        }
        return $pins ? round(array_sum($pins) / count($pins), 1) : null;
    }

    // ── Statistik ───────────────────────────────────────────

    /**
     * Kiraan kehadiran satu sesi berbanding senarai pemain aktif.
     * @return array{hadir:int,lewat:int,tidak_hadir:int,belum:int,total:int,rate:int}
     */
    public function tally(int $sessionId): array
    {
        $map = $this->attendanceMap($sessionId);
        $t = ['hadir' => 0, 'lewat' => 0, 'tidak_hadir' => 0, 'belum' => 0];
        $roster = $this->players(true);
        foreach ($roster as $p) {
            $st = $map[(int)$p['id']]['status'] ?? 'belum';
            if (!isset($t[$st])) {
                $st = 'belum';
            }
            $t[$st]++;
        }
        $t['total'] = count($roster);
        $t['rate'] = $t['total'] > 0
            ? (int)round((($t['hadir'] + $t['lewat']) / $t['total']) * 100)
            : 0;
        return $t;
    }

    /** Bilangan sesi yang sudah berjalan (bukan sekadar dijadualkan). */
    public function heldSessionCount(): int
    {
        return count(array_filter(
            $this->db->table('sessions'),
            fn($s) => $s['status'] !== 'dijadualkan'
        ));
    }

    /** Kadar kehadiran seorang pemain merentas semua sesi yang berjalan. */
    public function playerRate(int $playerId): ?int
    {
        $held = $this->heldSessionCount();
        if ($held === 0) {
            return null;
        }
        $n = 0;
        foreach ($this->db->table('attendance') as $a) {
            if ((int)$a['player_id'] === $playerId && in_array($a['status'], ['hadir', 'lewat'], true)) {
                $n++;
            }
        }
        return (int)round($n / $held * 100);
    }

    /** Pecahan setiap pemain untuk halaman laporan, disusun mengikut kadar. */
    public function playerReport(): array
    {
        $held = $this->heldSessionCount();
        $rows = [];
        foreach ($this->players() as $p) {
            $c = ['hadir' => 0, 'lewat' => 0, 'tidak_hadir' => 0];
            foreach ($this->db->table('attendance') as $a) {
                if ((int)$a['player_id'] === (int)$p['id'] && isset($c[$a['status']])) {
                    $c[$a['status']]++;
                }
            }
            $attended = $c['hadir'] + $c['lewat'];
            $rows[] = [
                'player' => $p,
                'counts' => $c,
                'rate' => $held > 0 ? (int)round($attended / $held * 100) : 0,
            ];
        }
        usort($rows, fn($a, $b) => $b['rate'] <=> $a['rate']);
        return $rows;
    }

    /** Sesi paling awal dahulu, untuk graf trend. */
    public function trend(int $limit = 6): array
    {
        $held = array_values(array_filter(
            $this->db->table('sessions'),
            fn($s) => $s['status'] !== 'dijadualkan'
        ));
        usort($held, fn($a, $b) => strcmp($a['date'] . $a['start'], $b['date'] . $b['start']));
        $held = array_slice($held, -$limit);
        return array_map(fn($s) => ['session' => $s, 'tally' => $this->tally((int)$s['id'])], $held);
    }

    public function reset(): void
    {
        $this->db->mutate(function (array &$data) {
            $data = Db::seed();
        });
    }

    // ── Tetapan (senarai jurulatih/lokasi & lalai) ─────────────

    public const LOADING_MS_MIN = 500;
    public const LOADING_MS_MAX = 6000;

    public function settings(): array
    {
        $s = $this->db->all()['settings'] ?? [];
        return array_merge([
            'coaches' => [],
            'venues' => [],
            'default_coach' => '',
            'default_venue' => '',
            'loading_ms' => 1500,
        ], $s);
    }

    public function saveSettings(array $coaches, array $venues, string $defaultCoach, string $defaultVenue, int $loadingMs): array
    {
        $loadingMs = max(self::LOADING_MS_MIN, min(self::LOADING_MS_MAX, $loadingMs));
        return $this->db->mutate(function (array &$data) use ($coaches, $venues, $defaultCoach, $defaultVenue, $loadingMs) {
            if ($defaultCoach !== '' && !in_array($defaultCoach, $coaches, true)) {
                $coaches[] = $defaultCoach;
            }
            if ($defaultVenue !== '' && !in_array($defaultVenue, $venues, true)) {
                $venues[] = $defaultVenue;
            }
            $data['settings'] = [
                'coaches' => $coaches,
                'venues' => $venues,
                'default_coach' => $defaultCoach,
                'default_venue' => $defaultVenue,
                'loading_ms' => $loadingMs,
            ];
            return $data['settings'];
        });
    }

    /** Baris rata untuk eksport CSV. */
    public function exportRows(): array
    {
        $data = $this->db->all();
        $sessions = [];
        foreach ($data['sessions'] as $s) {
            $sessions[(int)$s['id']] = $s;
        }
        $players = [];
        foreach ($data['players'] as $p) {
            $players[(int)$p['id']] = $p;
        }

        $rows = [];
        foreach ($data['attendance'] as $a) {
            $s = $sessions[(int)$a['session_id']] ?? null;
            $p = $players[(int)$a['player_id']] ?? null;
            if (!$s || !$p) {
                continue;
            }
            $rows[] = [
                $s['date'], $s['title'], $s['venue'],
                $p['no_ahli'], $p['name'], $p['category'],
                STATUS_LABEL[$a['status']] ?? $a['status'],
                strtoupper((string)$a['method']),
                $a['marked_at'],
            ];
        }
        usort($rows, fn($a, $b) => strcmp($b[0], $a[0]) ?: strcmp($a[4], $b[4]));
        return $rows;
    }
}
