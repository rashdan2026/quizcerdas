<?php

namespace App\Controllers;

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

        return view('landing/index');
    }
}
