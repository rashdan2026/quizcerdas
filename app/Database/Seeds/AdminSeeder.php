<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $adminEmail = 'admin@kampus.ac.id';
        $adminPass  = 'admin123';

        $existing = $this->db->table('admins')->where('email', $adminEmail)->get()->getRowArray();
        if (! $existing) {
            $this->db->table('admins')->insert([
                'nama'       => 'Administrator',
                'email'      => $adminEmail,
                'password'   => password_hash($adminPass, PASSWORD_BCRYPT),
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            echo "✓ Admin default dibuat: {$adminEmail} / {$adminPass}\n";
        } else {
            echo "• Admin default sudah ada.\n";
        }
    }
}
