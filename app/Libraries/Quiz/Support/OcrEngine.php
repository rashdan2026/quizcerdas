<?php

namespace App\Libraries\Quiz\Support;

/**
 * OCR engine dengan deteksi runtime (shared-hosting friendly).
 *
 * Rantai deteksi (semua opsional, tidak ada yang wajib):
 * - Renderer halaman: ext-imagick  ->  pdftoppm (Poppler)  ->  gs (Ghostscript)
 * - OCR teks        : tesseract (binary; path via env quiz.ocrTesseract atau PATH)
 *
 * Bila salah satu tidak tersedia, engine melaporkan dirinya TIDAK tersedia
 * dan pemanggil menampilkan informasi jujur ke dosen — hasil ekstraksi buruk
 * TIDAK pernah diam-diam dianggap valid.
 *
 * Privasi: tidak ada layanan OCR eksternal. Dokumen tidak pernah keluar dari
 * server. OCR eksternal hanya bila administrator mengonfigurasikannya sendiri
 * (di luar kelas ini) — default TIDAK AKTIF.
 *
 * Batas resource (tanpa akses root):
 * - DPI render 150 (cukup untuk teks, hemat memori).
 * - Batas halaman OCR per dokumen via env quiz.ocrMaxPages (default 10).
 * - Pemanggil melakukan cek waktu antar halaman (kelas ini tidak memanggil
 *   set_time_limit, sesuai konvensi PdfTextExtractor).
 */
final class OcrEngine
{
    private const RENDER_DPI = 150;

    /** Path tesseract terdeteksi (cache per-request). */
    private ?string $tesseract = null;

    /** Renderer terdeteksi: 'imagick'|'pdftoppm'|'gs'|null. */
    private ?string $renderer = null;

    private ?string $pdftoppm = null;
    private ?string $gs       = null;

    private bool $probed = false;

    private int $maxPages;

    public function __construct()
    {
        $this->maxPages = max(1, (int) ((function_exists('env') ? env('quiz.ocrMaxPages') : getenv('quiz.ocrMaxPages')) ?? 10));
    }

    public function maxPages(): int
    {
        return $this->maxPages;
    }

    /**
     * OCR layak dipakai di server ini?
     * @return array{available:bool, reason:?string}
     */
    public function availability(): array
    {
        if ((function_exists('env') ? env('quiz.ocr') : getenv('quiz.ocr')) === 'off') {
            return ['available' => false, 'reason' => 'disabled'];
        }

        $this->probe();

        if ($this->tesseract === null) {
            return ['available' => false, 'reason' => 'tesseract_missing'];
        }
        if ($this->renderer === null) {
            return ['available' => false, 'reason' => 'renderer_missing'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * OCR satu halaman PDF.
     *
     * @return array{ok:bool, text:string, error:?string}
     */
    public function ocrPage(string $pdfPath, int $page): array
    {
        $this->probe();
        if ($this->tesseract === null || $this->renderer === null) {
            return ['ok' => false, 'text' => '', 'error' => 'ocr_unavailable'];
        }

        $workDir = $this->workDir();
        if ($workDir === null) {
            return ['ok' => false, 'text' => '', 'error' => 'ocr_unavailable'];
        }

        // 1) Render halaman ke PNG.
        $image = $workDir . '/ocr_' . bin2hex(random_bytes(6)) . '.png';
        $render = $this->renderPage($pdfPath, $page, $image);
        if (! $render['ok']) {
            $this->cleanup([$image]);

            return ['ok' => false, 'text' => '', 'error' => $render['error'] ?? 'render_failed'];
        }

        // 2) Tesseract: output langsung ke file teks (hindari stdout encoding).
        $txtOut = $image . '.txt';
        $cmd    = escapeshellarg($this->tesseract) . ' ' . escapeshellarg($image) . ' ' . escapeshellarg(substr($txtOut, 0, -4)) . ' 2>&1';
        $out    = @shell_exec($cmd);
        $text   = is_file($txtOut) ? (string) file_get_contents($txtOut) : '';
        $produced = is_file($txtOut);

        $this->cleanup([$image, $txtOut]);

        if (! $produced) {
            log_message('warning', 'OcrEngine: tesseract halaman {page} tidak menghasilkan teks ({out})', ['page' => $page, 'out' => mb_substr((string) $out, 0, 200)]);

            return ['ok' => false, 'text' => '', 'error' => 'ocr_failed'];
        }

        return ['ok' => true, 'text' => trim($text), 'error' => null];
    }

    // ==================== Deteksi ====================

    private function probe(): void
    {
        if ($this->probed) {
            return;
        }
        $this->probed = true;

        if (! function_exists('exec') && ! function_exists('shell_exec')) {
            return;
        }

        $this->tesseract = $this->findBinary([
            (string) ((function_exists('env') ? env('quiz.ocrTesseract') : getenv('quiz.ocrTesseract')) ?? ''),
            'tesseract',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
        ]);
        if ($this->tesseract !== null && ! $this->binaryWorks($this->tesseract, 'tesseract')) {
            $this->tesseract = null;
        }

        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $this->renderer = 'imagick';

            return;
        }

        $this->pdftoppm = $this->findBinary(['pdftoppm', '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm']);
        if ($this->pdftoppm !== null && $this->binaryWorks($this->pdftoppm, '-v')) {
            $this->renderer = 'pdftoppm';

            return;
        }

        $this->gs = $this->findBinary(['gs', 'gswin64c', 'gswin32c', '/usr/bin/gs', '/usr/local/bin/gs']);
        if ($this->gs !== null && $this->binaryWorks($this->gs, '-v')) {
            $this->renderer = 'gs';
        }
    }

    /** @param list<string> $candidates */
    private function findBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            // Path absolut: cek keberadaan file. Nama pendek: cek lewat `where`/PATH.
            if (str_contains($candidate, '\\') || str_contains($candidate, '/')) {
                if (is_file($candidate)) {
                    return $candidate;
                }
                continue;
            }
            $which = @shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where ' . escapeshellarg($candidate) . ' 2>NUL' : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null');
            $line  = strtok(trim((string) $which), "\n");
            if ($line !== false && $line !== '' && @is_executable($line)) {
                return $line;
            }
        }

        return null;
    }

    private function binaryWorks(string $binary, string $arg): bool
    {
        $out = @shell_exec(escapeshellarg($binary) . ' ' . escapeshellarg($arg) . ' 2>&1');

        return is_string($out) && $out !== '';
    }

    /** @return array{ok:bool, error:?string} */
    private function renderPage(string $pdfPath, int $page, string $imagePath): array
    {
        try {
            if ($this->renderer === 'imagick') {
                $imagick = new \Imagick();
                $imagick->setResolution(self::RENDER_DPI, self::RENDER_DPI);
                $imagick->readImage($pdfPath . '[' . ($page - 1) . ']');
                $imagick->setImageFormat('png');
                // Batas ukuran gambar (hemat memori & tesseract).
                if ($imagick->getImageWidth() > 2480) {
                    $imagick->scaleImage(2480, 0);
                }
                $imagick->writeImage($imagePath);
                $imagick->clear();

                return is_file($imagePath) ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'render_failed'];
            }

            if ($this->renderer === 'pdftoppm') {
                $prefix = substr($imagePath, 0, -4);
                @shell_exec(escapeshellarg($this->pdftoppm) . ' -f ' . (int) $page . ' -l ' . (int) $page
                    . ' -r ' . self::RENDER_DPI . ' -png ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix) . ' 2>&1');
                // pdftoppm menambahkan suffix nomor halaman (mis. prefix-5.png).
                $glob = glob($prefix . '*.png');
                if ($glob !== false && $glob !== []) {
                    rename($glob[0], $imagePath);
                    $this->cleanup([$glob[0]]);

                    return ['ok' => true, 'error' => null];
                }

                return ['ok' => false, 'error' => 'render_failed'];
            }

            if ($this->renderer === 'gs') {
                @shell_exec(escapeshellarg($this->gs) . ' -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r' . self::RENDER_DPI
                    . ' -dFirstPage=' . (int) $page . ' -dLastPage=' . (int) $page
                    . ' -sOutputFile=' . escapeshellarg($imagePath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1');

                return is_file($imagePath) ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'render_failed'];
            }
        } catch (\Throwable $e) {
            log_message('warning', 'OcrEngine: render halaman {page} gagal — {msg}', ['page' => $page, 'msg' => $e->getMessage()]);
        }

        return ['ok' => false, 'error' => 'renderer_missing'];
    }

    private function workDir(): ?string
    {
        $dir = WRITEPATH . 'quiz_tmp';
        if (! is_dir($dir) && ! @mkdir($dir, 0770, true) && ! is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    /** @param list<string> $paths */
    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
