<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMeetingLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'meeting_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'link_type'  => ['type' => 'ENUM', 'constraint' => ['youtube', 'file']],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('meeting_id');
        $this->forge->addForeignKey('meeting_id', 'meetings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('meeting_links', true);
    }

    public function down()
    {
        $this->forge->dropTable('meeting_links', true);
    }
}
