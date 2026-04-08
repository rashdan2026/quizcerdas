<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAttendanceSystem extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'last_login' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('lecturers', true);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_mk'    => ['type' => 'VARCHAR', 'constraint' => 10],
            'nama_mk'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active'  => ['type' => 'BOOLEAN', 'default' => true],
            'dosen_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode_mk');
        $this->forge->addKey('dosen_id');
        $this->forge->addForeignKey('dosen_id', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('subjects', true);

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'subject_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'pertemuan_ke' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true],
            'judul'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'deskripsi'    => ['type' => 'TEXT', 'null' => true],
            'link_video'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'token_qr'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'expired_at'   => ['type' => 'DATETIME'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('subject_id');
        $this->forge->addUniqueKey(['subject_id', 'pertemuan_ke']);
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('meetings', true);

        $this->forge->addField([
            'npm'        => ['type' => 'VARCHAR', 'constraint' => 9],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'last_login' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('npm', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('students', true);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'meeting_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'npm'        => ['type' => 'VARCHAR', 'constraint' => 9],
            'latitude'   => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
            'longitude'  => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'waktu_absen'=> ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['meeting_id', 'npm']);
        $this->forge->addKey('meeting_id');
        $this->forge->addKey('npm');
        $this->forge->addForeignKey('meeting_id', 'meetings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('npm', 'students', 'npm', 'CASCADE', 'CASCADE');
        $this->forge->createTable('attendance', true);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'npm'        => ['type' => 'VARCHAR', 'constraint' => 9],
            'otp_code'   => ['type' => 'VARCHAR', 'constraint' => 6],
            'expired_at' => ['type' => 'DATETIME'],
            'is_used'    => ['type' => 'BOOLEAN', 'default' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('npm');
        $this->forge->addForeignKey('npm', 'students', 'npm', 'CASCADE', 'CASCADE');
        $this->forge->createTable('otp_codes', true);
    }

    public function down()
    {
        $this->forge->dropTable('otp_codes', true);
        $this->forge->dropTable('attendance', true);
        $this->forge->dropTable('students', true);
        $this->forge->dropTable('meetings', true);
        $this->forge->dropTable('subjects', true);
        $this->forge->dropTable('lecturers', true);
    }
}
