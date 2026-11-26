<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAttemptQuestionMapModel extends Model
{
    protected $table         = 'quiz_attempt_question_map';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'attempt_id', 'question_id', 'sort_order', 'points', 'penalty',
        'snapshot', 'option_order', 'duration_seconds', 'question_started_at',
        'question_deadline_at', 'is_expired', 'created_at',
    ];

    public function forAttempt(int $attemptId): array
    {
        return $this->where('attempt_id', $attemptId)->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Baris soal yang BELUM selesai (belum dijawab dan belum dianggap timeout)
     * untuk satu attempt — TANPA kolom snapshot (LONGTEXT). Dipakai perhitungan
     * progres soal-aktif; snapshot hanya diambil untuk soal yang benar-benar
     * ditampilkan (lihat find()). Menggunakan LEFT JOIN pada tabel jawaban +
     * unique key (attempt_id, question_id) sehingga satu query ringan.
     */
    public function unfinishedRowsForAttempt(int $attemptId): array
    {
        return $this->db->table('quiz_attempt_question_map m')
            ->select('m.id, m.attempt_id, m.question_id, m.sort_order, m.points, m.penalty,
                m.option_order, m.duration_seconds, m.question_started_at, m.question_deadline_at, m.is_expired')
            ->join('quiz_attempt_answers a', 'a.attempt_id = m.attempt_id AND a.question_id = m.question_id', 'left')
            ->where('m.attempt_id', $attemptId)
            ->where('a.id', null)
            ->where('m.is_expired', 0)
            ->orderBy('m.sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Jumlah total soal satu attempt (index-covered). */
    public function countForAttempt(int $attemptId): int
    {
        return $this->db->table($this->table)
            ->where('attempt_id', $attemptId)
            ->countAllResults();
    }

    /** Total poin maksimal satu attempt: SUM(points) tanpa memuat baris. */
    public function sumPointsForAttempt(int $attemptId): int
    {
        $row = $this->db->table($this->table)
            ->selectSum('points')
            ->where('attempt_id', $attemptId)
            ->get()
            ->getRowArray();

        return (int) ($row['points'] ?? 0);
    }
}
