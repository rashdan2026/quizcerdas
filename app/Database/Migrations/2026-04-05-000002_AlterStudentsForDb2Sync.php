<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterStudentsForDb2Sync extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('passwd', 'students')) {
            $fields['passwd'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'password',
            ];
        }

        if (! $this->db->fieldExists('kelas', 'students')) {
            $fields['kelas'] = [
                'type'       => 'CHAR',
                'constraint' => 1,
                'null'       => false,
                'default'    => '',
                'after'      => 'nama',
            ];
        }

        if (! $this->db->fieldExists('no_whatsapp', 'students')) {
            $fields['no_whatsapp'] = [
                'type'       => 'VARCHAR',
                'constraint' => 15,
                'null'       => false,
                'default'    => '',
                'after'      => 'kelas',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('students', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('no_whatsapp', 'students')) {
            $this->forge->dropColumn('students', 'no_whatsapp');
        }

        if ($this->db->fieldExists('kelas', 'students')) {
            $this->forge->dropColumn('students', 'kelas');
        }

        if ($this->db->fieldExists('passwd', 'students')) {
            $this->forge->dropColumn('students', 'passwd');
        }
    }
}
