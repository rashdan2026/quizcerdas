<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registrasi mandiri dosen:
 * - Kolom profil & status baru pada tabel lecturers.
 * - Tabel lecturer_registrations untuk data pendaftaran dosen yang
 *   belum menyelesaikan verifikasi email (status pending).
 *
 * Akun dosen lama dibuat oleh admin: email_verified=1 dan
 * profile_confirmed=1 (default), sehingga tidak memicu first login.
 */
class AddLecturerSelfRegistration extends Migration
{
    public function up()
    {
        $this->forge->addColumn('lecturers', [
            'jenis_kelamin' => [
                'type'       => 'ENUM',
                'constraint' => ['Laki-Laki', 'Perempuan'],
                'null'       => true,
                'after'      => 'email',
            ],
            'tanggal_lahir' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'jenis_kelamin',
            ],
            'nama_kampus' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'tanggal_lahir',
            ],
            // Default 1: dosen lama (dibuat admin) dianggap sudah terverifikasi.
            'email_verified' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'is_active',
            ],
            // Default 1: dosen lama tidak melalui konfirmasi profil first login.
            'profile_confirmed' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'email_verified',
            ],
            'profile_confirmed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'profile_confirmed',
            ],
        ]);

        // Data pendaftaran dosen pending (sebelum verifikasi email sukses).
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'jenis_kelamin'   => ['type' => 'ENUM', 'constraint' => ['Laki-Laki', 'Perempuan']],
            'tanggal_lahir'   => ['type' => 'DATE'],
            'nama_kampus'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'        => ['type' => 'VARCHAR', 'constraint' => 255],
            // Kode verifikasi disimpan sebagai hash, bukan plaintext.
            'verify_code_hash'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'verify_expires_at'  => ['type' => 'DATETIME'],
            'verify_attempts'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'null' => false],
            'resend_available_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('lecturer_registrations', true);
    }

    public function down()
    {
        $this->forge->dropTable('lecturer_registrations', true);
        $this->forge->dropColumn('lecturers', [
            'jenis_kelamin',
            'tanggal_lahir',
            'nama_kampus',
            'email_verified',
            'profile_confirmed',
            'profile_confirmed_at',
        ]);
    }
}
