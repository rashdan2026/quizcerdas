<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropSettingNumberAndPlacementFromAds extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('ads', ['setting_number', 'placement']);
    }

    public function down()
    {
        // Re-add columns if rolled back
        $this->forge->addColumn('ads', [
            'setting_number' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 5, 'after' => 'file_size'],
            'placement'      => ['type' => 'ENUM', 'constraint' => ['login', 'pdf', 'all'], 'default' => 'all', 'after' => 'setting_number'],
        ]);
    }
}
