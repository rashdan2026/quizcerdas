<?php

namespace App\Models\Quiz;

use CodeIgniter\Model;

class QuizBatchModel extends Model
{
    /** Status yang menandakan proses batch sudah selesai (tidak akan berubah lagi). */
    public const FINAL_STATUSES = ['completed', 'partial', 'failed'];

    /** Satu-satunya status menggantung: batch sedang diproses (tanpa antrean). */
    public const ACTIVE_STATUSES = ['processing'];

    /** Pesan aman (tanpa detail internal) untuk batch yang prosesnya mati di tengah jalan. */
    public const INTERRUPTED_MESSAGE = 'Proses generate terhenti sebelum selesai (batas waktu server tercapai). Tidak ada soal yang disimpan dari batch ini.';

    /** Pesan untuk batch "queued" warisan versi lama yang tidak pernah diproses. */
    public const QUEUED_CANCELLED_MESSAGE = 'Generate dibatalkan (batch tidak pernah diproses). Tidak ada soal yang disimpan.';

    protected $table         = 'quiz_question_batches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'quiz_id', 'lecturer_id', 'source_type', 'original_filename', 'file_size_bytes',
        'file_sha256', 'source_title', 'requested_count', 'generated_count',
        'duplicate_count', 'retry_count', 'difficulty_easy_pct', 'difficulty_medium_pct',
        'difficulty_hard_pct', 'provider_used', 'model_used', 'status', 'status_message',
        'extract_path', 'pdf_path', 'scan_status', 'scan_result', 'scan_at', 'scan_engine',
        'token_prompt', 'token_completion', 'uploaded_at', 'expires_at',
        'created_at', 'updated_at',
    ];

    public function findOwned(int $id, int $lecturerId): ?array
    {
        return $this->where('id', $id)->where('lecturer_id', $lecturerId)->first();
    }

    public function forQuiz(int $quizId): array
    {
        return $this->where('quiz_id', $quizId)->orderBy('id', 'DESC')->findAll();
    }

    /** Batch yang sedang diproses pada satu kuis, bila ada (tanpa antrean). */
    public function activeForQuiz(int $quizId): ?array
    {
        return $this->where('quiz_id', $quizId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Batch TERBARU pada satu kuis (untuk polling status generate). */
    public function latestForQuiz(int $quizId): ?array
    {
        return $this->where('quiz_id', $quizId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function setStatusMessage(int $batchId, string $message): void
    {
        $this->update($batchId, [
            'status_message' => mb_substr($message, 0, 255),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Bersihkan batch menggantung:
     * - "queued" warisan versi lama tidak akan pernah diproses lagi → gagalkan
     *   langsung (tidak boleh memblokir generate baru).
     * - "processing"/"quarantine" yang sudah lama tanpa pembaruan berarti
     *   prosesnya mati sebelum selesai → gagalkan agar tidak menggantung.
     */
    public function failStaleProcessing(int $quizId, int $staleSeconds = 900): int
    {
        $db     = db_connect();
        $cutoff = date('Y-m-d H:i:s', time() - $staleSeconds);

        $db->table('quiz_question_batches')
            ->where('quiz_id', $quizId)
            ->where('status', 'queued')
            ->update([
                'status'          => 'failed',
                'generated_count' => 0,
                'status_message'  => self::QUEUED_CANCELLED_MESSAGE,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        $count = $db->affectedRows();

        $db->table('quiz_question_batches')
            ->where('quiz_id', $quizId)
            ->whereIn('status', ['processing', 'quarantine'])
            ->where('updated_at <', $cutoff)
            ->update([
                'status'          => 'failed',
                'generated_count' => 0,
                'status_message'  => self::INTERRUPTED_MESSAGE,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

        return $count + $db->affectedRows();
    }

    /** Dipanggil dari shutdown guard saat skrip mati di tengah proses. */
    public function markInterrupted(int $batchId): void
    {
        $db = db_connect();

        $db->table('quiz_question_batches')
            ->where('id', $batchId)
            ->whereIn('status', ['processing', 'quarantine'])
            ->update([
                'status'          => 'failed',
                'generated_count' => 0,
                'status_message'  => self::INTERRUPTED_MESSAGE,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
    }

    public function expired(): array
    {
        return $this->where('expires_at <=', date('Y-m-d H:i:s'))
            ->where('status !=', 'failed')
            ->groupStart()
            ->where('pdf_path !=', null)
            ->orWhere('extract_path !=', null)
            ->groupEnd()
            ->findAll();
    }
}
