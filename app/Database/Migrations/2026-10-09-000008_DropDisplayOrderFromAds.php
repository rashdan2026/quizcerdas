<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropDisplayOrderFromAds extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('ads', 'display_order');
    }

    public function down()
    {
        $this->forge->addColumn('ads', [
            'display_order' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0, 'after' => 'is_active'],
        ]);
    }
}
