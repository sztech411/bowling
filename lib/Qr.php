<?php
declare(strict_types=1);

/**
 * Penjana QR Code dalam PHP tulen — mod byte, pembetulan ralat tahap M, versi 1–10.
 * Menghasilkan SVG terus dari pelayan, jadi tiada JavaScript diperlukan.
 *
 *   echo Qr::svg('http://localhost/bowling/?checkin=215608');
 */
final class Qr
{
    /** versi => [jumlah codeword, EC codeword per blok, [[bil. blok, data codeword], ...]] */
    private const VERSIONS = [
        1  => [26,  10, [[1, 16]]],
        2  => [44,  16, [[1, 28]]],
        3  => [70,  26, [[1, 44]]],
        4  => [100, 18, [[2, 32]]],
        5  => [134, 24, [[2, 43]]],
        6  => [172, 16, [[4, 27]]],
        7  => [196, 18, [[4, 31]]],
        8  => [242, 22, [[2, 38], [2, 39]]],
        9  => [292, 22, [[3, 36], [2, 37]]],
        10 => [346, 26, [[4, 43], [1, 44]]],
    ];

    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** Maklumat format 15-bit, tahap M, mask 0–7. */
    private const FORMAT_M = [
        '101010000010010', '101000100100101', '101111001111100', '101101101001011',
        '100010111111001', '100000011001110', '100111110010111', '100101010100000',
    ];

    /** Maklumat versi 18-bit (hanya versi 7 dan ke atas). */
    private const VERSION_BITS = [
        7  => '000111110010010100',
        8  => '001000010110111100',
        9  => '001001101010011001',
        10 => '001010010011010011',
    ];

    /** @var int[] */
    private static array $exp = [];
    /** @var int[] */
    private static array $log = [];

    // ── Medan Galois GF(256) ────────────────────────────────

    private static function initGf(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gmul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** @return int[] */
    private static function rsGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coeff) {
                $next[$j]     ^= self::gmul($coeff, 1);
                $next[$j + 1] ^= self::gmul($coeff, self::$exp[$i]);
            }
            $poly = $next;
        }
        return $poly;
    }

    /**
     * @param int[] $data
     * @return int[]
     */
    private static function rsEncode(array $data, int $ecLen): array
    {
        $gen = self::rsGenerator($ecLen);
        $res = array_fill(0, $ecLen, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $res[0];
            array_shift($res);
            $res[] = 0;
            for ($i = 0; $i < $ecLen; $i++) {
                $res[$i] ^= self::gmul($gen[$i + 1], $factor);
            }
        }
        return $res;
    }

    // ── Pengekodan data ────────────────────────────────────

    private static function pickVersion(int $byteLen): int
    {
        foreach (self::VERSIONS as $v => [$total, $ecPer, $blocks]) {
            $numBlocks = array_sum(array_column($blocks, 0));
            $dataCodewords = $total - $ecPer * $numBlocks;
            $countBits = $v < 10 ? 8 : 16;
            $needed = (int)ceil((4 + $countBits + $byteLen * 8) / 8);
            if ($needed <= $dataCodewords) {
                return $v;
            }
        }
        throw new RuntimeException('Data terlalu panjang untuk QR versi 1-10.');
    }

    /** @return array{0:int,1:int[]} */
    private static function buildCodewords(string $text): array
    {
        self::initGf();

        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = self::pickVersion(count($bytes));
        [$total, $ecPer, $blockSpec] = self::VERSIONS[$version];
        $numBlocks = array_sum(array_column($blockSpec, 0));
        $dataCodewords = $total - $ecPer * $numBlocks;

        // Mod byte (0100) + kiraan aksara + data.
        $bits = '0100';
        $countBits = $version < 10 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $countBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        // Terminator, kemudian padkan ke sempadan codeword.
        $bits .= str_repeat('0', max(0, min(4, $dataCodewords * 8 - strlen($bits))));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $data = [];
        foreach (str_split($bits, 8) as $chunk) {
            $data[] = bindec($chunk);
        }
        $pad = [0xec, 0x11];
        for ($i = 0; count($data) < $dataCodewords; $i++) {
            $data[] = $pad[$i % 2];
        }

        // Pecahkan kepada blok, jana EC, lalu jalin.
        $dataBlocks = [];
        $ecBlocks = [];
        $pos = 0;
        foreach ($blockSpec as [$count, $size]) {
            for ($i = 0; $i < $count; $i++) {
                $chunk = array_slice($data, $pos, $size);
                $pos += $size;
                $dataBlocks[] = $chunk;
                $ecBlocks[] = self::rsEncode($chunk, $ecPer);
            }
        }

        $result = [];
        $maxData = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $b) {
                if ($i < count($b)) {
                    $result[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPer; $i++) {
            foreach ($ecBlocks as $b) {
                $result[] = $b[$i];
            }
        }

        return [$version, $result];
    }

    // ── Pembinaan matriks ──────────────────────────────────

    private static function placeFinder(array &$m, int $r, int $c): void
    {
        $size = count($m);
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $rr = $r + $i;
                $cc = $c + $j;
                if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) {
                    continue;
                }
                if ($i === -1 || $i === 7 || $j === -1 || $j === 7) {
                    $m[$rr][$cc] = false; // jalur pemisah
                    continue;
                }
                $edge = $i === 0 || $i === 6 || $j === 0 || $j === 6;
                $core = $i >= 2 && $i <= 4 && $j >= 2 && $j <= 4;
                $m[$rr][$cc] = $edge || $core;
            }
        }
    }

    private static function placeAlignment(array &$m, int $r, int $c): void
    {
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $m[$r + $i][$c + $j] = max(abs($i), abs($j)) !== 1;
            }
        }
    }

    private static function reserveFunctionModules(array &$m, int $version): void
    {
        $size = count($m);

        self::placeFinder($m, 0, 0);
        self::placeFinder($m, 0, $size - 7);
        self::placeFinder($m, $size - 7, 0);

        for ($i = 8; $i < $size - 8; $i++) {
            $m[6][$i] = $i % 2 === 0;
            $m[$i][6] = $i % 2 === 0;
        }

        foreach (self::ALIGN[$version] as $r) {
            foreach (self::ALIGN[$version] as $c) {
                $nearFinder = ($r <= 8 && $c <= 8)
                    || ($r <= 8 && $c >= $size - 9)
                    || ($r >= $size - 9 && $c <= 8);
                if (!$nearFinder) {
                    self::placeAlignment($m, $r, $c);
                }
            }
        }

        $m[$size - 8][8] = true; // modul gelap tetap

        // Tempah kawasan maklumat format.
        for ($i = 0; $i < 9; $i++) {
            if ($m[8][$i] === null) {
                $m[8][$i] = false;
            }
            if ($m[$i][8] === null) {
                $m[$i][8] = false;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($m[8][$size - 1 - $i] === null) {
                $m[8][$size - 1 - $i] = false;
            }
            if ($m[$size - 1 - $i][8] === null) {
                $m[$size - 1 - $i][8] = false;
            }
        }

        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $m[$size - 11 + $j][$i] = false;
                    $m[$i][$size - 11 + $j] = false;
                }
            }
        }
    }

    /** @param int[] $codewords */
    private static function placeData(array &$m, array $codewords): void
    {
        $size = count($m);
        $totalBits = count($codewords) * 8;
        $bitIndex = 0;

        $bitAt = static function (int $i) use ($codewords, $totalBits): bool {
            if ($i >= $totalBits) {
                return false;
            }
            return (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
        };

        $upward = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--; // langkau lajur penentu masa
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? $size - 1 - $step : $step;
                foreach ([$col, $col - 1] as $c) {
                    if ($m[$row][$c] === null) {
                        $m[$row][$c] = $bitAt($bitIndex++);
                    }
                }
            }
            $upward = !$upward;
        }
    }

    private static function mask(int $n, int $i, int $j): bool
    {
        switch ($n) {
            case 0: return ($i + $j) % 2 === 0;
            case 1: return $i % 2 === 0;
            case 2: return $j % 3 === 0;
            case 3: return ($i + $j) % 3 === 0;
            case 4: return (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0;
            case 5: return (($i * $j) % 2) + (($i * $j) % 3) === 0;
            case 6: return ((($i * $j) % 2) + (($i * $j) % 3)) % 2 === 0;
            default: return ((($i + $j) % 2) + (($i * $j) % 3)) % 2 === 0;
        }
    }

    private static function isFunctionModule(int $version, int $size, int $r, int $c): bool
    {
        if ($r === 6 || $c === 6) {
            return true;
        }
        if ($r < 9 && $c < 9) {
            return true;
        }
        if ($r < 9 && $c >= $size - 8) {
            return true;
        }
        if ($r >= $size - 8 && $c < 9) {
            return true;
        }
        foreach (self::ALIGN[$version] as $ar) {
            foreach (self::ALIGN[$version] as $ac) {
                $nearFinder = ($ar <= 8 && $ac <= 8)
                    || ($ar <= 8 && $ac >= $size - 9)
                    || ($ar >= $size - 9 && $ac <= 8);
                if ($nearFinder) {
                    continue;
                }
                if (abs($r - $ar) <= 2 && abs($c - $ac) <= 2) {
                    return true;
                }
            }
        }
        if ($version >= 7) {
            if ($c < 6 && $r >= $size - 11) {
                return true;
            }
            if ($r < 6 && $c >= $size - 11) {
                return true;
            }
        }
        return false;
    }

    private static function runScore(array $line): int
    {
        $s = 0;
        $run = 1;
        $n = count($line);
        for ($i = 1; $i < $n; $i++) {
            if ($line[$i] === $line[$i - 1]) {
                $run++;
            } else {
                if ($run >= 5) {
                    $s += 3 + ($run - 5);
                }
                $run = 1;
            }
        }
        if ($run >= 5) {
            $s += 3 + ($run - 5);
        }
        return $s;
    }

    private static function penalty(array $m): int
    {
        $size = count($m);
        $score = 0;

        // Peraturan 1: lima modul sewarna berturutan atau lebih.
        for ($i = 0; $i < $size; $i++) {
            $score += self::runScore($m[$i]);
            $score += self::runScore(array_column($m, $i));
        }

        // Peraturan 2: blok 2x2 sewarna.
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Peraturan 3: corak 1:1:3:1:1 bersama empat modul terang.
        $p1 = [true, false, true, true, true, false, true, false, false, false, false];
        $p2 = [false, false, false, false, true, false, true, true, true, false, true];
        for ($i = 0; $i < $size; $i++) {
            $row = $m[$i];
            $col = array_column($m, $i);
            for ($j = 0; $j + 11 <= $size; $j++) {
                $rs = array_slice($row, $j, 11);
                $cs = array_slice($col, $j, 11);
                if ($rs === $p1 || $rs === $p2) {
                    $score += 40;
                }
                if ($cs === $p1 || $cs === $p2) {
                    $score += 40;
                }
            }
        }

        // Peraturan 4: pesongan dari nisbah gelap 50%.
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $v) {
                if ($v) {
                    $dark++;
                }
            }
        }
        $ratio = ($dark * 100) / ($size * $size);
        $score += (int)floor(abs($ratio - 50) / 5) * 10;

        return $score;
    }

    private static function applyFormat(array &$m, int $maskIndex): void
    {
        $size = count($m);
        $bits = array_map(fn($b) => $b === '1', str_split(self::FORMAT_M[$maskIndex]));

        // Salinan pertama: sekeliling penanda kiri atas.
        $k = 0;
        for ($i = 0; $i <= 5; $i++) {
            $m[8][$i] = $bits[$k++];
        }
        $m[8][7] = $bits[$k++];
        $m[8][8] = $bits[$k++];
        $m[7][8] = $bits[$k++];
        for ($i = 5; $i >= 0; $i--) {
            $m[$i][8] = $bits[$k++];
        }

        // Salinan kedua: bawah kiri dan atas kanan.
        $k = 0;
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 1 - $i][8] = $bits[$k++];
        }
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 8 + $i] = $bits[$k++];
        }
    }

    private static function applyVersionInfo(array &$m, int $version): void
    {
        if ($version < 7) {
            return;
        }
        $size = count($m);
        $bits = array_map(fn($b) => $b === '1', str_split(self::VERSION_BITS[$version]));
        $k = 17;
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $bit = $bits[$k--];
                $m[$size - 11 + $j][$i] = $bit;
                $m[$i][$size - 11 + $j] = $bit;
            }
        }
    }

    /**
     * Matriks modul QR. true = gelap.
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $text): array
    {
        [$version, $codewords] = self::buildCodewords($text);
        $size = 17 + $version * 4;

        $base = array_fill(0, $size, array_fill(0, $size, null));
        self::reserveFunctionModules($base, $version);
        self::placeData($base, $codewords);

        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $cand = $base;
            for ($r = 0; $r < $size; $r++) {
                for ($c = 0; $c < $size; $c++) {
                    if (!self::isFunctionModule($version, $size, $r, $c) && self::mask($mask, $r, $c)) {
                        $cand[$r][$c] = !$cand[$r][$c];
                    }
                }
            }
            self::applyFormat($cand, $mask);
            self::applyVersionInfo($cand, $version);
            $score = self::penalty($cand);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $cand;
            }
        }

        return $best;
    }

    /** Hasilkan SVG lengkap (skala mengikut bekas CSS). */
    public static function svg(string $text, string $dark = '#1c1714', string $light = '#ffffff', int $quiet = 4): string
    {
        try {
            $m = self::matrix($text);
        } catch (Throwable $e) {
            return '<p style="font-size:.8rem;padding:1rem">QR tidak dapat dijana.</p>';
        }

        $n = count($m);
        $dim = $n + $quiet * 2;
        $path = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c]) {
                    $path .= 'M' . ($c + $quiet) . ' ' . ($r + $quiet) . 'h1v1h-1z';
                }
            }
        }

        return sprintf(
            '<svg viewBox="0 0 %1$d %1$d" width="100%%" height="100%%" shape-rendering="crispEdges" '
            . 'xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kod QR untuk check-in latihan">'
            . '<rect width="%1$d" height="%1$d" fill="%2$s"/><path d="%3$s" fill="%4$s"/></svg>',
            $dim,
            htmlspecialchars($light, ENT_QUOTES),
            $path,
            htmlspecialchars($dark, ENT_QUOTES)
        );
    }
}
