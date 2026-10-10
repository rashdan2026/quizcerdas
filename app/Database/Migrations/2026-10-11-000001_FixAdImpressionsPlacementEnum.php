<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixAdImpressionsPlacementEnum extends Migration
{
    /**
     * Bug fix v5.8.5: kolom `ad_impressions.placement` masih
     * ENUM('login','pdf','all') dari migration lama (2026-10-09-000003).
     * Placement 'dashboard' diperkenalkan di v5.0 (Student\Dashboard::index
     * memanggil pickAdForDisplay('dashboard', ...)), tetapi nilainya TIDAK
     * ada di enum — MySQL (mode non-strict) menyimpannya sebagai string
     * kosong ''. Akibatnya countToday('dashboard') selalu 0 sehingga kuota
     * harian per-user untuk dashboard TIDAK PERNAH berfungsi, dan recordView
     * tidak pernah menemukan row existing (dedup broken — selalu INSERT baru).
     *
     * Fix:
     *  1) MODIFY kolom menjadi ENUM('login','pdf','dashboard','all')
     *  2) Backfill row dengan placement='' menjadi 'dashboard'
     *     (semua impresi kosong pasti berasal dari fitur dashboard v5.0+)
     */
    public function up()
    {
        // 1) Perluas enum dengan 'dashboard'
        $this->db->query(
            "ALTER TABLE ad_impressions "
            . "MODIFY placement ENUM('login','pdf','dashboard','all') NOT NULL DEFAULT 'all'"
        );

        // 2) Backfill data kosong -> 'dashboard'
        $this->db->query("UPDATE ad_impressions SET placement = 'dashboard' WHERE placement = ''");
    }

    public function down()
    {
        // Kembalikan row 'dashboard' menjadi kosong (kondisi sebelum fix),
        // lalu sempitkan enum kembali ke definisi lama.
        $this->db->query("UPDATE ad_impressions SET placement = '' WHERE placement = 'dashboard'");
        $this->db->query(
            "ALTER TABLE ad_impressions "
            . "MODIFY placement ENUM('login','pdf','all') NOT NULL DEFAULT 'all'"
        );
    }
}
