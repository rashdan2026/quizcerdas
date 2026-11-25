<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizModel extends Model
{
    protected $table         = 'quiz_quizzes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'lecturer_id', 'subject_id', 'title', 'description', 'status', 'access_code',
        'correct_points', 'wrong_penalty', 'starts_at', 'ends_at',
        'duration_minutes', 'max_attempts', 'created_at', 'updated_at',
    ];

    public function forLecturer(int $lecturerId): array
    {
        return $this->select('quiz_quizzes.*, subjects.kode_mk, subjects.nama_mk')
            ->join('subjects', 'subjects.id = quiz_quizzes.subject_id')
            ->where('quiz_quizzes.lecturer_id', $lecturerId)
            ->orderBy('quiz_quizzes.id', 'DESC')
            ->findAll();
    }

    public function findOwned(int $id, int $lecturerId): ?array
    {
        return $this->where('id', $id)->where('lecturer_id', $lecturerId)->first();
    }

    /**
     * Alphabet kode quiz: huruf uppercase + angka yang tidak mirip secara visual
     * (tanpa O/0, I/1/L/1, Z/2, S/5, G/6, B/8).
     */
    public const CODE_ALPHABET = 'ACDEFHJKMNPQRTUVWXY3479';

    /** Batas percobaan generate saat collision. */
    private const CODE_MAX_TRIES = 10;

    /**
     * Generator kode peserta: tepat 6 karakter, secure random (random_int),
     * unik terhadap seluruh kode yang pernah diterbitkan (aktif maupun historis,
     * dicek pada quiz_access_codes maupun quiz_quizzes legacy).
     */
    public function generateAccessCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $len      = strlen($alphabet);
        $codes    = new QuizAccessCodeModel();

        for ($try = 0; $try < self::CODE_MAX_TRIES; $try++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $len - 1)];
            }
            $inHistory = $codes->where('code', $code)->countAllResults();
            $inQuizzes = $this->where('access_code', $code)->countAllResults();
            if ($inHistory === 0 && $inQuizzes === 0) {
                return $code;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan kode kuis unik setelah ' . self::CODE_MAX_TRIES . ' percobaan.');
    }

    /**
     * Cari quiz berdasarkan kode pada tabel histori kode.
     * Mengembalikan [quiz => row, code => row] atau null bila kode tak dikenal.
     */
    public function findByAccessCode(string $code): ?array
    {
        $codeRow = (new QuizAccessCodeModel())->findByCode($code);
        if ($codeRow === null) {
            return null;
        }

        $quiz = $this->find((int) $codeRow['quiz_id']);
        if ($quiz === null) {
            return null;
        }

        return ['quiz' => $quiz, 'code' => $codeRow];
    }

    /** Validasi format kode di backend: format baru (6 karakter) atau legacy. */
    public static function isValidAccessCodeFormat(string $code): bool
    {
        if (preg_match('/^[' . self::CODE_ALPHABET . ']{6}$/', $code) === 1) {
            return true; // format baru
        }

        // Kode lama (format sebelumnya: QZ + 10 hex) tetap didukung masa transisi.
        return preg_match('/^QZ[0-9A-F]{10}$/', $code) === 1;
    }

    public function questionCount(int $quizId): int
    {
        return (new QuizQuestionModel())->where('quiz_id', $quizId)->where('is_active', 1)->countAllResults();
    }

    public function maxScore(int $quizId, ?int $questionCount = null): int
    {
        $quiz   = $this->find($quizId);
        $count  = $questionCount ?? $this->questionCount($quizId);

        return $count * (int) ($quiz['correct_points'] ?? 5);
    }
}
