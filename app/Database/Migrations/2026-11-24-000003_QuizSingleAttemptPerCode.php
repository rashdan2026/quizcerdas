<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aturan baru: satu kode quiz hanya boleh digunakan untuk SATU kali ujian
 * (tidak ada retake). max_attempts diturunkan dari 2 menjadi 1.
 *
 * Aman: tidak menghapus/mengubah riwayat attempt yang sudah ada — hanya
 * menyesuaikan batas attempt quiz (termasuk quiz yang belum berjalan).
 */
class QuizSingleAttemptPerCode extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('quiz_quizzes', [
            'max_attempts' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
        ]);
        $this->db->query('UPDATE quiz_quizzes SET max_attempts = 1');
    }

    public function down()
    {
        $this->forge->modifyColumn('quiz_quizzes', [
            'max_attempts' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 2],
        ]);
        $this->db->query('UPDATE quiz_quizzes SET max_attempts = 2');
    }
}
