<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdClicksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ad_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clicked_at' => ['type' => 'DATETIME'],
            'view_date'  => ['type' => 'DATE'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['ad_id', 'view_date']);
        $this->forge->addForeignKey('ad_id', 'ads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ad_clicks', true);
    }

    public function down()
    {
        $this->forge->dropTable('ad_clicks', true);
    }
}
