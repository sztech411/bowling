<?php
declare(strict_types=1);

require_once __DIR__ . '/DbBackendInterface.php';
require_once __DIR__ . '/JsonFileBackend.php';
require_once __DIR__ . '/GoogleAuth.php';
require_once __DIR__ . '/Firestore.php';
require_once __DIR__ . '/FirestoreBackend.php';

/**
 * Titik masuk pangkalan data aplikasi. Memilih storan secara automatik:
 *
 *  - Jika config/firebase.php wujud dan lengkap → Firestore (awan).
 *  - Jika tidak → fail JSON tempatan (data/db.json), seperti sedia ada.
 *
 * Repo.php dan index.php tidak perlu tahu storan mana sedang digunakan —
 * kedua-duanya cuma panggil all()/table()/mutate() seperti biasa.
 */
final class Db
{
    private DbBackendInterface $backend;

    public function __construct(string $localFallbackFile)
    {
        $this->backend = self::resolveBackend($localFallbackFile);
    }

    private static function resolveBackend(string $localFallbackFile): DbBackendInterface
    {
        $configFile = __DIR__ . '/../config/firebase.php';

        if (file_exists($configFile)) {
            $config = require $configFile;

            if (!empty($config['project_id']) && !empty($config['credentials_file'])) {
                $credentialsFile = $config['credentials_file'];
                if (!is_file($credentialsFile)) {
                    throw new RuntimeException(
                        'config/firebase.php dikonfigurasi tetapi fail kelayakan tidak dijumpai: ' . $credentialsFile
                    );
                }
                $cacheFile = $config['token_cache_file'] ?? (__DIR__ . '/../data/.firestore-token-cache.json');
                $docPath = $config['document_path'] ?? 'piko_taz/state';

                $auth = new GoogleAuth($credentialsFile, $cacheFile);
                $client = new Firestore($config['project_id'], $auth);
                return new FirestoreBackend($client, $docPath);
            }
        }

        return new JsonFileBackend($localFallbackFile);
    }

    public function all(): array
    {
        return $this->backend->all();
    }

    public function table(string $name): array
    {
        return $this->all()[$name] ?? [];
    }

    /** Ubah data dalam callback; hasil ditulis semula (fail tempatan atau Firestore). */
    public function mutate(callable $fn)
    {
        return $this->backend->mutate($fn);
    }

    public static function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, (int)($r['id'] ?? 0));
        }
        return $max + 1;
    }

    /** Data awal demo. */
    public static function seed(): array
    {
        $today = date('Y-m-d');
        $players = [
            ['Afizan Amer', 'Senior', 'MSN-1001', '013-2200481', 186, '900112015533'],
            ['Muhammad Hakim', 'Senior', 'MSN-1002', '019-4471203', 178, '920305086214'],
            ['Danish Irfan', 'Junior', 'MSN-1003', '011-3390118', 152, '050822041987'],
            ['Nursyazwani Aida', 'Ladies', 'MSN-1004', '012-6628870', 164, '981130025461'],
            ['Izzat Danial', 'Senior', 'MSN-1005', '017-8812440', 171, '890617076329'],
            ['Muhammad Adam', 'Junior', 'MSN-1006', '014-2298017', 148, '060214038756'],
            ['Farah Nabilah', 'Ladies', 'MSN-1007', '018-7734029', 159, '970425065190'],
            ['Zulhilmi Rashid', 'Youth', 'MSN-1008', '016-5540912', 143, '030901087245'],
        ];
        $rows = [];
        foreach ($players as $i => [$name, $cat, $no, $tel, $avg, $ic]) {
            $rows[] = [
                'id' => $i + 1,
                'no_ahli' => $no,
                'name' => $name,
                'category' => $cat,
                'phone' => $tel,
                'ic' => $ic,
                'average' => $avg,
                'active' => true,
                'joined_at' => date('Y-m-d', strtotime("-" . (30 + $i * 17) . " days")),
            ];
        }

        $sessions = [
            [
                'id' => 1,
                'title' => 'Latihan Teknik & Konsistensi',
                'date' => date('Y-m-d', strtotime('-7 days')),
                'start' => '20:00',
                'end' => '22:00',
                'venue' => 'Lite Superbowl',
                'coach' => 'Coach Rizal',
                'note' => 'Fokus pada spare shooting dan release yang konsisten.',
                'status' => 'selesai',
                'pin' => '480219',
            ],
            [
                'id' => 2,
                'title' => 'Latihan Kelajuan & Ketepatan',
                'date' => date('Y-m-d', strtotime('-3 days')),
                'start' => '20:00',
                'end' => '22:00',
                'venue' => 'Lite Superbowl',
                'coach' => 'Coach Rizal',
                'note' => 'Simulasi perlawanan 3 game.',
                'status' => 'selesai',
                'pin' => '733641',
            ],
            [
                'id' => 3,
                'title' => 'Latihan Persiapan Kejohanan',
                'date' => $today,
                'start' => '20:00',
                'end' => '22:00',
                'venue' => 'Lite Superbowl',
                'coach' => 'Coach Rizal',
                'note' => 'Sesi penuh: warm-up, teknik, dan 4 game penilaian.',
                'status' => 'aktif',
                'pin' => '215608',
            ],
        ];

        // Kehadiran demo untuk dua sesi lepas + sesi aktif separa.
        $pattern = [
            1 => ['hadir', 'hadir', 'lewat', 'hadir', 'hadir', 'tidak_hadir', 'hadir', 'hadir'],
            2 => ['hadir', 'lewat', 'hadir', 'hadir', 'tidak_hadir', 'hadir', 'hadir', 'lewat'],
            3 => ['hadir', 'hadir', 'lewat', 'hadir', 'belum', 'hadir', 'belum', 'belum'],
        ];
        $attendance = [];
        $aid = 1;
        foreach ($pattern as $sid => $statuses) {
            foreach ($statuses as $idx => $st) {
                if ($st === 'belum') {
                    continue;
                }
                $sesDate = $sessions[$sid - 1]['date'];
                $attendance[] = [
                    'id' => $aid++,
                    'session_id' => $sid,
                    'player_id' => $idx + 1,
                    'status' => $st,
                    'method' => $st === 'tidak_hadir' ? 'manual' : ($idx % 2 === 0 ? 'qr' : 'manual'),
                    'marked_at' => $sesDate . ' ' . sprintf('%02d:%02d:00', 19 + intdiv($idx, 4), ($idx * 7) % 60),
                ];
            }
        }

        // Skor demo: 3 game bagi setiap pemain yang hadir/lewat pada sesi selesai;
        // sesi aktif hanya mempunyai skor game 1 untuk yang sudah check-in.
        $scores = [];
        $scid = 1;
        foreach ($pattern as $sid => $statuses) {
            $isActive = $sessions[$sid - 1]['status'] === 'aktif';
            $gamesForSession = $isActive ? 1 : 3;
            foreach ($statuses as $idx => $st) {
                if ($st !== 'hadir' && $st !== 'lewat') {
                    continue;
                }
                $pid = $idx + 1;
                $base = $rows[$idx]['average'];
                for ($g = 1; $g <= $gamesForSession; $g++) {
                    // Variasi lembut berpandukan purata pemain supaya kelihatan realistik.
                    $swing = (($pid * 13 + $sid * 7 + $g * 5) % 41) - 20; // -20 .. +20
                    $pins = max(60, min(279, $base + $swing));
                    $scores[] = [
                        'id' => $scid++,
                        'session_id' => $sid,
                        'player_id' => $pid,
                        'game_no' => $g,
                        'pins' => $pins,
                        'recorded_at' => $sessions[$sid - 1]['date'] . ' ' . sprintf('%02d:%02d:00', 20 + $g, ($pid * 3) % 60),
                    ];
                }
            }
        }

        return [
            'meta' => ['app' => 'PIKO TAZ Bowling', 'version' => 2, 'created_at' => date('c')],
            'users' => [
                ['id' => 1, 'username' => 'admin', 'password' => 'admin123', 'name' => 'Shaharizuan', 'role' => 'Admin'],
                ['id' => 2, 'username' => 'coach', 'password' => 'coach123', 'name' => 'Coach Rizal', 'role' => 'Jurulatih'],
                ['id' => 3, 'username' => 'pemain', 'password' => 'pemain123', 'name' => 'Afizan Amer', 'role' => 'Pemain', 'player_id' => 1],
            ],
            'players' => $rows,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'scores' => $scores,
        ];
    }
}
