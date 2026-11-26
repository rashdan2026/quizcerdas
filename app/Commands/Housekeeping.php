<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Housekeeping writable/ agar tidak tumbuh tanpa batas di shared hosting.
 *
 * Cron (jalankan sekali sehari, mis. pukul 03:00):
 *   0 3 * * * php /path/to/spark app:housekeeping
 *
 * Yang dibersihkan (semua idempotent dan aman untuk data aplikasi):
 * - writable/logs        : file log CI lebih tua dari N hari (default 14).
 * - writable/debugbar    : file debug toolbar lama (hanya relevan saat development).
 * - writable/exports     : file export nilai lebih tua dari N hari (default 7).
 * - writable/quiz_tmp    : PDF & teks ekstraksi batch quiz yang expired
 *                          (logika sama dengan quiz:cleanup-temp).
 * - writable/session     : file session CI ('ci_session*') tidak tersentuh
 *                          lebih dari 2x masa kedaluwarsa session.
 * - writable/cache       : file cache file-handler berumur lebih dari TTL.
 *
 * Tidak menyentuh: uploads (dokumen aplikasi), quiz_secret.key, data database.
 */
class Housekeeping extends BaseCommand
{
    /** Batas umur file log (hari). */
    private const LOG_MAX_DAYS = 14;

    /** Batas umur file export (hari). */
    private const EXPORT_MAX_DAYS = 7;

    /** Batas umur file cache (jam). */
    private const CACHE_MAX_HOURS = 48;

    private const SESSION_PREFIX = 'ci_session';

    protected $group       = 'App';
    protected $name        = 'app:housekeeping';
    protected $description = 'Bersihkan file lama di writable/ (log, export, debugbar, cache, session, quiz_tmp).';
    protected $usage       = 'app:housekeeping';

    public function run(array $params)
    {
        $report = [
            'logs'      => $this->pruneDir(WRITEPATH . 'logs', self::LOG_MAX_DAYS * 86400),
            'debugbar'  => $this->pruneDir(WRITEPATH . 'debugbar', self::LOG_MAX_DAYS * 86400),
            'exports'   => $this->pruneDir(WRITEPATH . 'exports', self::EXPORT_MAX_DAYS * 86400),
            'cache'     => $this->pruneDir(WRITEPATH . 'cache', self::CACHE_MAX_HOURS * 3600),
            'session'   => $this->pruneSessions(),
            'quiz_tmp'  => $this->pruneQuizTemp(),
        ];

        foreach ($report as $label => $count) {
            CLI::write(str_pad($label, 10) . ': ' . $count . ' file dihapus', 'green');
        }

        return EXIT_SUCCESS;
    }

    /** Hapus semua file biasa dalam direktori yang lebih tua dari $maxAge detik. */
    private function pruneDir(string $dir, int $maxAge): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        $cutoff  = time() - $maxAge;

        foreach (glob($dir . '/*') ?: [] as $path) {
            if (! is_file($path) || basename($path) === 'index.html' || basename($path) === '.htaccess') {
                continue;
            }
            if (filemtime($path) < $cutoff && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Session CI berbasis file: hapus file 'ci_session*' yang tidak tersentuh
     * lebih dari 2x masa kedaluwarsa (default app 7200 detik). Penghapusan di
     * sini hanya mempercepat GC — session aktif tidak pernah tersentuh karena
     * setiap request menyegarkan mtime file session.
     */
    private function pruneSessions(): int
    {
        $dir = WRITEPATH . 'session';
        if (! is_dir($dir)) {
            return 0;
        }

        $expiration = (int) (config('Session')->expiration ?? 7200);
        $cutoff     = time() - max(7200, $expiration * 2);
        $removed    = 0;

        foreach (glob($dir . '/' . self::SESSION_PREFIX . '*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $cutoff && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /** PDF & teks ekstraksi batch quiz yang sudah expired (>= expires_at). */
    private function pruneQuizTemp(): int
    {
        $db   = db_connect();
        $rows = $db->table('quiz_question_batches')
            ->select('id, pdf_path, extract_path')
            ->where('expires_at <=', date('Y-m-d H:i:s'))
            ->groupStart()
            ->where('pdf_path !=', null)
            ->orWhere('extract_path !=', null)
            ->groupEnd()
            ->limit(200)
            ->get()
            ->getResultArray();

        $removed = 0;
        foreach ($rows as $b) {
            foreach (['pdf_path', 'extract_path'] as $field) {
                $path = $b[$field];
                if (empty($path)) {
                    continue;
                }

                if (! is_file($path)) {
                    // File sudah tidak ada: cukup kosongkan referensi (idempotent).
                    $db->table('quiz_question_batches')->where('id', $b['id'])->update([$field => null, 'updated_at' => date('Y-m-d H:i:s')]);
                    continue;
                }

                if (@unlink($path)) {
                    $removed++;
                    $db->table('quiz_question_batches')->where('id', $b['id'])->update([$field => null, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    log_message('error', 'Housekeeping: gagal menghapus {path} (batch #{id}); dicoba ulang siklus berikutnya.', ['path' => $path, 'id' => $b['id']]);
                }
            }
        }

        return $removed;
    }
}
