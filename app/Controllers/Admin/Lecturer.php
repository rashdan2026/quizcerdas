<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LecturerModel;

class Lecturer extends BaseController
{
    protected LecturerModel $model;

    public function __construct()
    {
        $this->model = new LecturerModel();
    }

    public function index()
    {
        $items = $this->model->orderBy('nama', 'ASC')->findAll();
        $data = [
            'pageTitle'    => 'Manajemen Dosen',
            'pageSubtitle' => 'Kelola akun dosen',
            'items'        => $items,
        ];
        return view('admin/lecturers/index', $data);
    }

    public function create()
    {
        return view('admin/lecturers/form', [
            'pageTitle'    => 'Tambah Dosen',
            'pageSubtitle' => 'Buat akun dosen baru',
            'dosen'        => null,
        ]);
    }

    public function edit($id)
    {
        $row = $this->model->find((int) $id);
        if (! $row) {
            return redirect()->to('/admin/lecturers')->with('error', 'Dosen tidak ditemukan.');
        }
        return view('admin/lecturers/form', [
            'pageTitle'    => 'Edit Dosen',
            'pageSubtitle' => 'Perbarui data dosen',
            'dosen'        => $row,
        ]);
    }

    public function save()
    {
        $id    = (int) ($this->request->getPost('id') ?? 0);
        $nama  = trim((string) $this->request->getPost('nama'));
        $email = trim((string) $this->request->getPost('email'));
        $pwd   = (string) $this->request->getPost('password');
        $active= (int) $this->request->getPost('is_active');

        if ($nama === '' || $email === '') {
            return redirect()->back()->withInput()->with('error', 'Nama dan email wajib diisi.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Email tidak valid.');
        }
        if ($id === 0 && strlen($pwd) < 6) {
            return redirect()->back()->withInput()->with('error', 'Password minimal 6 karakter.');
        }

        $existing = $this->model->where('email', $email)->first();
        if ($existing && (int) $existing['id'] !== $id) {
            return redirect()->back()->withInput()->with('error', 'Email sudah digunakan.');
        }

        $payload = [
            'nama'      => $nama,
            'email'     => $email,
            'is_active' => $active ? 1 : 0,
        ];

        if ($pwd !== '') {
            $payload['password'] = password_hash($pwd, PASSWORD_BCRYPT);
        }

        if ($id > 0) {
            $this->model->update($id, $payload);
            return redirect()->to('/admin/lecturers')->with('success', 'Data dosen diperbarui.');
        }
        if (! isset($payload['password'])) {
            $payload['password'] = password_hash('password123', PASSWORD_BCRYPT);
        }
        $this->model->insert($payload);
        return redirect()->to('/admin/lecturers')->with('success', 'Dosen baru ditambahkan. Password default: password123');
    }

    public function toggle($id)
    {
        $row = $this->model->find((int) $id);
        if (! $row) {
            return redirect()->to('/admin/lecturers')->with('error', 'Dosen tidak ditemukan.');
        }
        $this->model->update($id, ['is_active' => $row['is_active'] ? 0 : 1]);
        return redirect()->to('/admin/lecturers')->with('success', 'Status dosen diperbarui.');
    }

    public function resetPassword($id)
    {
        $row = $this->model->find((int) $id);
        if (! $row) {
            return redirect()->to('/admin/lecturers')->with('error', 'Dosen tidak ditemukan.');
        }
        $newPwd = bin2hex(random_bytes(4));
        $this->model->update($id, ['password' => password_hash($newPwd, PASSWORD_BCRYPT)]);
        return redirect()->to('/admin/lecturers')->with('success', 'Password direset. Password baru: ' . $newPwd);
    }
}
