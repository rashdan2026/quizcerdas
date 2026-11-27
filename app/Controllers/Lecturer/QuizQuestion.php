<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Libraries\Quiz\HtmlSanitizer;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;

class QuizQuestion extends BaseController
{
    private QuizQuestionModel $model;
    private QuizModel $quizModel;

    public function __construct()
    {
        $this->model     = new QuizQuestionModel();
        $this->quizModel = new QuizModel();
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

    private const MAX_QUESTIONS_PER_QUIZ = 100;

    /** Respons JSON untuk AJAX (fetch dari halaman bank soal). */
    private function jsonOut(bool $success, string $message, array $data = [])
    {
        return $this->response->setJSON(['success' => $success, 'message' => $message, 'data' => $data]);
    }

    /**
     * Redirect kembali ke bank soal MEMPERTAHANKAN halaman pagination & kata
     * pencarian (dipass form via input hidden) — untuk jalur non-JS.
     */
    private function backToBank(int $quizId, string $flashKey, string $flashMsg)
    {
        $page = max(1, (int) ($this->request->getPost('page') ?? 1));
        $q    = trim((string) $this->request->getPost('q'));
        $url  = '/lecturer/quiz/' . $quizId . '?page=' . $page . ($q !== '' ? '&q=' . urlencode($q) : '');

        return redirect()->to($url)->with($flashKey, $flashMsg);
    }

    /**
     * Tambah soal MANUAL.
     * Tidak memanggil AI dan TIDAK menjalankan pemeriksaan duplikasi (SDD 3.2A/4.4).
     * Difficulty untuk soal manual = NULL (tidak diisi dosen, tidak diklasifikasi AI).
     */
    public function addManual(int $quizId)
    {
        $quiz   = $this->ownedQuizOrFail($quizId);
        $isAjax = $this->request->isAJAX();

        // Batas maksimal 100 soal per kuis (validasi backend).
        $db = db_connect();
        $db->transBegin();
        // Kunci baris quiz agar penambahan bersamaan tidak melewati batas.
        $db->query('SELECT id FROM quiz_quizzes WHERE id = ? FOR UPDATE', [$quizId]);
        $existing = (int) $this->model->where('quiz_id', $quizId)->where('is_active', 1)->countAllResults();

        if ($existing >= self::MAX_QUESTIONS_PER_QUIZ) {
            $db->transRollback();
            $msg = 'Jumlah soal dalam Kuis ini sudah mencapai batas maksimal 100 soal. Penambahan soal dibatalkan. Silakan hapus beberapa soal terlebih dahulu jika ingin menambahkan soal baru.';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank($quizId, 'error', $msg);
        }

        $text = trim((string) $this->request->getPost('question_text'));
        $opts = [];
        foreach (['a', 'b', 'c', 'd'] as $k) {
            $opts[$k] = trim((string) $this->request->getPost('option_' . $k));
        }
        $correct = strtoupper(trim((string) $this->request->getPost('correct_option')));
        $expl    = trim((string) $this->request->getPost('explanation'));
        $durRaw  = $this->request->getPost('duration_seconds');
        $dur     = ($durRaw !== null && $durRaw !== '') ? (int) $durRaw : null;

        // Sanitasi HTML allowlist (XSS) SEBELUM validasi & penyimpanan.
        // Karakter khusus kode program tidak diubah — SQL injection dicegah
        // oleh Query Builder (parameter binding), bukan dengan merusak data.
        $text = HtmlSanitizer::sanitizeQuestion($text);
        foreach ($opts as $k => $v) {
            $opts[$k] = HtmlSanitizer::sanitizeInline($v);
        }
        $expl = HtmlSanitizer::sanitizeInline($expl);

        if ($text === '' || mb_strlen($text) > HtmlSanitizer::MAX_QUESTION_CHARS) {
            $msg = 'Pertanyaan wajib diisi (maks ' . HtmlSanitizer::MAX_QUESTION_CHARS . ' karakter).';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank($quizId, 'error', $msg);
        }
        foreach ($opts as $k => $v) {
            if ($v === '' || mb_strlen($v) > HtmlSanitizer::MAX_OPTION_CHARS) {
                $msg = 'Semua opsi A-D wajib diisi (maks ' . HtmlSanitizer::MAX_OPTION_CHARS . ' karakter).';

                return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank($quizId, 'error', $msg);
            }
        }
        if (! in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            $msg = 'Kunci jawaban hanya boleh A/B/C/D (E berarti "Tidak tahu").';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank($quizId, 'error', $msg);
        }
        if ($dur !== null && ! in_array($dur, [30, 60, 90, 120], true)) {
            $dur = null;
        }

        $sort = (int) $this->model->where('quiz_id', $quizId)->countAllResults() + 1;

        $inserted = $this->model->insert([
            'quiz_id'             => $quizId,
            'batch_id'            => null,
            'question_text'       => $text,
            'option_a'            => $opts['a'],
            'option_b'            => $opts['b'],
            'option_c'            => $opts['c'],
            'option_d'            => $opts['d'],
            'option_e'            => 'Tidak tahu',
            'correct_option'      => $correct,
            'explanation'         => $expl !== '' ? $expl : null,
            'duration_seconds'    => $dur, // null = default 60 detik
            'difficulty_level'    => null, // manual: tidak ada difficulty
            'ai_difficulty_level' => null,
            'source_type'         => 'manual',
            'source_title'        => null,
            'source_filename'     => null,
            'source_section'      => null,
            'created_by'          => $this->lid(),
            'sort_order'          => $sort,
            'is_active'           => 1,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        if ($inserted) {
            $db->transCommit();
        } else {
            $db->transRollback();
            $msg = 'Gagal menyimpan soal. Silakan coba lagi.';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank($quizId, 'error', $msg);
        }

        $msg = 'Soal berhasil disimpan.';

        return $isAjax
            ? $this->jsonOut(true, $msg, ['id' => (int) $inserted])
            : $this->backToBank($quizId, 'success', $msg);
    }

    private function ownedQuestionOrFail(int $questionId): array
    {
        $q = $this->model->findOwned($questionId, $this->lid());
        if ($q === null) {
            throw new \CodeIgniter\HTTP\Exceptions\PageNotFoundException();
        }

        return $q;
    }

    /**
     * Edit soal (manual atau AI).
     * Difficulty hanya boleh diedit untuk soal AI; edit manual tidak memicu dup-check.
     * Perubahan setelah attempt tidak mengubah histori (snapshot attempt).
     */
    public function update(int $questionId)
    {
        $q      = $this->ownedQuestionOrFail($questionId);
        $quiz   = $this->ownedQuizOrFail((int) $q['quiz_id']);
        $isAjax = $this->request->isAJAX();

        $text = trim((string) $this->request->getPost('question_text'));
        $opts = [];
        foreach (['a', 'b', 'c', 'd'] as $k) {
            $opts[$k] = trim((string) $this->request->getPost('option_' . $k));
        }
        $correct = strtoupper(trim((string) $this->request->getPost('correct_option')));
        $expl    = trim((string) $this->request->getPost('explanation'));
        $durRaw  = $this->request->getPost('duration_seconds');
        $dur     = ($durRaw !== null && $durRaw !== '') ? (int) $durRaw : null;

        // Sanitasi HTML allowlist (XSS) — sama dengan jalur tambah soal.
        $text = HtmlSanitizer::sanitizeQuestion($text);
        foreach ($opts as $k => $v) {
            $opts[$k] = HtmlSanitizer::sanitizeInline($v);
        }
        $expl = HtmlSanitizer::sanitizeInline($expl);

        if ($text === '' || mb_strlen($text) > HtmlSanitizer::MAX_QUESTION_CHARS) {
            $msg = 'Pertanyaan tidak valid (maks ' . HtmlSanitizer::MAX_QUESTION_CHARS . ' karakter).';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank((int) $q['quiz_id'], 'error', $msg);
        }
        foreach ($opts as $v) {
            if ($v === '' || mb_strlen($v) > HtmlSanitizer::MAX_OPTION_CHARS) {
                $msg = 'Semua opsi A-D wajib diisi (maks ' . HtmlSanitizer::MAX_OPTION_CHARS . ' karakter).';

                return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank((int) $q['quiz_id'], 'error', $msg);
            }
        }
        if (! in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            $msg = 'Kunci jawaban hanya boleh A/B/C/D.';

            return $isAjax ? $this->jsonOut(false, $msg) : $this->backToBank((int) $q['quiz_id'], 'error', $msg);
        }
        if ($dur !== null && ! in_array($dur, [30, 60, 90, 120], true)) {
            $dur = null; // null = otomatis (per difficulty untuk soal AI)
        }

        $difficulty = null;
        if ($q['source_type'] === 'ai_pdf') {
            $difficulty = strtolower(trim((string) $this->request->getPost('difficulty_level')));
            if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = $q['difficulty_level'];
            }
        }

        $ok = (bool) $this->model->update($questionId, [
            'question_text'   => $text,
            'option_a'        => $opts['a'],
            'option_b'        => $opts['b'],
            'option_c'        => $opts['c'],
            'option_d'        => $opts['d'],
            'correct_option'  => $correct,
            'explanation'     => $expl !== '' ? $expl : null,
            'duration_seconds'=> $dur, // durasi pengerjaan per soal
            'difficulty_level'=> $difficulty, // ai_difficulty_level (label awal AI) tetap
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $msg = $ok ? 'Soal berhasil disimpan.' : 'Gagal menyimpan soal. Silakan coba lagi.';

        return $isAjax
            ? $this->jsonOut($ok, $msg, ['id' => (int) $questionId])
            : $this->backToBank((int) $q['quiz_id'], $ok ? 'success' : 'error', $msg);
    }

    public function delete(int $questionId)
    {
        $q      = $this->ownedQuestionOrFail($questionId);
        $isAjax = $this->request->isAJAX();

        $this->model->update($questionId, ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        $msg = 'Soal dihapus dari bank soal (histori attempt tetap aman).';

        return $isAjax
            ? $this->jsonOut(true, $msg, ['id' => (int) $questionId])
            : $this->backToBank((int) $q['quiz_id'], 'success', $msg);
    }
}
