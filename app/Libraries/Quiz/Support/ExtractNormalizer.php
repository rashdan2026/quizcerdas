<?php

namespace App\Libraries\Quiz\Support;

/**
 * Normalisasi hasil ekstraksi teks PDF agar layak diproses AI — TANPA
 * menghilangkan informasi materi.
 *
 * Prinsip konservatif:
 * - Karakter khusus < > & ' " ; { } [ ] ( ) \ / DIPERTAHANKAN (contoh kode,
 *   rumus, SQL, simbol teknis jangan rusak).
 * - Tidak ada penghapusan kata; hanya artefak MEKANIS yang dibersihkan.
 * - Perbaikan yang berisiko mengubah makna TIDAK dilakukan.
 *
 * Yang dilakukan:
 * - Scrub UTF-8 invalid; karakter kontrol dibuang (kecuali \n).
 * - soft-hyphen & replacement char dibersihkan.
 * - De-hyphenation baris terpotong: "kon-\nsep" -> "konsep" (hanya huruf).
 * - Penggabungan baris terpotong yang konservatif (baris tidak berakhir
 *   tanda baca akhir kalimat DAN baris berikut diawali huruf kecil).
 * - Header/footer berulang dibuang HANYA bila terdeteksi aman: baris pendek
 *   (<=80 char) yang sama persis muncul di >= 60% halaman (minimal 4 halaman)
 *   dan bukan bagian daftar/judul.
 * - Daftar bernomor, bullet, judul bab, nomor halaman, tabel (teks berjarak)
 *   dipertahankan apa adanya.
 */
final class ExtractNormalizer
{
    /** Karakter kontrol yang dibuang (kecuali newline). */
    private const CONTROL_RE = '/[\x{00AD}\x{FFFD}\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    /** Header/footer: hanya baris pendek tanpa angka list & tidak bertanda akhir kalimat. */
    private const HF_MAX_LEN = 80;
    private const HF_MIN_PAGES = 4;
    private const HF_MIN_RATIO = 0.6;

    /**
     * @param list<string>|null $pageTexts teks per halaman (untuk deteksi header/footer).
     */
    public static function normalize(string $text, ?array $pageTexts = null): string
    {
        $text = self::scrub($text);
        if (trim($text) === '') {
            return '';
        }

        $hfPatterns = self::repeatedLines($pageTexts);

        $normalizedPages = [];
        $pages = $pageTexts !== null && $pageTexts !== [] ? $pageTexts : [$text];

        foreach ($pages as $page) {
            $page = self::scrub($page);
            $page = self::dropRepeated($page, $hfPatterns);
            $page = self::reflow($page);
            if (trim($page) !== '') {
                $normalizedPages[] = trim($page);
            }
        }

        $out = implode("\n\n", $normalizedPages);
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;

        return trim($out);
    }

    /** Bersihkan byte/char mekanis — isi materi tidak disentuh. */
    public static function scrub(string $text): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        $text  = ($clean === false) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : $clean;
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $text  = preg_replace(self::CONTROL_RE, '', $text) ?? $text;
        $text  = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text  = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        return $text;
    }

    /**
     * Gabungkan baris terpotong. Konservatif:
     * - de-hyphenation hanya untuk pola huruf-\n-huruf.
     * - penggabungan hanya bila baris TIDAK berakhir tanda baca akhir/judul
     *   dan baris berikut diawali huruf kecil (indikasi kalimat yang sama).
     */
    private static function reflow(string $text): string
    {
        // De-hyphenate: "kata-\nlanjutan" -> "katalanjutan" (huruf saja).
        $text = preg_replace('/(\p{Ll})-\n(\p{Ll})/u', '$1$2', $text) ?? $text;

        $lines = explode("\n", $text);
        $out   = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $out .= "\n\n";
                continue;
            }
            if ($out === '') {
                $out = $line;
                continue;
            }

            $prev = mb_substr($out, (int) -strrpos("\n" . $out, "\n"));
            $prev = trim($prev);

            if ($prev !== '' && self::isContinuation($prev, $line)) {
                $out = mb_substr($out, 0, mb_strlen($out) - mb_strlen($prev)) . $prev . ' ' . $line;
            } else {
                $out .= "\n" . $line;
            }
        }

        return $out;
    }

    private static function isContinuation(string $prev, string $next): bool
    {
        if ($prev === '' || $next === '') {
            return false;
        }
        // Baris sebelumnya berakhir tanda akhir kalimat/blok -> jangan gabung.
        if (preg_match('/[.!?:;)"\]\x{201D}\x{2019}]\s*$/u', $prev)) {
            return false;
        }
        // Baris berikutnya terlihat seperti item daftar/judul/tabulasi -> jangan gabung.
        if (preg_match('/^([-*\x{2022}\x{2013}]|\d+[.)]\s|#{1,4}\s|\p{Lu}[\p{L}\s]{2,30}:$)/u', $next)) {
            return false;
        }
        // Baris berikutnya mulai huruf KAPITAL -> kemungkinan kalimat/paragraf baru.
        if (preg_match('/^\p{Lu}/u', $next) && preg_match('/\p{Ll}/u', mb_substr($prev, -1))) {
            return false;
        }

        // Gabung hanya bila baris sebelumnya diawali huruf kecil (kalimat mengalir).
        return (bool) preg_match('/^\p{Ll}/u', $prev);
    }

    /**
     * Deteksi header/footer berulang: baris (setelah trim) yang muncul sama
     * persis di >= 60% halaman (minimal 4 halaman) dan pendek.
     *
     * @return list<string> pola persis yang boleh dibuang
     */
    private static function repeatedLines(?array $pageTexts): array
    {
        if ($pageTexts === null || count($pageTexts) < self::HF_MIN_PAGES) {
            return [];
        }

        $counts = [];
        foreach ($pageTexts as $page) {
            $seen = [];
            foreach (explode("\n", $page) as $line) {
                $line = trim($line);
                if ($line === '' || mb_strlen($line) > self::HF_MAX_LEN) {
                    continue;
                }
                // Jangan pernah menganggap konten penting sebagai header/footer:
                // baris dengan angka daftar/bullet/panjang kalimat dilewati.
                if (preg_match('/^\d+[.)]\s|^[-*\x{2022}]\s|[.!?;:]$/u', $line) && mb_strlen($line) > 25) {
                    continue;
                }
                if (isset($seen[$line])) {
                    continue;
                }
                $seen[$line] = true;
                $counts[$line] = ($counts[$line] ?? 0) + 1;
            }
        }

        $threshold = max(self::HF_MIN_PAGES, (int) ceil(count($pageTexts) * self::HF_MIN_RATIO));
        $patterns  = [];
        foreach ($counts as $line => $count) {
            if ($count >= $threshold) {
                $patterns[] = $line;
            }
        }

        return array_slice($patterns, 0, 8); // batasi jumlah pola (aman terhadap salah deteksi massal)
    }

    /** Buang baris yang persis cocok pola header/footer (baris penuh saja). */
    private static function dropRepeated(string $page, array $patterns): string
    {
        if ($patterns === []) {
            return $page;
        }

        $lines = explode("\n", $page);
        foreach ($lines as $i => $line) {
            if (in_array(trim($line), $patterns, true)) {
                unset($lines[$i]);
            }
        }

        return implode("\n", $lines);
    }
}
