<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'target_url'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_name'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'file_size'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'setting_number'=> ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 5],
            'placement'     => ['type' => 'ENUM', 'constraint' => ['login','pdf','all'], 'default' => 'all'],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'display_order' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_active', 'placement']);
        $this->forge->createTable('ads', true);
    }

    public function down()
    {
        $this->forge->dropTable('ads', true);
    }
}
