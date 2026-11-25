<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Libraries\Quiz\MalwareScanner;
use App\Libraries\Quiz\QuestionGenerationService;
use App\Models\Quiz\QuizBatchModel;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;

class QuizGenerate extends BaseController
{
    private const MAX_BYTES = 10485760; // 10 MB

    /** Anggaran waktu eksekusi satu proses generate (di atas anggaran internal service). */
    private const TIME_LIMIT_SEC = 600;

    /** Batch "processing" yang menggantung selama ini tanpa progres dianggap prosesnya sudah mati. */
    private const STALE_SECONDS = 900;

    private const SERVER_ERROR_MESSAGE = 'Proses generate gagal di server. Tidak ada soal yang disimpan. Silakan coba lagi; bila berulang, hubungi administrator.';

    private QuizModel $quizModel;
    private QuizBatchModel $batchModel;

    public function __construct()
    {
        $this->quizModel  = new QuizModel();
        $this->batchModel = new QuizBatchModel();
    }

    private function lid(): int
    {
        return (int) session('user_id');
    }

    private function ownedQuizOrFail(int $quizId): array
    {
        $quiz = $this->quizModel->findOwned($quizId, $this->lid());
        if ($quiz === null) {
            throw new \CodeIgniter\HTTP\Exceptions\PageNotFoundException();
        }

        return $quiz;
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    /**
     * Generate soal AI dari satu PDF dalam SATU request: validasi upload,
     * kapasitas, dan batch aktif, buat batch, lalu proses sampai selesai
     * (atau gagal — tanpa antrean, tanpa percobaan ulang otomatis).
     *
     * - AJAX   : hasil akhir dikembalikan sebagai JSON; frontend memantau
     *            progres lewat polling `generateStatus` selama request berjalan.
     * - Tanpa JS: proses sinkron lalu redirect dengan pesan flash.
     */
    public function generate(int $quizId)
    {
        $quiz   = $this->ownedQuizOrFail($quizId);
        $isAjax = $this->request->isAJAX();

        // Batch menggantung (proses mati / "queued" warisan versi lama) tidak
        // boleh memblokir generate baru — gagalkan lebih dulu.
        $this->batchModel->failStaleProcessing($quizId, self::STALE_SECONDS);

        $created = $this->createBatch((int) $quiz['id']);
        if (! $created['ok']) {
            return $isAjax
                ? $this->json(['ok' => false, 'error' => $created['error'], 'message' => $created['message']], 422)
                : redirect()->to('/lecturer/quiz/' . $quizId)->with('error', $created['message']);
        }

        $batchId = (int) $created['batch_id'];

        if ($isAjax) {
            // Proses bisa berjalan lama: lepaskan kunci session agar request lain
            // dari pengguna yang sama (polling status, navigasi) tidak ikut
            // menggantung, dan biarkan PHP menyelesaikan proses walau koneksi
            // browser terputus.
            session()->close();
        }

        ignore_user_abort(true);
        $this->raiseTimeLimit();

        $result = $this->runGuarded($batchId);

        if ($isAjax) {
            return $this->json(['ok' => $result['ok']] + $this->batchPayload($this->batchModel->find($batchId)));
        }

        return redirect()->to('/lecturer/quiz/' . $quizId)->with(
            $result['ok'] ? ($result['status'] === 'completed' ? 'success' : 'warning') : 'error',
            $result['message']
        );
    }

    /**
     * Status batch TERBARU kuis ini — dipakai frontend untuk memantau progres
     * generate yang sedang berjalan (payload aman: tanpa provider/model/path).
     */
    public function generateStatus(int $quizId)
    {
        $this->ownedQuizOrFail($quizId);

        $this->batchModel->failStaleProcessing($quizId, self::STALE_SECONDS);

        $latest = $this->batchModel->latestForQuiz($quizId);

        return $this->json(['ok' => true, 'batch' => $latest !== null ? $this->batchPayload($latest) : null]);
    }

    /** Status satu batch untuk polling AJAX dosen (tanpa provider/model/path internal). */
    public function batchStatus(int $batchId)
    {
        $batch = $this->batchModel->findOwned($batchId, $this->lid());
        if ($batch === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'not_found']);
        }

        return $this->json(['ok' => $batch['status'] !== 'failed'] + $this->batchPayload($batch));
    }

    // ======================== Internals ========================

    /**
     * Validasi lengkap request generate dan buat batch siap diproses (PDF
     * tersimpan, lolos pemeriksaan keamanan, status "processing"). Validasi
     * kapasitas & batch aktif dilakukan di dalam transaksi dengan lock baris
     * kuis agar aman terhadap request bersamaan.
     *
     * @return array{ok:bool, batch_id?:int, error?:string, message?:string}
     */
    private function createBatch(int $quizId): array
    {
        // Jumlah soal: HANYA 5/10/15/20 — nilai lain ditolak walau request dimanipulasi.
        $count = (int) $this->request->getPost('question_count');
        if (! in_array($count, QuestionGenerationService::ALLOWED_COUNTS, true)) {
            return ['ok' => false, 'error' => 'invalid_count', 'message' => 'Jumlah soal hanya boleh 5, 10, 15, atau 20 soal untuk satu kali generate.'];
        }

        $file = $this->request->getFile('pdf_file');
        if ($file === null || ! $file->isValid()) {
            return ['ok' => false, 'error' => 'invalid_file', 'message' => 'File PDF tidak valid.'];
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'too_large', 'message' => 'Ukuran PDF maksimum 10 MB.'];
        }
        if ($file->getSize() === 0) {
            return ['ok' => false, 'error' => 'empty_file', 'message' => 'File kosong.'];
        }

        // MIME/content sebenarnya, bukan nama file
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName());
        if ($mime !== 'application/pdf') {
            return ['ok' => false, 'error' => 'not_pdf', 'message' => 'File harus berupa PDF valid.'];
        }
        if (substr((string) file_get_contents($file->getTempName(), false, null, 0, 5), 0, 5) !== '%PDF-') {
            return ['ok' => false, 'error' => 'not_pdf', 'message' => 'File harus berupa PDF valid.'];
        }

        $easy   = (int) $this->request->getPost('difficulty_easy_pct');
        $medium = (int) $this->request->getPost('difficulty_medium_pct');
        $hard   = (int) $this->request->getPost('difficulty_hard_pct');

        // Nilai kosong -> default 30/50/20
        $easy   = ($easy > 0 && $easy <= 100) ? $easy : 30;
        $medium = ($medium > 0 && $medium <= 100) ? $medium : 50;
        $hard   = ($hard > 0 && $hard <= 100) ? $hard : 20;

        if ($easy + $medium + $hard !== 100) {
            return ['ok' => false, 'error' => 'invalid_composition', 'message' => 'Komposisi tingkat kesulitan wajib berjumlah tepat 100%.'];
        }

        // Simpan PDF di direktori private (bukan public)
        $dir = WRITEPATH . 'quiz_tmp';
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }

        $sha256  = hash_file('sha256', $file->getTempName());
        $pdfPath = $dir . '/pdf_' . bin2hex(random_bytes(8)) . '.pdf';
        $file->move($dir, basename($pdfPath));

        $now      = date('Y-m-d H:i:s');
        $db       = db_connect();
        $rejected = null;
        $batchId  = 0;

        $db->transBegin();
        // Kunci baris kuis agar pengecekan kapasitas & batch aktif aman terhadap request bersamaan.
        $db->query('SELECT id FROM quiz_quizzes WHERE id = ? FOR UPDATE', [$quizId]);
        $existing = (int) (new QuizQuestionModel())->where('quiz_id', $quizId)->where('is_active', 1)->countAllResults();

        if ($existing >= QuestionGenerationService::MAX_QUESTIONS_PER_QUIZ) {
            $rejected = $this->limitReachedMessage();
        } elseif ($existing + $count > QuestionGenerationService::MAX_QUESTIONS_PER_QUIZ) {
            $rejected = $this->limitExceededMessage($existing);
        } elseif (($active = $this->batchModel->activeForQuiz($quizId)) !== null) {
            $rejected = $this->inFlightMessage($active);
        } else {
            $batchId = (int) $this->batchModel->insert([
                'quiz_id'              => $quizId,
                'lecturer_id'          => $this->lid(),
                'source_type'          => 'ai_pdf',
                'original_filename'    => mb_substr($file->getClientName(), 0, 255),
                'file_size_bytes'      => (int) filesize($pdfPath),
                'file_sha256'          => $sha256,
                'source_title'         => trim((string) $this->request->getPost('source_title')) ?: null,
                'requested_count'      => $count,
                'difficulty_easy_pct'  => $easy,
                'difficulty_medium_pct'=> $medium,
                'difficulty_hard_pct'  => $hard,
                'status'               => 'quarantine', // karantina sampai scan bersih
                'pdf_path'             => $pdfPath,
                'uploaded_at'          => $now,
                'expires_at'           => date('Y-m-d H:i:s', strtotime($now . ' +7 days')),
                'created_at'           => $now,
                'updated_at'           => $now,
            ], true);
        }

        if ($rejected === null && $batchId > 0) {
            $db->transCommit();
        } else {
            $db->transRollback();
            @unlink($pdfPath);

            return ['ok' => false, 'error' => 'rejected', 'message' => $rejected ?? 'Gagal menyimpan batch. Silakan coba lagi.'];
        }

        // ===== Pemeriksaan malware WAJIB sebelum ekstraksi/AI =====
        $scan = (new MalwareScanner())->scan($pdfPath);

        $this->batchModel->update($batchId, [
            'scan_status' => $scan['status'],
            'scan_result' => $scan['result'],
            'scan_at'     => date('Y-m-d H:i:s'),
            'scan_engine' => $scan['engine'],
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        if ($scan['status'] !== 'clean') {
            @unlink($pdfPath);
            $this->batchModel->update($batchId, [
                'status'         => 'failed',
                'pdf_path'       => null,
                'status_message' => $scan['status'] === 'infected'
                    ? 'File ditolak karena gagal pemeriksaan keamanan.'
                    : 'File ditolak karena keamanannya tidak dapat dipastikan (pemindai tidak tersedia/gagal). Hubungi administrator.',
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => false, 'error' => 'scan_' . $scan['status'],
                'message' => $scan['status'] === 'infected'
                    ? 'File ditolak karena gagal pemeriksaan keamanan.'
                    : 'File ditolak karena keamanannya tidak dapat dipastikan (pemindai tidak tersedia/gagal). Hubungi administrator.',
            ];
        }

        // Langsung diproses di request yang sama — tidak ada antrean.
        $this->batchModel->update($batchId, [
            'status'         => 'processing',
            'status_message' => 'Menyiapkan materi dari PDF…',
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'batch_id' => $batchId];
    }

    /**
     * Jalankan service generate dengan pengaman: exception dicatat (tanpa
     * detail internal ke dosen), dan shutdown guard menandai batch gagal bila
     * skrip mati di tengah proses (mis. batas waktu eksekusi server) — gagal
     * berarti gagal, tidak ada antrean lanjutan.
     *
     * @return array{ok:bool, status:string, message:string}
     */
    private function runGuarded(int $batchId): array
    {
        $finished = false;
        $db       = db_connect();

        register_shutdown_function(static function () use ($batchId, $db, &$finished): void {
            if ($finished) {
                return;
            }

            try {
                $db->transRollback(); // no-op bila tidak ada transaksi terbuka
                (new QuizBatchModel())->markInterrupted($batchId);
            } catch (\Throwable $e) {
                log_message('error', 'QuizGenerate: batch #{batch} terhenti dan gagal ditandai: {msg}', ['batch' => $batchId, 'msg' => $e->getMessage()]);
            }
        });

        try {
            $result = (new QuestionGenerationService())->run($this->batchModel->find($batchId));
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'QuizGenerate: batch #{batch} gagal: {msg}', ['batch' => $batchId, 'msg' => $e->getMessage()]);

            $this->batchModel->update($batchId, [
                'status'          => 'failed',
                'generated_count' => 0,
                'status_message'  => self::SERVER_ERROR_MESSAGE,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $result = ['ok' => false, 'status' => 'failed', 'message' => self::SERVER_ERROR_MESSAGE];
        }

        $finished = true;

        return $result;
    }

    /** Data status yang aman untuk frontend dosen. */
    private function batchPayload(array $batch): array
    {
        $status = (string) $batch['status'];

        return [
            'batch_id'   => (int) $batch['id'],
            'id'         => (int) $batch['id'],
            'status'     => $status,
            'final'      => in_array($status, QuizBatchModel::FINAL_STATUSES, true),
            'requested'  => (int) $batch['requested_count'],
            'generated'  => (int) $batch['generated_count'],
            'duplicates' => (int) $batch['duplicate_count'],
            'message'    => (string) ($batch['status_message'] ?? ''),
        ];
    }

    private function raiseTimeLimit(): void
    {
        if (! function_exists('set_time_limit')) {
            return;
        }

        $current = (int) ini_get('max_execution_time');

        // 0 = tanpa batas (jangan diturunkan); batas yang sudah cukup besar tidak diubah.
        if ($current !== 0 && $current < self::TIME_LIMIT_SEC) {
            @set_time_limit(self::TIME_LIMIT_SEC);
        }
    }

    private function limitReachedMessage(): string
    {
        return 'Jumlah soal dalam Kuis ini sudah mencapai batas maksimal 100 soal. Penambahan soal dibatalkan. Silakan hapus beberapa soal terlebih dahulu jika ingin menambahkan soal baru.';
    }

    private function limitExceededMessage(int $existing): string
    {
        $remaining = QuestionGenerationService::MAX_QUESTIONS_PER_QUIZ - $existing;

        return "Total soal akan melebihi batas maksimal 100 soal (saat ini {$existing} soal). Sisa kapasitas maksimal {$remaining} soal. Silakan sesuaikan jumlah soal yang diminta.";
    }

    private function inFlightMessage(array $active): string
    {
        return 'Masih ada proses generate yang sedang berjalan untuk kuis ini (batch #' . (int) $active['id'] . '). Tunggu sampai selesai sebelum memulai generate baru.';
    }
}
