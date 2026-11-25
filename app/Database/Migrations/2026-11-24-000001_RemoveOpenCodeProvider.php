<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus konfigurasi provider OpenCode — aplikasi hanya memakai OpenRouter.
 * Aman: hanya menghapus baris konfigurasi di quiz_ai_providers.
 * Riwayat batch, soal, attempt, dan log AI tidak diubah.
 */
class RemoveOpenCodeProvider extends Migration
{
    public function up()
    {
        $this->db->table('quiz_ai_providers')->where('provider_code', 'opencode')->delete();
    }

    public function down()
    {
        $now = date('Y-m-d H:i:s');
        $exists = $this->db->table('quiz_ai_providers')->where('provider_code', 'opencode')->countAllResults();
        if ($exists === 0) {
            $this->db->table('quiz_ai_providers')->insert([
                'provider_code' => 'opencode',
                'display_name'  => 'OpenCode',
                'base_url'      => 'https://opencode.ai/zen/v1',
                'is_enabled'    => 0,
                'priority_order' => 2,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
