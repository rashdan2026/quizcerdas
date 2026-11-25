<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

/**
 * Histori penerbitan Kode Kuis per quiz.
 * Satu quiz dapat memiliki banyak kode sepanjang waktu; hanya satu yang aktif.
 * Kode lama tetap tersimpan (is_active = 0) untuk histori dan integritas attempt.
 */
class QuizAccessCodeModel extends Model
{
    protected $table         = 'quiz_access_codes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'quiz_id', 'code', 'is_active', 'created_by', 'created_at', 'deactivated_at',
    ];

    /** Kode aktif sebuah quiz (atau null bila belum ada). */
    public function activeForQuiz(int $quizId): ?array
    {
        return $this->where('quiz_id', $quizId)->where('is_active', 1)->orderBy('id', 'DESC')->first();
    }

    /** Kode aktif banyak quiz sekaligus: [quiz_id => row]. */
    public function activeForQuizzes(array $quizIds): array
    {
        if ($quizIds === []) {
            return [];
        }

        $rows = $this->whereIn('quiz_id', $quizIds)->where('is_active', 1)->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['quiz_id']] = $r;
        }

        return $out;
    }

    /** Histori seluruh kode sebuah quiz (terbaru dulu). */
    public function historyForQuiz(int $quizId): array
    {
        return $this->where('quiz_id', $quizId)->orderBy('id', 'DESC')->findAll();
    }

    /** Cari kode berdasarkan string kode (case-insensitive, sudah di-upper oleh pemanggil). */
    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }

    /**
     * Terbitkan kode baru untuk quiz: nonaktifkan semua kode aktif lama,
     * lalu sisipkan kode baru yang aktif. Kode lama tidak dihapus/diubah.
     * Mengembalikan row kode baru.
     */
    public function publishNewCode(int $quizId, int $lecturerId, string $code): array
    {
        $db = db_connect();
        $db->transBegin();

        $now = date('Y-m-d H:i:s');

        // Nonaktifkan kode aktif lama (tetap tersimpan sebagai histori).
        $this->where('quiz_id', $quizId)->where('is_active', 1)->update(null, [
            'is_active'      => 0,
            'deactivated_at' => $now,
        ]);

        $id = $this->insert([
            'quiz_id'        => $quizId,
            'code'           => $code,
            'is_active'      => 1,
            'created_by'     => $lecturerId,
            'created_at'     => $now,
            'deactivated_at' => null,
        ], true);

        if (! $id) {
            $db->transRollback();

            return [];
        }

        // Sinkronkan kode aktif pada quiz_quizzes (kompatibilitas tampilan lama).
        $db->table('quiz_quizzes')->where('id', $quizId)->update([
            'access_code' => $code,
            'updated_at'  => $now,
        ]);

        $db->transCommit();

        return $this->find($id) ?? [];
    }
}
