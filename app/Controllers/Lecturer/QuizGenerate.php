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

    /** Usia maksimum hasil analisis preview (detik) sebelum harus dianalisis ulang. */
    private const ANALYSIS_TTL_SEC = 86400;

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

        try {
            $created = $this->createBatch((int) $quiz['id']);
        } catch (\Throwable $e) {
            log_message('error', 'QuizGenerate::generate gagal menyiapkan batch — {class}: {msg} @ {file}:{line}', [
                'class' => $e::class, 'msg' => mb_substr($e->getMessage(), 0, 300),
                'file'  => basename($e->getFile()), 'line' => $e->getLine(),
            ]);
            $created = ['ok' => false, 'error' => 'server_error', 'message' => self::SERVER_ERROR_MESSAGE];
        }
        if (! $created['ok']) {
            return $isAjax
                ? $this->json(['ok' => false, 'error' => $created['error'], 'message' => $created['message']], 422)
                : redirect()->to('/lecturer/quiz/' . $quizId)->with('error', $created['message']);
        }

        $batchId     = (int) $created['batch_id'];
        // Instruksi tambahan dosen: hanya hidup di memori selama request generate
        // ini — TIDAK disimpan ke database, session, cache, maupun log.
        $instruction = $created['instruction'] ?? null;

        if ($isAjax) {
            // Proses bisa berjalan lama: lepaskan kunci session agar request lain
            // dari pengguna yang sama (polling status, navigasi) tidak ikut
            // menggantung, dan biarkan PHP menyelesaikan proses walau koneksi
            // browser terputus.
            session()->close();
        }

        ignore_user_abort(true);
        $this->raiseTimeLimit();

        $result = $this->runGuarded($batchId, $instruction);

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
    /** Batas panjang instruksi tambahan dosen (karakter, bukan byte). */
    private const MAX_INSTRUCTION_CHARS = 500;

    private function createBatch(int $quizId): array
    {
        // Jumlah soal: HANYA 5/10/15/20 — nilai lain ditolak walau request dimanipulasi.
        $count = (int) $this->request->getPost('question_count');
        if (! in_array($count, QuestionGenerationService::ALLOWED_COUNTS, true)) {
            return ['ok' => false, 'error' => 'invalid_count', 'message' => 'Jumlah soal hanya boleh 5, 10, 15, atau 20 soal untuk satu kali generate.'];
        }

        // Instruksi tambahan dosen (wajib): validasi backend, tidak mempercayai
        // validasi JavaScript. Batas dihitung per KARAKTER (mb_strlen), bukan byte.
        $instruction = $this->sanitizedInstruction();
        if ($instruction === null) {
            return ['ok' => false, 'error' => 'invalid_instruction', 'message' => 'Instruksi tambahan untuk AI wajib diisi (tidak boleh kosong atau hanya spasi).'];
        }
        if (mb_strlen($instruction) > self::MAX_INSTRUCTION_CHARS) {
            return ['ok' => false, 'error' => 'instruction_too_long', 'message' => 'Instruksi tambahan untuk AI maksimal ' . self::MAX_INSTRUCTION_CHARS . ' karakter.'];
        }

        // Hasil analisis preview (analysis_token) — bila valid, file PDF yang
        // sudah diunggah & dianalisis dipakai ulang (tanpa unggah ulang).
        $analysis    = $this->reuseAnalysis();
        $tokenGiven  = trim((string) $this->request->getPost('analysis_token')) !== '';

        $file    = null;
        $pdfPath = null;
        $sha256  = null;
        $clientName = null;

        if ($analysis !== null) {
            if (empty($analysis['summary']['can_generate'])) {
                @unlink((string) $analysis['pdf_path']);
                @unlink($this->tempDir() . '/analyze_' . (string) $this->request->getPost('analysis_token') . '.json');

                return ['ok' => false, 'error' => 'unreadable_pdf', 'message' => 'Hasil pembacaan PDF tidak memadai untuk pembuatan soal (materi tidak cukup terbaca). Tidak ada soal yang dibuat.'];
            }

            $pdfPath    = (string) $analysis['pdf_path'];
            $sha256     = (string) ($analysis['sha256'] ?? '') ?: (string) hash_file('sha256', $pdfPath);
            $clientName = mb_substr((string) ($analysis['original_filename'] ?? 'dokumen.pdf'), 0, 255);
        } else {
            if ($tokenGiven) {
                return ['ok' => false, 'error' => 'analysis_expired', 'message' => 'Hasil pemeriksaan PDF sudah kadaluarsa atau tidak ditemukan. Silakan periksa PDF lagi atau unggah ulang.'];
            }

            $file = $this->request->getFile('pdf_file');
            if ($file === null || ! $file->isValid()) {
                return [
                    'ok'      => false,
                    'error'   => 'invalid_file',
                    'message' => $this->uploadErrorMessage($file),
                ];
            }

            if ($file->getSize() > self::MAX_BYTES) {
                return ['ok' => false, 'error' => 'too_large', 'message' => 'Ukuran PDF maksimum 10 MB.'];
            }
            if ($file->getSize() === 0) {
                return ['ok' => false, 'error' => 'empty_file', 'message' => 'File kosong.'];
            }

            // Validasi PDF: andalkan magic bytes %PDF- (spec mengizinkan header di
            // 1024 byte pertama). finfo hanya pelengkap — di sebagian shared hosting
            // magic database finfo tidak lengkap sehingga PDF valid terdeteksi
            // salah (application/octet-stream) dan menolak file yang sebenarnya sah.
            $tmpPath  = $file->getTempName();
            $head     = (string) file_get_contents($tmpPath, false, null, 0, 1024);
            $isPdf    = str_contains($head, '%PDF-');
            $mime     = null;
            if (! $isPdf) {
                try {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
                } catch (\Throwable $e) {
                    $mime = null;
                }
                $isPdf = ($mime === 'application/pdf');
            }
            if (! $isPdf) {
                return ['ok' => false, 'error' => 'not_pdf', 'message' => 'File harus berupa PDF valid.'];
            }
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

        if ($pdfPath === null) {
            $sha256  = hash_file('sha256', $file->getTempName());
            $pdfPath = $dir . '/pdf_' . bin2hex(random_bytes(8)) . '.pdf';
            $file->move($dir, basename($pdfPath));
            $clientName = mb_substr($file->getClientName(), 0, 255);
        }

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
                'original_filename'    => $clientName,
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
            if ($analysis === null) {
                @unlink($pdfPath); // PDF hasil unggah langsung: tidak ada referensi lain, hapus.
            }

            return ['ok' => false, 'error' => 'rejected', 'message' => $rejected ?? 'Gagal menyimpan batch. Silakan coba lagi.'];
        }

        // ===== Pemeriksaan malware WAJIB sebelum ekstraksi/AI =====
        // Hasil analisis preview sudah memuat hasil scan yang masih segar (<24 jam):
        // reuse agar tidak memindai dua kali; jalur unggah langsung memindai normal.
        if ($analysis !== null) {
            $scan = [
                'status' => (string) ($analysis['scan']['status'] ?? 'unknown'),
                'result' => (string) ($analysis['scan']['result'] ?? ''),
                'engine' => (string) ($analysis['scan']['engine'] ?? 'reused'),
            ];
        } else {
            $scan = (new MalwareScanner())->scan($pdfPath);
        }

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
                    : 'File ditolak karena pemindai keamanan tidak tersedia di server ini. Administrator dapat mengatur quiz.malwarePolicy = relaxed pada .env atau memasang ClamAV/Windows Defender.',
            ];
        }

        // Langsung diproses di request yang sama — tidak ada antrean.
        //
        // Bila hasil analisis preview tersedia: seed hasil ekstraksi (teks
        // ternormalisasi) langsung ke batch agar PDF TIDAK diekstraksi ulang,
        // lalu hapus file analisis sementara (token sudah dikonsumsi).
        if ($analysis !== null) {
            $extractPath = $dir . '/extract_' . $batchId . '.txt';
            @file_put_contents($extractPath, (string) ($analysis['text'] ?? ''));
            if (is_file($extractPath) && trim((string) file_get_contents($extractPath)) !== '') {
                @file_put_contents(
                    $dir . '/extract_' . $batchId . '.json',
                    json_encode([
                        'analyzed_at' => $analysis['created_at'] ?? date('c'),
                        'summary'     => $analysis['summary'] ?? null,
                        'warnings'    => $analysis['warnings'] ?? [],
                        'reused_from' => 'preview',
                    ], JSON_UNESCAPED_UNICODE)
                );
                $this->batchModel->update($batchId, ['extract_path' => $extractPath, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            @unlink($this->tempDir() . '/analyze_' . (string) $this->request->getPost('analysis_token') . '.json');
        }

        $this->batchModel->update($batchId, [
            'status'         => 'processing',
            'status_message' => 'Menyiapkan materi dari PDF…',
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'batch_id' => $batchId, 'instruction' => $instruction];
    }

    /**
     * Analisis pembacaan PDF (Adaptive PDF Reader) SEBELUM generate dijalankan.
     * Dosen mendapat ringkasan kualitas per halaman, peringatan, dan preview teks
     * — jujur, bukan "berhasil" hanya karena upload berhasil.
     *
     * PDF & hasil analisis disimpan sementara di quiz_tmp (token acak, bukan
     * URL publik); dipakai ulang oleh generate via analysis_token agar file
     * tidak perlu diunggah & dianalisis dua kali. Kadaluarsa 24 jam.
     */
    public function analyzePdf(int $quizId)
    {
        $this->ownedQuizOrFail($quizId);

        // AdaptivePdfReader & supporting classes adalah berkas baru — bila
        // deployment belum memuatnya, jangan 500 mentah: beri pesan jelas.
        if (! class_exists(\App\Libraries\Quiz\AdaptivePdfReader::class)) {
            return $this->json([
                'ok'      => false,
                'error'   => 'deployment_incomplete',
                'message' => 'Komponen analisis PDF belum lengkap di server (berkas app/Libraries/Quiz/AdaptivePdfReader.php tidak ditemukan). Administrator perlu memastikan deployment memuat seluruh berkas terbaru. Anda tetap bisa langsung menekan Generate tanpa Periksa.',
            ], 503);
        }

        $file = $this->request->getFile('pdf_file');
        if ($file === null || ! $file->isValid()) {
            return $this->json([
                'ok'      => false,
                'error'   => 'invalid_file',
                'message' => $this->uploadErrorMessage($file),
            ], 422);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return $this->json(['ok' => false, 'error' => 'too_large', 'message' => 'Ukuran PDF maksimum 10 MB.'], 422);
        }
        if ($file->getSize() === 0) {
            return $this->json(['ok' => false, 'error' => 'empty_file', 'message' => 'File kosong.'], 422);
        }

        // Kelas pendukung juga wajib ada — deployment parsial (folder Support/
        // tidak ikut) adalah penyebab umum kegagalan analisis di server.
        $missing = array_filter([
            \App\Libraries\Quiz\Support\OcrEngine::class,
            \App\Libraries\Quiz\Support\ExtractNormalizer::class,
        ], static fn ($c) => ! class_exists($c));
        if ($missing !== []) {
            return $this->json([
                'ok'      => false,
                'error'   => 'deployment_incomplete',
                'message' => 'Komponen analisis PDF belum lengkap di server: berkas ' . implode(', ', array_map(static fn ($c) => str_replace('App\\Libraries\\Quiz\\', 'app/Libraries/Quiz/', $c) . '.php', $missing)) . ' tidak ditemukan. Sinkronkan seluruh berkas terbaru ke server. Anda tetap bisa langsung menekan Generate tanpa Periksa.',
            ], 503);
        }

        $tmpPath = $file->getTempName();
        $head    = (string) file_get_contents($tmpPath, false, null, 0, 1024);
        $isPdf   = str_contains($head, '%PDF-');
        if (! $isPdf) {
            try {
                $isPdf = ((new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath)) === 'application/pdf';
            } catch (\Throwable $e) {
                $isPdf = false;
            }
        }
        if (! $isPdf) {
            return $this->json(['ok' => false, 'error' => 'not_pdf', 'message' => 'File harus berupa PDF valid.'], 422);
        }

        $dir = $this->tempDir();

        // Simpan sementara + pindai malware (sama ketat dengan jalur generate).
        $token   = bin2hex(random_bytes(16));
        $pdfPath = $dir . '/analyze_' . $token . '.pdf';
        try {
            $file->move($dir, basename($pdfPath));
        } catch (\Throwable $e) {
            log_message('error', 'QuizGenerate::analyzePdf gagal menyimpan PDF sementara ke {dir} — {msg}', ['dir' => $dir, 'msg' => mb_substr($e->getMessage(), 0, 200)]);

            return $this->json([
                'ok'      => false,
                'error'   => 'storage_error',
                'message' => 'Server gagal menyimpan berkas sementara (kemungkinan izin folder writable/). Administrator perlu memastikan folder writable/quiz_tmp dapat ditulis.',
            ], 500);
        }

        try {
            // Analisis dapat memakai memori lebih (PDF besar, banyak halaman).
            // Naikkan bila konfigurasi mengizinkan — gagal diam-diam tidak apa.
            $currentLimit = trim((string) ini_get('memory_limit'));
            if ($currentLimit !== '' && $currentLimit !== '-1' && $this->iniBytes($currentLimit) < 512 * 1048576) {
                @ini_set('memory_limit', '512M');
            }

            $scan = (new MalwareScanner())->scan($pdfPath);
            if ($scan['status'] !== 'clean') {
                @unlink($pdfPath);

                return $this->json([
                    'ok'      => false,
                    'error'   => 'scan_' . $scan['status'],
                    'message' => $scan['status'] === 'infected'
                        ? 'File ditolak karena gagal pemeriksaan keamanan.'
                        : 'File ditolak karena keamanannya tidak dapat dipastikan (pemindai tidak tersedia/gagal).',
                ], 422);
            }

            // Analisis pembacaan — kegagalan ekstraksi diketahui SEBELUM AI dipanggil.
            // SEMUA exception dibungkus: endpoint wajib selalu menjawab JSON
            // (respons kosong/error HTML membuat frontend kehilangan pesan).
            $result = (new \App\Libraries\Quiz\AdaptivePdfReader())->analyze($pdfPath);
        } catch (\Throwable $e) {
            @unlink($pdfPath);
            log_message('error', 'QuizGenerate::analyzePdf gagal — {class}: {msg} @ {file}:{line}', [
                'class' => $e::class, 'msg' => mb_substr($e->getMessage(), 0, 300),
                'file'  => basename($e->getFile()), 'line' => $e->getLine(),
            ]);

            return $this->json([
                'ok'      => false,
                'error'   => 'server_error',
                'message' => 'Pemeriksaan PDF gagal karena kesalahan internal server (detail dicatat di log aplikasi). Anda tetap bisa langsung menekan Generate tanpa Periksa; bila Generate juga gagal, hubungi administrator.',
                'debug'   => mb_substr($e::class . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(), 0, 300),
            ], 500);
        }

        $payload = [
            'ok'       => true,
            'token'    => $token,
            'readable' => $result['ok'],
            'error'    => $result['error'],
            'document' => [
                'filename'   => mb_substr((string) $file->getClientName(), 0, 255),
                'size_bytes' => (int) @filesize($pdfPath),
                'size_human' => number_format((int) @filesize($pdfPath) / 1048576, 2) . ' MB',
            ],
            'summary'  => $result['summary'],
            'warnings' => $result['warnings'],
            'preview'  => $result['preview'],
            'pages'    => array_map(static fn ($p) => [
                'page' => $p['page'], 'status' => $p['status'], 'chars' => $p['chars'], 'method' => $p['method'],
            ], $result['pages']),
        ];

        if ($result['ok']) {
            // Simpan hasil analisis + PDF untuk dipakai ulang saat generate.
            $stored = [
                'lecturer_id'       => $this->lid(),
                'created_at'        => date('c'),
                'expires_at'        => date('c', time() + self::ANALYSIS_TTL_SEC),
                'original_filename' => mb_substr((string) $file->getClientName(), 0, 255),
                'pdf_path'          => $pdfPath,
                'sha256'            => hash_file('sha256', $pdfPath),
                'scan'              => ['status' => $scan['status'], 'result' => $scan['result'], 'engine' => $scan['engine'], 'at' => date('c')],
                'text'              => $result['text'],
                'summary'           => $result['summary'],
                'warnings'          => $result['warnings'],
            ];
            @file_put_contents($dir . '/analyze_' . $token . '.json', json_encode($stored, JSON_UNESCAPED_UNICODE));
        } else {
            @unlink($pdfPath);
        }

        return $this->json($payload);
    }

    /**
     * Ambil hasil analisis preview yang dipakai ulang (analysis_token).
     * Validasi ketat: format token, kepemilikan dosen, TTL, dan file masih ada.
     */
    private function reuseAnalysis(): ?array
    {
        $token = (string) $this->request->getPost('analysis_token');
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $path = $this->tempDir() . '/analyze_' . $token . '.json';
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)
            || (int) ($data['lecturer_id'] ?? 0) !== $this->lid()
            || (strtotime((string) ($data['expires_at'] ?? '')) ?: 0) < time()
            || empty($data['pdf_path'])
            || ! is_file((string) $data['pdf_path'])
            || ! str_starts_with(basename((string) $data['pdf_path']), 'analyze_' . $token . '.pdf')
        ) {
            @unlink($path);

            return null;
        }

        return $data;
    }

    private function tempDir(): string
    {
        $dir = WRITEPATH . 'quiz_tmp';
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }

        return $dir;
    }

    /**
     * Return a useful, safe upload error. PHP marks uploads larger than
     * upload_max_filesize as invalid before application-level size checks run;
     * post_max_size can even leave getFile() empty. Do not misreport those
     * cases as a corrupt PDF.
     */
    private function uploadErrorMessage($file): string
    {
        $contentLength = (int) ($this->request->getServer('CONTENT_LENGTH') ?? 0);
        $postLimit = $this->iniBytes((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postLimit > 0 && $contentLength > $postLimit) {
            return 'Ukuran file PDF melewati batas upload server (post_max_size = ' . ini_get('post_max_size')
                . '). Batas aplikasi adalah 10 MB; administrator dapat menaikkan batas PHP (upload_max_filesize & post_max_size, mis. via public/.user.ini atau panel hosting), atau kompres PDF menjadi di bawah '
                . ini_get('post_max_size') . ', lalu coba lagi.';
        }

        if ($file !== null && method_exists($file, 'getError')) {
            return match ((int) $file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'Ukuran file PDF melewati batas upload PHP server (upload_max_filesize = ' . ini_get('upload_max_filesize')
                    . '). Batas aplikasi adalah 10 MB; administrator dapat menaikkan batas PHP (upload_max_filesize & post_max_size, mis. via public/.user.ini atau panel hosting), atau kompres PDF menjadi di bawah '
                    . ini_get('upload_max_filesize') . ', lalu coba lagi.',
                UPLOAD_ERR_PARTIAL =>
                    'Upload PDF terputus sebelum selesai. Periksa koneksi lalu unggah ulang.',
                UPLOAD_ERR_NO_TMP_DIR =>
                    'Server tidak memiliki folder sementara upload yang tersedia. Hubungi administrator.',
                UPLOAD_ERR_CANT_WRITE =>
                    'Server gagal menyimpan file sementara upload. Coba lagi atau hubungi administrator.',
                UPLOAD_ERR_EXTENSION =>
                    'Upload PDF dihentikan oleh konfigurasi ekstensi PHP. Hubungi administrator.',
                UPLOAD_ERR_NO_FILE =>
                    'File PDF belum terkirim. Pilih file kembali lalu coba lagi.',
                default =>
                    'Upload PDF gagal atau file tidak diterima server. Pilih ulang file dan coba lagi.',
            };
        }

        return 'File PDF tidak terkirim dengan benar. Pilih file kembali dan pastikan ukurannya tidak lebih dari 10 MB.';
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Ambil & bersihkan instruksi tambahan dosen dari request.
     *
     * - Trim awal/akhir; spasi saja dianggap kosong (null).
     * - Buang karakter kontrol & byte UTF-8 invalid agar aman masuk payload
     *   JSON ke provider AI (byte invalid bikin json_encode gagal → 400).
     * - NILAINYA TIDAK PERNAH DISIMPAN: hanya dikembalikan untuk dipakai
     *   prompt pada request generate ini.
     */
    private function sanitizedInstruction(): ?string
    {
        $raw  = (string) $this->request->getPost('ai_instruction');
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        // Bersihkan byte UTF-8 invalid & karakter kontrol non-newline.
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        $text  = ($clean === false) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : $clean;
        $text  = preg_replace('/[^\P{C}\n]/u', ' ', $text) ?? $text; // kontrol kecuali \n
        $text  = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Jalankan service generate dengan pengaman: exception dicatat (tanpa
     * detail internal ke dosen), dan shutdown guard menandai batch gagal bila
     * skrip mati di tengah proses (mis. batas waktu eksekusi server) — gagal
     * berarti gagal, tidak ada antrean lanjutan.
     *
     * @return array{ok:bool, status:string, message:string}
     */
    private function runGuarded(int $batchId, ?string $instruction = null): array
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
            $result = (new QuestionGenerationService())->run($this->batchModel->find($batchId), $instruction);
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
