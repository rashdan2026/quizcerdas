<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Models\AttendanceModel;
use App\Models\MeetingModel;

class Scan extends BaseController
{
    public function index()
    {
        $meetings = db_connect()->table('meetings m')
            ->select('m.id, m.judul, m.pertemuan_ke, s.kode_mk, s.nama_mk')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('s.is_active', 1)
            ->orderBy('m.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('student/scan', ['meetings' => $meetings]);
    }

    public function submit()
    {
        $meetingId = (int) $this->request->getPost('meeting_id');
        $token     = trim((string) $this->request->getPost('token_qr'));
        $latitude  = trim((string) $this->request->getPost('latitude'));
        $longitude = trim((string) $this->request->getPost('longitude'));
        $email     = (string) session('user_id');

        if ($meetingId <= 0 || $token === '') {
            return redirect()->back()->withInput()->with('error', 'Meeting dan token QR wajib diisi.');
        }

        // Validasi geolokasi (wajib jika environment mengkonfigurasi attendance.requireGeo = true)
        $requireGeo = (bool) env('attendance.requireGeo', true);
        if ($requireGeo && ($latitude === '' || $longitude === '')) {
            return redirect()->back()->withInput()->with('error', 'Lokasi GPS wajib diaktifkan. Klik "Ambil Lokasi" terlebih dahulu.');
        }

        $meetingModel = new MeetingModel();
        $meeting      = $meetingModel->find($meetingId);

        if (! $meeting) {
            return redirect()->back()->withInput()->with('error', 'Pertemuan tidak ditemukan.');
        }

        if ($meeting['token_qr'] !== $token) {
            // Toleransi: cek apakah token yang di-input adalah token yang baru saja diganti
            // (grace period 60 detik untuk mengantisipasi keterlambatan scan/submit)
            $db         = db_connect();
            $recentRows = $db->table('meetings')
                ->select('id')
                ->where('id', $meetingId)
                ->where('expired_at >=', date('Y-m-d H:i:s', time() - 60))
                ->get()
                ->getResultArray();

            if (empty($recentRows)) {
                return redirect()->back()->withInput()->with('error', 'Token QR tidak sinkron dengan server.');
            }
            // Jika masih dalam grace period, lanjutkan proses
        }

        if (strtotime($meeting['expired_at']) < time() - 60) {
            return redirect()->back()->withInput()->with('error', 'Token QR sudah kedaluwarsa. Silakan scan ulang.');
        }

        $attendanceModel = new AttendanceModel();
        $exists          = $attendanceModel
            ->where('meeting_id', $meetingId)
            ->where('email', $email)
            ->first();

        if ($exists) {
            return redirect()->to('/student/dashboard')->with('info', 'Anda sudah absen pada pertemuan ini.');
        }

        $attendanceModel->insert([
            'meeting_id' => $meetingId,
            'email'      => $email,
            'latitude'   => $latitude !== '' ? $latitude : null,
            'longitude'  => $longitude !== '' ? $longitude : null,
            'waktu_absen'=> date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/student/dashboard')->with('success', 'Absensi berhasil dicatat.');
    }

    public function video(int $meetingId)
    {
        // Legacy endpoint — redirect ke detail
        return redirect()->to('/student/detail/' . $meetingId);
    }

    /**
     * API AJAX: validasi token QR sebelum mahasiswa mengambil koordinat.
     * GET /student/scan/validate-token?meeting_id=X&token_qr=Y
     */
    public function validateToken()
    {
        $meetingId = (int) $this->request->getGet('meeting_id');
        $token     = trim((string) $this->request->getGet('token_qr'));

        if ($meetingId <= 0 || $token === '') {
            return $this->response->setJSON(['valid' => false, 'message' => 'Parameter tidak lengkap.']);
        }

        $meetingModel = new MeetingModel();
        $meeting      = $meetingModel->find($meetingId);

        if (! $meeting) {
            return $this->response->setJSON(['valid' => false, 'message' => 'Pertemuan tidak ditemukan.']);
        }

        if ($meeting['token_qr'] !== $token) {
            return $this->response->setJSON(['valid' => false, 'message' => 'Token QR tidak cocok dengan server.']);
        }

        // Toleransi grace period 60 detik setelah expired_at
        if (strtotime($meeting['expired_at']) < time() - 60) {
            return $this->response->setJSON(['valid' => false, 'message' => 'Token QR sudah kedaluwarsa. Silakan scan ulang.']);
        }

        return $this->response->setJSON(['valid' => true, 'message' => 'OK']);
    }

    /**
     * API AJAX (manual input): cari meeting aktif berdasarkan token saja.
     * Digunakan saat mahasiswa input token manual (tanpa scan QR).
     * GET /student/scan/lookup-token?token_qr=X
     * Response: { found: bool, meeting_id: int, message: string }
     */
    public function lookupToken()
    {
        $token = trim((string) $this->request->getGet('token_qr'));

        if ($token === '') {
            return $this->response->setJSON(['found' => false, 'message' => 'Token kosong.']);
        }

        $db      = db_connect();
        $meeting = $db->table('meetings')
            ->where('token_qr', $token)
            ->where('expired_at >=', date('Y-m-d H:i:s', time() - 60))
            ->get()
            ->getRowArray();

        if (! $meeting) {
            return $this->response->setJSON(['found' => false, 'message' => 'Token QR tidak aktif atau tidak ditemukan.']);
        }

        return $this->response->setJSON([
            'found'      => true,
            'meeting_id' => (int) $meeting['id'],
            'message'    => 'OK',
        ]);
    }


    public function meetingInfo(int $meetingId)
    {
        $row = db_connect()->table('meetings m')
            ->select('m.id, m.pertemuan_ke, m.judul, s.kode_mk, s.nama_mk')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('m.id', $meetingId)
            ->get()
            ->getRowArray();

        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }

        return $this->response->setJSON($row);
    }

    /**
     * Detail pertemuan + absensi mahasiswa (PDF viewer)
     */
    public function detail(int $meetingId)
    {
        $email = (string) session('user_id');

        $row = db_connect()->table('attendance a')
            ->select('a.waktu_absen, a.latitude, a.longitude, m.id as meeting_id, m.pertemuan_ke, m.judul, m.pdf_file_id, pf.file_name, s.kode_mk, s.nama_mk')
            ->join('meetings m', 'm.id = a.meeting_id')
            ->join('subjects s', 's.id = m.subject_id')
            ->join('pdf_files pf', 'pf.id = m.pdf_file_id', 'left')
            ->where('a.meeting_id', $meetingId)
            ->where('a.email', $email)
            ->get()
            ->getRowArray();

        if (! $row) {
            return redirect()->to('/student/dashboard')->with('error', 'Data absensi tidak ditemukan.');
        }

        $pdfToken = '';
        if (! empty($row['file_name'])) {
            $filePath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $row['file_name'];
            if (is_file($filePath)) {
                $pdfToken = hash_hmac('sha256', $meetingId . '|' . $email, (string) env('app.encryptionKey', 'fallback'));
            }
        }

        return view('student/detail', ['meeting' => $row, 'pdfToken' => $pdfToken]);
    }
}
