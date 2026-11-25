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
}
