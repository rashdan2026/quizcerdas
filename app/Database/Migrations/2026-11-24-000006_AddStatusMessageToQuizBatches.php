<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kolom status_message pada quiz_question_batches: pesan progres/hasil yang
 * aman ditampilkan ke dosen (tanpa provider, prompt, atau detail internal).
 * Dipakai oleh polling status generate soal AI.
 *
 * Aman: kolom additive NULL; data batch lama tidak diubah.
 */
class AddStatusMessageToQuizBatches extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('status_message', 'quiz_question_batches')) {
            $this->forge->addColumn('quiz_question_batches', [
                'status_message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null, 'after' => 'status'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('status_message', 'quiz_question_batches')) {
            $this->forge->dropColumn('quiz_question_batches', 'status_message');
        }
    }
}
