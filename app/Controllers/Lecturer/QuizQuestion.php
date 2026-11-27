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

    /**
     * Tambah soal MANUAL.
     * Tidak memanggil AI dan TIDAK menjalankan pemeriksaan duplikasi (SDD 3.2A/4.4).
     * Difficulty untuk soal manual = NULL (tidak diisi dosen, tidak diklasifikasi AI).
     */
    public function addManual(int $quizId)
    {
        $quiz = $this->ownedQuizOrFail($quizId);

        // Batas maksimal 100 soal per kuis (validasi backend).
        $db = db_connect();
        $db->transBegin();
        // Kunci baris quiz agar penambahan bersamaan tidak melewati batas.
        $db->query('SELECT id FROM quiz_quizzes WHERE id = ? FOR UPDATE', [$quizId]);
        $existing = (int) $this->model->where('quiz_id', $quizId)->where('is_active', 1)->countAllResults();

        if ($existing >= self::MAX_QUESTIONS_PER_QUIZ) {
            $db->transRollback();

            return redirect()->to('/lecturer/quiz/' . $quizId)->with('error',
                'Jumlah soal dalam Kuis ini sudah mencapai batas maksimal 100 soal. Penambahan soal dibatalkan. Silakan hapus beberapa soal terlebih dahulu jika ingin menambahkan soal baru.');
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
            return redirect()->to('/lecturer/quiz/' . $quizId)->with('error', 'Pertanyaan wajib diisi (maks ' . HtmlSanitizer::MAX_QUESTION_CHARS . ' karakter).');
        }
        foreach ($opts as $k => $v) {
            if ($v === '' || mb_strlen($v) > HtmlSanitizer::MAX_OPTION_CHARS) {
                return redirect()->to('/lecturer/quiz/' . $quizId)->with('error', 'Semua opsi A-D wajib diisi (maks ' . HtmlSanitizer::MAX_OPTION_CHARS . ' karakter).');
            }
        }
        if (! in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            return redirect()->to('/lecturer/quiz/' . $quizId)->with('error', 'Kunci jawaban hanya boleh A/B/C/D (E berarti "Tidak tahu").');
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

            return redirect()->to('/lecturer/quiz/' . $quizId)->with('error', 'Gagal menyimpan soal.');
        }

        return redirect()->to('/lecturer/quiz/' . $quizId)->with('success', 'Soal manual disimpan.');
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
        $q    = $this->ownedQuestionOrFail($questionId);
        $quiz = $this->ownedQuizOrFail((int) $q['quiz_id']);

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
            return redirect()->to('/lecturer/quiz/' . $q['quiz_id'])->with('error', 'Pertanyaan tidak valid (maks ' . HtmlSanitizer::MAX_QUESTION_CHARS . ' karakter).');
        }
        foreach ($opts as $v) {
            if ($v === '' || mb_strlen($v) > HtmlSanitizer::MAX_OPTION_CHARS) {
                return redirect()->to('/lecturer/quiz/' . $q['quiz_id'])->with('error', 'Semua opsi A-D wajib diisi (maks ' . HtmlSanitizer::MAX_OPTION_CHARS . ' karakter).');
            }
        }
        if (! in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            return redirect()->to('/lecturer/quiz/' . $q['quiz_id'])->with('error', 'Kunci jawaban hanya boleh A/B/C/D.');
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

        $this->model->update($questionId, [
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

        return redirect()->to('/lecturer/quiz/' . $q['quiz_id'])->with('success', 'Soal diperbarui.');
    }

    public function delete(int $questionId)
    {
        $q = $this->ownedQuestionOrFail($questionId);

        $this->model->update($questionId, ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('/lecturer/quiz/' . $q['quiz_id'])->with('success', 'Soal dihapus dari bank soal (histori attempt tetap aman).');
    }
}
