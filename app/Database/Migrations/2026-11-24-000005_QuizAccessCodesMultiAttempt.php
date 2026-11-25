<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Regenerate Kode Kuis + multiple attempt dengan kode berbeda.
 *
 * - Tabel quiz_access_codes: histori penerbitan kode per quiz.
 *   Satu quiz dapat memiliki banyak kode sepanjang waktu; hanya satu yang aktif.
 * - quiz_attempts: attempt terhubung ke kode yang digunakan (access_code_id +
 *   snapshot access_code), unique (access_code_id, student_email) menegakkan
 *   aturan satu kode = satu attempt per mahasiswa di level DB.
 * - Index unik lama quiz_attempts.quiz_id (single-attempt-per-quiz) dihapus.
 *
 * Aman: tidak menghapus/mengubah histori attempt yang sudah ada. Kode saat ini
 * dimigrasikan menjadi baris aktif di quiz_access_codes.
 */
class QuizAccessCodesMultiAttempt extends Migration
{
    public function up()
    {
        // ==================== quiz_access_codes ====================
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quiz_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 16],
            'is_active'      => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'deactivated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('quiz_id');
        $this->forge->addForeignKey('quiz_id', 'quiz_quizzes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_access_codes', true);

        // Migrasikan kode yang sudah ada menjadi kode aktif pertama tiap quiz.
        $this->db->query(
            'INSERT INTO quiz_access_codes (quiz_id, code, is_active, created_by, created_at)
             SELECT id, access_code, 1, lecturer_id, COALESCE(created_at, NOW())
             FROM quiz_quizzes'
        );

        // ==================== quiz_attempts ====================
        $this->forge->addColumn('quiz_attempts', [
            'access_code_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null, 'after' => 'attempt_no'],
            'access_code'    => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true, 'default' => null, 'after' => 'access_code_id'],
        ]);

        // Backfill attempt lama: hubungkan dengan kode aktif quiz-nya.
        $this->db->query(
            'UPDATE quiz_attempts t
             JOIN quiz_access_codes c ON c.quiz_id = t.quiz_id AND c.is_active = 1
             SET t.access_code_id = c.id, t.access_code = c.code'
        );

        // Index unik lama (satu attempt per quiz) dihapus; kembalikan index biasa.
        $old = $this->db->query(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_attempts_quiz_id_unique' LIMIT 1"
        )->getRow();
        if ($old !== null) {
            $this->db->query('ALTER TABLE `quiz_attempts` DROP INDEX `quiz_attempts_quiz_id_unique`');
        }
        $plain = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_id' LIMIT 1"
        )->getRow();
        if ($plain === null) {
            $this->db->query('ALTER TABLE `quiz_attempts` ADD KEY `quiz_id` (`quiz_id`)');
        }

        // Satu kode hanya boleh dipakai satu kali oleh satu mahasiswa (race-safe).
        $uniq = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_attempts_code_student_unique' LIMIT 1"
        )->getRow();
        if ($uniq === null) {
            $this->db->query(
                'ALTER TABLE `quiz_attempts` ADD UNIQUE KEY `quiz_attempts_code_student_unique` (`access_code_id`, `student_email`)'
            );
        }
    }

    public function down()
    {
        $uniq = $this->db->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
             AND INDEX_NAME = 'quiz_attempts_code_student_unique' LIMIT 1"
        )->getRow();
        if ($uniq !== null) {
            $this->db->query('ALTER TABLE `quiz_attempts` DROP INDEX `quiz_attempts_code_student_unique`');
        }

        // Pulihkan proteksi lama hanya bila tidak ada quiz dengan >1 attempt.
        $multi = $this->db->query(
            'SELECT quiz_id FROM quiz_attempts GROUP BY quiz_id HAVING COUNT(*) > 1 LIMIT 1'
        )->getRow();
        if ($multi === null) {
            $idx = $this->db->query(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_attempts'
                 AND COLUMN_NAME = 'quiz_id' AND NON_UNIQUE = 1
                 GROUP BY INDEX_NAME LIMIT 1"
            )->getRow();
            if ($idx !== null) {
                $this->db->query('ALTER TABLE `quiz_attempts` DROP INDEX `' . $idx->INDEX_NAME . '`');
            }
            $this->db->query('ALTER TABLE `quiz_attempts` ADD UNIQUE KEY `quiz_attempts_quiz_id_unique` (`quiz_id`)');
        }

        $this->forge->dropColumn('quiz_attempts', ['access_code_id', 'access_code']);
        $this->forge->dropTable('quiz_access_codes', true);
    }
}
