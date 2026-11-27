<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reset password aman (v11.0).
 *
 * - password_reset_settings : konfigurasi (dapat diubah Admin, audit tercatat).
 * - password_reset_counters : pembatasan per email, periode bergulir 24 jam.
 * - password_reset_tokens   : token (HASH saja) + masa berlaku + status pakai.
 * - password_reset_requests : log permintaan (email, IP, urutan, status kirim).
 * - password_reset_audit    : audit reset berhasil + perubahan pengaturan admin.
 *
 * Token mentah TIDAK pernah disimpan.
 */
class AddPasswordResetSecurity extends Migration
{
    public function up()
    {
        // ===== Pengaturan =====
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'max_requests'      => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 4],
            'delay_2_minutes'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 5],
            'delay_3_minutes'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 10],
            'delay_4_minutes'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 15],
            'token_ttl_minutes' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 15],
            'ip_max_per_hour'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 20],
            'updated_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('password_reset_settings', true);
        $this->db->table('password_reset_settings')->insert([
            'max_requests' => 4, 'delay_2_minutes' => 5, 'delay_3_minutes' => 10, 'delay_4_minutes' => 15,
            'token_ttl_minutes' => 15, 'ip_max_per_hour' => 20,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // ===== Counter per email (periode bergulir 24 jam) =====
        $this->forge->addField([
            'email'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'window_started_at'=> ['type' => 'DATETIME', 'null' => true],
            'sent_count'       => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'last_sent_at'     => ['type' => 'DATETIME', 'null' => true],
            'next_allowed_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('email', true);
        $this->forge->createTable('password_reset_counters', true);

        // ===== Token reset (hash) =====
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'user_id'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['role', 'user_id']);
        $this->forge->addKey('expires_at');
        $this->forge->createTable('password_reset_tokens', true);

        // ===== Log permintaan =====
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'role'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'ip'          => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'sequence_no' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'result'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'email_status'=> ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['email', 'created_at']);
        $this->forge->addKey(['ip', 'created_at']);
        $this->forge->createTable('password_reset_requests', true);

        // ===== Audit (reset selesai + perubahan pengaturan admin) =====
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'event'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'user_ref'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'detail'     => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at');
        $this->forge->createTable('password_reset_audit', true);
    }

    public function down()
    {
        $this->forge->dropTable('password_reset_audit', true);
        $this->forge->dropTable('password_reset_requests', true);
        $this->forge->dropTable('password_reset_tokens', true);
        $this->forge->dropTable('password_reset_counters', true);
        $this->forge->dropTable('password_reset_settings', true);
    }
}
