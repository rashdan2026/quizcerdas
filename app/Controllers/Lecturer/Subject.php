<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\SubjectModel;

class Subject extends BaseController
{
    public function index()
    {
        $subjectModel = new SubjectModel();
        $subjects     = $subjectModel
            ->where('dosen_id', session('user_id'))
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('lecturer/subjects_index', ['subjects' => $subjects]);
    }

    public function create()
    {
        if ($this->request->getMethod() !== 'POST') {
            return view('lecturer/subject_form');
        }

        $kodeMk = strtoupper(trim((string) $this->request->getPost('kode_mk')));
        $namaMk = trim((string) $this->request->getPost('nama_mk'));

        if ($kodeMk === '' || $namaMk === '') {
            return redirect()->back()->withInput()->with('error', 'Kode MK dan nama MK wajib diisi.');
        }

        $subjectModel = new SubjectModel();
        $subjectModel->insert([
            'kode_mk'   => $kodeMk,
            'nama_mk'   => $namaMk,
            'is_active' => (int) ($this->request->getPost('is_active') ? 1 : 0),
            'dosen_id'  => session('user_id'),
        ]);

        return redirect()->to('/lecturer/subjects')->with('success', 'Matakuliah berhasil ditambahkan.');
    }

    public function toggle(int $id)
    {
        $subjectModel = new SubjectModel();
        $subject      = $subjectModel
            ->where('id', $id)
            ->where('dosen_id', session('user_id'))
            ->first();

        if (! $subject) {
            return redirect()->to('/lecturer/subjects')->with('error', 'Matakuliah tidak ditemukan.');
        }

        $subjectModel->update($id, ['is_active' => $subject['is_active'] ? 0 : 1]);

        return redirect()->to('/lecturer/subjects')->with('success', 'Status matakuliah diperbarui.');
    }
}
