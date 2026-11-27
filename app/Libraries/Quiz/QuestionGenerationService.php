<?php

namespace App\Libraries\Quiz;

use App\Libraries\Quiz\Support\QuestionSchema;
use App\Models\Quiz\QuizBatchModel;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;

/**
 * Pipeline generate soal AI dari satu PDF (satu batch).
 *
 * Alur yang disederhanakan (hemat token & waktu):
 * 1. Ekstraksi PDF satu kali per batch (file cache; dokumen sha256 sama pakai ulang).
 * 2. Konteks duplikasi dibangun SATU KALI: satu query (id + teks pertanyaan),
 *    normalisasi/fingerprint tiap soal lama dihitung sekali di indeks.
 * 3. Satu prompt generate per putaran. Kandidat divalidasi & diperiksa
 *    duplikasinya secara lokal (fingerprint, trigram, skor token); pasangan
 *    yang belum pasti dinilai AI dalam SATU request per putaran.
 * 4. Bila jumlah soal unik kurang dari permintaan, putaran berikutnya hanya
 *    meminta KEEKURANGANNYA (bukan mengulang seluruh batch).
 * 5. Putaran dibatasi (MAX_ROUNDS) dan berhenti bila layanan AI gagal beruntun.
 * 6. Soal unik disimpan dalam satu transaksi dengan guard batas 100 soal/kuis.
 */
class QuestionGenerationService
{
    /** Satu-satunya jumlah soal yang boleh diminta per generate (maksimal 20). */
    public const ALLOWED_COUNTS = [5, 10, 15, 20];

    /** Batas total soal per kuis (manual + AI). */
    public const MAX_QUESTIONS_PER_QUIZ = 100;

    /** Putaran generate maksimal: 1 awal + 2 putaran pengganti (hanya kekurangan). */
    private const MAX_ROUNDS = 3;

    /** AI gagal beruntun sebanyak ini -> berhenti memanggil (jangan menghantam layanan). */
    private const MAX_FAILED_CALLS = 2;

    /** Ukuran materi per request (karakter). */
    private const CHUNK_CHARS = 12000;

    /** Maksimum jendela materi yang disiapkan dari satu dokumen. */
    private const MAX_CHUNKS = 6;

    /** Batas token per batch (env quiz.maxBatchTokens). */
    private const MAX_BATCH_TOKENS = 400000;

    /** Anggaran waktu total satu batch (detik); di bawah set_time_limit controller. */
    private const TIME_BUDGET_SEC = 480;

    private const GENERATE_TIMEOUT_SEC = 120;
    private const VERIFY_TIMEOUT_SEC   = 45;
    private const MIN_STEP_SECONDS     = 15;

    /** Maksimum soal yang diminta per SATU call AI (pecah target besar agar
     * durasi tiap call jauh di bawah timeout — model lambat/free OpenRouter
     * kerap tidak selesai menghasilkan 20 soal JSON dalam 120 detik). */
    private const MAX_PER_CALL = 10;

    private QuizBatchModel $batches;
    private QuizQuestionModel $questions;
    private AIProviderManager $ai;

    public function __construct(?AIProviderManager $ai = null)
    {
        $this->batches   = new QuizBatchModel();
        $this->questions = new QuizQuestionModel();
        $this->ai        = $ai ?? new AIProviderManager();
    }

    private function tokenLimit(): int
    {
        return (int) (env('quiz.maxBatchTokens') ?? self::MAX_BATCH_TOKENS);
    }

    /** Timeout satu call generate (detik); dapat dioverride via env quiz.generateTimeout. */
    private function generateTimeout(): int
    {
        return max(30, (int) (env('quiz.generateTimeout') ?? self::GENERATE_TIMEOUT_SEC));
    }

    // ==================== Alokasi difficulty ====================

    /**
     * Alokasi jumlah per tingkat dengan metode largest remainder
     * sehingga total alokasi SELALU sama dengan jumlah target.
     * (Aturan pembulatan konsisten ini mengikuti saran SDD Bagian 20.)
     */
    public static function allocateCounts(int $total, int $easyPct, int $mediumPct, int $hardPct): array
    {
        $raw = [
            'easy'   => $total * $easyPct / 100,
            'medium' => $total * $mediumPct / 100,
            'hard'   => $total * $hardPct / 100,
        ];

        $alloc = array_map(static fn ($v) => (int) floor($v), $raw);
        $rem   = $total - array_sum($alloc);

        if ($rem > 0) {
            arsort($raw);
            $order = array_keys($raw);
            $i     = 0;
            while ($rem > 0) {
                $alloc[$order[$i % 3]]++;
                $rem--;
                $i++;
            }
        }

        return $alloc;
    }

    // ================= Ekstraksi (satu kali per batch) =================

    /** @return array{ok:bool, text?:string, error?:string} */
    public function ensureExtraction(array $batch): array
    {
        if (! empty($batch['extract_path']) && is_file($batch['extract_path'])) {
            $text = file_get_contents($batch['extract_path']);

            return $text === false ? ['ok' => false, 'error' => 'extract_read_failed'] : ['ok' => true, 'text' => $text];
        }

        $dir  = $this->tempDir();
        $path = $dir . '/extract_' . (int) $batch['id'] . '.txt';

        // Dokumen yang identik (sha256 sama) pernah diekstrak: pakai ulang
        // hasilnya (ekstraksi deterministik) alih-alih mengekstrak ulang.
        $sibling = $this->findSiblingExtract($batch);
        if ($sibling !== null) {
            $text = file_get_contents($sibling);
            if ($text !== false && trim($text) !== '') {
                file_put_contents($path, $text);
                $this->batches->update((int) $batch['id'], ['extract_path' => $path, 'updated_at' => date('Y-m-d H:i:s')]);

                return ['ok' => true, 'text' => $text];
            }
        }

        $res = (new PdfTextExtractor())->extract((string) $batch['pdf_path']);

        if (! $res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'extract_failed'];
        }

        file_put_contents($path, $res['text']);
        $this->batches->update((int) $batch['id'], ['extract_path' => $path, 'updated_at' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'text' => $res['text']];
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

    private function findSiblingExtract(array $batch): ?string
    {
        $sha = (string) ($batch['file_sha256'] ?? '');
        if ($sha === '') {
            return null;
        }

        $rows = $this->batches->select('extract_path')
            ->where('lecturer_id', (int) $batch['lecturer_id'])
            ->where('file_sha256', $sha)
            ->where('id !=', (int) $batch['id'])
            ->where('extract_path !=', null)
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->findAll();

        foreach ($rows as $row) {
            if (is_string($row['extract_path']) && is_file($row['extract_path'])) {
                return $row['extract_path'];
            }
        }

        return null;
    }

    // ======================== Pipeline utama ========================

    /**
     * @param array $batch row quiz_question_batches (status processing, scan clean)
     *
     * @return array{ok:bool, status:string, error:?string, message:string, generated:int,
     *   requested:int, duplicates:int, invalid:int, rounds:int, generate_calls:int,
     *   verify_calls:int, tokens:int, elapsed_ms:int}
     */
    public function run(array $batch): array
    {
        $start   = microtime(true);
        $batchId = (int) $batch['id'];
        $quizId  = (int) $batch['quiz_id'];
        $logCtx  = ['batch_id' => $batchId, 'quiz_id' => $quizId, 'lecturer_id' => (int) $batch['lecturer_id']];

        $stats = [
            'prompt_tokens' => 0, 'completion_tokens' => 0,
            'generate_calls' => 0, 'verify_calls' => 0, 'rounds' => 0,
            'duplicates' => 0, 'invalid' => 0, 'failed_calls' => 0,
            'provider' => null, 'model' => null, 'last_error' => null, 'stop_reason' => null,
        ];

        $target = min(max(1, (int) $batch['requested_count']), max(self::ALLOWED_COUNTS));

        // 1) Ekstraksi satu kali per batch.
        $this->batches->setStatusMessage($batchId, 'Menyiapkan materi dari PDF…');

        $extraction = $this->ensureExtraction($batch);
        if (! $extraction['ok']) {
            $message = match ($extraction['error']) {
                'no_text_layer' => 'PDF tidak memiliki lapisan teks (kemungkinan hasil scan) sehingga materinya tidak dapat dibaca. Tidak ada soal yang dibuat.',
                default         => 'Teks PDF tidak dapat dibaca. Pastikan file PDF valid dan tidak rusak. Tidak ada soal yang dibuat.',
            };

            return $this->finalize($batch, $stats, $start, ['ok' => false, 'status' => 'failed', 'error' => $extraction['error'], 'message' => $message, 'generated' => 0, 'requested' => $target]);
        }

        // 2) Konteks duplikasi dibangun SEKALI: satu query, hanya id + teks pertanyaan.
        $existing = $this->questions->questionTextsForQuiz($quizId);
        $checker  = new SemanticDuplicateChecker();
        foreach ($existing as $row) {
            $checker->add('e' . $row['id'], (string) $row['question_text']);
        }

        $chunks = $this->buildChunks((string) $extraction['text']);
        $system = QuestionSchema::systemPrompt();

        $accepted = [];   // key => soal valid & unik pada batch ini (independen per soal)
        $got      = ['easy' => 0, 'medium' => 0, 'hard' => 0];
        $seq      = 0;

        // 3) Putaran generate: putaran berikutnya hanya meminta KEEKURANGANNYA.
        while (count($accepted) < $target) {
            $elapsed = microtime(true) - $start;

            if ($stats['rounds'] >= self::MAX_ROUNDS) {
                $stats['stop_reason'] = 'max_rounds';
                break;
            }
            if ($stats['failed_calls'] >= self::MAX_FAILED_CALLS) {
                $stats['stop_reason'] = 'ai_unavailable';
                break;
            }
            if ($stats['prompt_tokens'] + $stats['completion_tokens'] >= $this->tokenLimit()) {
                $stats['stop_reason'] = 'token_limit';
                break;
            }
            if (self::TIME_BUDGET_SEC - $elapsed < self::MIN_STEP_SECONDS) {
                $stats['stop_reason'] = 'time_limit';
                break;
            }

            $stats['rounds']++;
            $round = $stats['rounds'];
            // Pecah target besar menjadi call-call kecil: model butuh ~6.000-9.000
            // token output untuk 20 soal JSON dan kerap melewati timeout di provider
            // lambat. Maks 10 soal per call menjaga durasi tiap call tetap aman.
            $need  = min($target - count($accepted), self::MAX_PER_CALL);

            // Alokasi per call dihitung ulang dari persentase asli agar komposisi
            // difficulty tetap mengikuti rencana meski target dipatah-batahkan.
            $alloc = self::allocateCounts(
                $need,
                (int) $batch['difficulty_easy_pct'],
                (int) $batch['difficulty_medium_pct'],
                (int) $batch['difficulty_hard_pct']
            );

            // Chunk dipilih bergilir agar putaran pengganti memakai materi yang berbeda.
            $chunk = $chunks[($round - 1) % count($chunks)];
            $avoid = array_map(static fn ($q) => $q['question_text'], array_values($accepted));

            $this->batches->setStatusMessage(
                $batchId,
                $round === 1 ? "AI menyusun {$need} soal…" : "AI menyusun {$need} soal pengganti (putaran {$round})…"
            );

            $res = $this->ai->chatJsonWithFallback(
                $system,
                QuestionSchema::userPrompt($chunk, $alloc, $need, $existing, $avoid),
                $logCtx + ['requested_count' => $need, 'received_count' => count($accepted), 'duplicate_count' => $stats['duplicates']],
                $round,
                ['timeout' => (int) min($this->generateTimeout(), self::TIME_BUDGET_SEC - $elapsed)]
            );

            $this->accountTokens($stats, $res);
            $stats['generate_calls']++;

            if (! $res['ok'] || $res['content'] === null) {
                $stats['failed_calls']++;
                $stats['last_error']        = $res['error_category'];
                $stats['last_error_detail'] = trim(
                    ($res['error_category'] ?? '') . (isset($res['http_status']) ? ' (HTTP ' . $res['http_status'] . ')' : '')
                    . (! empty($res['error_message']) ? ': ' . $res['error_message'] : '')
                );

                continue;
            }

            $stats['provider'] = $res['provider'] ?: $stats['provider'];
            $stats['model']    = $res['model'] ?: $stats['model'];

            $this->batches->setStatusMessage($batchId, 'Memeriksa format & kemiripan soal…');

            // Validasi lokal seluruh output.
            $candidates = QuestionSchema::extractQuestions((string) $res['content']);
            if ($candidates === []) {
                // Respons "sukses" tetapi tanpa satu pun soal utuh = kegagalan respons.
                $stats['invalid']++;
                $stats['failed_calls']++;

                continue;
            }

            $stats['failed_calls'] = 0;

            // Pemeriksaan lokal: terhadap soal lama DAN kandidat batch yang sudah diterima
            // (kandidat yang diterima langsung masuk indeks).
            $pairs = [];
            foreach ($candidates as $raw) {
                if (count($accepted) >= $target) {
                    break;
                }

                $validated = QuestionSchema::validate($raw);
                if ($validated === null) {
                    $stats['invalid']++;
                    continue;
                }

                $screen = $checker->screen($validated['question_text']);
                if ($screen['decision'] === 'duplicate') {
                    $stats['duplicates']++;
                    continue;
                }

                $aiLabel = $validated['difficulty_level'];
                $level   = $this->pickDifficulty($aiLabel, $alloc);
                $got[$level]++;
                $alloc[$level] = max(0, $alloc[$level] - 1);

                $key = 'b' . (++$seq);
                $accepted[$key] = $this->decorate($validated, $batch, $aiLabel, $level);
                $checker->add($key, $validated['question_text']);

                foreach ($screen['neighbors'] as $neighbor) {
                    $pairs[] = [
                        'id'        => count($pairs),
                        'key'       => $key,
                        'score'     => (float) $neighbor['score'],
                        'candidate' => $validated['question_text'],
                        'existing'  => (string) $neighbor['text'],
                    ];
                }
            }

            // Pemeriksaan semantik: SATU request untuk seluruh pasangan belum pasti.
            foreach ($this->verifyPairs($pairs, $checker, $logCtx, $round, $stats, $start) as $key) {
                $got[$accepted[$key]['difficulty_level']]--;
                unset($accepted[$key]);
                $checker->remove($key);
                $stats['duplicates']++;
            }
        }

        $accepted = array_values($accepted);

        if ($accepted === []) {
            return $this->finalize($batch, $stats, $start, [
                'ok' => false, 'status' => 'failed', 'error' => 'generation_failed',
                'message' => $this->emptyResultMessage($stats), 'generated' => 0, 'requested' => $target,
            ]);
        }

        // Simpan soal unik dalam satu transaksi; guard batas 100 soal per kuis
        // membatalkan SELURUH penyimpanan bila total melebihi batas.
        $persisted = $this->persist($batch, $accepted);
        if (! $persisted['ok']) {
            return $this->finalize($batch, $stats, $start, [
                'ok' => false, 'status' => 'failed', 'error' => $persisted['error'],
                'message' => $persisted['message'], 'generated' => 0, 'requested' => $target,
            ]);
        }

        $generated = count($accepted);
        $status    = $generated >= $target ? 'completed' : 'partial';

        return $this->finalize($batch, $stats, $start, [
            'ok' => true, 'status' => $status, 'error' => null,
            'message' => $this->resultMessage($generated, $target, $stats, $status),
            'generated' => $generated, 'requested' => $target,
        ]);
    }

    // ======================== Helpers pipeline ========================

    private function accountTokens(array &$stats, array $res): void
    {
        $stats['prompt_tokens']     += (int) ($res['tokens_prompt'] ?? 0);
        $stats['completion_tokens'] += (int) ($res['tokens_completion'] ?? 0);
    }

    /**
     * Nilai pasangan belum-pasti dengan SATU request AI per putaran.
     *
     * @return list<string> key kandidat yang terbukti duplikat
     */
    private function verifyPairs(array $pairs, SemanticDuplicateChecker $checker, array $logCtx, int $round, array &$stats, float $start): array
    {
        if ($pairs === []) {
            return [];
        }

        $stats['verify_calls']++;

        $res = $this->ai->chatJsonWithFallback(
            SemanticDuplicateChecker::JUDGE_SYSTEM,
            $checker->judgePrompt($pairs),
            $logCtx + ['requested_count' => count($pairs), 'received_count' => count($pairs), 'duplicate_count' => 0],
            $round,
            [
                'timeout'    => (int) min(self::VERIFY_TIMEOUT_SEC, max(5, self::TIME_BUDGET_SEC - (microtime(true) - $start))),
                'max_tokens' => SemanticDuplicateChecker::judgeMaxTokens(count($pairs)),
            ]
        );

        $this->accountTokens($stats, $res);

        $verdict = ($res['ok'] && $res['content'] !== null)
            ? $checker->parseJudgeVerdict((string) $res['content'], $pairs)
            : null;

        // AI penilai tidak tersedia / jawaban tidak terbaca: putuskan dengan
        // ambang skor lokal (sama dengan perilaku sebelum optimasi).
        $duplicateIds = [];
        if ($verdict === null) {
            foreach ($pairs as $pair) {
                if ($pair['score'] >= $checker->threshold()) {
                    $duplicateIds[] = $pair['id'];
                }
            }
        } else {
            $duplicateIds = $verdict;
        }

        $keys = [];
        foreach ($duplicateIds as $id) {
            if (isset($pairs[$id])) {
                $keys[] = $pairs[$id]['key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /** Pilih difficulty sesuai sisa alokasi; label AI dipakai bila slotnya masih ada. */
    private function pickDifficulty(?string $aiLevel, array $alloc): string
    {
        $aiLevel = in_array($aiLevel, ['easy', 'medium', 'hard'], true) ? $aiLevel : null;

        if ($aiLevel !== null && ($alloc[$aiLevel] ?? 0) > 0) {
            return $aiLevel;
        }
        foreach (['easy', 'medium', 'hard'] as $lvl) {
            if (($alloc[$lvl] ?? 0) > 0) {
                return $lvl;
            }
        }

        return $aiLevel ?? 'medium';
    }

    /** Lengkapi soal dengan metadata penyimpanan (durasi default per tingkat kesulitan). */
    private function decorate(array $q, array $batch, string $aiLabel, string $level): array
    {
        $q['difficulty_level']     = $level;
        $q['ai_difficulty_level']  = $aiLabel;   // label awal AI tetap tersimpan apa adanya
        $q['batch_id']             = (int) $batch['id'];
        $q['quiz_id']              = (int) $batch['quiz_id'];
        $q['created_by']           = (int) $batch['lecturer_id'];
        $q['source_type']          = 'ai_pdf';
        // Durasi otomatis sesuai tingkat kesulitan: easy=60, medium=90, hard=120 detik
        // (dosen dapat mengubahnya kemudian).
        $q['duration_seconds']     = QuizScoringService::defaultDurationFor($level);
        $q['source_title']         = $batch['source_title'] ?: $batch['original_filename'];
        $q['source_filename']      = $batch['original_filename'];
        $q['sort_order']           = 0;

        return $q;
    }

    private function emptyResultMessage(array $stats): string
    {
        if (($stats['last_error'] ?? null) === 'no_provider') {
            return 'Layanan AI belum dikonfigurasi. Hubungi administrator. Tidak ada soal yang dibuat.';
        }

        if ($stats['duplicates'] === 0 && $stats['invalid'] === 0) {
            $hint = match ($stats['last_error'] ?? null) {
                'timeout'          => 'Panggilan ke layanan AI melewati batas waktu.',
                'connection'       => 'Server aplikasi gagal terhubung ke layanan AI.',
                'auth'             => 'API key ditolak oleh penyedia AI (cek konfigurasi provider).',
                'rate_limit'       => 'Kuota/batas pemakaian penyedia AI tercapai (rate limit).',
                'http_5xx'         => 'Server penyedia AI sedang bermasalah.',
                'http_error'       => 'Penyedia AI menolak request (cek model/konfigurasi provider).',
                'empty_content'    => 'Penyedia AI mengembalikan respons kosong (budget output model kemungkinan terlalu kecil).',
                'invalid_response' => 'Respons penyedia AI tidak dapat dibaca.',
                default            => null,
            };

            $detail = trim((string) ($stats['last_error_detail'] ?? ''));
            if ($detail !== '' && $detail !== ($stats['last_error'] ?? '')) {
                $hint = ($hint !== null ? $hint . ' ' : '') . '[' . $detail . ']';
            }

            return 'Layanan AI tidak dapat dihubungi saat ini'
                . ($hint !== null ? ' (' . $hint . ')' : '.')
                . ' Tidak ada soal yang dibuat. Silakan coba lagi beberapa saat; bila berulang, hubungi administrator.';
        }

        return 'Tidak ada soal baru yang unik dan valid dari PDF ini (' . $stats['duplicates'] . ' kandidat duplikat, '
            . $stats['invalid'] . ' tidak valid). Tidak ada soal yang disimpan. Materi kemungkinan sudah tercakup seluruhnya oleh soal yang ada.';
    }

    private function resultMessage(int $generated, int $target, array $stats, string $status): string
    {
        if ($status === 'completed') {
            return "Berhasil membuat {$generated} soal baru.";
        }

        $missing = $target - $generated;
        $message = "Berhasil membuat {$generated} dari {$target} soal. Sisa {$missing} tidak terpenuhi ({$stats['duplicates']} kandidat duplikat, {$stats['invalid']} tidak valid)";
        $message .= ($stats['stop_reason'] ?? null) === 'ai_unavailable' ? '; putaran pengganti terhenti karena layanan AI tidak merespons.' : '.';

        return $message;
    }

    /**
     * Simpan soal unik dalam satu transaksi. Guard batas 100 soal per kuis:
     * transaksi + lock baris kuis mencegah dua proses bersamaan melewati batas,
     * dan membatalkan seluruh batch bila total melebihi batas (tanpa menyimpan sebagian).
     *
     * @return array{ok:bool, error?:string, message?:string}
     */
    private function persist(array $batch, array $accepted): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $db->query('SELECT id FROM quiz_quizzes WHERE id = ? FOR UPDATE', [(int) $batch['quiz_id']]);
            $existingCount = (int) $this->questions->where('quiz_id', $batch['quiz_id'])->where('is_active', 1)->countAllResults();

            if ($existingCount + count($accepted) > self::MAX_QUESTIONS_PER_QUIZ) {
                $db->transRollback();

                return [
                    'ok' => false, 'error' => 'limit_exceeded',
                    'message' => 'Jumlah soal dalam Kuis ini sudah mencapai batas maksimal 100 soal. Penambahan soal dibatalkan. Silakan hapus beberapa soal terlebih dahulu jika ingin menambahkan soal baru.',
                ];
            }

            $now  = date('Y-m-d H:i:s');
            $sort = (int) $this->questions->where('quiz_id', $batch['quiz_id'])->countAllResults();
            $rows = [];

            foreach ($accepted as $q) {
                $sort++;
                $rows[] = [
                    'quiz_id'             => $q['quiz_id'],
                    'batch_id'            => $q['batch_id'],
                    'question_text'       => $q['question_text'],
                    'option_a'            => $q['option_a'],
                    'option_b'            => $q['option_b'],
                    'option_c'            => $q['option_c'],
                    'option_d'            => $q['option_d'],
                    'option_e'            => 'Tidak tahu',
                    'correct_option'      => $q['correct_option'],
                    'explanation'         => $q['explanation'] ?? null,
                    'duration_seconds'    => $q['duration_seconds'],
                    'difficulty_level'    => $q['difficulty_level'],
                    'ai_difficulty_level' => $q['ai_difficulty_level'],
                    'source_type'         => $q['source_type'],
                    'source_title'        => $q['source_title'],
                    'source_filename'     => $q['source_filename'],
                    'source_section'      => $q['source_section'],
                    'created_by'          => $q['created_by'],
                    'sort_order'          => $sort,
                    'is_active'           => 1,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            $inserted = $this->questions->insertBatch($rows);
            if ($inserted === false || (int) $inserted !== count($rows)) {
                $db->transRollback();
                log_message('error', 'QuestionGeneration: insert batch #{batch} tidak lengkap ({got}/{want}).', ['batch' => $batch['id'], 'got' => var_export($inserted, true), 'want' => count($rows)]);

                return ['ok' => false, 'error' => 'db_error', 'message' => 'Soal gagal disimpan ke database. Tidak ada soal yang disimpan. Silakan coba lagi.'];
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'QuestionGeneration: gagal menyimpan soal batch #{batch}: {msg}', ['batch' => $batch['id'], 'msg' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'db_error', 'message' => 'Soal gagal disimpan ke database. Tidak ada soal yang disimpan. Silakan coba lagi.'];
        }

        return ['ok' => true];
    }

    /** Tulis status akhir batch + akumulasi token, lalu kembalikan hasil untuk controller. */
    private function finalize(array $batch, array $stats, float $start, array $result): array
    {
        $tokens = (int) ($stats['prompt_tokens'] + $stats['completion_tokens']);

        $this->batches->update((int) $batch['id'], [
            'status'          => $result['status'],
            'generated_count' => (int) $result['generated'],
            'duplicate_count' => (int) $stats['duplicates'],
            'retry_count'     => max(0, (int) $stats['rounds'] - 1),
            'provider_used'   => $stats['provider'],
            'model_used'      => $stats['model'],
            'status_message'  => mb_substr((string) $result['message'], 0, 255),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // Token generate + verifikasi dicatat terpisah (prompt / completion).
        $this->batches->query(
            'UPDATE quiz_question_batches SET token_prompt = token_prompt + ?, token_completion = token_completion + ? WHERE id = ?',
            [(int) $stats['prompt_tokens'], (int) $stats['completion_tokens'], (int) $batch['id']]
        );

        return $result + [
            'duplicates'     => (int) $stats['duplicates'],
            'invalid'        => (int) $stats['invalid'],
            'rounds'         => (int) $stats['rounds'],
            'generate_calls' => (int) $stats['generate_calls'],
            'verify_calls'   => (int) $stats['verify_calls'],
            'tokens'         => $tokens,
            'elapsed_ms'     => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * Jendela materi tidak tumpang tindih yang tersebar merata di dokumen,
     * jumlahnya dibatasi sesuai kebutuhan putaran — dokumen tidak pernah
     * dikirim utuh berkali-kali.
     */
    private function buildChunks(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Teks hasil ekstraksi PDF bisa mengandung byte UTF-8 tidak valid.
        // Jika dibiarkan, json_encode() payload request gagal dan provider AI
        // menolak dengan 400 "JSON parsing failed". Bersihkan ke UTF-8 valid.
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        $text  = ($clean === false) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : $clean;
        $text  = preg_replace('/[\x{FFFD}\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $text) ?? $text;
        $text  = trim($text);
        if ($text === '') {
            return [];
        }

        $len = mb_strlen($text);
        $max = self::CHUNK_CHARS;

        if ($len <= $max) {
            return [$text];
        }

        $count  = min(self::MAX_CHUNKS, max(2, (int) ceil($len / $max)));
        $span   = intdiv($len, $count);
        $chunks = [];

        for ($i = 0; $i < $count; $i++) {
            $offset = $i * $span;
            $part   = mb_substr($text, $offset, $max);

            if ($offset + $max < $len) {
                $part = $this->trimToBoundary($part);
            }
            if (trim($part) !== '') {
                $chunks[] = $part;
            }
        }

        return $chunks === [] ? [mb_substr($text, 0, $max)] : $chunks;
    }

    /** Potong ekor jendela pada batas kalimat/paragraf terakhir agar tidak terpotong di tengah kalimat. */
    private function trimToBoundary(string $part): string
    {
        $cut = mb_strrpos($part, "\n");
        $dot = mb_strrpos($part, '. ');

        if ($dot !== false && ($cut === false || $dot > $cut)) {
            $cut = $dot;
        }

        return ($cut !== false && $cut > (int) (mb_strlen($part) * 0.6)) ? mb_substr($part, 0, $cut + 1) : $part;
    }
}
