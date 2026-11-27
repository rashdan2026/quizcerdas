<?php

namespace App\Controllers;

use App\Libraries\ContactService;

class Home extends BaseController
{
    /**
     * Halaman awal publik: Landing Page.
     *
     * Pengguna yang sudah login tetap diarahkan ke dashboard sesuai role
     * (perilaku lama dipertahankan). Pengunjung anonim melihat Landing Page,
     * bukan langsung halaman login.
     */
    public function index()
    {
        if (session('logged_in')) {
            $role = session('role');

            if ($role === 'admin') {
                return redirect()->to('/admin/dashboard');
            }

            if ($role === 'lecturer') {
                return redirect()->to('/lecturer/subjects');
            }

            return redirect()->to('/student/dashboard');
        }

        // Status formulir kontak (aktif/terblokir/sukses + token CSRF form).
        // Kegagalan layanan tidak boleh membuat Landing Page ikut gagal.
        $contact = [];
        try {
            $contact = (new ContactService())->state();
        } catch (\Throwable $e) {
            log_message('error', 'Landing: gagal memuat status formulir kontak ({class}): {message}', [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        return view('landing/index', ['contact' => $contact]);
    }
}
