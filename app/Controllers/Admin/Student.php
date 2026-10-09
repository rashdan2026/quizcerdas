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
            ->orderBy('last_login IS NULL', 'ASC', false)  // NULL di bawah (no-escape raw expr)
            ->orderBy('last_login', 'DESC')                // terbaru di atas
            ->orderBy('nama', 'ASC')                       // tie-breaker alphabetis
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

    public function edit($email)
    {
        $row = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan.');
        }
        return view('admin/students/form', [
            'pageTitle'    => 'Edit Mahasiswa',
            'pageSubtitle' => $row['nama'] . ' (' . $row['email'] . ')',
            'mhs'          => $row,
        ]);
    }

    public function save()
    {
        $email = trim((string) $this->request->getPost('email'));
        $row   = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan.');
        }
        $nama    = trim((string) $this->request->getPost('nama'));
        $npm     = trim((string) $this->request->getPost('npm'));
        $kelas   = trim((string) $this->request->getPost('kelas'));
        $noWa    = trim((string) $this->request->getPost('no_whatsapp'));
        $pwd     = (string) $this->request->getPost('password');

        if ($nama === '' || $npm === '') {
            return redirect()->back()->withInput()->with('error', 'Nama dan NPM wajib diisi.');
        }

        $payload = [
            'nama'         => $nama,
            'npm'          => $npm,
            'kelas'        => $kelas,
            'no_whatsapp'  => $noWa,
        ];
        if ($pwd !== '') {
            $payload['password'] = password_hash($pwd, PASSWORD_BCRYPT);
        }
        $this->model->update($email, $payload);
        return redirect()->to('/admin/students')->with('success', 'Data mahasiswa diperbarui.');
    }

    public function resetPassword($email)
    {
        $row = $this->model->find($email);
        if (! $row) {
            return redirect()->to('/admin/students')->with('error', 'Mahasiswa tidak ditemukan.');
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
