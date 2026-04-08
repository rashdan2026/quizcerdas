<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterMeetingsForPdfFileRef extends Migration
{
    public function up()
    {
        $fields = [];

        // Tambah kolom pdf_file_id (FK ke pdf_files)
        if (! $this->db->fieldExists('pdf_file_id', 'meetings')) {
            $fields['pdf_file_id'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'deskripsi',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('meetings', $fields);
        }

        // Tambah foreign key
        if ($this->db->fieldExists('pdf_file_id', 'meetings')) {
            // Cek apakah FK sudah ada
            $fkExists = false;
            $keys = $this->db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meetings' AND COLUMN_NAME = 'pdf_file_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->getResult();
            if (!empty($keys)) $fkExists = true;

            if (!$fkExists) {
                $this->forge->addForeignKey('pdf_file_id', 'pdf_files', 'id', 'SET NULL', 'CASCADE');
            }
        }

        // Hapus kolom link_pdf lama
        if ($this->db->fieldExists('link_pdf', 'meetings')) {
            $this->forge->dropColumn('meetings', 'link_pdf');
        }
    }

    public function down()
    {
        // Kembalikan link_pdf
        if (! $this->db->fieldExists('link_pdf', 'meetings')) {
            $fields['link_pdf'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'deskripsi',
            ];
            $this->forge->addColumn('meetings', $fields);
        }

        // Hapus pdf_file_id
        if ($this->db->fieldExists('pdf_file_id', 'meetings')) {
            $this->forge->dropColumn('meetings', 'pdf_file_id');
        }
    }
}
