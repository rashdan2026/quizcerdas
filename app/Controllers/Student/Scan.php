<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\AdService;
use App\Models\AttendanceModel;
use App\Models\MeetingModel;

class Scan extends BaseController
{
    /**
     * Toleransi grace period (detik) setelah expired_at untuk tetap menerima
     * token yang baru saja di-rotate. Sebelumnya logika ini BROKEN — hanya
     * cek `expired_at >= now - 60` tanpa memverifikasi token, sehingga token
     * expired berapa pun umurnya bisa dipakai selama meeting masih aktif
     * di-refresh (45 detik). Sekarang cek token sebelumnya valid dalam
     * window grace period.
     */
    private const TOKEN_GRACE_SECONDS = 60;

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

        if (! $this->isTokenValid($meeting, $token)) {
            return redirect()->back()->withInput()->with('error', 'Token QR tidak valid atau sudah kedaluwarsa. Silakan scan ulang.');
        }

        if (strtotime((string) $meeting['expired_at']) < time() - self::TOKEN_GRACE_SECONDS) {
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

        if (! $this->isTokenValid($meeting, $token)) {
            return $this->response->setJSON(['valid' => false, 'message' => 'Token QR tidak valid atau sudah kedaluwarsa. Silakan scan ulang.']);
        }

        if (strtotime((string) $meeting['expired_at']) < time() - self::TOKEN_GRACE_SECONDS) {
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
        // Lookup by current token (grace period via expired_at >= now-60)
        $meeting = $db->table('meetings')
            ->where('token_qr', $token)
            ->where('expired_at >=', date('Y-m-d H:i:s', time() - 60))
            ->get()
            ->getRowArray();

        if (! $meeting) {
            // Fallback: lookup by previous token (yang baru di-rotate)
            $meeting = $db->table('meetings')
                ->where('previous_token_qr', $token)
                ->where('previous_token_expired_at >=', date('Y-m-d H:i:s', time() - 60))
                ->get()
                ->getRowArray();
        }

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

        $adService = new AdService();
        $ad        = $adService->pickAdForDisplay('pdf');

        $linkModel = new \App\Models\MeetingLinkModel();
        $links     = $linkModel->getByMeeting($meetingId);

        return view('student/detail', [
            'meeting'   => $row,
            'pdfToken'  => $pdfToken,
            'pdfAd'     => $ad,
            'adLockSec' => $adService->getLockSeconds(),
            'links'     => $links,
        ]);
    }

    /**
     * Validasi token QR — harus match current ATAU previous (jika previous
     * masih dalam grace period). Mencegah token expired berapa pun umurnya
     * diterima hanya karena meeting masih aktif di-refresh.
     */
    private function isTokenValid(array $meeting, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        // Match dengan token saat ini
        if (hash_equals((string) $meeting['token_qr'], $token)) {
            return true;
        }

        // Match dengan token sebelumnya (previous) — hanya jika masih dalam grace period
        if (! empty($meeting['previous_token_qr'])
            && hash_equals((string) $meeting['previous_token_qr'], $token)
            && ! empty($meeting['previous_token_expired_at'])
            && strtotime((string) $meeting['previous_token_expired_at']) >= time() - self::TOKEN_GRACE_SECONDS
        ) {
            return true;
        }

        return false;
    }
}
