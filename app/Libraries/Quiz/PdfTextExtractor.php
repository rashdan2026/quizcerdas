<?php

namespace App\Libraries\Quiz;

/**
 * Ekstraktor teks PDF fleksibel (tanpa dependensi eksternal).
 *
 * Dukungan format (v7.2):
 * - Filter stream: FlateDecode, LZWDecode (+ EarlyChange), ASCIIHexDecode,
 *   ASCII85Decode, RunLengthDecode, dan rantai filter gabungan
 *   (/Filter [/ASCII85Decode /FlateDecode]) termasuk /DecodeParms predictor
 *   (TIFF 2 & PNG 10-15) untuk content stream.
 * - Objek di dalam ObjStm (compressed object stream) — PDF modern.
 * - Font:
 *   - Type0/CID (Identity-H & 1-byte CMap) via /ToUnicode (bfchar/bfrange).
 *   - Simple font dengan /Encoding /Differences (nama glyph -> karakter,
 *     termasuk uniXXXX & nama standar Adobe).
 *   - Simple font /WinAnsiEncoding (CP1252 -> UTF-8).
 *   - Font tanpa map: jalur latin + filter anti-sampah di clean().
 * - Peta font dibangun PER HALAMAN dari /Resources (mencegah salah petah saat
 *   nama resource /F1 dipakai ulang dengan font berbeda antar halaman); urutan
 *   halaman mengikuti pohon /Kids bila dapat diurai, fallback urutan dokumen.
 *
 * PENTING: kelas ini TIDAK boleh memanggil set_time_limit(). set_time_limit()
 * me-restart hitungan waktu eksekusi PHP dengan nilai baru, sehingga batas
 * milik pemanggil (controller generate: 600 detik) sempat tertimpa.
 */
class PdfTextExtractor
{
    private const MAX_OUTPUT_CHARS = 300000;
    private const MAX_STREAMS      = 3000;
    private const TIME_LIMIT_SEC   = 60;

    /** @return array{ok:bool,text:string,error:?string} */
    public function extract(string $path): array
    {
        if (! is_file($path)) {
            return ['ok' => false, 'text' => '', 'error' => 'file_missing'];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['ok' => false, 'text' => '', 'error' => 'read_failed'];
        }
        if (substr($raw, 0, 5) !== '%PDF-') {
            return ['ok' => false, 'text' => '', 'error' => 'not_pdf'];
        }
        if (strpos($raw, '%%EOF') === false) {
            return ['ok' => false, 'text' => '', 'error' => 'incomplete_pdf'];
        }

        $start = microtime(true);

        // 1) Indeks objek + stream.
        ['objs' => $objs, 'streams' => $streams] = $this->buildIndex($raw);

        // 2) Kumpulkan info font (CID CMap / Differences / WinAnsi) per objek font.
        ['cmaps' => $cmaps, 'encodings' => $encodings] = $this->collectFontMaps($raw, $objs, $streams);

        // 3) Halaman: peta font per halaman + daftar objek content stream.
        $pages = $this->collectPages($objs, $cmaps, $encodings);

        $out = '';
        if ($pages !== []) {
            // Jalur utama: ekstraksi terurut per halaman (font map akurat).
            foreach ($pages as $page) {
                if (microtime(true) - $start > self::TIME_LIMIT_SEC * 2) {
                    break;
                }
                foreach ($page['contents'] as $objNum) {
                    $data = $this->getStreamData($objNum, $streams);
                    if ($data === null || $data === '') {
                        continue;
                    }
                    $txt = $this->extractTextOps($data, $page['fonts']);
                    if ($txt !== '') {
                        $out .= $txt . "\n";
                    }
                    if (strlen($out) >= self::MAX_OUTPUT_CHARS) {
                        $out = substr($out, 0, self::MAX_OUTPUT_CHARS) . "\n[TRUNCATED]";
                        break 2;
                    }
                }
            }
        }

        // 4) Fallback (halaman tidak bisa diurai / tidak ada content di jalur halaman):
        //    pindai semua stream seperti versi lama dengan peta font global.
        if (trim($out) === '') {
            $global = $this->globalFontMap($cmaps, $encodings);
            $count  = 0;
            foreach ($streams as $num => $st) {
                if ($count++ >= self::MAX_STREAMS || microtime(true) - $start > self::TIME_LIMIT_SEC * 2) {
                    break;
                }
                $data = $this->getStreamData((int) $num, $streams);
                if ($data === null || $data === '' || ! str_contains($data, 'Tj')) {
                    continue;
                }
                $txt = $this->extractTextOps($data, $global);
                if ($txt !== '') {
                    $out .= $txt . "\n";
                }
                if (strlen($out) >= self::MAX_OUTPUT_CHARS) {
                    $out = substr($out, 0, self::MAX_OUTPUT_CHARS) . "\n[TRUNCATED]";
                    break;
                }
            }
        }

        $out = $this->clean($out);

        if (mb_strlen($out) < 200) {
            return ['ok' => false, 'text' => '', 'error' => 'no_text_layer'];
        }

        return ['ok' => true, 'text' => $out, 'error' => null];
    }

    // ==================== Indeks objek & stream ====================

    /**
     * Indeks seluruh objek & stream. ObjStm diurai sehingga objek di dalamnya
     * ikut masuk indeks.
     *
     * @return array{objs:array<int,string>, streams:array<int,array{dict:string,raw:string,dictOnly:bool}>}
     */
    protected function buildIndex(string $raw): array
    {
        $objs    = [];
        $streams = [];

        // a) Stream objects: dict DILARANG melewati 'endobj'/'stream' (tempered
        //    pattern) — tanpa ini, dict objek non-stream sebelum sebuah stream
        //    di-backtrack sampai stream milik objek lain (regresi halaman hilang).
        if (preg_match_all(
            '/(\d+)\s+0\s+obj\s*<<((?:(?!endobj|stream).)*?)>>\s*stream\r?\n(.*?)endstream/s',
            $raw,
            $sm,
            PREG_SET_ORDER
        )) {
            foreach ($sm as $s) {
                $num = (int) $s[1];
                if (! isset($streams[$num])) {
                    $streams[$num] = ['dict' => '<<' . $s[2] . '>>', 'raw' => rtrim($s[3], "\r\n"), 'dictOnly' => false];
                    $objs[$num]    = '<<' . $s[2] . '>>';
                }
            }
        }

        // b) Objek top-level non-stream dengan dict (catalog, page, font, dsb).
        if (preg_match_all(
            '/(\d+)\s+0\s+obj\s*<<((?:(?!endobj|stream).)*?)>>\s*endobj/s',
            $raw,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $x) {
                $num = (int) $x[1];
                if (! isset($streams[$num])) {
                    $objs[$num] = '<<' . $x[2] . '>>';
                }
            }
        }

        // c) Sisa objek tanpa dict (jarang) + objek di dalam ObjStm.
        if (preg_match_all('/(?<!\d)(\d+)\s+0\s+obj/s', $raw, $om, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($om as $x) {
                $num    = (int) $x[1][0];
                $offset = $x[1][1] + strlen($x[1][0]);
                if (isset($objs[$num])) {
                    continue;
                }
                $endObj = strpos($raw, 'endobj', $offset);
                $endStr = strpos($raw, 'stream', $offset);
                if ($endObj !== false && ($endStr === false || $endObj < $endStr)) {
                    $objs[$num] = substr($raw, $offset, $endObj - $offset);
                } elseif ($endStr !== false) {
                    // stream tanpa dict terdeteksi (rare): perlakukan dict kosong.
                    $end = strpos($raw, 'endstream', $endStr);
                    if ($end !== false && ! isset($streams[$num])) {
                        $streams[$num] = ['dict' => '', 'raw' => rtrim(substr($raw, $endStr + 6, $end - $endStr - 6), "\r\n"), 'dictOnly' => false];
                    }
                }
            }
        }

        // d) ObjStm: objek di dalam compressed object stream.
        foreach ($streams as $num => $st) {
            if (! str_contains($st['dict'], '/ObjStm')) {
                continue;
            }
            $dict = $st['dict'];
            if (! preg_match('/\/N\s+(\d+)/', $dict, $n) || ! preg_match('/\/First\s+(\d+)/', $dict, $first)) {
                continue;
            }
            $data = $this->decodeStream($dict, $st['raw']);
            if ($data === null) {
                continue;
            }
            $count = min((int) $n[1], 512);
            $first = (int) $first[1];
            if (! preg_match_all('/(\d+)\s+(\d+)/', substr($data, 0, $first), $pairs, PREG_SET_ORDER)) {
                continue;
            }
            $positions = [];
            foreach ($pairs as $p) {
                $positions[] = [(int) $p[1], (int) $p[2]];
            }
            foreach (array_slice($positions, 0, $count) as $i => [$objNum, $off]) {
                $end = isset($positions[$i + 1]) ? $positions[$i + 1][1] : strlen($data);
                $objs[$objNum] = substr($data, $first + $off, max(0, $end - $first - $off));
            }
        }

        return ['objs' => $objs, 'streams' => $streams];
    }

    /** Dekode satu stream (rantai /Filter) berdasarkan dict-nya. */
    protected function decodeStream(string $dict, string $raw): ?string
    {
        $filters = [];
        if (preg_match('/\/Filter\s*([\/\[\]].*?)(?=\/(?:Length|DecodeParms|Type|Filter)|$)/s', $dict, $m)) {
            $spec = trim($m[1]);
            if (preg_match_all('/\/(\w+)/', $spec, $names)) {
                $filters = $names[1];
            }
        } elseif (preg_match('/\/Filter\s*\/(\w+)/', $dict, $m)) {
            $filters = [$m[1]];
        }

        $data = $raw;
        foreach ($filters as $filter) {
            $data = match ($filter) {
                'FlateDecode'       => $this->inflate($data),
                'LZWDecode'         => $this->decodeLZW($data, $this->earlyChange($dict)),
                'ASCIIHexDecode'    => $this->decodeASCIIHex($data),
                'ASCII85Decode'     => $this->decodeASCII85($data),
                'RunLengthDecode'   => $this->decodeRunLength($data),
                default             => $data, // filter tak dikenal: biarkan (kemungkinan gagal filter berikut)
            };
            if ($data === null || $data === '') {
                return null;
            }
        }

        // Predictor (biasanya pada FlateDecode): TIFF(2) & PNG(10-15).
        if ($data !== null && preg_match('/\/Predictor\s+(\d+)/', $dict, $p) && (int) $p[1] >= 2) {
            $data = $this->undoPredictor($data, (int) $p[1], $dict);
        }

        return $data;
    }

    private function earlyChange(string $dict): bool
    {
        return ! preg_match('/\/EarlyChange\s+0/', $dict); // default 1
    }

    /** Ambil data stream yang SUDAH didekode dari indeks (cache hasil dekode). */
    private array $decodedCache = [];

    protected function getStreamData(int $objNum, array $streams): ?string
    {
        if (isset($this->decodedCache[$objNum])) {
            return $this->decodedCache[$objNum];
        }
        $st = $streams[$objNum] ?? null;
        if ($st === null || $st['dictOnly']) {
            return null;
        }
        $data = $this->decodeStream($st['dict'], $st['raw']);
        if (count($this->decodedCache) < 500) {
            $this->decodedCache[$objNum] = $data;
        }

        return $data;
    }

    // ==================== Filter decoder ====================

    protected function inflate(string $data): ?string
    {
        foreach (['gzuncompress', 'gzdecode', 'gzinflate'] as $fn) {
            try {
                $out = @$fn($data);
                if (is_string($out) && $out !== '') {
                    return $out;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function decodeASCIIHex(string $data): ?string
    {
        $clean = preg_replace('/[^0-9A-Fa-f]/', '', $data) ?? '';
        if ($clean === '' || strlen($clean) % 2 === 1) {
            $clean .= '0';
        }
        $bin = @hex2bin($clean);

        return is_string($bin) && $bin !== '' ? $bin : null;
    }

    protected function decodeASCII85(string $data): ?string
    {
        $data = preg_replace('/\s+/', '', $data) ?? '';
        $data = preg_replace('/^<~|~>$/', '', $data) ?? $data;
        if ($data === '') {
            return null;
        }
        $out = '';
        $grp = '';
        foreach (str_split($data, 1) as $ch) {
            if ($ch === 'z') {
                $out .= "\0\0\0\0";
                continue;
            }
            if ($ch === '~') {
                break;
            }
            if ($ch < '!' || $ch > 'u') {
                continue;
            }
            $grp .= $ch;
            if (strlen($grp) === 5) {
                $val = 0;
                foreach (str_split($grp) as $c) {
                    $val = $val * 85 + (ord($c) - 33);
                }
                $out .= substr(pack('N', $val), 1);
                $grp = '';
            }
        }
        if ($grp !== '') {
            $grp .= str_repeat('u', 5 - strlen($grp));
            $val = 0;
            foreach (str_split($grp) as $c) {
                $val = $val * 85 + (ord($c) - 33);
            }
            $out .= substr(pack('N', $val), 1, strlen($grp) - 1);
        }

        return $out !== '' ? $out : null;
    }

    protected function decodeRunLength(string $data): ?string
    {
        $out = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($data[$i]);
            if ($code === 128) {
                break; // EOD
            }
            if ($code < 128) {
                $out .= substr($data, $i + 1, $code + 1);
                $i += $code + 1;
            } else {
                $repeat = 257 - $code;
                if ($i + 1 >= $len) {
                    break;
                }
                $out .= str_repeat($data[$i + 1], $repeat);
                $i++;
            }
        }

        return $out !== '' ? $out : null;
    }

    /** PDF LZW decode (EarlyChange default = 1). */
    protected function decodeLZW(string $data, bool $early = true): ?string
    {
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[$i] = chr($i);
        }
        $next = 258;
        $bits = 9;
        $prev = null;
        $out  = '';
        $buf  = 0;
        $bBuf = 0;
        $len  = strlen($data);
        $pos  = 0;

        while ($pos < $len) {
            while ($bBuf < $bits && $pos < $len) {
                $buf = ($buf << 8) | ord($data[$pos++]);
                $bBuf += 8;
            }
            if ($bBuf < $bits) {
                break;
            }
            $code  = ($buf >> ($bBuf - $bits)) & ((1 << $bits) - 1);
            $bBuf -= $bits;

            if ($code === 256) { // clear table
                $table = [];
                for ($i = 0; $i < 256; $i++) {
                    $table[$i] = chr($i);
                }
                $next = 258;
                $bits = 9;
                $prev = null;
                continue;
            }
            if ($code === 257) { // EOD
                break;
            }

            if (isset($table[$code])) {
                $entry = $table[$code];
            } elseif ($prev !== null) {
                $entry = $table[$prev] . $table[$prev][0];
            } else {
                break;
            }

            $out .= $entry;

            if ($prev !== null) {
                if ($next < 4096) {
                    $table[$next] = $table[$prev] . $entry[0];
                    $next++;
                    if ($next >= ($early ? 4095 : 4096)) {
                        // tabel penuh: reset
                        $table = [];
                        for ($i = 0; $i < 256; $i++) {
                            $table[$i] = chr($i);
                        }
                        $next = 258;
                        $bits = 9;
                    } elseif ($next + ($early ? 1 : 0) > (1 << $bits) && $bits < 12) {
                        $bits++;
                    }
                }
            }
            $prev = $code;
        }

        return $out !== '' ? $out : null;
    }

    /** Undone predictor TIFF(2) / PNG(10-15) setelah inflate. */
    protected function undoPredictor(string $data, int $predictor, string $dict): ?string
    {
        if ($predictor === 2) {
            return null; // TIFF predictor: jarang untuk content stream — lewati
        }

        // Ambil /Colors /BitsPerComponent /Columns dari DecodeParms.
        $colors = 1;
        $bpc    = 8;
        if (preg_match('/\/Colors\s+(\d+)/', $dict, $m)) {
            $colors = max(1, (int) $m[1]);
        }
        if (preg_match('/\/BitsPerComponent\s+(\d+)/', $dict, $m)) {
            $bpc = (int) $m[1];
        }
        if ($bpc !== 8) {
            return null;
        }
        $bpp = $colors;

        // Columns: jika tidak ada, coba tebak dari panjang data (asumsi 1 baris).
        $columns = 0;
        if (preg_match('/\/Columns\s+(\d+)/', $dict, $m)) {
            $columns = (int) $m[1];
        }
        if ($columns <= 0) {
            return null; // tanpa Columns predictor tidak bisa dibatalkan
        }

        $rowLen = $columns * $bpp + 1; // +1 byte filter
        $rows   = intdiv(strlen($data), $rowLen);
        if ($rows === 0) {
            return null;
        }

        $out    = str_repeat("\0", $columns * $bpp);
        $result = '';
        for ($r = 0; $r < $rows; $r++) {
            $filter = ord($data[$r * $rowLen]);
            $line   = substr($data, $r * $rowLen + 1, $columns * $bpp);
            $line   = $this->unfilterRow($filter, $line, $out, $bpp);
            $result .= $line;
            $out = $line;
        }

        return $result !== '' ? $result : null;
    }

    private function unfilterRow(int $filter, string $line, string $prev, int $bpp): string
    {
        $len = strlen($line);
        switch ($filter) {
            case 0:
                return $line;
            case 1: // Sub
                for ($i = $bpp; $i < $len; $i++) {
                    $line[$i] = chr((ord($line[$i]) + ord($line[$i - $bpp])) % 256);
                }
                return $line;
            case 2: // Up
                for ($i = 0; $i < $len; $i++) {
                    $line[$i] = chr((ord($line[$i]) + ord($prev[$i])) % 256);
                }
                return $line;
            case 3: // Average
                for ($i = 0; $i < $len; $i++) {
                    $left  = $i >= $bpp ? ord($line[$i - $bpp]) : 0;
                    $up    = ord($prev[$i]);
                    $line[$i] = chr((ord($line[$i]) + intdiv($left + $up, 2)) % 256);
                }
                return $line;
            case 4: // Paeth
                for ($i = 0; $i < $len; $i++) {
                    $a = $i >= $bpp ? ord($line[$i - $bpp]) : 0;
                    $b = ord($prev[$i]);
                    $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                    $p = $a + $b - $c;
                    $pa = abs($p - $a);
                    $pb = abs($p - $b);
                    $pc = abs($p - $c);
                    $pred = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                    $line[$i] = chr((ord($line[$i]) + $pred) % 256);
                }
                return $line;
            default:
                return $line;
        }
    }

    // ==================== Font: CMap / Differences / WinAnsi ====================

    /**
     * Parse isi CMap ToUnicode: blok beginbfchar & beginbfrange.
     *
     * @return array{map:array<int,string>, width:int}|array{}
     */
    protected function parseCMap(string $data): array
    {
        $map   = [];
        $width = 0;

        // bfchar: <src> <dst>
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $this->cmapBlocks($data, 'bfchar'), $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $p) {
                $src = hexdec($p[1]);
                $dst = $this->utf16beToUtf8($p[2]);
                if ($dst !== '') {
                    $map[$src] = $dst;
                    $width     = max($width, intdiv(strlen($p[1]), 2));
                }
            }
        }

        // bfrange: <lo> <hi> <dst>  |  <lo> <hi> [<dst1> <dst2> ...]
        if (preg_match_all('/beginbfrange\s*(.*?)endbfrange/s', $data, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<([0-9A-Fa-f]+)>|\[(.*?)\])/', $block, $r, PREG_SET_ORDER)) {
                    foreach ($r as $x) {
                        $lo = hexdec($x[1]);
                        $hi = hexdec($x[2]);
                        if ($hi < $lo || $hi - $lo > 65535) {
                            continue;
                        }
                        $width = max($width, intdiv(strlen($x[1]), 2));
                        if (($x[4] ?? '') !== '') {
                            $dst = $this->utf16beToUtf8($x[4]);
                            for ($g = $lo; $g <= $hi; $g++) {
                                $map[$g] = $dst;
                            }
                        } elseif (($x[5] ?? '') !== '') {
                            if (preg_match_all('/<([0-9A-Fa-f]+)>/', $x[5], $dsts)) {
                                $g = $lo;
                                foreach ($dsts[1] as $d) {
                                    $char = $this->utf16beToUtf8($d);
                                    if ($char !== '') {
                                        $map[$g] = $char;
                                    }
                                    $g++;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $map === [] ? [] : ['map' => $map, 'width' => max(1, $width)];
    }

    /** Gabungan blok begin<type> ... end<type> (buang komentar CMap %...). */
    protected function cmapBlocks(string $data, string $type): string
    {
        $data = preg_replace('/(^|[^%\n])%.*$/m', '$1', $data) ?? $data;
        $out  = '';
        if (preg_match_all('/begin' . $type . '\s*(.*?)end' . $type . '/s', $data, $m)) {
            $out = implode("\n", $m[1]);
        }

        return $out;
    }

    /** Hex UTF-16BE -> string UTF-8. */
    protected function utf16beToUtf8(string $hex): string
    {
        $bin = @hex2bin(preg_replace('/\s+/', '', $hex));
        if ($bin === false || $bin === '') {
            return '';
        }

        return @iconv('UTF-16BE', 'UTF-8//IGNORE', $bin) ?: '';
    }

    /**
     * Kumpulkan info encoding per objek font dari seluruh indeks objek.
     *
     * @return array{cmaps:array<int,array{map:array<int,string>,width:int}>, encodings:array<int,array{mode:string, map:array<int,string>, width:int}>}
     */
    protected function collectFontMaps(string $raw, array $objs, array $streams): array
    {
        $cmaps     = [];
        $encodings = [];

        foreach ($objs as $objNum => $body) {
            if (strpos($body, '/Font') === false) {
                continue;
            }
            if (! preg_match('/\/Type\s*\/Font\b/', $body) && ! preg_match('/\/Subtype\s*\/(Type0|TrueType|Type1|Type3)/', $body)) {
                continue;
            }

            $isType0 = (bool) preg_match('/\/Subtype\s*\/Type0\b/', $body);

            // --- CID: /ToUnicode ---
            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $body, $m)) {
                $cmapObj = (int) $m[1];
                if (! isset($cmaps[$cmapObj])) {
                    // dict embedded?
                    $mapData = null;
                    $cBody   = $objs[$cmapObj] ?? null;
                    if ($cBody !== null && ! isset($streams[$cmapObj])) {
                        $p = $this->parseCMap($cBody);
                        if ($p !== []) {
                            $cmaps[$cmapObj] = $p;
                        }
                    }
                    if (! isset($cmaps[$cmapObj]) && isset($streams[$cmapObj])) {
                        $data = $this->getStreamData($cmapObj, $streams);
                        if ($data !== null) {
                            $p = $this->parseCMap($data);
                            if ($p !== []) {
                                $cmaps[$cmapObj] = $p;
                            }
                        }
                    }
                }

                // Type0: descendant font menentukan encoding 2-byte (Identity-H).
                $width = isset($cmaps[$cmapObj]) ? $cmaps[$cmapObj]['width'] : 2;
                $encodings[$objNum] = ['mode' => 'cid', 'map' => $cmaps[$cmapObj]['map'] ?? [], 'width' => $width];

                continue;
            }

            // --- Simple font: /Encoding name atau << /Differences [...] >> ---
            $mode = 'raw';
            $codeMap = [];

            if (preg_match('/\/Encoding\s*<<(.*?)>>/s', $body, $em)) {
                $dictEnc = $em[1];
                if (preg_match('/\/WinAnsiEncoding|\/MicrosoftEncoding/', $dictEnc)) {
                    $mode = 'winansi';
                }
                if (preg_match('/\/Differences\s*\[(.*?)\]/s', $dictEnc, $dm)) {
                    $codeMap = $this->parseDifferences($dm[1]);
                    $mode    = 'diffs';
                }
            } elseif (preg_match('/\/Encoding\s*\/(\w+)/', $body, $em)) {
                $mode = match ($em[1]) {
                    'WinAnsiEncoding', 'MicrosoftEncoding' => 'winansi',
                    default => 'raw', // StandardEncoding ~ ASCII (jalur latin cukup)
                };
            }

            if ($mode !== 'raw' || $codeMap !== []) {
                $encodings[$objNum] = [
                    'mode'  => $mode,
                    'map'   => $codeMap,
                    'width' => 1,
                ];
            }
        }

        return ['cmaps' => $cmaps, 'encodings' => $encodings];
    }

    /** Parse /Differences: [code /name code /name ...] -> code => karakter. */
    protected function parseDifferences(string $spec): array
    {
        $map  = [];
        $code = 0;
        if (preg_match_all('/(\d+)|\/([A-Za-z0-9_.]+)/', $spec, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                if ($x[1] !== '' && $x[1] !== null) {
                    $code = (int) $x[1];
                    continue;
                }
                $name = $x[2];
                $char = $this->glyphNameToChar($name);
                if ($char !== '') {
                    $map[$code] = $char;
                }
                $code++;
            }
        }

        return $map;
    }

    /** Nama glyph Adobe standar -> karakter (subset yang umum). */
    protected function glyphNameToChar(string $name): string
    {
        // uniXXXX / uXXXX{2,6}
        if (preg_match('/^uni([0-9A-Fa-f]{4})$/', $name, $m)) {
            $cp = hexdec($m[1]);

            return $cp >= 32 && $cp <= 0x10FFFF ? $this->codePointToUtf8($cp) : '';
        }
        if (preg_match('/^u([0-9A-Fa-f]{4,6})$/', $name, $m)) {
            $cp = hexdec($m[1]);

            return $cp >= 32 ? $this->codePointToUtf8($cp) : '';
        }

        $standard = [
            'space' => ' ', 'exclam' => '!', 'quotedbl' => '"', 'numbersign' => '#',
            'dollar' => '$', 'percent' => '%', 'ampersand' => '&', 'quotesingle' => "'",
            'parenleft' => '(', 'parenright' => ')', 'asterisk' => '*', 'plus' => '+',
            'comma' => ',', 'hyphen' => '-', 'period' => '.', 'slash' => '/',
            'colon' => ':', 'semicolon' => ';', 'less' => '<', 'equal' => '=',
            'greater' => '>', 'question' => '?', 'at' => '@', 'bracketleft' => '[',
            'backslash' => '\\', 'bracketright' => ']', 'asciicircum' => '^',
            'underscore' => '_', 'grave' => '`', 'braceleft' => '{', 'bar' => '|',
            'braceright' => '}', 'asciitilde' => '~',
            'endash' => '–', 'emdash' => '—', 'quoteleft' => '‘', 'quoteright' => '’',
            'quotedblleft' => '“', 'quotedblright' => '”', 'bullet' => '•',
            'minus' => '−', 'multiply' => '×', 'divide' => '÷', 'plusminus' => '±',
            'lessequal' => '≤', 'greaterequal' => '≥', 'notequal' => '≠',
            'infinity' => '∞', 'mu' => 'μ', 'arrowright' => '→', 'arrowleft' => '←',
        ];

        if (isset($standard[$name])) {
            return $standard[$name];
        }
        if (strlen($name) === 1 && preg_match('/[A-Za-z]/', $name)) {
            return $name;
        }
        if (preg_match('/^(zero|one|two|three|four|five|six|seven|eight|nine)$/', $name, $m)) {
            return (string) array_search($m[1], ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'], true);
        }
        if (preg_match('/^[A-Z]$/', $name)) {
            return $name;
        }

        return ''; // g12, glyph tak dikenal, dsb.
    }

    private function codePointToUtf8(int $cp): string
    {
        $s = @iconv('UTF-16BE', 'UTF-8', pack('n', $cp));

        return $s === false ? '' : $s;
    }

    // ==================== Halaman & resources ====================

    /**
     * Kumpulkan halaman (urut pohon /Kids bila bisa) + peta font per halaman.
     *
     * @return list<array{fonts:array<string,array{mode:string,map:array<int,string>,width:int}>, contents:list<int>}>
     */
    protected function collectPages(array $objs, array $cmaps, array $encodings): array
    {
        $pages = [];
        foreach ($objs as $objNum => $body) {
            if (! preg_match('/\/Type\s*\/Page\b(?!s)/', $body)) {
                continue;
            }

            // --- Sumber dict font: body halaman (Resources inline) + obj Resources
            //     indirect. Dict /Font diekstrak dengan depth-tracking karena
            //     Resources berisi dict bersarang (ExtGState, XObject, ...).
            $candidates = [$body];
            if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $body, $m)) {
                $candidates[] = $objs[(int) $m[1]] ?? '';
            }

            // --- peta font nama resource -> info encoding ---
            $fonts = [];
            foreach ($candidates as $src) {
                if ($src === '' || strpos($src, '/Font') === false) {
                    continue;
                }
                $block = $this->extractFontBlock($src);
                if ($block === null) {
                    continue;
                }
                if (preg_match_all('/\/(\w+)\s+(\d+)\s+0\s+R/', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $p) {
                        $fontObj = (int) $p[2];
                        if (isset($encodings[$fontObj])) {
                            $enc = $encodings[$fontObj];
                            // Type0: ambil CMap dari DescendantFonts bila map kosong
                            if ($enc['mode'] === 'cid' && $enc['map'] === []) {
                                $enc = $this->cmapViaDescendant($objs, $fontObj, $cmaps, $encodings);
                            }
                            $fonts[$p[1]] = $enc;
                        }
                    }
                }
            }

            // --- contents: tunggal atau array ---
            $contents = [];
            if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $body, $m)) {
                $contents = [(int) $m[1]];
            } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $body, $m)) {
                if (preg_match_all('/(\d+)\s+0\s+R/', $m[1], $cs)) {
                    $contents = array_map('intval', $cs[1]);
                }
            }

            $pages[] = ['objNum' => $objNum, 'fonts' => $fonts, 'contents' => $contents, 'parent' => $this->parentRef($body)];
        }

        if ($pages === []) {
            return [];
        }

        // Urutan halaman: pohon /Kids bila lengkap, fallback urutan dokumen.
        $order = $this->flattenKids($objs, array_column($pages, 'objNum'));
        if ($order !== []) {
            $byObj = array_column($pages, null, 'objNum');
            $ordered = [];
            foreach ($order as $objNum) {
                if (isset($byObj[$objNum])) {
                    $ordered[] = $byObj[$objNum];
                }
            }
            // halaman yang tidak muncul di Kids (aneh) ditambahkan di akhir.
            foreach ($pages as $p) {
                if (! in_array($p['objNum'], $order, true)) {
                    $ordered[] = $p;
                }
            }
            $pages = $ordered;
        }

        return array_map(static fn ($p) => ['fonts' => $p['fonts'], 'contents' => $p['contents']], $pages);
    }

    private function parentRef(string $body): ?int
    {
        return preg_match('/\/Parent\s+(\d+)\s+0\s+R/', $body, $m) ? (int) $m[1] : null;
    }

    /**
     * Ekstrak isi dict setelah kata kunci /Font dengan depth-tracking `<< >>`
     * (Resources berisi dict bersarang; lazy regex akan berhenti terlalu dini).
     */
    protected function extractFontBlock(string $text): ?string
    {
        $pos = strpos($text, '/Font');
        $len = strlen($text);
        while ($pos !== false) {
            $open  = strpos($text, '<<', $pos);
            $close = $open !== false ? strpos($text, '>>', $pos) : false;
            if ($open === false || $close === false) {
                return null;
            }
            if ($open < $close) {
                // dict /Font ditemukan: pindai sampai depth kembali 0
                $depth = 0;
                $i     = $open;
                while ($i < $len - 1) {
                    if (substr($text, $i, 2) === '<<') {
                        $depth++;
                        $i += 2;
                        continue;
                    }
                    if (substr($text, $i, 2) === '>>') {
                        $depth--;
                        if ($depth === 0) {
                            return substr($text, $open + 2, $i + 2 - $open - 2);
                        }
                        $i += 2;
                        continue;
                    }
                    $i++;
                }

                return null;
            }
            // '/' key lain muncul sebelum '<<' — cari kemungkinan berikutnya.
            $pos = strpos($text, '/Font', $pos + 5);
        }

        return null;
    }

    /** Flatten pohon /Kids (best-effort): urutan kemunculan halaman di gabungan Kids. */
    protected function flattenKids(array $objs, array $pageNums): array
    {
        $kidsMap = [];
        foreach ($objs as $body) {
            if (preg_match('/\/Type\s*\/Pages\b/', $body) && preg_match('/\/Kids\s*\[(.*?)\]/s', $body, $m)) {
                if (preg_match_all('/(\d+)\s+0\s+R/', $m[1], $k)) {
                    $kidsMap[] = array_map('intval', $k[1]);
                }
            }
        }
        if ($kidsMap === []) {
            return [];
        }

        // Halaman daun selalu muncul di posisi dokumennya pada gabungan /Kids.
        $children = array_merge(...$kidsMap);
        $out   = [];
        $seen  = [];
        foreach ($children as $n) {
            if (in_array($n, $pageNums, true) && ! isset($seen[$n])) {
                $seen[$n] = true;
                $out[] = $n;
            }
        }

        return $out;
    }

    /** Cari CMap Type0 melalui DescendantFonts bila ToUnicode menggantung di sana. */
    protected function cmapViaDescendant(array $objs, int $fontObj, array $cmaps, array $encodings): array
    {
        $body = $objs[$fontObj] ?? '';
        if (preg_match('/\/DescendantFonts\s*\[\s*(\d+)\s+0\s+R\s*\]/', $body, $m)) {
            $desc = (int) $m[1];
            if (isset($encodings[$desc])) {
                return $encodings[$desc];
            }
        }

        return ['mode' => 'cid', 'map' => [], 'width' => 2];
    }

    /** Peta font global untuk jalur fallback (semua resource digabung). */
    protected function globalFontMap(array $cmaps, array $encodings): array
    {
        $global = [];
        foreach ($encodings as $objNum => $enc) {
            if ($enc['mode'] === 'cid' && $enc['map'] === []) {
                $enc = $this->cmapViaDescendant([], $objNum, $cmaps, $encodings);
            }
            $global['G' . $objNum] = $enc;
        }

        return $global;
    }

    // ==================== Ekstraksi operator teks ====================

    /**
     * Pindai operator teks berurutan; font aktif /Fx 10 Tf menentukan dekode.
     *
     * @param array<string, array{mode:string,map:array<int,string>,width:int}> $fonts
     */
    protected function extractTextOps(string $data, array $fonts): string
    {
        $out  = '';
        $font = null;

        $re = '/\/(\w+)\s+[\d.]+\s+Tf'                       // 1: pilih font
            . '|\[((?:[^\[\]\\\\]|\\\\.)*?)\]\s*TJ'          // 2: array TJ
            . '|<([0-9A-Fa-f\s]+)>\s*Tj'                     // 3: hex string Tj
            . '|\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\'|")/s'; // 4: literal Tj/'/"

        if (! preg_match_all($re, $data, $ms, PREG_SET_ORDER)) {
            return '';
        }

        foreach ($ms as $m) {
            if (($m[1] ?? '') !== '') {
                $font = isset($fonts[$m[1]]) ? $m[1] : null;
                continue;
            }

            if (($m[2] ?? '') !== '') {
                $seg = '';
                if (preg_match_all('/<([0-9A-Fa-f\s]+)>|\(((?:[^()\\\\]|\\\\.)*)\)/s', $m[2], $inner, PREG_SET_ORDER)) {
                    foreach ($inner as $s) {
                        if (($s[1] ?? '') !== '') {
                            $seg .= $this->decodeStr($this->hexToBin($s[1]), $font, $fonts);
                        } else {
                            $seg .= $this->decodeStr($this->decodePdfString($s[2]), $font, $fonts);
                        }
                    }
                }
                if ($seg !== '') {
                    $out .= $seg . ' ';
                }
                continue;
            }

            if (($m[3] ?? '') !== '') {
                $out .= $this->decodeStr($this->hexToBin($m[3]), $font, $fonts) . ' ';
                continue;
            }

            $out .= $this->decodeStr($this->decodePdfString($m[4] ?? ''), $font, $fonts) . ' ';
        }

        return $out;
    }

    /**
     * Dekode string sesuai mode font:
     * - cid: width-byte per glyph -> CMap -> karakter.
     * - winansi: CP1252 -> UTF-8.
     * - diffs: byte -> tabel /Differences.
     * - raw: latin-1 apa adanya.
     *
     * @param array<string, array{mode:string,map:array<int,string>,width:int}> $fonts
     */
    protected function decodeStr(string $bytes, ?string $font, array $fonts): string
    {
        if ($bytes === '') {
            return '';
        }
        $enc = $font !== null ? ($fonts[$font] ?? null) : null;

        if ($enc !== null) {
            if ($enc['mode'] === 'cid') {
                if ($enc['map'] === []) {
                    return ''; // CMap tidak tersedia: string CID = sampah, buang.
                }
                $width = $enc['width'];
                $out   = '';
                $len   = strlen($bytes);
                for ($i = 0; $i + $width <= $len; $i += $width) {
                    $glyph = 0;
                    for ($j = 0; $j < $width; $j++) {
                        $glyph = ($glyph << 8) | ord($bytes[$i + $j]);
                    }
                    $out .= $enc['map'][$glyph] ?? '';
                }

                return $out;
            }
            if ($enc['mode'] === 'winansi') {
                $s = @iconv('CP1252', 'UTF-8//IGNORE', $bytes);

                return $s === false ? $bytes : $s;
            }
            if ($enc['mode'] === 'diffs') {
                $out = '';
                $len = strlen($bytes);
                for ($i = 0; $i < $len; $i++) {
                    $out .= $enc['map'][$i] ?? (ord($bytes[$i]) >= 32 && ord($bytes[$i]) <= 126 ? chr(ord($bytes[$i])) : '');
                }

                return $out;
            }
        }

        // Jalur lama (font tanpa info / tidak dikenal).
        return $bytes;
    }

    protected function decodePdfString(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace(['\\(', '\\)', '\\\\'], ["\x00P", "\x00R", "\x00B"], $s);
        $s = preg_replace_callback('/\\\\([nrtbf])/', static function ($m) {
            return match ($m[1]) {
                'n' => "\n", 'r' => '', 't' => "\t", 'b' => '', 'f' => '',
            };
        }, $s) ?? $s;
        // Oktal \ddd (1-3 digit, nilai <=255)
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', static function ($m) {
            return chr(octdec($m[1]) & 0xFF);
        }, $s) ?? $s;
        $s = str_replace(["\x00P", "\x00R", "\x00B"], ['(', ')', '\\'], $s);

        return $s;
    }

    /** Hex string PDF (boleh berisi spasi) -> binary. */
    protected function hexToBin(string $hex): string
    {
        $b = @hex2bin(preg_replace('/\s+/', '', $hex));

        return is_string($b) ? $b : '';
    }

    /** Bersihkan header/footer berulang, nomor halaman, whitespace berlebih. */
    protected function clean(string $text): string
    {
        $scrub = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        $text  = ($scrub === false) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : $scrub;

        $lines  = preg_split('/\n/', $text) ?: [];
        $freq   = [];
        $parsed = [];

        foreach ($lines as $line) {
            $l = trim($line);
            if ($l === '' || ! preg_match('/[\x20-\x7E]/', $l)) {
                continue;
            }

            $len       = strlen($l);
            $printable = preg_match_all('/[\x20-\x7E]/', $l);
            if ($len > 0 && $printable / $len < 0.9) {
                continue;
            }
            if (! preg_match('/[A-Za-z]{4,}/', $l)) {
                continue;
            }

            $parsed[] = $l;
            if ($len > 2 && $len < 90) {
                $freq[$l] = ($freq[$l] ?? 0) + 1;
            }
        }

        $parsed = array_values(array_filter($parsed, static function ($l) use ($freq) {
            if (($freq[$l] ?? 0) >= 5) {
                return false;
            }

            return ! preg_match('/^\s*(page\s*)?\d{1,4}(\s*\/\s*\d{1,4})?\s*$/iu', $l);
        }));

        $out = implode("\n", $parsed);
        $out = preg_replace('/[ \t]{2,}/', ' ', $out) ?? $out;
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;

        return trim($out);
    }
}
