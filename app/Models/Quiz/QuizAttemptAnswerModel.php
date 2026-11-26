<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAttemptAnswerModel extends Model
{
    protected $table         = 'quiz_attempt_answers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id', 'question_id', 'selected_option', 'is_correct',
        'points_awarded', 'started_at', 'answered_at', 'response_time_ms', 'created_at',
    ];

    /**
     * Simpan jawaban (idempotent per attempt+soal lewat unique key).
     *
     * @return string 'inserted' | 'duplicate' (sudah ada — request ganda/race) | 'error'
     */
    public function saveAnswer(array $data): string
    {
        try {
            $this->db->table('quiz_attempt_answers')->insert($data);

            return 'inserted';
        } catch (\Throwable $e) {
            // Bisa dua request bersamaan menyisipkan jawaban yang sama.
            // Konfirmasi lewat unique key (attempt_id, question_id) sebelum
            // menyimpulkan ini error database sungguhan.
            try {
                $exists = $this->db->table('quiz_attempt_answers')
                    ->select('1')
                    ->where('attempt_id', (int) $data['attempt_id'])
                    ->where('question_id', (int) $data['question_id'])
                    ->limit(1)
                    ->get()
                    ->getRow();

                return $exists !== null ? 'duplicate' : 'error';
            } catch (\Throwable $ignored) {
                return 'error';
            }
        }
    }

    public function forAttempt(int $attemptId): array
    {
        return $this->where('attempt_id', $attemptId)->findAll();
    }

    /** Total skor mentah satu attempt: SUM(points_awarded) tanpa memuat baris. */
    public function sumPointsForAttempt(int $attemptId): int
    {
        $row = $this->db->table($this->table)
            ->selectSum('points_awarded')
            ->where('attempt_id', $attemptId)
            ->get()
            ->getRowArray();

        return (int) ($row['points_awarded'] ?? 0);
    }
}
