<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGenderAndPriorityToAds extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('target_gender', 'ads')) {
            $fields['target_gender'] = [
                'type'       => 'ENUM',
                'constraint' => ['Laki-Laki', 'Perempuan', 'Both'],
                'default'    => 'Both',
                'null'       => false,
                'after'      => 'is_active',
            ];
        }

        if (! $this->db->fieldExists('priority_score', 'ads')) {
            $fields['priority_score'] = [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'after'      => 'target_gender',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('ads', $fields);
        }

        $this->db->query("ALTER TABLE ads ADD INDEX idx_ads_target_gender (target_gender)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE ads DROP INDEX idx_ads_target_gender");

        $cols = [];
        if ($this->db->fieldExists('priority_score', 'ads')) {
            $cols[] = 'priority_score';
        }
        if ($this->db->fieldExists('target_gender', 'ads')) {
            $cols[] = 'target_gender';
        }
        if ($cols !== []) {
            $this->forge->dropColumn('ads', $cols);
        }
    }
}
