<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Formulir kontak Landing Page (Hubungi Tim KelasMerdeka).
 *
 * Tabel log minimal untuk audit & pembatasan pengiriman:
 * - TIDAK menyimpan nama, email, nomor WhatsApp, atau isi pesan.
 * - `device_hash` = SHA-256 token cookie perangkat (bukan token mentah).
 * - `ip_hash`     = SHA-256 alamat IP (bukan IP mentah).
 * - `provider` / `error_note` hanya status pengiriman yang aman ditampilkan.
 *
 * Tidak mengubah/menghapus tabel yang sudah ada.
 */
class CreateContactMessages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'device_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'ip_hash'     => ['type' => 'CHAR', 'constraint' => 64],
            'provider'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'error_note'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('device_hash');
        $this->forge->addKey('ip_hash');
        $this->forge->addKey('created_at');
        $this->forge->createTable('contact_messages', true);
    }

    public function down()
    {
        $this->forge->dropTable('contact_messages', true);
    }
}
