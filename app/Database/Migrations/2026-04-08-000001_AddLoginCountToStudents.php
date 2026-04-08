<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLoginCountToStudents extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('login_count', 'students')) {
            $fields = [
                'login_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'last_login',
                ],
            ];

            $this->forge->addColumn('students', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('login_count', 'students')) {
            $this->forge->dropColumn('students', 'login_count');
        }
    }
}
