<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPreviousTokenToMeetings extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('previous_token_qr', 'meetings')) {
            $fields['previous_token_qr'] = [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'token_qr',
            ];
        }

        if (! $this->db->fieldExists('previous_token_expired_at', 'meetings')) {
            $fields['previous_token_expired_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'previous_token_qr',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('meetings', $fields);
        }
    }

    public function down()
    {
        $cols = ['previous_token_expired_at', 'previous_token_qr'];
        foreach ($cols as $c) {
            if ($this->db->fieldExists($c, 'meetings')) {
                $this->forge->dropColumn('meetings', $c);
            }
        }
    }
}
