<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdImpressionModel;

class Impression extends BaseController
{
    protected AdImpressionModel $impressionModel;

    public function __construct()
    {
        $this->impressionModel = new AdImpressionModel();
    }

    /**
     * Halaman "Kuota Iklan Mahasiswa" — menampilkan impresi harian (hari ini
     * SAJA, view_count > 0) yang dikelompokkan per mahasiswa. Admin dapat
     * me-reset view_count ke 0 untuk satu mahasiswa via form POST.
     *
     * Aturan tampilan:
     *  - Hanya user_identifier berprefix "student:" (mahasiswa saja)
     *  - Hanya view_date = hari ini (Asia/Jakarta)
     *  - Hanya view_count > 0 (user yang belum melihat iklan hari ini TIDAK muncul)
     *  - Diurutkan by total views DESC (paling banyak lihat iklan di atas)
     */
    public function index()
    {
        $db    = db_connect();
        $today = date('Y-m-d');

        // JOIN students & ads untuk dapat nama, npm, judul iklan
        // GROUP BY user_identifier agar 1 baris = 1 mahasiswa.
        $rows = $db->table('ad_impressions ai')
            ->select('
                ai.user_identifier,
                SUBSTRING_INDEX(ai.user_identifier, ":", -1) AS email,
                s.nama,
                s.npm,
                s.kelas,
                SUM(ai.view_count) AS total_views,
                COUNT(DISTINCT ai.ad_id) AS ad_count,
                GROUP_CONCAT(
                    DISTINCT CONCAT(a.title, " (", ai.view_count, "x)")
                    ORDER BY ai.view_count DESC
                    SEPARATOR ", "
                ) AS ads_seen
            ')
            ->join('students s', 's.email = SUBSTRING_INDEX(ai.user_identifier, ":", -1)', 'left')
            ->join('ads a', 'a.id = ai.ad_id', 'left')
            ->where('ai.user_identifier LIKE', 'student:%')
            ->where('ai.view_date', $today)
            ->where('ai.view_count >', 0)
            ->groupBy('ai.user_identifier, s.nama, s.npm, s.kelas')
            ->orderBy('total_views', 'DESC')
            ->get()
            ->getResultArray();

        $totalStudents = count($rows);
        $totalViewsAll = 0;
        foreach ($rows as $r) {
            $totalViewsAll += (int) $r['total_views'];
        }

        $data = [
            'pageTitle'         => 'Kuota Iklan Mahasiswa',
            'pageSubtitle'      => 'Impresi iklan harian (hari ini) yang dapat di-reset per mahasiswa',
            'rows'              => $rows,
            'totalStudents'     => $totalStudents,
            'totalViewsAll'     => $totalViewsAll,
            'today'             => $today,
        ];
        return view('admin/impressions/index', $data);
    }

    /**
     * Reset impresi 1 mahasiswa (hari ini) ke 0. Hanya update view_count
     * (jangan hapus row) supaya histori view_date tetap tercatat — besok akan
     * jadi impresi baru (view_count=1) lagi saat user lihat iklan.
     *
     * Hanya menerima POST + CSRF. Hanya set impresi yang:
     *  - user_identifier berprefix "student:"
     *  - view_date = hari ini
     *  - view_count > 0 (skip yang sudah 0 untuk hemat query)
     */
    public function reset()
    {
        $email = trim((string) $this->request->getPost('email'));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to('/admin/impressions')
                ->with('error', 'Email mahasiswa tidak valid.');
        }

        $identifier = 'student:' . $email;
        $today      = date('Y-m-d');

        $db = db_connect();
        $updated = $db->table('ad_impressions')
            ->where('user_identifier', $identifier)
            ->where('view_date', $today)
            ->where('view_count >', 0)
            ->update(['view_count' => 0]);

        $msg = $updated > 0
            ? "Berhasil me-reset {$updated} impresi mahasiswa " . esc($email) . " ke 0 untuk hari ini ({$today})."
            : "Mahasiswa " . esc($email) . " tidak memiliki impresi aktif hari ini ({$today}).";

        return redirect()->to('/admin/impressions')->with($updated > 0 ? 'success' : 'info', $msg);
    }
}
