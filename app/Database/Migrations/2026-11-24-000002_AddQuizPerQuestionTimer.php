<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengerjaan kuis satu soal per halaman + timer per soal.
 * - quiz_questions.duration_seconds: durasi pengerjaan soal (NULL = otomatis
 *   berdasarkan difficulty AI: easy 60, medium 90, hard 120; manual default 60).
 *   Nilai yang diisi dosen hanya 30/60/90/120.
 * - quiz_attempt_question_map: snapshot durasi + state timer per soal per attempt
 *   (server-side, tidak direset oleh refresh browser).
 * Tidak ada tabel/data lama yang diubah secara destructive.
 */
class AddQuizPerQuestionTimer extends Migration
{
    public function up()
    {
        $this->forge->addColumn('quiz_questions', [
            'duration_seconds' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null, 'after' => 'explanation'],
        ]);

        $this->forge->addColumn('quiz_attempt_question_map', [
            'duration_seconds'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 60, 'after' => 'option_order'],
            'question_started_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null, 'after' => 'duration_seconds'],
            'question_deadline_at'=> ['type' => 'DATETIME', 'null' => true, 'default' => null, 'after' => 'question_started_at'],
            'is_expired'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'question_deadline_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('quiz_attempt_question_map', ['duration_seconds', 'question_started_at', 'question_deadline_at', 'is_expired']);
        $this->forge->dropColumn('quiz_questions', 'duration_seconds');
    }
}
