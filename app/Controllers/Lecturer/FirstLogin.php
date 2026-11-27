<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\LecturerModel;

/**
 * Konfirmasi profil pada login pertama dosen hasil registrasi mandiri.
 *
 * Dosen yang belum memenuhi profile_confirmed dipaksa ke halaman ini
 * (di-gerbang oleh AuthGuard). Semua field dapat diedit KECUALI email
 * institusi yang sudah diverifikasi. Setelah tersimpan, profil menjadi
 * read-only secara permanen.
 */
class FirstLogin extends BaseController
{
    public function index()
    {
        if (session('profile_confirmed')) {
            return redirect()->to('/lecturer/subjects');
        }

        $lecturer = (new LecturerModel())->find(session('user_id'));
        if (! $lecturer) {
            return redirect()->to('/logout')->with('error', 'Data dosen tidak ditemukan.');
        }

        return view('lecturer/first_login', ['lecturer' => $lecturer]);
    }

    public function confirm()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/lecturer/confirm-profile');
        }

        $session  = session();
        $lecturer = (new LecturerModel())->find($session->get('user_id'));

        if (! $lecturer) {
            return redirect()->to('/logout')->with('error', 'Data dosen tidak ditemukan.');
        }

        // Sudah dikonfirmasi sebelumnya: tolak (tidak bisa dikonfirmasi ulang).
        if (! empty($lecturer['profile_confirmed'])) {
            $session->set('profile_confirmed', true);
            return redirect()->to('/lecturer/subjects');
        }

        $nama         = trim((string) $this->request->getPost('nama'));
        $jenisKelamin = trim((string) $this->request->getPost('jenis_kelamin'));
        $tanggalLahir = trim((string) $this->request->getPost('tanggal_lahir'));
        $namaKampus   = trim((string) $this->request->getPost('nama_kampus'));

        // Validasi ulang di backend. Email TIDAK dibaca dari request:
        // tetap menggunakan email yang telah diverifikasi (anti mass assignment).
        $email = $lecturer['email'];

        if ($nama === '' || mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama lengkap wajib diisi (3-100 karakter).');
        }

        if (! in_array($jenisKelamin, ['Laki-Laki', 'Perempuan'], true)) {
            return redirect()->back()->withInput()->with('error', 'Jenis kelamin wajib dipilih.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalLahir) || ! checkdate((int) substr($tanggalLahir, 5, 2), (int) substr($tanggalLahir, 8, 2), (int) substr($tanggalLahir, 0, 4))) {
            return redirect()->back()->withInput()->with('error', 'Tanggal lahir tidak valid.');
        }

        if (strtotime($tanggalLahir) > time()) {
            return redirect()->back()->withInput()->with('error', 'Tanggal lahir tidak boleh di masa depan.');
        }

        if ($namaKampus === '' || mb_strlen($namaKampus) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama kampus wajib diisi (maksimal 100 karakter).');
        }

        // Simpan permanen + tandai konfirmasi selesai.
        $result = (new LecturerModel())->update($lecturer['id'], [
            'nama'          => $nama,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'nama_kampus'   => $namaKampus,
            'email'         => $email,
            'profile_confirmed'    => 1,
            'profile_confirmed_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $result) {
            // Penyimpanan gagal: tetap di halaman konfirmasi, JANGAN ke dashboard.
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan profil. Silakan coba lagi.');
        }

        $session->set([
            'profile_confirmed' => true,
            'user_name'         => $nama,
        ]);

        return redirect()->to('/lecturer/subjects')
            ->with('success', 'Profil berhasil dikonfirmasi. Selamat datang di dashboard dosen!');
    }
}
