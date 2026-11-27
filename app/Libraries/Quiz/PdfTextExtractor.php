<?php

namespace App\Libraries\Quiz;

/**
 * Ekstraktor teks PDF fleksibel (tanpa dependensi eksternal).
 *
 * Dukungan format (v7.2):
 * - Filter stream: FlateDecode, LZWDecode (+ EarlyChange), ASCIIHexDecode,
 *   ASCII85Decode, RunLengthDecode, rantai filter, /DecodeParms predictor
 *   (TIFF 2 & PNG 10-15).
 * - ObjStm (compressed object stream) — PDF modern.
 * - Font: Type0/CID via /ToUnicode (peta per-halaman), /Differences,
 *   /WinAnsiEncoding, fallback latin + filter anti-sampah.
 * - ENKRIPSI Standard Security Handler (v7.2): PDF terproteksi (owner
 *   password, user password kosong) — RC4 40/128-bit (R2-R4), AES-128
 *   (V4/AESV2), AES-256 (R6). Validasi password via /O /U; bila terbukti
 *   diproteksi password user, dikembalikan error 'encrypted'.
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

    /** Standard padding string PDF spec 7.6.3.3 (32 byte). */
    private const PAD = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xAD\x06\xB6\x40\xDB\xFD\x38\x54\xEC\xF6\x6D\x1B";

    /** Konteks dekripsi aktif (null = PDF tidak terenkripsi). */
    private ?array $crypto = null;

    /** Cache hasil dekode stream per objek. */
    private array $decodedCache = [];

    /** @return array{ok:bool,text:string,error:?string} */
    public function extract(string $path, ?string $password = null): array
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

        // 0) PDF terenkripsi? Siapkan konteks dekripsi (parser internal).
        $this->decodedCache = [];
        $this->crypto       = $this->loadCrypto($raw, $password ?? '');
        $encrypted          = $this->crypto !== null;
        if ($encrypted && $this->crypto['mode'] === 'invalid') {
            return ['ok' => false, 'text' => '', 'error' => 'encrypted'];
        }

        // 0a) STRATEGI UTAMA (PDF tidak terenkripsi): parser penuh
        //     Smalot\PdfParser (bundled via composer) — menangani hampir semua
        //     varian PDF lebih akurat daripada parser ringan internal.
        if (! $encrypted && class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $doc  = (new \Smalot\PdfParser\Parser())->parseFile($path);
                $text = trim((string) $doc->getText());
                if (mb_strlen($text) >= 200) {
                    return ['ok' => true, 'text' => $this->clean($text), 'error' => null];
                }
            } catch (\Throwable $e) {
                // parser penuh gagal (mis. PDF rusak/aneh): lanjut ke strategi internal.
            }
        }
        // 1) Indeks objek + stream.
        ['objs' => $objs, 'streams' => $streams] = $this->buildIndex($raw);

        // 2) Info font.
        ['cmaps' => $cmaps, 'encodings' => $encodings] = $this->collectFontMaps($raw, $objs, $streams);

        // 3) Halaman: peta font per halaman + daftar objek content stream.
        $pages = $this->collectPages($objs, $cmaps, $encodings);

        $out = '';
        if ($pages !== []) {
            foreach ($pages as $page) {
                if (microtime(true) - $start > self::TIME_LIMIT_SEC * 3) {
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

        // 4) Fallback: pindai semua stream (halaman tidak terurai dsb).
        if (trim($out) === '') {
            $global = $this->globalFontMap($cmaps, $encodings);
            $count  = 0;
            foreach ($streams as $num => $st) {
                if ($count++ >= self::MAX_STREAMS || microtime(true) - $start > self::TIME_LIMIT_SEC * 3) {
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

    // ==================== ENKRIPSI (Standard Security Handler) ====================

    /**
     * Deteksi & siapkan konteks dekripsi. null = tidak terenkripsi.
     * mode 'invalid' = terenkripsi dengan password user (tidak bisa dibaca).
     */
    protected function loadCrypto(string $raw, string $password = ''): ?array
    {
        $encPos = strpos($raw, '/Encrypt');
        if ($encPos === false) {
            return null;
        }

        // Cari objek dict /Encrypt (bisa mengandung string biner dengan '>>').
        // 1) rujukan langsung: /Encrypt 1410 0 R -> objek 1410.
        // 2) fallback: posisi '/Filter/Standard' lalu mundur ke 'N 0 obj'.
        $objNum = null;
        if (preg_match('/\/Encrypt\s+(\d+)\s+0\s+R/', $raw, $m)) {
            $objNum = (int) $m[1];
        }

        $dict = null;
        if ($objNum !== null && preg_match('/(?<!\d)' . $objNum . '\s+0\s+obj/s', $raw, $mm, PREG_OFFSET_CAPTURE)) {
            $dict = $this->extractDictFrom($raw, (int) $mm[0][1] + strlen($mm[0][0]));
        }
        if ($dict === null && preg_match('/\/Filter\s*\/Standard/s', $raw, $fm, PREG_OFFSET_CAPTURE)) {
            $objStart = strrpos(substr($raw, 0, (int) $fm[0][1]), ' obj');
            if ($objStart !== false) {
                $dict = $this->extractDictFrom($raw, $objStart + 4);
            }
        }
        if ($dict === null) {
            return ['mode' => 'invalid'];
        }

        $vals = $this->pdfDictValues($dict);
        if (! isset($vals['R'], $vals['V'], $vals['P'], $vals['O'], $vals['U'])) {
            return ['mode' => 'invalid'];
        }

        $R      = (int) $vals['R'];
        $V      = (int) $vals['V'];
        $P      = (int) $vals['P'];
        $length = isset($vals['Length']) ? (int) $vals['Length'] : ($V >= 3 ? 128 : 40);

        $oBytes = $vals['O'];
        $uBytes = $vals['U'];
        if (! is_string($oBytes) || ! is_string($uBytes)) {
            return ['mode' => 'invalid'];
        }

        // /ID[<..><..>] dari trailer.
        $id0 = '';
        if (preg_match('/\/ID\s*\[\s*<([0-9A-Fa-f]*)>\s*<([0-9A-Fa-f]*)>/s', $raw, $id)) {
            $bin = @hex2bin($id[1]);
            $id0 = is_string($bin) ? $bin : '';
        }

        $isAES256 = ($R === 6);
        $isAES    = $isAES256 || str_contains($dict, 'AESV2') || str_contains($dict, 'AESV3');
        $encryptMetadata = ! str_contains($dict, '/EncryptMetadata false');

        $keyLen = max(5, intdiv($length, 8));

        // File encryption key untuk password yang diberikan.
        $fileKey = null;
        if ($isAES256) {
            // R6 (V5/R6): password mentah (UTF-8, maks 127 byte), tanpa PAD.
            $pw = substr($password, 0, 127);
            if (strlen($uBytes) < 48) {
                return ['mode' => 'invalid'];
            }
            $uHash = substr($uBytes, 0, 32);
            $uVs   = substr($uBytes, 32, 8);
            $uKs   = substr($uBytes, 40, 8);
            // 1) validasi password user: hash(pw, u_validation_salt) == U[0:32]
            if ($this->r6Password($pw, $uVs, null) !== $uHash) {
                return ['mode' => 'invalid']; // password salah/berbeda
            }
            // 2) file key = AES-256-CBC dec(/UE), key = hash(pw, u_key_salt), IV nol.
            $ueKey = $this->r6Password($pw, $uKs, null);
            $ue    = $vals['UE'] ?? null;
            if (! is_string($ue) || strlen($ue) < 32) {
                return ['mode' => 'invalid'];
            }
            $fileKey = openssl_decrypt($ue, 'aes-256-cbc', $ueKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
            if ($fileKey === false) {
                return ['mode' => 'invalid'];
            }
        } elseif (($R === 5)) {
            // V5/R5: hash sederhana tanpa AES rounds.
            $pw = substr($password, 0, 127);
            if (strlen($uBytes) < 48) {
                return ['mode' => 'invalid'];
            }
            $uVs = substr($uBytes, 32, 8);
            $uKs = substr($uBytes, 40, 8);
            if (hash('sha256', $pw . $uVs, true) !== substr($uBytes, 0, 32)) {
                return ['mode' => 'invalid'];
            }
            $fileKey = hash('sha256', $pw . $uKs, true);
        } else {
            $key = $this->computeFileKey($oBytes, $P, $id0, $R, $keyLen, $encryptMetadata);
            if ($key === null) {
                return ['mode' => 'invalid'];
            }
            // Validasi password via /U (Algorithm 3 / 2).
            if ($R >= 3) {
                $s     = $this->rc4($key, self::PAD);
                $h     = md5(self::PAD . $id0, true);
                $check = $this->rc4($key, $h);
                $uCalc = substr($s . $check, 0, 16);
                if (substr($uBytes, 0, 16) !== $uCalc) {
                    return ['mode' => 'invalid'];
                }
            } else {
                $uCalc = $this->rc4($key, self::PAD);
                if (substr($uBytes, 0, 32) !== $uCalc) {
                    return ['mode' => 'invalid'];
                }
            }
            $fileKey = $key;
        }

        return [
            'mode'    => 'ok',
            'R'       => $R,
            'V'       => $V,
            'keyLen'  => $keyLen,
            'P'       => $P,
            'id0'     => $id0,
            'o'       => $oBytes,
            'u'       => $uBytes,
            'isAES'   => $isAES,
            'isAES256'=> $isAES256,
            'fileKey' => $fileKey,
        ];
    }

    /**
     * Ekstrak blok `<< ... >>` yang seimbang mulai dari offset (setelah 'obj').
     * Tokenizer sadar-string: karakter '>' di dalam string (…) tidak dihitung.
     */
    protected function extractDictFrom(string $raw, int $from): ?string
    {
        $open = strpos($raw, '<<', $from);
        if ($open === false) {
            return null;
        }
        $i     = $open;
        $depth = 0;
        $len   = strlen($raw);
        while ($i < $len - 1) {
            $ch = $raw[$i];
            if ($ch === '(') {
                // string PDF: lewati dengan escape & nested paren
                $depthParen = 1;
                $i++;
                while ($i < $len && $depthParen > 0) {
                    $c = $raw[$i];
                    if ($c === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($c === '(') {
                        $depthParen++;
                    } elseif ($c === ')') {
                        $depthParen--;
                    }
                    $i++;
                }
                continue;
            }
            if (substr($raw, $i, 2) === '<<') {
                $depth++;
                $i += 2;
                continue;
            }
            if (substr($raw, $i, 2) === '>>') {
                $depth--;
                if ($depth === 0) {
                    return substr($raw, $open, $i + 2 - $open);
                }
                $i += 2;
                continue;
            }
            $i++;
        }

        return null;
    }

    /**
     * Tokenizer dict PDF sederhana: key -> nilai mentah (angka int, string byte,
     * hex byte, atau nama/nilai lain sebagai string).
     *
     * @return array<string, string|int>
     */
    protected function pdfDictValues(string $dict): array
    {
        $out = [];
        $len = strlen($dict);
        $i   = 2; // lewati '<<'
        while ($i < $len - 1) {
            if ($dict[$i] !== '/') {
                $i++;
                continue;
            }
            if (! preg_match('/\/([A-Za-z]\w*)/', $dict, $km, 0, $i)) {
                $i++;
                continue;
            }
            $key = $km[1];
            $i  += strlen($km[0]);
            while ($i < $len && ctype_space($dict[$i])) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            $ch = $dict[$i];
            if ($ch === '<' && ($dict[$i + 1] ?? '') !== '<') {
                // hex string
                $end = strpos($dict, '>', $i);
                if ($end === false) {
                    break;
                }
                $hex       = preg_replace('/\s+/', '', substr($dict, $i + 1, $end - $i - 1)) ?? '';
                $bin       = @hex2bin($hex);
                $out[$key] = is_string($bin) ? $bin : '';
                $i         = $end + 1;
                continue;
            }
            if ($ch === '(') {
                $j = $i + 1;
                $d = 1;
                while ($j < $len && $d > 0) {
                    $c = $dict[$j];
                    if ($c === '\\') {
                        $j += 2;
                        continue;
                    }
                    if ($c === '(') {
                        $d++;
                    } elseif ($c === ')') {
                        $d--;
                    }
                    $j++;
                }
                $out[$key] = $this->decodePdfString(substr($dict, $i + 1, $j - $i - 2));
                $i         = $j;
                continue;
            }
            if (preg_match('/-?\d+/', $dict, $nm, 0, $i)) {
                $out[$key] = (int) $nm[0];
                $i        += strlen($nm[0]);
                continue;
            }
            if (preg_match('/[^\s\/\(\)<>\[\]]+/', $dict, $nm, 0, $i)) {
                $out[$key] = $nm[0];
                $i        += strlen($nm[0]);
                continue;
            }
            $i++;
        }

        return $out;
    }

    /** Algorithm 2 (PDF spec): file key dari password (dipad ke 32 byte). */
    protected function computeFileKey(string $password, string $oBytes, int $P, string $id0, int $R, int $keyLen, bool $encryptMetadata = true): ?string
    {
        $pw = substr($password . self::PAD, 0, 32);

        $h = hash_init('md5');
        hash_update($h, $pw);
        hash_update($h, $oBytes);
        hash_update($h, pack('V', $P));
        hash_update($h, $id0);
        // Spec: FFFFFFFF di-append HANYA bila /EncryptMetadata false (default true).
        if ($R >= 4 && ! $encryptMetadata) {
            hash_update($h, "\xFF\xFF\xFF\xFF");
        }
        $key = hash_final($h, true);

        if ($R >= 3) {
            $key = substr(md5(substr($key, 0, $keyLen), true), 0, $keyLen);
            for ($i = 1; $i < 50; $i++) {
                $key = substr(md5(substr($key, 0, $keyLen), true), 0, $keyLen);
            }

            return substr($key, 0, $keyLen);
        }

        return substr($key, 0, $keyLen);
    }

    /** Per-object key (Algorithm 4 / R6 / R5). */
    private function objectKey(int $objNum, int $gen): ?string
    {
        $c = $this->crypto;
        if ($c === null || $c['fileKey'] === null) {
            return null;
        }
        if ($c['isAES256']) {
            // R6: file key (32 byte dari /UE) langsung dipakai AES-256.
            return $c['fileKey'];
        }
        $h = md5($c['fileKey'] . substr(pack('V', $objNum), 0, 3) . substr(pack('v', $gen), 0, 2) . ($c['isAES'] ? 'sAlT' : ''), true);
        $n = min($c['keyLen'] + 5, 16);

        return substr($h, 0, $n);
    }

    /** Dekripsi raw stream/string objek (sebelum filter). */
    private function decryptBytes(int $objNum, int $gen, string $data): ?string
    {
        $c  = $this->crypto;
        $key = $this->objectKey($objNum, $gen);
        if ($key === null) {
            return null;
        }
        if ($c['isAES']) {
            $bits = $c['isAES256'] ? 'aes-256-cbc' : 'aes-128-cbc';
            if (strlen($data) < 17 || strlen($data) % 16 !== 0) {
                return null;
            }
            $iv  = substr($data, 0, 16);
            $out = openssl_decrypt(substr($data, 16), $bits, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
            if ($out === false) {
                return null;
            }
            // buang PKCS#7 padding
            $pad = ord(substr($out, -1));
            if ($pad >= 1 && $pad <= 16) {
                $out = substr($out, 0, -$pad);
            }

            return $out !== '' ? $out : null;
        }

        $out = $this->rc4($key, $data);

        return $out !== '' ? $out : null;
    }

    /** RC4 (untuk R2-R4). */
    protected function rc4(string $key, string $data): string
    {
        $s = range(0, 255);
        $j = 0;
        $kl = strlen($key);
        for ($i = 0; $i < 256; $i++) {
            $j       = ($j + $s[$i] + ord($key[$i % $kl])) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }
        $out = '';
        $len = strlen($data);
        $i   = 0;
        $j   = 0;
        for ($y = 0; $y < $len; $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $out .= $data[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }

        return $out;
    }

    /**
     * Algorithm 2.B (R6): password hash dengan AES-128-CBC internal rounds
     * dan rotasi SHA-256/SHA-384/SHA-512 (implementasi sesuai pdfminer.six).
     */
    protected function r6Password(string $pw, string $salt8, ?string $vector): string
    {
        $k     = hash('sha256', $pw . $salt8 . ($vector ?? ''), true);
        $tail  = $vector ?? '';
        $round = 0;
        $last  = 0;
        $len   = strlen($k);
        do {
            $k1 = str_repeat($pw . $k . $tail, 64);
            $e  = openssl_encrypt($k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16));
            if ($e === false) {
                break;
            }
            $mod  = 0;
            $head = substr($e, 0, 16);
            for ($i = 0; $i < 16; $i++) {
                $mod += ord($head[$i]) % 3;
            }
            $algo = ['sha256', 'sha384', 'sha512'][$mod % 3];
            $k    = hash($algo, $e, true);
            $last = ord(substr($e, -1));
            $round++;
        } while ($round < 64 || $last > $round - 32);

        return substr($k, 0, 32);
    }

    // ==================== Indeks objek & stream ====================

    /**
     * @return array{objs:array<int,string>, streams:array<int,array{dict:string,raw:string,dictOnly:bool}>}
     */
    protected function buildIndex(string $raw): array
    {
        $objs    = [];
        $streams = [];

        if (preg_match_all(
            '/(\d+)\s+0\s+obj\s*<<((?:(?!endobj|stream).)*?)>>\s*stream\r?\n(.*?)endstream/s',
            $raw,
            $sm,
            PREG_SET_ORDER
        )) {
            foreach ($sm as $s) {
                $num = (int) $s[1];
                if (! isset($streams[$num])) {
                    // Gunakan /Length utk slice data PERSIS (byte sifer boleh 0x0A/0x0D
                    // — rtrim akan merusak data terenkripsi). Fallback: rtrim.
                    $data = rtrim($s[3], "\r\n");
                    if (preg_match('/\/Length\s+(\d+)\D/', '<<' . $s[2] . '>>', $lm)) {
                        $want = (int) $lm[1];
                        if ($want > 0 && $want <= strlen($s[3])) {
                            $data = substr($s[3], 0, $want);
                        }
                    }
                    $streams[$num] = ['dict' => '<<' . $s[2] . '>>', 'raw' => $data, 'dictOnly' => false];
                    $objs[$num]    = '<<' . $s[2] . '>>';
                }
            }
        }

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
                    $end = strpos($raw, 'endstream', $endStr);
                    if ($end !== false && ! isset($streams[$num])) {
                        $streams[$num] = ['dict' => '', 'raw' => rtrim(substr($raw, $endStr + 6, $end - $endStr - 6), "\r\n"), 'dictOnly' => false];
                    }
                }
            }
        }

        foreach ($streams as $num => $st) {
            if (! str_contains($st['dict'], '/ObjStm')) {
                continue;
            }
            $dict = $st['dict'];
            if (! preg_match('/\/N\s+(\d+)/', $dict, $n) || ! preg_match('/\/First\s+(\d+)/', $dict, $first)) {
                continue;
            }
            $data = $this->decodeStream($num, 0, $dict, $st['raw']);
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

    /** Dekode satu stream (dekripsi jika perlu + rantai /Filter + predictor). */
    protected function decodeStream(int $objNum, int $gen, string $dict, string $raw): ?string
    {
        $data = $raw;

        if ($this->crypto !== null && $this->crypto['mode'] === 'ok') {
            $data = $this->decryptBytes($objNum, $gen, $data);
            if ($data === null) {
                return null;
            }
        }

        $filters = [];
        if (preg_match('/\/Filter\s*([\/\[\]].*?)(?=\/(?:Length|DecodeParms|Type|Filter)|$)/s', $dict, $m)) {
            if (preg_match_all('/\/(\w+)/', trim($m[1]), $names)) {
                $filters = $names[1];
            }
        } elseif (preg_match('/\/Filter\s*\/(\w+)/', $dict, $m)) {
            $filters = [$m[1]];
        }

        foreach ($filters as $filter) {
            $data = match ($filter) {
                'FlateDecode'     => $this->inflate($data),
                'LZWDecode'       => $this->decodeLZW($data, $this->earlyChange($dict)),
                'ASCIIHexDecode'  => $this->decodeASCIIHex($data),
                'ASCII85Decode'   => $this->decodeASCII85($data),
                'RunLengthDecode' => $this->decodeRunLength($data),
                default           => $data,
            };
            if ($data === null || $data === '') {
                return null;
            }
        }

        if ($data !== null && preg_match('/\/Predictor\s+(\d+)/', $dict, $p) && (int) $p[1] >= 2) {
            $data = $this->undoPredictor($data, (int) $p[1], $dict);
        }

        return $data;
    }

    private function earlyChange(string $dict): bool
    {
        return ! preg_match('/\/EarlyChange\s+0/', $dict);
    }

    protected function getStreamData(int $objNum, array $streams): ?string
    {
        if (isset($this->decodedCache[$objNum])) {
            return $this->decodedCache[$objNum];
        }
        $st = $streams[$objNum] ?? null;
        if ($st === null || $st['dictOnly']) {
            return null;
        }
        $data = $this->decodeStream($objNum, 0, $st['dict'], $st['raw']);
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
        if ($clean === '') {
            return null;
        }
        if (strlen($clean) % 2 === 1) {
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
                break;
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

            if ($code === 256) {
                $table = [];
                for ($i = 0; $i < 256; $i++) {
                    $table[$i] = chr($i);
                }
                $next = 258;
                $bits = 9;
                $prev = null;
                continue;
            }
            if ($code === 257) {
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

    protected function undoPredictor(string $data, int $predictor, string $dict): ?string
    {
        if ($predictor === 2) {
            return null;
        }

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

        $columns = 0;
        if (preg_match('/\/Columns\s+(\d+)/', $dict, $m)) {
            $columns = (int) $m[1];
        }
        if ($columns <= 0) {
            return null;
        }

        $rowLen = $columns * $bpp + 1;
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
            case 1:
                for ($i = $bpp; $i < $len; $i++) {
                    $line[$i] = chr((ord($line[$i]) + ord($line[$i - $bpp])) % 256);
                }
                return $line;
            case 2:
                for ($i = 0; $i < $len; $i++) {
                    $line[$i] = chr((ord($line[$i]) + ord($prev[$i])) % 256);
                }
                return $line;
            case 3:
                for ($i = 0; $i < $len; $i++) {
                    $left = $i >= $bpp ? ord($line[$i - $bpp]) : 0;
                    $up   = ord($prev[$i]);
                    $line[$i] = chr((ord($line[$i]) + intdiv($left + $up, 2)) % 256);
                }
                return $line;
            case 4:
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

    protected function parseCMap(string $data): array
    {
        $map   = [];
        $width = 0;

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

    protected function cmapBlocks(string $data, string $type): string
    {
        $data = preg_replace('/(^|[^%\n])%.*$/m', '$1', $data) ?? $data;
        $out  = '';
        if (preg_match_all('/begin' . $type . '\s*(.*?)end' . $type . '/s', $data, $m)) {
            $out = implode("\n", $m[1]);
        }

        return $out;
    }

    protected function utf16beToUtf8(string $hex): string
    {
        $bin = @hex2bin(preg_replace('/\s+/', '', $hex));
        if ($bin === false || $bin === '') {
            return '';
        }

        return @iconv('UTF-16BE', 'UTF-8//IGNORE', $bin) ?: '';
    }

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

            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $body, $m)) {
                $cmapObj = (int) $m[1];
                if (! isset($cmaps[$cmapObj])) {
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

                $width = isset($cmaps[$cmapObj]) ? $cmaps[$cmapObj]['width'] : 2;
                $encodings[$objNum] = ['mode' => 'cid', 'map' => $cmaps[$cmapObj]['map'] ?? [], 'width' => $width];

                continue;
            }

            $mode    = 'raw';
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
                    default => 'raw',
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

    protected function glyphNameToChar(string $name): string
    {
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

        return '';
    }

    private function codePointToUtf8(int $cp): string
    {
        $s = @iconv('UTF-16BE', 'UTF-8', pack('n', $cp));

        return $s === false ? '' : $s;
    }

    // ==================== Halaman & resources ====================

    /**
     * @return list<array{fonts:array<string,array{mode:string,map:array<int,string>,width:int}>, contents:list<int>}>
     */
    protected function collectPages(array $objs, array $cmaps, array $encodings): array
    {
        $pages = [];
        foreach ($objs as $objNum => $body) {
            if (! preg_match('/\/Type\s*\/Page\b(?!s)/', $body)) {
                continue;
            }

            $candidates = [$body];
            if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $body, $m)) {
                $candidates[] = $objs[(int) $m[1]] ?? '';
            }

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
                            if ($enc['mode'] === 'cid' && $enc['map'] === []) {
                                $enc = $this->cmapViaDescendant($objs, $fontObj, $cmaps, $encodings);
                            }
                            $fonts[$p[1]] = $enc;
                        }
                    }
                }
            }

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

        $order = $this->flattenKids($objs, array_column($pages, 'objNum'));
        if ($order !== []) {
            $byObj = array_column($pages, null, 'objNum');
            $ordered = [];
            foreach ($order as $objNum) {
                if (isset($byObj[$objNum])) {
                    $ordered[] = $byObj[$objNum];
                }
            }
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
            $pos = strpos($text, '/Font', $pos + 5);
        }

        return null;
    }

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
     * @param array<string, array{mode:string,map:array<int,string>,width:int}> $fonts
     */
    protected function extractTextOps(string $data, array $fonts): string
    {
        $out  = '';
        $font = null;

        $re = '/\/(\w+)\s+[\d.]+\s+Tf'
            . '|\[((?:[^\[\]\\\\]|\\\\.)*?)\]\s*TJ'
            . '|<([0-9A-Fa-f\s]+)>\s*Tj'
            . '|\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\'|")/s';

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
                    return '';
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

        return $bytes;
    }

    protected function decodePdfString(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace(['\\(', '\\)', '\\\\'], ["\x00P", "\x00R", "\x00B"], $s);
        $s = preg_replace_callback('/\\\\([nrtbf])/', static function ($m) {
            return match ($m[1]) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
            };
        }, $s) ?? $s;
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', static function ($m) {
            return chr(octdec($m[1]) & 0xFF);
        }, $s) ?? $s;
        $s = str_replace(["\x00P", "\x00R", "\x00B"], ['(', ')', '\\'], $s);

        return $s;
    }

    protected function hexToBin(string $hex): string
    {
        $b = @hex2bin(preg_replace('/\s+/', '', $hex));

        return is_string($b) ? $b : '';
    }

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
