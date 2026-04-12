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
        try {
            $db         = db_connect();
            $dosenId    = session('user_id');
            
            if (!$dosenId) {
                return redirect()->to('/auth')->with('error', 'Silakan login terlebih dahulu.');
            }
            
            $selectedKelas = $this->request->getGet('kelas');

            // Check if students table exists
            $studentsTableExists = $db->query(
                "SELECT COUNT(*) as cnt FROM information_schema.tables 
                 WHERE table_schema = ? AND table_name = 'students'",
                [$db->getDatabase()]
            )->getRowArray()['cnt'] > 0;

            // Daftar pertemuan + jumlah yang sudah absen
            if ($selectedKelas && $studentsTableExists) {
                // Get meetings with attendance count for specific class
                $sql = "SELECT m.id, m.pertemuan_ke, m.judul, m.expired_at, s.kode_mk, s.nama_mk,
                        (SELECT COUNT(*) FROM attendance a 
                         JOIN students st ON st.email = a.email 
                         WHERE a.meeting_id = m.id AND st.kelas = ?) as total_absen
                        FROM meetings m
                        JOIN subjects s ON s.id = m.subject_id
                        WHERE s.dosen_id = ?
                        ORDER BY m.id DESC";
                $meetings = $db->query($sql, [$selectedKelas, $dosenId])->getResultArray();
            } else {
                $meetings = $db->table('meetings m')
                    ->select('m.id, m.pertemuan_ke, m.judul, m.expired_at, s.kode_mk, s.nama_mk, COUNT(a.id) as total_absen')
                    ->join('subjects s', 's.id = m.subject_id')
                    ->join('attendance a', 'a.meeting_id = m.id', 'left')
                    ->where('s.dosen_id', $dosenId)
                    ->groupBy('m.id')
                    ->orderBy('m.id', 'DESC')
                    ->get()
                    ->getResultArray();
            }

            // Get list of all classes for filter dropdown
            $kelasList = [];
            if ($studentsTableExists) {
                try {
                    $kelasListRaw = $db->table('students')
                        ->select('DISTINCT kelas')
                        ->where('kelas IS NOT NULL', null, false)
                        ->where('kelas !=', '')
                        ->orderBy('kelas', 'ASC')
                        ->get()
                        ->getResultArray();
                    $kelasList = array_filter(array_column($kelasListRaw, 'kelas'), function($k) {
                        return !empty($k);
                    });
                    $kelasList = array_values($kelasList);
                } catch (\Exception $e) {
                    // kelas column might not exist
                    $kelasList = [];
                }
            }

            // Daftar mahasiswa aktif (untuk form tambah manual)
            $students = [];
            if ($studentsTableExists) {
                try {
                    $students = (new StudentModel())->orderBy('email', 'ASC')->findAll();
                } catch (\Exception $e) {
                    $students = [];
                }
            }

            return view('lecturer/report', [
                'meetings' => $meetings ?? [],
                'students' => $students ?? [],
                'kelasList' => $kelasList ?? [],
                'selectedKelas' => $selectedKelas,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Report index error: ' . $e->getMessage());
            log_message('error', 'Trace: ' . $e->getTraceAsString());
            
            // Return with error flash message
            return redirect()->back()->with('error', 'Error loading report: ' . $e->getMessage());
        }
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
