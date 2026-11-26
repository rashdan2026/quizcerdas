<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizQuestionModel extends Model
{
    protected $table         = 'quiz_questions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'quiz_id', 'batch_id', 'question_text', 'option_a', 'option_b', 'option_c',
        'option_d', 'option_e', 'correct_option', 'explanation', 'duration_seconds',
        'difficulty_level', 'ai_difficulty_level', 'source_type',
        'source_title', 'source_filename', 'source_section',
        'created_by', 'sort_order', 'is_active', 'created_at', 'updated_at',
    ];

    public function findOwned(int $id, int $lecturerId): ?array
    {
        return $this->select('quiz_questions.*')
            ->join('quiz_quizzes', 'quiz_quizzes.id = quiz_questions.quiz_id')
            ->where('quiz_questions.id', $id)
            ->where('quiz_quizzes.lecturer_id', $lecturerId)
            ->first();
    }

    public function forQuiz(int $quizId): array
    {
        return $this->where('quiz_id', $quizId)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /** Bank soal terbaru di atas + pagination backend (10 per halaman). */
    public function forQuizPaginated(int $quizId, ?string $search, int $perPage, int $offset): array
    {
        $builder = $this->where('quiz_id', $quizId)->where('is_active', 1);
        if ($search !== null && $search !== '') {
            $builder->like('question_text', $search);
        }

        return $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);
    }

    public function countForQuiz(int $quizId, ?string $search): int
    {
        $builder = $this->where('quiz_id', $quizId)->where('is_active', 1);
        if ($search !== null && $search !== '') {
            $builder->like('question_text', $search);
        }

        return $builder->countAllResults();
    }

    /**
     * Konteks duplikasi: hanya id + teks pertanyaan dari seluruh soal aktif
     * (manual & AI) pada satu kuis, diambil dengan SATU query. Opsi, kunci,
     * pembahasan, dan metadata lain tidak diperlukan untuk pemeriksaan duplikasi.
     *
     * @return list<array{id:int|string, question_text:string}>
     */
    public function questionTextsForQuiz(int $quizId): array
    {
        return $this->select('id, question_text')
            ->where('quiz_id', $quizId)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function difficultyStats(int $quizId): array
    {
        $rows = $this->select('source_type, difficulty_level, COUNT(*) AS total')
            ->where('quiz_id', $quizId)
            ->where('is_active', 1)
            ->groupBy('source_type, difficulty_level')
            ->findAll();

        $stats = ['manual' => 0, 'ai_pdf' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $stats[$r['source_type']] += (int) $r['total'];
            $stats['total']           += (int) $r['total'];
            if ($r['difficulty_level']) {
                $stats[$r['difficulty_level']] += (int) $r['total'];
            }
        }

        return $stats;
    }

    /**
     * Jumlah soal aktif untuk BANYAK kuis sekaligus (satu query GROUP BY,
     * menggantikan query COUNT per kuis di dashboard dosen): [quiz_id => int].
     */
    public function activeCountsForQuizzes(array $quizIds): array
    {
        if ($quizIds === []) {
            return [];
        }

        $rows = $this->db->table('quiz_questions')
            ->select('quiz_id, COUNT(*) AS total')
            ->whereIn('quiz_id', $quizIds)
            ->where('is_active', 1)
            ->groupBy('quiz_id')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['quiz_id']] = (int) $r['total'];
        }

        return $out;
    }

    /**
     * Statistik jawaban per soal untuk satu kuis dalam SATU query GROUP BY
     * (menggantikan satu query per soal pada halaman analitik dosen).
     * Filter via quiz_attempts.quiz_id memakai index (quiz_id, status).
     *
     * @return array<int, array{n:int, right:int, wrong:int, blank:int, rt_sum:int}>
     */
    public function answerStatsForQuiz(int $quizId): array
    {
        $rows = $this->db->table('quiz_attempt_answers a')
            ->select("a.question_id,
                COUNT(*) AS n,
                SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS right_count,
                SUM(CASE WHEN a.is_correct = 0 AND a.selected_option IS NOT NULL AND a.selected_option <> 'E' THEN 1 ELSE 0 END) AS wrong_count,
                SUM(CASE WHEN a.selected_option IS NULL OR a.selected_option = '' OR a.selected_option = 'E' THEN 1 ELSE 0 END) AS blank_count,
                SUM(COALESCE(a.response_time_ms, 0)) AS rt_sum")
            ->join('quiz_attempts t', 't.id = a.attempt_id')
            ->where('t.quiz_id', $quizId)
            ->whereIn('t.status', ['submitted', 'timeout'])
            ->groupBy('a.question_id')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['question_id']] = [
                'n'     => (int) $r['n'],
                'right' => (int) $r['right_count'],
                'wrong' => (int) $r['wrong_count'],
                'blank' => (int) $r['blank_count'],
                'rt_sum' => (int) $r['rt_sum'],
            ];
        }

        return $out;
    }
}
