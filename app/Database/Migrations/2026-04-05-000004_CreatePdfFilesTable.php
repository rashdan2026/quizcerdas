<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePdfFilesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'dosen_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'judul'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'deskripsi'   => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'default' => null],
            'file_name'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('dosen_id');
        $this->forge->addForeignKey('dosen_id', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pdf_files', true);
    }

    public function down()
    {
        $this->forge->dropTable('pdf_files', true);
    }
}
