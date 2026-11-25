<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizAttemptModel extends Model
{
    protected $table         = 'quiz_attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'quiz_id', 'student_email', 'attempt_no', 'access_code_id', 'access_code', 'status', 'device_token',
        'device_bound_at', 'started_at', 'expires_at', 'submitted_at',
        'raw_score', 'max_score', 'percentage', 'created_at', 'updated_at',
    ];

    public function findForStudent(int $id, string $email): ?array
    {
        return $this->where('id', $id)->where('student_email', $email)->first();
    }

    public function attemptsUsed(int $quizId, string $email): int
    {
        return $this->where('quiz_id', $quizId)
            ->where('student_email', $email)
            ->whereIn('status', ['in_progress', 'submitted', 'timeout'])
            ->countAllResults();
    }

    /** Nomor percobaan berikutnya mahasiswa pada satu quiz (per mahasiswa, bukan global). */
    public function nextAttemptNo(int $quizId, string $email): int
    {
        return $this->attemptsUsed($quizId, $email) + 1;
    }

    /** True bila mahasiswa sudah pernah memulai attempt dengan kode tertentu (one code = one attempt). */
    public function hasUsedCode(int $accessCodeId, string $email): bool
    {
        return $this->where('access_code_id', $accessCodeId)
            ->where('student_email', $email)
            ->whereIn('status', ['in_progress', 'submitted', 'timeout'])
            ->countAllResults() > 0;
    }

    public function hasActiveAttempt(int $quizId, string $email): ?array
    {
        return $this->where('quiz_id', $quizId)
            ->where('student_email', $email)
            ->where('status', 'in_progress')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Attempt aktif apa pun quiz-nya (untuk pemulihan sesi). */
    public function hasActiveAttemptAnyQuiz(string $email): ?array
    {
        return $this->where('student_email', $email)
            ->where('status', 'in_progress')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Attempt pertama milik mahasiswa tertentu pada satu quiz (owner kode). */
    public function firstAttemptFor(int $quizId, string $email): ?array
    {
        return $this->where('quiz_id', $quizId)
            ->where('student_email', $email)
            ->orderBy('id', 'ASC')
            ->first();
    }

    /**
     * DEPRECATED: satuan attempt global per quiz tidak berlaku lagi.
     * Satu quiz kini mendukung banyak attempt (per kode/per mahasiswa).
     */
    public function attemptsUsedForQuiz(int $quizId): int
    {
        return $this->where('quiz_id', $quizId)
            ->whereIn('status', ['in_progress', 'submitted', 'timeout'])
            ->countAllResults();
    }

    public function forQuiz(int $quizId): array
    {
        return $this->select('quiz_attempts.*, students.nama, COALESCE(quiz_attempts.access_code, quiz_quizzes.access_code) AS access_code')
            ->join('students', 'students.email = quiz_attempts.student_email')
            ->join('quiz_quizzes', 'quiz_quizzes.id = quiz_attempts.quiz_id')
            ->where('quiz_attempts.quiz_id', $quizId)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout'])
            ->orderBy('quiz_attempts.id', 'DESC')
            ->findAll();
    }

    /**
     * Nilai mahasiswa untuk halaman Nilai Mahasiswa (dengan pencarian + pagination).
     * Hanya attempt yang sudah selesai (submitted/timeout = valid).
     */
    public function gradesForQuiz(int $quizId, ?string $search, int $perPage, int $offset): array
    {
        $builder = $this->select('quiz_attempts.*, students.nama, students.npm, students.kelas')
            ->join('students', 'students.email = quiz_attempts.student_email')
            ->where('quiz_attempts.quiz_id', $quizId)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout']);

        if ($search !== null && $search !== '') {
            $builder->groupStart()
                ->like('students.npm', $search)
                ->orLike('students.nama', $search)
                ->groupEnd();
        }

        return $builder->orderBy('quiz_attempts.submitted_at', 'DESC')->findAll($perPage, $offset);
    }

    public function countGradesForQuiz(int $quizId, ?string $search): int
    {
        $builder = $this->select('quiz_attempts.id')
            ->join('students', 'students.email = quiz_attempts.student_email')
            ->where('quiz_attempts.quiz_id', $quizId)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout']);

        if ($search !== null && $search !== '') {
            $builder->groupStart()
                ->like('students.npm', $search)
                ->orLike('students.nama', $search)
                ->groupEnd();
        }

        return $builder->countAllResults();
    }

    /** Seluruh nilai quiz (untuk export Excel) tanpa limit. */
    public function allGradesForQuiz(int $quizId): array
    {
        return $this->select('quiz_attempts.*, students.nama, students.npm, students.kelas')
            ->join('students', 'students.email = quiz_attempts.student_email')
            ->where('quiz_attempts.quiz_id', $quizId)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout'])
            ->orderBy('quiz_attempts.submitted_at', 'DESC')
            ->findAll();
    }

    public function summaryForStudent(string $email): array
    {
        return $this->select('quiz_attempts.*, quiz_quizzes.title, subjects.kode_mk, subjects.nama_mk')
            ->join('quiz_quizzes', 'quiz_quizzes.id = quiz_attempts.quiz_id')
            ->join('subjects', 'subjects.id = quiz_quizzes.subject_id')
            ->where('quiz_attempts.student_email', $email)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout'])
            ->orderBy('quiz_attempts.submitted_at', 'DESC')
            ->findAll();
    }

    /**
     * Histori keikutsertaan mahasiswa: tiap baris adalah satu percobaan
     * dengan kode kuisnya masing-masing. Nomor percobaan dihitung backend
     * berdasarkan urutan attempt mahasiswa pada quiz yang sama.
     */
    public function historyForStudent(string $email): array
    {
        $rows = $this->select('quiz_attempts.*, quiz_quizzes.title, subjects.kode_mk, subjects.nama_mk')
            ->join('quiz_quizzes', 'quiz_quizzes.id = quiz_attempts.quiz_id')
            ->join('subjects', 'subjects.id = quiz_quizzes.subject_id')
            ->where('quiz_attempts.student_email', $email)
            ->whereIn('quiz_attempts.status', ['submitted', 'timeout'])
            ->orderBy('quiz_attempts.quiz_id', 'ASC')
            ->orderBy('quiz_attempts.id', 'ASC')
            ->findAll();

        // Nomor percobaan per (quiz, mahasiswa) dihitung dari data backend.
        $counter = [];
        foreach ($rows as &$r) {
            $qid = (int) $r['quiz_id'];
            $counter[$qid] = ($counter[$qid] ?? 0) + 1;
            $r['attempt_no'] = (int) ($r['attempt_no'] ?: $counter[$qid]);
        }
        unset($r);

        return $rows;
    }
}
