<?php

namespace App\Libraries\Quiz;

use App\Libraries\Quiz\Support\ExtractNormalizer;
use App\Libraries\Quiz\Support\OcrEngine;

/**
 * Adaptive PDF Reader — pembacaan PDF bertingkat dengan pemeriksaan kualitas
 * per halaman (v8).
 *
 * Tahapan:
 * 1. Parser utama  : Smalot\PdfParser per halaman (PDF teks digital).
 * 2. Parser alt.   : parser internal PdfTextExtractor (dokumen) — HANYA bila
 *                    hasil utama tidak memadai; dipilih skor terbaik.
 * 3. OCR fallback  : hanya halaman TANPA teks, dibatasi quiz.ocrMaxPages;
 *                    bila OCR tidak tersedia di server, dilaporkan jujur.
 * 4. Kualitas per halaman: jumlah char/kata, rasio karakter rusak (PUA/
 *    replacement), halaman tipis, pola CID — status: ok|warning|empty|
 *    needs_ocr|failed.
 * 5. Normalisasi   : ExtractNormalizer (konservatif, karakter khusus utuh).
 *
 * Keluaran berisi ringkasan + peringatan + preview sehingga dosen dapat
 * menilai kualitas pembacaan SEBELUM Generate Soal dijalankan.
 *
 * Konvensi resource (shared hosting):
 * - Tanpa set_time_limit (milik pemanggil); anggaran waktu internal dijaga
 *   dengan pengecekan antar halaman.
 * - Output dibatasi MAX_OUTPUT_CHARS; OCR dibatasi max pages.
 * - Tanpa dependensi Docker/Redis/daemon/akses root.
 */
class AdaptivePdfReader
{
    private const MAX_OUTPUT_CHARS   = 300000;
    private const TIME_BUDGET_SEC    = 60;

    /** Threshold analisis dokumen (pengganti ambang 200 char yang kaku). */
    private const MIN_DOC_CHARS    = 300;
    private const MIN_DOC_READABLE = 300;   // char halaman ok+warning minimal untuk layak generate
    private const MIN_PREVIEW_CHARS = 2000;

    private OcrEngine $ocr;
    private float $start;
    private bool $timeExceeded = false;

    public function __construct(?OcrEngine $ocr = null)
    {
        $this->ocr = $ocr ?? new OcrEngine();
    }

    /**
     * Analisis dokumen lengkap.
     *
     * @return array{
     *   ok: bool, error: ?string, text: string,
     *   pages: list<array{page:int, status:string, chars:int, words:int, method:string}>,
     *   summary: array{pages_total:int, pages_ok:int, pages_warning:int, pages_empty:int,
     *                  pages_ocr:int, pages_failed:int, chars:int, words:int,
     *                  methods:list<string>, can_generate:bool, ocr_available:bool},
     *   warnings: list<string>, preview: string, duration_ms: int
     * }
     */
    public function analyze(string $path, ?string $password = null): array
    {
        $this->start = microtime(true);

        $pages = $this->extractWithSmalot($path, $password);

        $methods  = [];
        $warnings = [];
        try {
            $ocrInfo = $this->ocr->availability();
        } catch (\Throwable $e) {
            // OCR tidak boleh menjadi penyebab kegagalan analisis.
            log_message('warning', 'AdaptivePdfReader: deteksi OCR gagal — {msg}', ['msg' => mb_substr($e->getMessage(), 0, 200)]);
            $ocrInfo = ['available' => false, 'reason' => 'error'];
        }

        if ($pages !== []) {
            $methods[] = 'smalot';
            $pages = $this->applyOcr($pages, $path, $ocrInfo, $methods, $warnings);
        }

        // Dokumen sangat lemah -> parser alternatif (internal) sekali coba.
        $smalotChars = array_sum(array_map(static fn ($p) => $p['chars'], $pages));
        $encrypted   = false;
        if ($this->documentPoor($pages, $smalotChars) && ! $this->timeExceeded) {
            $internal = (new PdfTextExtractor())->extract($path, $password);
            if (($internal['ok'] ?? false) && mb_strlen((string) ($internal['text'] ?? '')) > $smalotChars * 1.2) {
                $methods[] = 'internal';
                $pages = $this->pagesFromDocumentText((string) $internal['text']);
                $pages = $this->applyOcr($pages, $path, $ocrInfo, $methods, $warnings);
            } elseif (! ($internal['ok'] ?? false) && ($internal['error'] ?? null) === 'encrypted') {
                // Terproteksi password user — tidak ada cara membaca isinya.
                $encrypted = true;
            }
        }

        [$statuses, $summary] = $this->summarize($pages, $methods, $ocrInfo['available']);

        if ($encrypted) {
            return $this->result(false, 'encrypted', '', $statuses, $summary, ['PDF terproteksi password sehingga isinya tidak dapat dibaca.'], $warnings);
        }

        // Teks akhir: normalisasi konservatif dengan penanda halaman.
        // Normalizer tidak boleh membuat analisis gagal: bila gagal, pakai teks
        // hasil scrub sederhana (bersihkan UTF-8/karakter kontrol).
        $pageTexts = [];
        foreach ($pages as $p) {
            $pageTexts[] = $p['text'] ?? '';
        }
        try {
            $normalized = ExtractNormalizer::normalize(implode("\n", $pageTexts), $pageTexts);
        } catch (\Throwable $e) {
            log_message('warning', 'AdaptivePdfReader: normalisasi gagal (fallback scrub) — {msg}', ['msg' => mb_substr($e->getMessage(), 0, 200)]);
            $normalized = ExtractNormalizer::scrub(implode("\n", $pageTexts));
        }
        if (mb_strlen($normalized) > self::MAX_OUTPUT_CHARS) {
            $normalized = mb_substr($normalized, 0, self::MAX_OUTPUT_CHARS) . "\n[TRUNCATED]";
        }

        $warnings = array_merge($warnings, $this->buildWarnings($pages, $summary, $ocrInfo));

        $summary['can_generate'] = $summary['chars'] >= self::MIN_DOC_READABLE && ($summary['pages_ok'] + $summary['pages_warning']) >= 1;

        $ok = $summary['chars'] >= self::MIN_DOC_CHARS;

        return $this->result($ok, $ok ? null : 'no_text_layer', $normalized, $statuses, $summary, [], $warnings);
    }

    /** Struktur hasil standar. */
    private function result(bool $ok, ?string $error, string $text, array $statuses, array $summary, array $extraWarnings, array $warnings): array
    {
        $warnings = array_values(array_unique(array_merge($warnings, $extraWarnings)));
        if (! $ok && $warnings === []) {
            $warnings[] = 'Tidak ada teks yang dapat dibaca dari dokumen ini.';
        }

        return [
            'ok'          => $ok,
            'error'       => $error,
            'text'        => $text,
            'pages'       => $statuses,
            'summary'     => $summary,
            'warnings'    => $warnings,
            'preview'     => mb_substr($text, 0, self::MIN_PREVIEW_CHARS),
            'duration_ms' => (int) ((microtime(true) - $this->start) * 1000),
        ];
    }

    // ==================== Tahap 1: parser utama ====================

    /** @return list<array{page:int,status:string,chars:int,words:int,method:string,text:string}> */
    private function extractWithSmalot(string $path, ?string $password): array
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            return [];
        }

        try {
            $doc = (new \Smalot\PdfParser\Parser())->parseFile($path);
        } catch (\Throwable $e) {
            log_message('warning', 'AdaptivePdfReader: parser utama gagal membuka PDF — {msg}', ['msg' => mb_substr($e->getMessage(), 0, 200)]);

            return [];
        }

        // PDF terenkripsi dengan password user: Smalot melempar exception di atas,
        // tapi sebagian kasus menghasilkan dokumen kosong — cek cepat.
        try {
            $pages = $doc->getPages();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        $n   = 0;
        foreach ($pages as $page) {
            $n++;
            if ($this->overBudget()) {
                $this->timeExceeded = true;
                break;
            }
            try {
                $text = trim((string) $page->getText());
            } catch (\Throwable $e) {
                $text = '';
            }
            $out[] = $this->pageRow($n, $text, 'smalot');
        }

        return $out;
    }

    // ==================== Tahap 3: OCR fallback ====================

    /**
     * OCR hanya halaman tanpa teks, dibatasi jumlah halaman & anggaran waktu.
     *
     * @param list<array{page:int,status:string,chars:int,words:int,method:string,text:string}> $pages
     * @param array{available:bool, reason:?string} $ocrInfo
     * @param list<string> $methods
     * @param list<string> $warnings
     */
    private function applyOcr(array $pages, string $path, array $ocrInfo, array &$methods, array &$warnings): array
    {
        $emptyIdx = [];
        foreach ($pages as $i => $p) {
            if ($p['chars'] === 0) {
                $emptyIdx[] = $i;
            }
        }

        if ($emptyIdx === []) {
            return $pages;
        }

        if (! $ocrInfo['available']) {
            // Jujur: halaman kosong kemungkinan gambar/scan, OCR tidak tersedia.
            $reason = match ($ocrInfo['reason']) {
                'disabled'         => 'OCR dimatikan oleh konfigurasi server (quiz.ocr=off).',
                'tesseract_missing'=> 'Tesseract OCR tidak terpasang di server.',
                default            => 'Renderer halaman PDF (Imagick/pdftoppm/Ghostscript) tidak tersedia di server.',
            };
            $warnings[] = count($emptyIdx) . ' halaman tanpa teks (kemungkinan gambar/halaman scan). ' . $reason;

            return $pages;
        }

        $targets = array_slice($emptyIdx, 0, $this->ocr->maxPages());
        if (count($emptyIdx) > count($targets)) {
            $warnings[] = 'Halaman tanpa teks melebihi batas OCR (' . $this->ocr->maxPages() . ' halaman); sisanya dilewati.';
        }

        $ocrOk = 0;
        foreach ($targets as $i) {
            if ($this->overBudget()) {
                $warnings[] = 'OCR dihentikan karena batas waktu ekstraksi.';
                break;
            }
            try {
                $res = $this->ocr->ocrPage($path, (int) $pages[$i]['page']);
            } catch (\Throwable $e) {
                log_message('warning', 'AdaptivePdfReader: OCR halaman {page} gagal — {msg}', ['page' => $pages[$i]['page'], 'msg' => mb_substr($e->getMessage(), 0, 200)]);
                $res = ['ok' => false, 'text' => '', 'error' => 'ocr_failed'];
            }
            if ($res['ok'] && trim($res['text']) !== '') {
                $pages[$i]['text']   = trim($res['text']);
                $pages[$i]['method'] = 'ocr';
                $ocrOk++;
                $pages[$i] = $this->recheck($pages[$i]);
            }
        }

        if ($ocrOk > 0) {
            $methods[] = 'ocr';
        } elseif ($targets !== []) {
            $warnings[] = 'OCR tersedia tetapi tidak menghasilkan teks untuk halaman yang tidak terbaca.';
        }

        return $pages;
    }

    // ==================== Tahap 2: parser alternatif ====================

    private function documentPoor(array $pages, int $chars): bool
    {
        if ($pages === [] || $chars < self::MIN_DOC_CHARS) {
            return true;
        }
        $bad = 0;
        foreach ($pages as $p) {
            if (in_array($p['status'], ['failed', 'needs_ocr'], true)) {
                $bad++;
            }
        }

        return $bad / count($pages) > 0.5;
    }

    /**
     * Hasil parser alternatif bersifat dokumen-level: status per halaman
     * tidak tersedia, teks dibagi baris form-feed bila ada, else satu blok.
     *
     * @return list<array{page:int,status:string,chars:int,words:int,method:string,text:string}>
     */
    private function pagesFromDocumentText(string $text): array
    {
        $chunks = str_contains($text, "\f") ? explode("\f", $text) : [$text];
        $rows   = [];
        $n      = 0;
        foreach ($chunks as $chunk) {
            $n++;
            $rows[] = $this->pageRow($n, trim($chunk), 'internal');
        }

        return $rows;
    }

    // ==================== Tahap 4: kualitas per halaman ====================

    /** Baris analisis satu halaman (status dihitung dari metrik teks). */
    private function pageRow(int $num, string $text, string $method): array
    {
        $row = [
            'page'   => $num,
            'text'   => $text,
            'method' => $method,
            'chars'  => 0,
            'words'  => 0,
            'status' => 'empty',
        ];

        return $this->recheck($row);
    }

    /** Hitung ulang metrik & status halaman dari teks terkini (setelah OCR dsb). */
    private function recheck(array $row): array
    {
        $text  = $row['text'];
        $chars = mb_strlen(preg_replace('/\s/u', '', $text) ?? $text);
        $words = self::countWords($text);

        $row['chars'] = $chars;
        $row['words'] = $words;

        // Rasio karakter mencurigakan: PUA (font CID tanpa ToUnicode), replacement,
        // karakter kontrol yang lolos.
        $len = max(1, mb_strlen($text));
        $bad = preg_match_all('/[\x{E000}-\x{F8FF}\x{FFFD}\x{0001}-\x{001F}]/u', $text) ?? 0;
        $ratio = $bad / $len;

            $row['status'] = match (true) {
                $chars === 0                       => 'empty',
                $ratio >= 0.30 || ($chars >= 100 && $words === 0) => 'failed',
                $ratio >= 0.08                     => 'needs_ocr',
                $chars < 50                        => 'warning',
                $ratio >= 0.02                     => 'warning',
                default                            => 'ok',
            };

            return $row;
        }

    private static function countWords(string $text): int
    {
        preg_match_all('/\p{L}[\p{L}\p{N}\x27\x2D]*/u', $text, $m);

        return count($m[0] ?? []);
    }

    // ==================== Ringkasan & peringatan ====================

    /**
     * @param list<array{page:int,status:string,chars:int,words:int,method:string,text:string}> $pages
     * @return array{0: list<array{page:int,status:string,chars:int,words:int,method:string}>, 1: array}
     */
    private function summarize(array $pages, array $methods, bool $ocrAvailable): array
    {
        $statusRows = [];
        $count = ['ok' => 0, 'warning' => 0, 'empty' => 0, 'needs_ocr' => 0, 'failed' => 0];
        $chars = 0;
        $words = 0;

        foreach ($pages as $p) {
            $status = $p['status'] ?? 'empty';
            $count[$status] = ($count[$status] ?? 0) + 1;
            if ($status !== 'failed') {
                $chars += $p['chars'];
                $words += $p['words'];
            }
            $statusRows[] = [
                'page'   => $p['page'],
                'status' => $status,
                'chars'  => $p['chars'],
                'words'  => $p['words'],
                'method' => $p['method'],
            ];
        }

        $summary = [
            'pages_total'    => count($pages),
            'pages_ok'       => $count['ok'],
            'pages_warning'  => $count['warning'],
            'pages_empty'    => $count['empty'],
            'pages_ocr'      => 0,
            'pages_failed'   => $count['failed'] + $count['needs_ocr'],
            'chars'          => $chars,
            'words'          => $words,
            'methods'        => array_values(array_unique($methods)),
            'ocr_available'  => $ocrAvailable,
            'can_generate'   => false,
        ];

        foreach ($statusRows as $row) {
            if ($row['method'] === 'ocr' && in_array($row['status'], ['ok', 'warning'], true)) {
                $summary['pages_ocr']++;
            }
        }

        return [$statusRows, $summary];
    }

    /** @param list<array{page:int,status:string,chars:int,words:int,method:string,text:string}> $pages */
    private function buildWarnings(array $pages, array $summary, array $ocrInfo): array
    {
        $warnings = [];

        if ($summary['pages_failed'] > 0) {
            $badPages = [];
            foreach ($pages as $p) {
                if (in_array($p['status'], ['failed', 'needs_ocr'], true)) {
                    $badPages[] = $p['page'];
                }
            }
            $warnings[] = 'Beberapa halaman tidak terbaca baik: halaman ' . implode(', ', array_slice($badPages, 0, 15)) . (count($badPages) > 15 ? '…' : '') . '.';
        }

        if ($summary['pages_warning'] > 0) {
            $warnings[] = $summary['pages_warning'] . ' halaman terbaca dengan peringatan (teks tipis / sebagian karakter tidak terbaca).';
        }

        if ($summary['pages_empty'] > 0 && $summary['pages_ocr'] === 0 && ! $ocrInfo['available']) {
            // Sudah dijelaskan di applyOcr; tambah konteks dokumen scan.
        }

        if ($summary['pages_total'] > 0 && $summary['pages_ok'] === 0 && $summary['pages_warning'] === 0) {
            $warnings[] = 'Tidak ada halaman yang terbaca baik — dokumen kemungkinan hasil scan atau terlalu rusak untuk dibaca.';
        }

        if ($this->timeExceeded) {
            $warnings[] = 'Ekstraksi dihentikan karena batas waktu; sebagian halaman mungkin tidak dianalisis.';
        }

        return $warnings;
    }

    private function overBudget(): bool
    {
        return (microtime(true) - $this->start) > self::TIME_BUDGET_SEC * 3;
    }
}
