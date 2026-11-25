<?php

namespace App\Libraries\Quiz;

/**
 * Ekstraktor teks PDF sederhana (tanpa dependensi eksternal).
 * Mendukung stream FlateDecode dan operator teks Tj/TJ/'/".
 * Berjalan dengan batas sumber daya internal (batas waktu loop, ukuran hasil).
 *
 * PENTING: kelas ini TIDAK boleh memanggil set_time_limit(). set_time_limit()
 * me-restart hitungan waktu eksekusi PHP dengan nilai baru, sehingga batas
 * milik pemanggil (controller generate: 600 detik) sempat tertimpa menjadi
 * 60 detik dan request meninggal di tengah pemanggilan AI.
 *
 * Jika hasil ekstraksi terlalu sedikit, PDF kemungkinan hasil scan
 * (tanpa lapisan teks) dan akan ditolak oleh pipeline.
 */
class PdfTextExtractor
{
    private const MAX_OUTPUT_CHARS = 300000; // batas ukuran hasil ekstraksi
    private const MAX_STREAMS      = 3000;
    private const TIME_LIMIT_SEC   = 60;     // anggaran wall-clock internal untuk loop stream

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

        // Struktur file dasar
        if (substr($raw, 0, 5) !== '%PDF-') {
            return ['ok' => false, 'text' => '', 'error' => 'not_pdf'];
        }
        if (strpos($raw, '%%EOF') === false) {
            return ['ok' => false, 'text' => '', 'error' => 'incomplete_pdf'];
        }

        $start   = microtime(true);
        $out     = '';
        $streams = 0;

        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if ($streams >= self::MAX_STREAMS || microtime(true) - $start > self::TIME_LIMIT_SEC) {
                    break;
                }
                $streams++;

                $data = $this->inflate($m[1]);
                if ($data === null) {
                    $data = $m[1];
                }

                $txt = $this->extractTextOps($data);
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

    private function inflate(string $data): ?string
    {
        // Buang trailing newline sebelum "endstream" agar decompress valid.
        $data = rtrim($data, "\r\n");

        foreach (['gzdecode', 'gzuncompress', 'gzinflate'] as $fn) {
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

    /** Ambil string dari operator teks dalam content stream. */
    private function extractTextOps(string $data): string
    {
        $out = '';

        // 1. Array TJ lengkap: [(s) -8 (s) 20 (s)] TJ — kumpulkan seluruh array
        //    (string literal + angka kerning di antaranya).
        $nArrays = preg_match_all('/\[(?:[^\[\]\\\\]|\\\\.)*\]\s*(?:TJ|Tj)/', $data, $arrays);
        foreach ($arrays[0] as $op) {
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $op, $strs)) {
                foreach ($strs[1] as $s) {
                    $out .= $this->decodePdfString($s);
                }
            }
            $out .= ' ';
        }

        // 2. Operator Tj / ' / " dengan string literal.
        preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*(?:Tj|\'|\")/', $data, $tjs);
        foreach ($tjs[0] as $op) {
            if (preg_match('/\(((?:[^()\\\\]|\\\\.)*)\)/', $op, $s)) {
                $out .= $this->decodePdfString($s[1]) . ' ';
            }
        }

        // 3. Hex string <...> Tj (latin1, untuk font non-subset).
        preg_match_all('/<([0-9A-Fa-f\s]+)>\s*(?:Tj|TJ)/', $data, $hexes);
        foreach ($hexes[1] as $h) {
            $bin = @hex2bin(preg_replace('/\s+/', '', $h));
            if (is_string($bin) && preg_match('/^[\x20-\x7E\s]+$/', $bin)) {
                $out .= $bin . ' ';
            }
        }

        return $out;
    }

    private function decodePdfString(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace(['\\(', '\\)', '\\\\'], ["\x00P", "\x00R", "\x00B"], $s);
        $s = preg_replace_callback('/\\\\([nrtbf])/', static function ($m) {
            return match ($m[1]) {
                'n' => "\n", 'r' => '', 't' => "\t", 'b' => '', 'f' => '',
            };
        }, $s) ?? $s;
        $s = preg_replace('/\\\\([0-7]{1,3})/', "\$1", $s) ?? $s; // oktal dasar
        $s = str_replace(["\x00P", "\x00R", "\x00B"], ['(', ')', '\\'], $s);

        return $s;
    }

    /** Bersihkan header/footer berulang, nomor halaman, whitespace berlebih. */
    private function clean(string $text): string
    {
        // Tanpa modifier /u: teks mentah dari stream biner bisa invalid UTF-8
        // yang membuat preg_split /u gagal (null) dan seluruh teks terbuang.
        $lines  = preg_split('/\n/', $text) ?: [];
        $freq   = [];
        $parsed = [];

        foreach ($lines as $line) {
            $l = trim($line);
            if ($l === '' || ! preg_match('/[\x20-\x7E]/', $l)) {
                continue; // buang baris kosong / baris biner non-printable
            }
            $parsed[] = $l;
            $len      = strlen($l);
            if ($len > 2 && $len < 90) {
                $freq[$l] = ($freq[$l] ?? 0) + 1;
            }
        }

        // Buang baris berulang sangat sering (header/footer), dan baris berisi angka halaman saja
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
