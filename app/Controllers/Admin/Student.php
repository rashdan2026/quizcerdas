<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\StudentModel;

class Student extends BaseController
{
    protected StudentModel $model;

    public function __construct()
    {
        $this->model = new StudentModel();
    }

    public function index()
    {
        $keyword = trim((string) ($this->request->getGet('q') ?? ''));
        $builder = $this->model;
        if ($keyword !== '') {
            $builder = $builder->groupStart()
                ->like('nama', $keyword)
                ->orLike('email', $keyword)
                ->orLike('npm', $keyword)
                ->groupEnd();
        }
        $items = $builder
            // Sort: mahasiswa yang paling baru login (last_login) tampil paling atas.
            // 1) NULL last_login (= belum pernah login) dikumpulkan di bagian bawah.
            // 2) Di antara yang sudah pernah login: yang paling baru (DESC) di atas.
            // 3) Tie-breaker: urut alfabetis by nama (ASC).
            ->orderBy('students.last_login IS NULL', 'ASC', false)  // NULL di bawah
            ->orderBy('students.last_login', 'DESC')                // terbaru di atas
            ->orderBy('students.nama', 'ASC')                       // tie-breaker alphabetis
            ->paginate(50, 'default');

        $pager = $builder->pager;
        if ($keyword !== '') {
            $pager->only(['q']); // pertahankan query string ?q=... saat navigasi halaman
        }

        $data = [
            'pageTitle'    => 'Manajemen Mahasiswa',
            'pageSubtitle' => 'Kelola akun mahasiswa',
            'items'        => $items,
            'keyword'      => $keyword,
            'pager'        => $pager,
        ];
        return view('admin/students/index', $data);
    }

    /**
     * Tampilkan form edit mahasiswa. Email sudah ter-decode otomatis oleh
     * CodeIgniter router dari URL segment (rawurlencode di link view).
     */
    public function edit($email = null)
    {
        // Defensive: kalau email kosong atau null, redirect ke index
        if (empty($email)) {
            return redirect()->to('/admin/students')->with('error', 'Email mahasiswa tidak valid.');
        }

        $row = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan: ' . esc($email));
        }
        return view('admin/students/form', [
            'pageTitle'    => 'Edit Mahasiswa',
            'pageSubtitle' => $row['nama'] . ' (' . $row['email'] . ')',
            'mhs'          => $row,
        ]);
    }

    /**
     * Simpan update mahasiswa. Email dikirim via POST hidden field,
     * bukan via URL — sehingga tidak perlu khawatir tentang karakter URL.
     */
    public function save()
    {
        $email = trim((string) $this->request->getPost('email'));
        if ($email === '') {
            return redirect()->to('/admin/students')->with('error', 'Email mahasiswa wajib diisi.');
        }

        $row = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan: ' . esc($email));
        }

        $nama    = trim((string) $this->request->getPost('nama'));
        $npm     = trim((string) $this->request->getPost('npm'));
        $kelas   = strtoupper(trim((string) $this->request->getPost('kelas')));
        $noWa    = trim((string) $this->request->getPost('no_whatsapp'));
        $jenkel  = (string) $this->request->getPost('jenkel');
        $pwd     = (string) $this->request->getPost('password');

        if ($nama === '' || $npm === '') {
            return redirect()->back()->withInput()->with('error', 'Nama dan NPM wajib diisi.');
        }
        if (! preg_match('/^[A-Z]+$/', $kelas)) {
            return redirect()->back()->withInput()->with('error', 'Kelas wajib huruf besar A-Z saja.');
        }
        if ($jenkel !== '' && ! in_array($jenkel, ['Laki-Laki', 'Perempuan'], true)) {
            return redirect()->back()->withInput()->with('error', 'Jenis kelamin tidak valid.');
        }

        $payload = [
            'nama'         => $nama,
            'npm'          => $npm,
            'kelas'        => $kelas,
            'no_whatsapp'  => $noWa,
        ];
        if ($jenkel !== '') {
            $payload['jenkel'] = $jenkel;
        }
        if ($pwd !== '') {
            $payload['password'] = password_hash($pwd, PASSWORD_BCRYPT);
        }
        $this->model->update($email, $payload);
        return redirect()->to('/admin/students')->with('success', 'Data mahasiswa diperbarui.');
    }

    /**
     * Reset password mahasiswa. Email dari URL segment (sudah ter-decode).
     */
    public function resetPassword($email = null)
    {
        if (empty($email)) {
            return redirect()->to('/admin/students')->with('error', 'Email mahasiswa tidak valid.');
        }

        $row = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan: ' . esc($email));
        }
        $newPwd = bin2hex(random_bytes(4));
        $this->model->update($email, ['password' => password_hash($newPwd, PASSWORD_BCRYPT), 'login_count' => 0]);
        return redirect()->to('/admin/students')->with('success', 'Password direset. Password baru: ' . $newPwd);
    }

    public function resetAllOtpCounters()
    {
        $db = db_connect();
        $affected = $db->table('students')
            ->where('login_count >', 0)
            ->update(['login_count' => 0]);

        return redirect()->to('/admin/students')->with('success', "✓ Berhasil reset counter OTP untuk {$affected} mahasiswa. Semua mahasiswa kembali ke siklus login pertama tanpa OTP.");
    }
}
