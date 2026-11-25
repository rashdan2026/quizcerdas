<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Constraint DB-level: SATU kode quiz hanya boleh menghasilkan SATU attempt.
 * Unique index pada quiz_attempts.quiz_id mencegah dua request bersamaan
 * membuat dua attempt untuk kode yang sama (race condition).
 *
 * Aman: tidak menyentuh data attempt/bank soal yang sudah ada.
 */
class QuizAttemptsUniquePerQuiz extends Migration
{
    public function up()
    {
        // Hapus index non-unique pada quiz_id (nama bisa berbeda antar environment).
        $idx = $this->db->query(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND COLUMN_NAME = 'quiz_id' AND NON_UNIQUE = 1
             GROUP BY INDEX_NAME LIMIT 1"
        )->getRow();
        if ($idx !== null) {
            $this->db->query('ALTER TABLE `quiz_attempts` DROP INDEX `' . $idx->INDEX_NAME . '`');
        }

        // Pasang unique index khusus quiz_id (lewati bila sudah ada).
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_attempts_quiz_id_unique' LIMIT 1"
        )->getRow();
        if ($exists === null) {
            $this->db->query('ALTER TABLE `quiz_attempts` ADD UNIQUE KEY `quiz_attempts_quiz_id_unique` (`quiz_id`)');
        }
    }

    public function down()
    {
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_attempts_quiz_id_unique' LIMIT 1"
        )->getRow();
        if ($exists !== null) {
            $this->db->query('ALTER TABLE `quiz_attempts` DROP INDEX `quiz_attempts_quiz_id_unique`');
        }
        $this->db->query('ALTER TABLE `quiz_attempts` ADD KEY `quiz_id` (`quiz_id`)');
    }
}
