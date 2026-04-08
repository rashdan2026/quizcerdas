<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\AttendanceModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;

class Report extends BaseController
{
    /**
     * Dashboard utama: list pertemuan + jumlah absensi
     */
    public function index()
    {
        $db         = db_connect();
        $dosenId    = session('user_id');

        // Daftar pertemuan + jumlah yang sudah absen
        $meetings = $db->table('meetings m')
            ->select('m.id, m.pertemuan_ke, m.judul, m.expired_at, s.kode_mk, s.nama_mk, COUNT(a.id) as total_absen')
            ->join('subjects s', 's.id = m.subject_id')
            ->join('attendance a', 'a.meeting_id = m.id', 'left')
            ->where('s.dosen_id', $dosenId)
            ->groupBy('m.id')
            ->orderBy('m.id', 'DESC')
            ->get()
            ->getResultArray();

        // Daftar mahasiswa aktif (untuk form tambah manual)
        $students = (new StudentModel())->orderBy('email', 'ASC')->findAll();

        return view('lecturer/report', [
            'meetings' => $meetings,
            'students' => $students,
        ]);
    }

    /**
     * Detail mahasiswa yang sudah absen pada pertemuan tertentu
     */
    public function detail(int $meetingId)
    {
        $db      = db_connect();
        $dosenId = session('user_id');

        // Cek kepemilikan pertemuan
        $meeting = $db->table('meetings m')
            ->select('m.pertemuan_ke, m.judul, s.kode_mk, s.nama_mk')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('m.id', $meetingId)
            ->where('s.dosen_id', $dosenId)
            ->get()
            ->getRowArray();

        if (! $meeting) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Pertemuan tidak ditemukan.']);
        }

        // Daftar mahasiswa yang sudah absen
        $rows = $db->table('attendance a')
            ->select('a.waktu_absen, a.latitude, a.longitude, st.email, st.nama, st.kelas')
            ->join('students st', 'st.email = a.email')
            ->where('a.meeting_id', $meetingId)
            ->orderBy('a.waktu_absen', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'meeting' => $meeting,
            'rows'    => $rows,
        ]);
    }

    /**
     * Tambah absensi manual oleh dosen
     */
    public function addManual()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $meetingId = (int) $this->request->getPost('meeting_id');
        $email     = trim((string) $this->request->getPost('email'));
        $latitude  = trim((string) $this->request->getPost('latitude'));
        $longitude = trim((string) $this->request->getPost('longitude'));
        $dosenId   = session('user_id');

        // Validasi kepemilikan pertemuan
        $meeting = db_connect()->table('meetings m')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('m.id', $meetingId)
            ->where('s.dosen_id', $dosenId)
            ->get()
            ->getRowArray();

        if (! $meeting) {
            return redirect()->back()->with('error', 'Pertemuan tidak valid.');
        }

        // Validasi mahasiswa
        $student = (new StudentModel())->find($email);
        if (! $student) {
            return redirect()->back()->with('error', 'Mahasiswa dengan email tersebut tidak ditemukan.');
        }

        // Cek duplikasi
        $exists = (new AttendanceModel())
            ->where('meeting_id', $meetingId)
            ->where('email', $email)
            ->first();

        if ($exists) {
            return redirect()->back()->with('error', 'Mahasiswa ini sudah absen pada pertemuan tersebut.');
        }

        // Insert absensi manual
        (new AttendanceModel())->insert([
            'meeting_id'  => $meetingId,
            'email'       => $email,
            'latitude'    => $latitude !== '' ? $latitude : null,
            'longitude'   => $longitude !== '' ? $longitude : null,
            'waktu_absen' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/lecturer/report')->with('success', 'Absensi manual berhasil ditambahkan.');
    }
}
