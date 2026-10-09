<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\AdService;
use App\Models\StudentModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $studentModel = new StudentModel();
        $student = $studentModel->find(session('user_id'));

        $rows = db_connect()->table('attendance a')
            ->select('a.id as attendance_id, a.waktu_absen, a.latitude, a.longitude, m.id as meeting_id, m.pertemuan_ke, m.judul, s.kode_mk, s.nama_mk')
            ->join('meetings m', 'm.id = a.meeting_id')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('a.email', session('user_id'))
            ->where('s.is_active', 1)
            ->orderBy('a.waktu_absen', 'DESC')
            ->get()
            ->getResultArray();

        $canEditProfile = $this->canEditProfile($student['profile_updated_at'] ?? null);

        return view('student/dashboard', [
            'rows' => $rows,
            'student' => $student,
            'canEditProfile' => $canEditProfile,
        ]);
    }

    public function editProfile()
    {
        $studentModel = new StudentModel();
        $student = $studentModel->find(session('user_id'));

        if (!$this->canEditProfile($student['profile_updated_at'] ?? null)) {
            return redirect()->to('/student/dashboard')->with('error', 'Anda tidak dapat mengedit profil. Hubungi administrator jika ada kesalahan data.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('student/edit_profile', ['student' => $student]);
        }

        $npm = trim((string) $this->request->getPost('npm'));
        $nama = trim((string) $this->request->getPost('nama'));
        $kelas = trim((string) $this->request->getPost('kelas'));
        $noWhatsapp = trim((string) $this->request->getPost('no_whatsapp'));

        if (strlen($npm) !== 9 || !ctype_digit($npm)) {
            return redirect()->back()->withInput()->with('error', 'NPM harus 9 digit angka.');
        }

        if (strlen($nama) < 3 || strlen($nama) > 100) {
            return redirect()->back()->withInput()->with('error', 'Nama harus 3-100 karakter.');
        }

        if (strlen($kelas) > 20) {
            return redirect()->back()->withInput()->with('error', 'Kelas maksimal 20 karakter.');
        }

        if (strlen($noWhatsapp) > 15) {
            return redirect()->back()->withInput()->with('error', 'No. WhatsApp maksimal 15 karakter.');
        }

        $studentModel->update(session('user_id'), [
            'npm' => $npm,
            'nama' => $nama,
            'kelas' => $kelas ?: null,
            'no_whatsapp' => $noWhatsapp ?: null,
            'profile_updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/student/dashboard')->with('success', 'Profil berhasil diperbarui. Anda dapat mengedit kembali setelah 1 minggu.');
    }

    private function canEditProfile(?string $profileUpdatedAt): bool
    {
        if (empty($profileUpdatedAt)) {
            return true;
        }

        $lastUpdate = strtotime($profileUpdatedAt);
        $oneWeekAgo = strtotime('-1 week');

        return $lastUpdate <= $oneWeekAgo;
    }
}
