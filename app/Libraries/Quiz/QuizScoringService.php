<?php

namespace App\Libraries\Quiz;

use App\Models\Quiz\QuizAttemptAnswerModel;
use App\Models\Quiz\QuizAttemptModel;
use App\Models\Quiz\QuizAttemptQuestionMapModel;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;

/**
 * Pelaksanaan attempt: binding perangkat, snapshot soal/opsi,
 * penyimpanan jawaban idempotent, penilaian di server.
 *
 * Optimasi (shared hosting):
 * - progressState() tidak lagi memuat snapshot (LONGTEXT) seluruh soal dan
 *   seluruh histori jawaban; progres dihitung dari SATU query ringan
 *   (LEFT JOIN pada tabel jawaban) + COUNT berindex, dan snapshot hanya
 *   diambil untuk soal yang benar-benar ditampilkan.
 * - recordAnswer() memvalidasi attempt + soal aktif + deadline dari hasil
 *   progres yang sama (tidak ada query progres ganda), idempotent lewat
 *   unique key, dan mengembalikan status selesai-nya attempt sehingga
 *   controller tidak perlu menghitung ulang.
 * - finalize() memakai SUM() agregat, bukan memuat seluruh baris.
 */
class QuizScoringService
{
    /** Pilihan durasi per soal (detik). */
    public const DURATIONS = [30, 60, 90, 120];

    /** Default durasi per tingkat kesulitan soal AI. */
    private const DIFFICULTY_DURATION = ['easy' => 60, 'medium' => 90, 'hard' => 120];
    private const MANUAL_DEFAULT_DURATION = 60;

    private QuizAttemptModel $attempts;
    private QuizAttemptAnswerModel $answers;
    private QuizAttemptQuestionMapModel $mapModel;
    private QuizModel $quizzes;
    private QuizQuestionModel $questions;

    public function __construct()
    {
        $this->attempts  = new QuizAttemptModel();
        $this->answers   = new QuizAttemptAnswerModel();
        $this->mapModel  = new QuizAttemptQuestionMapModel();
        $this->quizzes   = new QuizModel();
        $this->questions = new QuizQuestionModel();
    }

    /** Default durasi berdasarkan tingkat kesulitan soal AI. */
    public static function defaultDurationFor(?string $difficulty): int
    {
        return self::DIFFICULTY_DURATION[$difficulty] ?? self::MANUAL_DEFAULT_DURATION;
    }

    /** Resolusi durasi soal (detik): nilai dosen (30/60/90/120) atau default per difficulty/manual. */
    public function resolveDuration(?int $duration, ?string $difficulty): int
    {
        if ($duration !== null && in_array($duration, self::DURATIONS, true)) {
            return $duration;
        }

        return self::defaultDurationFor($difficulty);
    }

    // ========================= Mulai attempt =========================

    public function startAttempt(array $quiz, string $studentEmail, array $codeRow): array
    {
        $db = db_connect();
        $db->transBegin();

        // Aturan ONE CODE = ONE ATTEMPT per mahasiswa:
        // kode yang sama tidak dapat dipakai memulai attempt kedua oleh
        // mahasiswa yang sama. Mahasiswa yang sama boleh mengikuti quiz yang
        // sama lagi menggunakan kode yang berbeda (hasil attempt lama utuh).
        if ((int) ($codeRow['is_active'] ?? 0) !== 1) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'code_inactive'];
        }
        if ($this->attempts->hasUsedCode((int) $codeRow['id'], $studentEmail)) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'code_used'];
        }

        $allQuestions = $this->questions->forQuiz((int) $quiz['id']);
        if ($allQuestions === []) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'no_questions'];
        }

        $deviceToken = bin2hex(random_bytes(32));
        $now         = date('Y-m-d H:i:s');
        $expires     = date('Y-m-d H:i:s', strtotime($now . ' +' . (int) $quiz['duration_minutes'] . ' minutes'));

        // Nomor percobaan dihitung per mahasiswa pada quiz yang sama.
        $attemptNo = $this->attempts->nextAttemptNo((int) $quiz['id'], $studentEmail);

        $attemptId = $this->attempts->insert([
            'quiz_id'         => (int) $quiz['id'],
            'student_email'   => $studentEmail,
            'attempt_no'      => $attemptNo,
            'access_code_id'  => (int) $codeRow['id'],
            'access_code'     => (string) $codeRow['code'],
            'status'          => 'in_progress',
            'device_token'    => $deviceToken,
            'device_bound_at' => $now,
            'started_at'      => $now,
            'expires_at'      => $expires,
            'max_score'       => count($allQuestions) * (int) $quiz['correct_points'],
            'raw_score'       => 0,
            'created_at'      => $now,
            'updated_at'      => $now,
        ], true);

        if (! $attemptId) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'db_error'];
        }

        // Acak urutan soal per attempt
        $shuffled = $allQuestions;
        shuffle($shuffled);

        $mapRows = [];
        foreach ($shuffled as $i => $q) {
            // Acak urutan opsi A-D; E ("Tidak tahu") selalu terakhir.
            $letters     = ['a', 'b', 'c', 'd'];
            shuffle($letters);
            $optionOrder = implode(',', $letters);

            // Snapshot durasi per soal (nilai dosen atau default per difficulty).
            $duration = $this->resolveDuration(
                $q['duration_seconds'] !== null ? (int) $q['duration_seconds'] : null,
                $q['difficulty_level']
            );

            $mapRows[] = [
                'attempt_id'       => $attemptId,
                'question_id'      => (int) $q['id'],
                'sort_order'       => $i + 1,
                'points'           => (int) $quiz['correct_points'],
                'penalty'          => (int) $quiz['wrong_penalty'],
                'snapshot'         => json_encode([
                    'question_text'  => $q['question_text'],
                    'options'        => [
                        'a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d'],
                    ],
                    'correct_option' => $q['correct_option'],
                ]),
                'option_order'     => $optionOrder,
                'duration_seconds' => $duration,
                'created_at'       => $now,
            ];
        }

        // Satu insertBatch (bukan N insert) untuk seluruh snapshot soal.
        $inserted = $this->mapModel->insertBatch($mapRows);
        if ($inserted === false || (int) $inserted !== count($mapRows)) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'db_error'];
        }

        $db->transCommit();

        return ['ok' => true, 'attempt_id' => $attemptId, 'device_token' => $deviceToken];
    }

    /**
     * Cookie perangkat HARUS di-attach ke response yang dikirim (redirect)
     * oleh controller, karena cookie pada response lain tidak terkirim.
     */
    public function deviceCookieParams(int $attemptId, string $token): array
    {
        // secure=true hanya bila koneksi HTTPS, agar tetap berfungsi pada deployment HTTP lokal.
        $secure = service('request')->isSecure();

        return ['quiz_device_' . $attemptId, $token, 0, '', '/', '', $secure, true];
    }

    public function clearDeviceCookieParams(int $attemptId): array
    {
        $secure = service('request')->isSecure();

        return ['quiz_device_' . $attemptId, '', -3600, '', '/', '', $secure, true];
    }

    // ====================== Validasi perangkat =======================

    public function verifyDevice(array $attempt): bool
    {
        $cookie = $_COOKIE['quiz_device_' . $attempt['id']] ?? null;

        return is_string($cookie) && $cookie !== '' && hash_equals((string) $attempt['device_token'], $cookie);
    }

    // ================= Progres satu-soal-per-halaman =================

    /**
     * State progres attempt: proses timeout bergantung di server.
     * Soal berjalan berurutan; soal timeout diberi nilai 0 dan dilewati
     * otomatis ke soal berikutnya. Sumber waktu = server.
     *
     * Query per panggilan (jalur normal): 1 query baris belum-selesai
     * (tanpa snapshot) + 1 COUNT berindex; snapshot (LONGTEXT) hanya
     * dimuat untuk soal AKTIF yang akan ditampilkan. Refresh tidak mereset
     * timer karena question_started_at/disimpan di database.
     *
     * @return array{current:?array, index:int, total:int, answered:int, remaining_sec:?int}
     */
    public function progressState(array $attempt): array
    {
        $attemptId = (int) $attempt['id'];
        $now       = time();

        $rows  = $this->mapModel->unfinishedRowsForAttempt($attemptId);
        $total = $this->mapModel->countForAttempt($attemptId);

        // Soal belum selesai yang deadlinenya sudah lewat -> nilai 0, lewati.
        // (Baris berikut diproses dari daftar yang sudah ada di memori —
        // tidak ada pembacaan ulang seluruh jawaban per iterasi.)
        $current = null;
        foreach ($rows as $row) {
            if ($row['question_deadline_at'] !== null && strtotime((string) $row['question_deadline_at']) <= $now) {
                if ($this->closeQuestion($row, null, 'timeout')) {
                    continue;
                }
            }
            $current = $row;
            break;
        }

        // Semua baris sebelum soal aktif pasti sudah selesai (dijawab/timeout):
        // posisi = sort_order soal aktif, jumlah terjawab = posisi - 1.
        $answered = $current !== null ? (int) $current['sort_order'] - 1 : $total;
        $index    = $current !== null ? (int) $current['sort_order'] : $total + 1;

        $remainingSec = null;
        if ($current !== null) {
            if ($current['question_started_at'] === null) {
                // Mulai timer soal ini di server (sekali saja; tidak direset oleh refresh).
                $nowStr   = date('Y-m-d H:i:s', $now);
                $deadline = date('Y-m-d H:i:s', $now + (int) $current['duration_seconds']);
                $this->mapModel->update((int) $current['id'], [
                    'question_started_at'  => $nowStr,
                    'question_deadline_at' => $deadline,
                ]);
                $current['question_started_at']  = $nowStr;
                $current['question_deadline_at'] = $deadline;
            }
            $remainingSec = max(0, strtotime((string) $current['question_deadline_at']) - $now);

            // Snapshot (teks soal + opsi) hanya untuk soal aktif ini.
            $full = $this->mapModel->find((int) $current['id']);
            if ($full !== null) {
                $current = $full;
            }
        }

        return [
            'current'       => $current,
            'index'         => $index,
            'total'         => $total,
            'answered'      => $answered,
            'remaining_sec' => $remainingSec,
        ];
    }

    /** Tandai soal aktif sebagai timeout (nilai 0) bila melewati deadline. Return true jika di-expire. */
    private function expireIfNeeded(array $row): bool
    {
        if ((int) $row['is_expired'] === 1) {
            return false;
        }
        if ($row['question_deadline_at'] === null || strtotime((string) $row['question_deadline_at']) > time()) {
            return false;
        }

        return $this->closeQuestion($row, null, 'timeout');
    }

    /**
     * Abaikan soal aktif yang ditinggalkan mahasiswa (menavigasi keluar lalu
     * kembali ke menu Quiz): dihitung sebagai pilihan "Tidak tahu" (E), nilai 0,
     * dan mahasiswa melanjutkan ke soal berikutnya. Return true jika soal
     * aktif ditandai.
     */
    public function abandonCurrentQuestion(array $attempt): bool
    {
        $state   = $this->progressState($attempt);
        $current = $state['current'];
        if ($current === null) {
            return false;
        }

        return $this->closeQuestion($current, 'E', 'abandoned');
    }

    /** Tutup satu soal tanpa jawaban mahasiswa (timeout / abandoned), nilai 0. */
    private function closeQuestion(array $row, ?string $asOption, string $reason): bool
    {
        if ((int) $row['is_expired'] === 1) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->mapModel->update((int) $row['id'], ['is_expired' => 1]);

        // Tidak dijawab = nilai 0 (jawaban tetap dicatat untuk analitik).
        $this->answers->saveAnswer([
            'attempt_id'       => (int) $row['attempt_id'],
            'question_id'      => (int) $row['question_id'],
            'selected_option'  => $asOption,
            'is_correct'       => null,
            'points_awarded'   => 0,
            'started_at'       => $row['question_started_at'],
            'answered_at'      => $now,
            'response_time_ms' => ((int) $row['duration_seconds']) * 1000,
            'created_at'       => $now,
        ]);

        return true;
    }

    // ============================ Jawaban ============================

    public function isExpired(array $attempt): bool
    {
        return strtotime((string) $attempt['expires_at']) < time();
    }

    /**
     * Simpan jawaban (idempotent per attempt+soal).
     *
     * Satu perhitungan progres dipakai untuk memvalidasi soal aktif + deadline
     * + menyiapkan snapshot penilaian; hasilnya juga menandai apakah attempt
     * sudah selesai (soal terakhir) sehingga pemanggil tidak perlu menghitung
     * ulang progres.
     *
     * @param string $selectedLetter huruf TAMPILAN yang dipilih (A-E)
     *
     * @return array{ok:bool, error?:string, duplicate?:bool, finished?:bool}
     */
    public function recordAnswer(array $attempt, int $questionId, ?string $selectedLetter): array
    {
        if ($attempt['status'] !== 'in_progress') {
            return ['ok' => false, 'error' => 'not_active'];
        }
        if ($this->isExpired($attempt)) {
            $this->finalize($attempt, 'timeout');

            return ['ok' => false, 'error' => 'expired'];
        }

        // Satu panggilan progres: soal aktif (dengan snapshot) + sisa soal.
        $state   = $this->progressState($attempt);
        $current = $state['current'];

        if ($current === null) {
            return ['ok' => false, 'error' => 'not_current_question'];
        }
        if ((int) $current['question_id'] !== $questionId) {
            // Soal tidak termasuk attempt ini, atau bukan soal aktif (sudah
            // terjawab/selesai) — keduanya ditolak dengan pengecekan unik
            // berikut agar pesan tetap spesifik.
            $inAttempt = $this->mapModel->where('attempt_id', (int) $attempt['id'])
                ->where('question_id', $questionId)->countAllResults() > 0;

            return ['ok' => false, 'error' => $inAttempt ? 'not_current_question' : 'question_not_in_attempt'];
        }

        // Deadline divalidasi pakai waktu server; jawaban setelah waktu habis ditolak.
        if ((int) $current['is_expired'] === 1 || strtotime((string) $current['question_deadline_at']) < time()) {
            $this->expireIfNeeded($current);

            return ['ok' => false, 'error' => 'expired'];
        }

        $snapshot = json_decode((string) $current['snapshot'], true);
        $order    = array_map('trim', explode(',', (string) $current['option_order']));

        $selected  = strtoupper((string) $selectedLetter);
        $original  = null;

        if ($selected === 'E') {
            // Opsi E = "Tidak tahu", identitas tetap E, nilai 0.
            $original = 'E';
        } elseif (in_array($selected, ['A', 'B', 'C', 'D'], true)) {
            $pos      = ord($selected) - 65;                 // posisi tampil 0..3
            $original = strtoupper($order[$pos] ?? '');      // huruf opsi ASLI
        }

        $isCorrect = ($original !== null && $original !== 'E' && $original === ($snapshot['correct_option'] ?? ''));

        $points = match (true) {
            $original === null || $original === 'E' => 0,
            $isCorrect                              => (int) $current['points'],
            default                                 => -1 * (int) $current['penalty'],
        };

        $now = date('Y-m-d H:i:s');
        $responseMs = null;
        $startedAt  = (string) $current['question_started_at'];
        if ($startedAt !== '') {
            $responseMs = max(0, (int) floor((time() - strtotime($startedAt)) * 1000));
        }

        $result = $this->answers->saveAnswer([
            'attempt_id'       => (int) $attempt['id'],
            'question_id'      => $questionId,
            'selected_option'  => $selected === '' ? null : $selected,
            'is_correct'       => $original === 'E' || $original === null ? null : ($isCorrect ? 1 : 0),
            'points_awarded'   => $points,
            'started_at'       => $startedAt ?: null,
            'answered_at'      => $now,
            'response_time_ms' => $responseMs,
            'created_at'       => $now,
        ]);

        if ($result === 'error') {
            return ['ok' => false, 'error' => 'db_error'];
        }
        if ($result === 'duplicate') {
            // Idempotent: jawaban soal ini sudah tersimpan (request ganda/race).
            return ['ok' => true, 'duplicate' => true];
        }

        // Soal ini adalah yang terakhir? (state dihitung SEBELUM insert:
        // current = soal aktif; selesai bila tidak ada baris setelahnya.)
        return ['ok' => true, 'finished' => $state['index'] === $state['total']];
    }

    // =========================== Finalisasi ==========================

    public function finalize(array $attempt, string $status = 'submitted'): array
    {
        $attempt = $this->attempts->find((int) $attempt['id']);
        if ($attempt === null || in_array($attempt['status'], ['submitted', 'timeout'], true)) {
            return ['ok' => false, 'error' => 'already_final'];
        }

        // SUM agregat — tanpa memuat seluruh baris map/jawaban.
        $max = $this->mapModel->sumPointsForAttempt((int) $attempt['id']);
        $raw = $this->answers->sumPointsForAttempt((int) $attempt['id']);
        $pct = $max > 0 ? round(max(0, $raw) / $max * 100, 2) : null;

        $this->attempts->update((int) $attempt['id'], [
            'status'       => $status,
            'submitted_at' => date('Y-m-d H:i:s'),
            'raw_score'    => $raw,          // skor mentah tetap disimpan apa adanya (bisa negatif)
            'max_score'    => $max,
            'percentage'   => $pct,          // persentase dibatasi 0-100
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'raw_score' => $raw, 'max_score' => $max, 'percentage' => $pct];
    }

    // ==================== Tampilan soal per attempt ===================

    /** Soal tunggal (satu per halaman) dari baris map attempt. */
    public function displayQuestion(array $mapRow): array
    {
        $snapshot = json_decode((string) $mapRow['snapshot'], true);
        $order    = array_map('trim', explode(',', (string) $mapRow['option_order']));

        $options = [];
        foreach ($order as $pos => $orig) {
            $letter    = chr(65 + $pos); // posisi tampil A..D
            $options[] = [
                'letter' => $letter,
                'text'   => (string) ($snapshot['options'][$orig] ?? ''),
                'orig'   => $orig,
            ];
        }
        // E "Tidak tahu" selalu tampil terakhir dengan identitas E.
        $options[] = ['letter' => 'E', 'text' => 'Tidak tahu', 'orig' => 'e'];

        return [
            'id'      => (int) $mapRow['question_id'],
            'sort'    => (int) $mapRow['sort_order'],
            'text'    => (string) ($snapshot['question_text'] ?? ''),
            'options' => $options,
        ];
    }

    public function displayQuestions(array $attempt): array
    {
        $out = [];
        foreach ($this->mapModel->forAttempt((int) $attempt['id']) as $map) {
            $snapshot = json_decode((string) $map['snapshot'], true);
            $order    = array_map('trim', explode(',', (string) $map['option_order']));

            $options = [];
            foreach ($order as $pos => $orig) {
                $letter    = chr(65 + $pos); // posisi tampil A..D
                $options[] = [
                    'letter'  => $letter,
                    'text'    => (string) ($snapshot['options'][$orig] ?? ''),
                    'orig'    => $orig,
                ];
            }
            // E "Tidak tahu" selalu tampil terakhir dengan identitas E.
            $options[] = ['letter' => 'E', 'text' => 'Tidak tahu', 'orig' => 'e'];

            $out[] = [
                'id'      => (int) $map['question_id'],
                'sort'    => (int) $map['sort_order'],
                'text'    => (string) ($snapshot['question_text'] ?? ''),
                'options' => $options,
            ];
        }

        return $out;
    }

    public function answeredIds(array $attempt): array
    {
        return array_map('intval', array_column($this->answers->forAttempt((int) $attempt['id']), 'question_id'));
    }
}
