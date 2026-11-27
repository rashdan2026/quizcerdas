<?php

namespace App\Libraries;

use App\Models\LecturerLimitOverrideModel;
use App\Models\LecturerLimitSettingsModel;
use App\Models\Quiz\QuizQuestionModel;

/**
 * Limitasi resource dosen.
 *
 * Prioritas limitasi: override admin > snapshot akun dosen > default global.
 * Snapshot akun diambil saat akun dosen dibuat/ diaktifkan; perubahan default
 * admin tidak mengubah snapshot akun lama.
 *
 * Zona waktu perhitungan harian/mingguan: Asia/Jakarta.
 * Minggu dimulai hari Senin pukul 00:00 WIB.
 */
class LecturerLimits
{
    /** Nilai darurat jika baris settings hilang (diseed ulang otomatis). */
    private const FALLBACK = ['subjects' => 5, 'meetings' => 20, 'ai' => 100, 'quizzes' => 5];

    private LecturerLimitSettingsModel $settingsModel;
    private LecturerLimitOverrideModel $overrideModel;

    public function __construct()
    {
        $this->settingsModel  = new LecturerLimitSettingsModel();
        $this->overrideModel  = new LecturerLimitOverrideModel();
    }

    // ==================== Sumber nilai ====================

    /** Default global saat ini (dari lecturer_limit_settings). */
    public function defaults(): array
    {
        $row = $this->settingsModel->singleton();

        if ($row === null) {
            // Pulihkan baris settings bila hilang (idempotent).
            $this->settingsModel->insert([
                'max_subjects'     => self::FALLBACK['subjects'],
                'max_meetings'     => self::FALLBACK['meetings'],
                'max_ai_questions' => self::FALLBACK['ai'],
                'max_quizzes'      => self::FALLBACK['quizzes'],
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
            $row = $this->settingsModel->singleton() ?? [];
        }

        return [
            'subjects' => (int) ($row['max_subjects'] ?? self::FALLBACK['subjects']),
            'meetings' => (int) ($row['max_meetings'] ?? self::FALLBACK['meetings']),
            'ai'       => (int) ($row['max_ai_questions'] ?? self::FALLBACK['ai']),
            'quizzes'  => (int) ($row['max_quizzes'] ?? self::FALLBACK['quizzes']),
        ];
    }

    /** Snapshot limit untuk dosen BARU (dipanggil saat aktivasi/pembuatan akun). */
    public function snapshotForNewLecturer(): array
    {
        $d = $this->defaults();

        return [
            'limit_max_subjects'     => $d['subjects'],
            'limit_max_meetings'     => $d['meetings'],
            'limit_max_ai_questions' => $d['ai'],
            'limit_max_quizzes'      => $d['quizzes'],
        ];
    }

    /**
     * Limitasi efektif + pemakaian berjalan untuk satu dosen.
     * source: 'custom' (override) | 'account' (snapshot) | 'default'.
     */
    public function forLecturer(int $lecturerId): array
    {
        $defaults = $this->defaults();
        $lecturer = (new \App\Models\LecturerModel())->find($lecturerId);

        $limits = [
            'subjects' => $lecturer && $lecturer['limit_max_subjects']     !== null ? (int) $lecturer['limit_max_subjects']     : $defaults['subjects'],
            'meetings' => $lecturer && $lecturer['limit_max_meetings']     !== null ? (int) $lecturer['limit_max_meetings']     : $defaults['meetings'],
            'ai'       => $lecturer && $lecturer['limit_max_ai_questions'] !== null ? (int) $lecturer['limit_max_ai_questions'] : $defaults['ai'],
            'quizzes'  => $lecturer && $lecturer['limit_max_quizzes']      !== null ? (int) $lecturer['limit_max_quizzes']      : $defaults['quizzes'],
        ];

        $source = 'account';
        if ($this->overrideModel->forLecturer($lecturerId) !== null) {
            $o = $this->overrideModel->forLecturer($lecturerId);
            $limits = [
                'subjects' => (int) $o['max_subjects'],
                'meetings' => (int) $o['max_meetings'],
                'ai'       => (int) $o['max_ai_questions'],
                'quizzes'  => (int) $o['max_quizzes'],
            ];
            $source = 'custom';
        }

        $weekStart = $this->weekStart();
        $aiUsed    = $this->aiUsedWeek($lecturerId, $weekStart);

        return [
            'limits'     => $limits,
            'source'     => $source,
            'week_start' => $weekStart,
            'used'       => [
                'subjects' => $this->subjectCount($lecturerId),
                'quizzes'  => $this->quizCount($lecturerId),
                'ai'       => $aiUsed,
            ],
            'remaining'  => [
                'subjects' => max(0, $limits['subjects'] - $this->subjectCount($lecturerId)),
                'quizzes'  => max(0, $limits['quizzes'] - $this->quizCount($lecturerId)),
                'ai'       => max(0, $limits['ai'] - $aiUsed),
            ],
        ];
    }

    // ==================== Penghitungan ====================

    public function subjectCount(int $lecturerId): int
    {
        return (int) (new \App\Models\SubjectModel())
            ->where('dosen_id', $lecturerId)
            ->countAllResults();
    }

    /**
     * Semua kuis yang pernah dibuat dosen (termasuk archived) — penghapusan/
     * pengarsipan tidak dapat dipakai mengakali batas pembuatan.
     */
    public function quizCount(int $lecturerId): int
    {
        $db = db_connect();

        return (int) $db->table('quiz_quizzes')->where('lecturer_id', $lecturerId)->countAllResults();
    }

    /**
     * Jumlah pertemuan yang pernah dibuat pada satu mata kuliah
     * (termasuk yang sudah dihapus/soft delete) — anti-abuse.
     */
    public function meetingCount(int $subjectId): int
    {
        $db = db_connect();

        return (int) $db->table('meetings')->where('subject_id', $subjectId)->countAllResults();
    }

    /** Senin 00:00:00 pekan ini (Asia/Jakarta), format Y-m-d H:i:s. */
    public function weekStart(): string
    {
        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);
        $now->setTime(0, 0, 0);
        $dow = (int) $now->format('N'); // 1 = Senin
        $now->modify('-' . ($dow - 1) . ' days');

        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Pemakaian AI minggu berjalan:
     * soal AI yang benar-benar tersimpan + reservasi batch yang masih berjalan
     * (queued/quarantine/processing). Batch gagal tidak dihitung; soal duplikat
     * tidak tersimpan sehingga tidak dihitung dua kali.
     */
    public function aiUsedWeek(int $lecturerId, ?string $weekStart = null): int
    {
        $weekStart = $weekStart ?? $this->weekStart();
        $db = db_connect();

        $actual = (int) $db->query(
            'SELECT COUNT(*) AS n
             FROM quiz_questions q
             JOIN quiz_quizzes z ON z.id = q.quiz_id
             WHERE z.lecturer_id = ? AND q.source_type = ? AND q.created_at >= ?',
            [$lecturerId, 'ai_pdf', $weekStart]
        )->getRow()->n ?? 0;

        $reserved = (int) $db->query(
            "SELECT COALESCE(SUM(requested_count), 0) AS n
             FROM quiz_question_batches
             WHERE lecturer_id = ? AND created_at >= ? AND status IN ('queued','quarantine','processing')",
            [$lecturerId, $weekStart]
        )->getRow()->n ?? 0;

        return $actual + $reserved;
    }

    // ==================== Pemeriksaan limit ====================

    /** @return array{ok:bool,message:string,used:int,limit:int} */
    public function checkSubject(int $lecturerId): array
    {
        $info = $this->forLecturer($lecturerId);
        $used = $info['used']['subjects'];
        $lim  = $info['limits']['subjects'];

        if ($used >= $lim) {
            return [
                'ok' => false,
                'message' => 'Anda telah mencapai batas maksimal ' . $lim . ' mata kuliah. Silakan hubungi Admin apabila membutuhkan penambahan kuota.',
                'used' => $used, 'limit' => $lim,
            ];
        }

        return ['ok' => true, 'message' => '', 'used' => $used, 'limit' => $lim];
    }

    /** @return array{ok:bool,message:string,used:int,limit:int} */
    public function checkQuiz(int $lecturerId): array
    {
        $info = $this->forLecturer($lecturerId);
        $used = $info['used']['quizzes'];
        $lim  = $info['limits']['quizzes'];

        if ($used >= $lim) {
            return [
                'ok' => false,
                'message' => 'Anda telah mencapai batas maksimal pembuatan kuis (' . $used . ' dari ' . $lim . '). Silakan hubungi Admin apabila membutuhkan penambahan kuota.',
                'used' => $used, 'limit' => $lim,
            ];
        }

        return ['ok' => true, 'message' => '', 'used' => $used, 'limit' => $lim];
    }

    /** @return array{ok:bool,message:string,used:int,limit:int,subject:string|null} */
    public function checkMeeting(int $subjectId, int $lecturerId): array
    {
        $db      = db_connect();
        $subject = $db->table('subjects')->where('id', $subjectId)->where('dosen_id', $lecturerId)->get()->getRowArray();

        if (! $subject) {
            return ['ok' => false, 'message' => 'Mata kuliah tidak ditemukan.', 'used' => 0, 'limit' => 0, 'subject' => null];
        }

        $info = $this->forLecturer($lecturerId);
        $used = $this->meetingCount($subjectId);
        $lim  = $info['limits']['meetings'];

        if ($used >= $lim) {
            return [
                'ok' => false,
                'message' => 'Mata kuliah ' . $subject['kode_mk'] . ' telah mencapai batas maksimal ' . $lim . ' pertemuan. Silakan hubungi Admin apabila membutuhkan penambahan kuota.',
                'used' => $used, 'limit' => $lim,
                'subject' => $subject['kode_mk'],
            ];
        }

        return ['ok' => true, 'message' => '', 'used' => $used, 'limit' => $lim, 'subject' => $subject['kode_mk']];
    }

    /**
     * Reservasi kuota AI mingguan (aman terhadap request bersamaan):
     * mengunci baris lecturers FOR UPDATE, menghitung pemakaian
     * (soal tersimpan + reservasi batch berjalan), lalu menyesuaikan
     * jumlah soal yang akan digenerate terhadap sisa kuota.
     *
     * Dipanggil DI DALAM transaksi createBatch (setelah lock lecturer).
     *
     * @return array{ok:bool,message:string,requested:int,remaining:int,limit:int}
     */
    public function reserveAiWithinTransaction(int $lecturerId, int $requested): array
    {
        $db = db_connect();
        // Kunci baris dosen agar generate bersamaan (quiz berbeda) terserialisasi.
        $db->query('SELECT id FROM lecturers WHERE id = ? FOR UPDATE', [$lecturerId]);

        $info      = $this->forLecturer($lecturerId);
        $limit     = $info['limits']['ai'];
        $used      = $info['used']['ai'];
        $remaining = max(0, $limit - $used);

        if ($remaining <= 0) {
            return [
                'ok' => false,
                'message' => 'Kuota Generate AI minggu ini telah mencapai batas maksimal (' . $used . ' dari ' . $limit . ' soal). Kuota akan diperbarui pada hari Senin pukul 00.00 WIB.',
                'requested' => 0, 'remaining' => 0, 'limit' => $limit,
            ];
        }

        if ($requested > $remaining) {
            return [
                'ok' => true,
                'message' => 'Kuota tersisa ' . $remaining . ' soal, sehingga jumlah generate disesuaikan dari ' . $requested . ' menjadi ' . $remaining . ' soal.',
                'requested' => $remaining, 'remaining' => 0, 'limit' => $limit,
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'requested' => $requested,
            'remaining' => $remaining - $requested,
            'limit' => $limit,
        ];
    }
}
