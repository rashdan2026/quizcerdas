<?php

namespace App\Commands;

use App\Libraries\Quiz\QuizScoringService;
use App\Models\Quiz\QuizAccessCodeModel;
use App\Models\Quiz\QuizAttemptModel;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Regression test alur ujian mahasiswa (dijalankan lewat service layer,
 * tanpa HTTP) — memastikan optimasi query tidak mengubah perilaku bisnis:
 *
 *  1. startAttempt: snapshot soal + acak urutan + durasi per soal.
 *  2. progressState: soal aktif satu-satu, timer TIDAK direset saat dibaca ulang.
 *  3. recordAnswer: validasi soal aktif, penilaian, idempotent (request ganda).
 *  4. Timeout per soal: soal lewat deadline -> nilai 0, lanjut otomatis.
 *  5. Finalisasi: skor/persentase benar; finalisasi ganda tidak mengubah nilai.
 *  6. One-code-one-attempt: kode sama tidak bisa memulai attempt kedua;
 *     kode baru pada quiz sama -> attempt_no bertambah, histori terpisah.
 *  7. Attempt kedua tidak melihat jawaban attempt pertama.
 *
 * Data uji dibuat dengan prefix SELFTEST_ dan dihapus di akhir (quiz
 * dihapus -> attempt/jawaban/map ikut ter-CASCADE). Aman untuk dijalankan
 * berulang; JANGAN dijalankan saat ujian berlangsung (menyentuh tabel yang sama).
 *
 *   php spark quiz:selftest
 */
class QuizSelfTest extends BaseCommand
{
    private const STUDENT_EMAIL = 'SELFTEST_student@local.test';
    private const LECTURER_EMAIL = 'SELFTEST_lecturer@local.test';
    private const KODE_MK = 'SELFTEST1';

    protected $group       = 'Quiz';
    protected $name        = 'quiz:selftest';
    protected $description = 'Regression test alur ujian (snapshot, timer, idempotensi, timeout, penilaian, one-code-one-attempt).';
    protected $usage       = 'quiz:selftest';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $db = db_connect();
        $cleanupIds = [];

        // Bersihkan sisa uji sebelumnya (idempotent bila dijalankan berulang).
        $db->table('students')->where('email', self::STUDENT_EMAIL)->delete();
        $db->table('lecturers')->where('email', self::LECTURER_EMAIL)->delete();
        $db->table('subjects')->where('kode_mk', self::KODE_MK)->delete();

        try {
            // ===== Siapkan data uji =====
            $db->table('students')->insert([
                // NPM unik per eksekusi (kolom students.npm UNIQUE).
                'npm' => '9' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'email' => self::STUDENT_EMAIL, 'nama' => 'Selftest Mahasiswa',
                'kelas' => 'Z', 'no_whatsapp' => '000', 'password' => password_hash('x', PASSWORD_DEFAULT), 'login_count' => 0,
            ]);
            $db->table('lecturers')->insert([
                'nama' => 'Selftest Dosen', 'email' => self::LECTURER_EMAIL,
                'password' => password_hash('x', PASSWORD_DEFAULT), 'is_active' => 1,
            ]);
            $lecturerId = (int) $db->insertID();
            $db->table('subjects')->insert([
                'kode_mk' => self::KODE_MK, 'nama_mk' => 'Selftest MK', 'is_active' => 1, 'dosen_id' => $lecturerId,
            ]);
            $subjectId = (int) $db->insertID();

            $quizModel = new QuizModel();
            $quizId = (int) $quizModel->insert([
                'lecturer_id' => $lecturerId, 'subject_id' => $subjectId,
                'title' => 'SELFTEST Quiz', 'description' => null, 'status' => 'active',
                'access_code' => $quizModel->generateAccessCode(),
                'correct_points' => 5, 'wrong_penalty' => 1,
                'starts_at' => date('Y-m-d H:i:s', time() - 60),
                'ends_at' => date('Y-m-d H:i:s', time() + 3600),
                'duration_minutes' => 30,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ], true);
            $cleanupIds['quiz'][] = $quizId;

            $codeModel = new QuizAccessCodeModel();
            $codeId = (int) $codeModel->insert([
                'quiz_id' => $quizId, 'code' => $quizModel->generateAccessCode(), 'is_active' => 1,
                'created_by' => $lecturerId, 'created_at' => date('Y-m-d H:i:s'),
            ], true);

            $questionModel = new QuizQuestionModel();
            $now = date('Y-m-d H:i:s');
            $questionIds = [];
            foreach ([
                ['A', 'Apakah 1+1?', 'Dua', 'Tiga', 'Empat', 'Lima', 60, 'easy'],
                ['B', 'Apakah 2+2?', 'Lima', 'Empat', 'Enam', 'Tujuh', 60, 'medium'],
                ['C', 'Apakah 3+3?', 'Tujuh', 'Delapan', 'Enam', 'Sembilan', 60, 'hard'],
            ] as [$correct, $text, $a, $b, $c, $d, $dur, $level]) {
                $questionIds[] = (int) $questionModel->insert([
                    'quiz_id' => $quizId, 'batch_id' => null,
                    'question_text' => $text, 'option_a' => $a, 'option_b' => $b, 'option_c' => $c, 'option_d' => $d,
                    'option_e' => 'Tidak tahu', 'correct_option' => $correct, 'explanation' => null,
                    'duration_seconds' => $dur, 'difficulty_level' => $level, 'ai_difficulty_level' => $level,
                    'source_type' => 'manual', 'created_by' => $lecturerId, 'sort_order' => count($questionIds) + 1,
                    'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ], true);
            }

            $service = new QuizScoringService();
            $attemptModel = new QuizAttemptModel();
            $quiz = $quizModel->find($quizId);
            $codeRow = $codeModel->find($codeId);

            // ===== 1. Start attempt =====
            $res = $service->startAttempt($quiz, self::STUDENT_EMAIL, $codeRow);
            $this->check($res['ok'] === true, 'startAttempt ok');
            $attemptId = (int) $res['attempt_id'];
            $cleanupIds['attemptDirect'] = $attemptId;

            $attempt = $attemptModel->find($attemptId);
            $this->check((int) $attempt['attempt_no'] === 1, 'attempt_no = 1');
            $this->check((int) $attempt['max_score'] === 15, 'max_score snapshot = 15 (3 soal x 5)');

            // ===== 2. Progres + timer tidak reset =====
            $state = $service->progressState($attempt);
            $this->check($state['current'] !== null, 'progressState menemukan soal aktif');
            $this->check((int) $state['total'] === 3 && (int) $state['answered'] === 0, 'total=3, answered=0');
            $this->check((int) $state['index'] === 1, 'index soal pertama = 1');
            $started1 = $state['current']['question_started_at'];
            $this->check($started1 !== null, 'timer soal dimulai server-side');

            $state2 = $service->progressState($attempt);
            $this->check($state2['current']['question_started_at'] === $started1, 'timer TIDAK reset saat progress dibaca ulang (refresh)');

            $q1 = (int) $state['current']['question_id'];
            $snapshot = json_decode($state['current']['snapshot'], true);
            $this->check(isset($snapshot['correct_option']), 'snapshot memuat kunci (server-side)');
            $order = explode(',', $state['current']['option_order']);
            $this->check(count($order) === 4 && count(array_unique($order)) === 4, 'opsi A-D diacak utuh');

            // ===== 3. Jawaban: salah, benar, idempotent =====
            // Cari huruf TAMPILAN yang salah & benar untuk Q1 dari option_order.
            $correctOrig = $snapshot['correct_option'];
            $correctDisplay = chr(65 + array_search(strtolower($correctOrig), $order, true));
            $wrongDisplay = $correctDisplay === 'A' ? 'B' : 'A';

            $r = $service->recordAnswer($attempt, $q1, $wrongDisplay);
            $this->check(($r['ok'] ?? false) === true && empty($r['finished']), 'jawaban salah Q1 tersimpan');

            // Race dua request bersamaan: idempotensi diuji langsung di lapisan
            // penyimpanan (unique key attempt+question).
            $dup = (new \App\Models\Quiz\QuizAttemptAnswerModel())->saveAnswer([
                'attempt_id' => $attemptId, 'question_id' => $q1, 'selected_option' => $wrongDisplay,
                'is_correct' => 0, 'points_awarded' => -99, 'answered_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->check($dup === 'duplicate', 'penyimpanan ganda (race) terdeteksi duplicate — tidak menimpa nilai');
            // Menjawab ulang soal yang sudah lewat (sekuensial) -> ditolak.
            $r2 = $service->recordAnswer($attempt, $q1, $wrongDisplay);
            $this->check(($r2['ok'] ?? true) === false && ($r2['error'] ?? '') === 'not_current_question', 'request ganda Q1 (sekuensial) -> ditolak, bukan duplikat nilai');

            $cnt = $db->table('quiz_attempt_answers')->where('attempt_id', $attemptId)->where('question_id', $q1)->countAllResults();
            $this->check($cnt === 1, 'hanya 1 baris jawaban Q1 di database');

            $state = $service->progressState($attempt);
            $q2 = (int) $state['current']['question_id'];
            $this->check((int) $state['answered'] === 1 && (int) $state['index'] === 2, 'progres maju ke soal 2');

            // Huruf benar Q2 dihitung dari snapshot & urutan opsi Q2 sendiri.
            $snap2 = json_decode($state['current']['snapshot'], true);
            $order2 = explode(',', $state['current']['option_order']);
            $correctDisplay2 = chr(65 + array_search(strtolower($snap2['correct_option']), $order2, true));

            $r = $service->recordAnswer($attempt, $q2, $correctDisplay2);
            $this->check(($r['ok'] ?? false) === true, 'jawaban Q2 (benar) tersimpan');
            // Jawab soal yang bukan aktif (Q1 lagi) -> ditolak.
            $r = $service->recordAnswer($attempt, $q1, 'A');
            $this->check(($r['ok'] ?? true) === false, 'menjawab soal yang sudah lewat -> ditolak');

            // ===== 4. Timeout soal ketiga: nilai 0, otomatis selesai =====
            $state = $service->progressState($attempt);
            $q3 = (int) $state['current']['question_id'];
            // Percepat deadline Q3 menjadi lampau.
            $db->table('quiz_attempt_question_map')
                ->where('attempt_id', $attemptId)->where('question_id', $q3)
                ->update(['question_deadline_at' => date('Y-m-d H:i:s', time() - 5)]);

            $state = $service->progressState($attempt);
            $this->check($state['current'] === null, 'soal timeout dilewati otomatis (attempt selesai)');

            $ans3 = $db->table('quiz_attempt_answers')->where('attempt_id', $attemptId)->where('question_id', $q3)->get()->getRowArray();
            $this->check($ans3 !== null && (int) $ans3['points_awarded'] === 0, 'jawaban timeout tercatat nilai 0');

            // ===== 5. Finalisasi & penilaian =====
            $f = $service->finalize($attempt, 'submitted');
            $this->check(($f['ok'] ?? false) === true, 'finalize ok');
            // Q1 salah (-1), Q2 benar (+5), Q3 timeout (0) -> raw = 4, max = 15.
            $this->check($f['raw_score'] === 4, 'skor mentah = 4 (Q1 salah -1, Q2 benar +5, Q3 timeout 0)');
            $this->check($f['max_score'] === 15, 'skor maksimal = 15');
            $this->check((float) $f['percentage'] === round(4 / 15 * 100, 2), 'persentase benar');

            $f2 = $service->finalize($attempt, 'submitted');
            $this->check(($f2['ok'] ?? true) === false, 'finalisasi ganda ditolak (nilai tidak berubah)');

            // ===== 6. One-code-one-attempt + kode baru =====
            $res = $service->startAttempt($quiz, self::STUDENT_EMAIL, $codeRow);
            $this->check(($res['ok'] ?? true) === false && ($res['error'] ?? '') === 'code_used', 'kode sama tidak bisa memulai attempt kedua');

            $codeId2 = (int) $codeModel->insert([
                'quiz_id' => $quizId, 'code' => $quizModel->generateAccessCode(), 'is_active' => 1,
                'created_by' => $lecturerId, 'created_at' => date('Y-m-d H:i:s'),
            ], true);
            $res = $service->startAttempt($quiz, self::STUDENT_EMAIL, $codeModel->find($codeId2));
            $this->check(($res['ok'] ?? false) === true, 'kode baru boleh memulai attempt baru');
            $attempt2Id = (int) $res['attempt_id'];
            $cleanupIds['attemptDirect2'] = $attempt2Id;

            $attempt2 = $attemptModel->find($attempt2Id);
            $this->check((int) $attempt2['attempt_no'] === 2, 'attempt_no kode baru = 2');
            $this->check((int) $attempt2['raw_score'] === 0, 'attempt baru mulai bersih (skor 0)');

            // ===== 7. Attempt kedua tidak melihat jawaban attempt pertama =====
            $state = $service->progressState($attempt2);
            $this->check((int) $state['answered'] === 0, 'histori attempt pertama tidak terbawa ke attempt kedua');
            $sameQuizAttempts = $attemptModel->where('quiz_id', $quizId)->where('student_email', self::STUDENT_EMAIL)->countAllResults();
            $this->check($sameQuizAttempts === 2, 'histori multiple attempt terpisah (2 baris)');

            // Timeout attempt keseluruhan: jawaban ditolak.
            $db->table('quiz_attempts')->where('id', $attempt2Id)->update(['expires_at' => date('Y-m-d H:i:s', time() - 10)]);
            $attempt2 = $attemptModel->find($attempt2Id);
            $r = $service->recordAnswer($attempt2, (int) $state['current']['question_id'], 'A');
            $this->check(($r['ok'] ?? true) === false && ($r['error'] ?? '') === 'expired', 'jawaban setelah attempt kedaluwarsa ditolak server');

            // ===== 8. Query batch dashboard dosen (anti N+1) =====
            $qCounts = $questionModel->activeCountsForQuizzes([$quizId]);
            $this->check(($qCounts[$quizId] ?? 0) === 3, 'activeCountsForQuizzes: jumlah soal benar (3)');
            $gCounts = $attemptModel->gradedCountsForQuizzes([$quizId]);
            $this->check(($gCounts[$quizId] ?? 0) === 2, 'gradedCountsForQuizzes: attempt selesai benar (2)');
            $agg = $attemptModel->aggregateForQuiz($quizId);
            $this->check($agg['n'] === 2 && (float) $agg['avg_raw'] === 2.0, 'aggregateForQuiz: rata-rata benar ((4+0)/2)');
            $aStats = $questionModel->answerStatsForQuiz($quizId);
            $this->check(count($aStats) === 3, 'answerStatsForQuiz: statistik seluruh soal tersedia');
        } finally {
            // ===== Bersihkan data uji (cascade: attempt, jawaban, map, soal, kode) =====
            if (! empty($cleanupIds['quiz'])) {
                $db->table('quiz_quizzes')->whereIn('id', $cleanupIds['quiz'])->delete();
            }
            if (isset($cleanupIds['attemptDirect'])) {
                $db->table('quiz_attempts')->where('id', $cleanupIds['attemptDirect'])->delete();
            }
            if (isset($cleanupIds['attemptDirect2'])) {
                $db->table('quiz_attempts')->where('id', $cleanupIds['attemptDirect2'])->delete();
            }
            $db->table('subjects')->where('kode_mk', self::KODE_MK)->delete();
            $db->table('lecturers')->where('email', self::LECTURER_EMAIL)->delete();
            $db->table('students')->where('email', self::STUDENT_EMAIL)->delete();
        }

        CLI::write('-----------------------------');
        CLI::write("LULUS: {$this->pass}  GAGAL: {$this->fail}", $this->fail === 0 ? 'green' : 'red');

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function check(bool $condition, string $label): void
    {
        if ($condition) {
            $this->pass++;
            CLI::write('[PASS] ' . $label, 'green');
            return;
        }

        $this->fail++;
        CLI::write('[FAIL] ' . $label, 'red');
    }
}
