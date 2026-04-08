<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLinkPdfToMeetings extends Migration
{
    public function up()
    {
        $fields = [];

        // Tambah kolom link_pdf
        if (! $this->db->fieldExists('link_pdf', 'meetings')) {
            $fields['link_pdf'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'deskripsi',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('meetings', $fields);
        }

        // Hapus kolom link_video
        if ($this->db->fieldExists('link_video', 'meetings')) {
            $this->forge->dropColumn('meetings', 'link_video');
        }
    }

    public function down()
    {
        // Tambah kembali link_video
        if (! $this->db->fieldExists('link_video', 'meetings')) {
            $fields['link_video'] = [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'deskripsi',
            ];
            $this->forge->addColumn('meetings', $fields);
        }

        // Hapus link_pdf
        if ($this->db->fieldExists('link_pdf', 'meetings')) {
            $this->forge->dropColumn('meetings', 'link_pdf');
        }
    }
}
