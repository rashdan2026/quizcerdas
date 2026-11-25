<?php

namespace App\Commands;

use App\Models\Quiz\QuizBatchModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cleanup PDF & teks ekstraksi sementara berumur > 7 hari.
 *
 * Cron (contoh, sekali sehari):
 *   0 3 * * * php /path/to/spark quiz:cleanup-temp
 *
 * Aturan (SDD Bagian 10):
 * - Idempotent: aman bila file sudah tidak ada; error dicatat, metadata soal
 *   TIDAK dihapus; dapat dicoba lagi pada siklus berikutnya.
 * - Penghapusan file tidak menghapus soal/batch/attempt/jawaban.
 */
class QuizCleanupTemporaryFiles extends BaseCommand
{
    protected $group       = 'Quiz';
    protected $name        = 'quiz:cleanup-temp';
    protected $description = 'Hapus PDF & teks ekstraksi sementara quiz yang berumur lebih dari 7 hari.';
    protected $usage       = 'quiz:cleanup-temp';

    public function run(array $params)
    {
        $batches = new QuizBatchModel();

        $rows = $batches->where('expires_at <=', date('Y-m-d H:i:s'))
            ->groupStart()
            ->where('pdf_path !=', null)
            ->orWhere('extract_path !=', null)
            ->groupEnd()
            ->findAll();

        $removed = 0;
        $errors  = 0;

        foreach ($rows as $b) {
            foreach (['pdf_path', 'extract_path'] as $field) {
                $path = $b[$field];
                if (empty($path)) {
                    continue;
                }

                if (! is_file($path)) {
                    // Idempotent: file sudah tidak ada, cukup kosongkan referensi.
                    $batches->update((int) $b['id'], [$field => null, 'updated_at' => date('Y-m-d H:i:s')]);
                    continue;
                }

                if (@unlink($path)) {
                    $removed++;
                    $batches->update((int) $b['id'], [$field => null, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    $errors++;
                    log_message('error', 'QuizCleanup: gagal menghapus ' . basename($path) . ' (batch #' . $b['id'] . '); akan dicoba ulang siklus berikutnya.');
                }
            }
        }

        CLI::write("Batch diproses: " . count($rows), 'green');
        CLI::write("File dihapus: {$removed}", 'green');
        if ($errors > 0) {
            CLI::write("Gagal: {$errors} (dicatat di log, dicoba ulang nanti)", 'red');
        }

        return EXIT_SUCCESS;
    }
}
