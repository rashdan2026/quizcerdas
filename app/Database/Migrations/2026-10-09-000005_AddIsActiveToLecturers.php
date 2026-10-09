<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsActiveToLecturers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('lecturers', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'password',
            ],
        ]);
        $this->forge->addKey('is_active');
    }

    public function down()
    {
        $this->forge->dropColumn('lecturers', 'is_active');
    }
}
