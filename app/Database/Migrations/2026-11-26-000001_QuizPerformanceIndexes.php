<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index komposit untuk query panas modul quiz (audit optimasi shared hosting):
 *
 * 1. quiz_attempt_question_map (attempt_id, sort_order)
 *    - Query progres attempt: WHERE attempt_id = ? ORDER BY sort_order ASC.
 *      Sebelumnya memakai unique index (attempt_id, question_id) + filesort.
 *
 * 2. quiz_questions (quiz_id, is_active)
 *    - Hitung jumlah soal aktif & pengambilan soal kuis:
 *      WHERE quiz_id = ? AND is_active = 1 (dipakai dashboard dosen,
 *      validasi kapasitas generate, snapshot attempt).
 *
 * 3. quiz_attempts (quiz_id, status)
 *    - Daftar nilai, rekap attempt selesai, dan analitik:
 *      WHERE quiz_id = ? AND status IN (submitted, timeout).
 *
 * Semua perubahan additive (ADD INDEX); tidak mengubah kolom/data yang ada.
 * Index unik & index lama dipertahankan (tidak diganti) agar rollback aman.
 */
class QuizPerformanceIndexes extends Migration
{
    public function up()
    {
        $this->addIndexIfMissing(
            'quiz_attempt_question_map',
            'qmap_attempt_sort',
            'ALTER TABLE `quiz_attempt_question_map` ADD KEY `qmap_attempt_sort` (`attempt_id`, `sort_order`)'
        );

        $this->addIndexIfMissing(
            'quiz_questions',
            'qq_quiz_active',
            'ALTER TABLE `quiz_questions` ADD KEY `qq_quiz_active` (`quiz_id`, `is_active`)'
        );

        $this->addIndexIfMissing(
            'quiz_attempts',
            'qa_quiz_status',
            'ALTER TABLE `quiz_attempts` ADD KEY `qa_quiz_status` (`quiz_id`, `status`)'
        );
    }

    public function down()
    {
        $this->dropIndexIfExists('quiz_attempt_question_map', 'qmap_attempt_sort');
        $this->dropIndexIfExists('quiz_questions', 'qq_quiz_active');
        $this->dropIndexIfExists('quiz_attempts', 'qa_quiz_status');
    }

    private function addIndexIfMissing(string $table, string $indexName, string $ddl): void
    {
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             AND INDEX_NAME = ? LIMIT 1",
            [$table, $indexName]
        )->getRow();

        if ($exists === null) {
            $this->db->query($ddl);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             AND INDEX_NAME = ? LIMIT 1",
            [$table, $indexName]
        )->getRow();

        if ($exists !== null) {
            $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }
}
