<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Limitasi resource dosen + pembatasan resend email (v10.0).
 *
 * - lecturer_limit_settings  : nilai default global (dapat diubah admin).
 * - lecturers.limit_*        : snapshot limit saat akun dibuat (akun lama
 *                              di-backfill dengan default agar stabil).
 * - lecturer_limit_overrides : limitasi khusus per dosen (mengesampingkan snapshot).
 * - limitation_audit_logs    : jejak perubahan oleh admin.
 * - email_resend_counters    : penghitung resend harian per email (4/hari).
 * - email_resend_logs        : catatan setiap permintaan resend (tanpa isi OTP).
 * - meetings.deleted_at      : soft delete agar batas pertemuan tidak bisa
 *                              diakali lewat hapus+tambah berulang.
 */
class AddLecturerResourceLimits extends Migration
{
    public function up()
    {
        // ===== Snapshot limitasi pada lecturers =====
        $this->forge->addColumn('lecturers', [
            'limit_max_subjects'    => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'null' => true, 'after' => 'profile_confirmed_at'],
            'limit_max_meetings'    => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'null' => true, 'after' => 'limit_max_subjects'],
            'limit_max_ai_questions'=> ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'null' => true, 'after' => 'limit_max_meetings'],
            'limit_max_quizzes'     => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'null' => true, 'after' => 'limit_max_ai_questions'],
        ]);
        // Akun lama: snapshot default saat ini (perubahan default di kemudian
        // hari tidak mengubah snapshot akun).
        $this->db->query('UPDATE lecturers SET limit_max_subjects = 5, limit_max_meetings = 20, limit_max_ai_questions = 100, limit_max_quizzes = 5');

        // ===== Pengaturan default global =====
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'max_subjects'       => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 5],
            'max_meetings'       => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 20],
            'max_ai_questions'   => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 100],
            'max_quizzes'        => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 5],
            'updated_by'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('lecturer_limit_settings', true);
        $this->db->table('lecturer_limit_settings')->insert([
            'max_subjects' => 5, 'max_meetings' => 20, 'max_ai_questions' => 100, 'max_quizzes' => 5,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // ===== Limitasi khusus per dosen =====
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lecturer_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'max_subjects'         => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true],
            'max_meetings'         => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true],
            'max_ai_questions'     => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true],
            'max_quizzes'          => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true],
            'updated_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lecturer_id');
        $this->forge->addForeignKey('lecturer_id', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('lecturer_limit_overrides', true);

        // ===== Audit log perubahan limitasi =====
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'admin_email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 40],
            'lecturer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'old_value'   => ['type' => 'TEXT', 'null' => true],
            'new_value'   => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->createTable('limitation_audit_logs', true);

        // ===== Penghitung resend email per alamat =====
        $this->forge->addField([
            'email'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'counter_date'    => ['type' => 'DATE'],
            'resend_count'    => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 0],
            'last_sent_at'    => ['type' => 'DATETIME', 'null' => true],
            'next_allowed_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('email', true);
        $this->forge->addKey('counter_date');
        $this->forge->createTable('email_resend_counters', true);

        // ===== Log resend (tanpa isi OTP/password) =====
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'kind'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'resend_seq'  => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'error_note'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['email', 'created_at']);
        $this->forge->createTable('email_resend_logs', true);

        // ===== Meetings: soft delete =====
        $this->forge->addColumn('meetings', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'deskripsi'],
        ]);
        $this->forge->addKey('deleted_at');
    }

    public function down()
    {
        $this->forge->dropColumn('meetings', ['deleted_at']);
        $this->forge->dropTable('email_resend_logs', true);
        $this->forge->dropTable('email_resend_counters', true);
        $this->forge->dropTable('limitation_audit_logs', true);
        $this->forge->dropTable('lecturer_limit_overrides', true);
        $this->forge->dropTable('lecturer_limit_settings', true);
        $this->forge->dropColumn('lecturers', [
            'limit_max_subjects',
            'limit_max_meetings',
            'limit_max_ai_questions',
            'limit_max_quizzes',
        ]);
    }
}
