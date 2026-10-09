<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdImpressionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ad_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_identifier' => ['type' => 'VARCHAR', 'constraint' => 191],
            'placement'       => ['type' => 'ENUM', 'constraint' => ['login','pdf','all'], 'default' => 'all'],
            'view_date'       => ['type' => 'DATE'],
            'view_count'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['ad_id', 'user_identifier', 'view_date'], false, true);
        $this->forge->addKey('view_date');
        $this->forge->addForeignKey('ad_id', 'ads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ad_impressions', true);
    }

    public function down()
    {
        $this->forge->dropTable('ad_impressions', true);
    }
}
