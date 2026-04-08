<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AppSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('lecturers')->insert([
            'nama'       => 'Dosen Demo',
            'email'      => 'dosen@kampus.ac.id',
            'password'   => password_hash('dosen123', PASSWORD_BCRYPT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lecturerId = $this->db->insertID();

        $this->db->table('subjects')->insert([
            'kode_mk'    => 'IF401',
            'nama_mk'    => 'Pemrograman Web Mobile',
            'is_active'  => 1,
            'dosen_id'   => $lecturerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subjectId = $this->db->insertID();
        $token     = bin2hex(random_bytes(8));

        $this->db->table('meetings')->insert([
            'subject_id'   => $subjectId,
            'pertemuan_ke' => 1,
            'judul'        => 'Pengantar Sistem Absensi',
            'deskripsi'    => 'Pertemuan awal untuk validasi alur absensi.',
            'link_video'   => 'https://drive.google.com/file/d/1abc123/view',
            'token_qr'     => $token,
            'expired_at'   => date('Y-m-d H:i:s', strtotime('+30 seconds')),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $this->db->table('students')->insertBatch([
            [
                'npm'        => '230000001',
                'email'      => 'mahasiswa1@kampus.ac.id',
                'nama'       => 'Mahasiswa Satu',
                'password'   => password_hash('student123', PASSWORD_BCRYPT),
                'last_login' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'npm'        => '230000002',
                'email'      => 'mahasiswa2@kampus.ac.id',
                'nama'       => 'Mahasiswa Dua',
                'password'   => password_hash('student123', PASSWORD_BCRYPT),
                'last_login' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
