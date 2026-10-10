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

        $canEditProfile        = $this->canEditProfile($student['profile_updated_at'] ?? null);
        $needsProfileComplete  = empty($student['profile_updated_at']);

        // v5.8.5: iklan dashboard tampil untuk SEMUA mahasiswa yang login,
        // bukan hanya first-login (sebelumnya dibatasi $needsProfileComplete,
        // sehingga mahasiswa lama tidak pernah melihat iklan setelah login).
        // Aturan tayang per-user tetap dikontrol AdService:
        //  - kuota harian  : app_settings.daily_ad_max_display (per user per hari)
        //  - frequency gate: detik % default_ad_setting_number == 0
        //  - anti-repeat   : iklan yang sama tidak muncul 2x untuk user yang sama
        //                     dalam hari yang sama
        //  - countdown     : app_settings.ad_lock_duration_seconds (tombol X aktif
        //                     setelah countdown habis; tidak ada tombol lewati)
        $adService   = new AdService();
        $dashboardAd = $adService->pickAdForDisplay('dashboard', $student['jenkel'] ?? null);

        return view('student/dashboard', [
            'rows'                  => $rows,
            'student'               => $student,
            'canEditProfile'        => $canEditProfile,
            'needsProfileComplete'  => $needsProfileComplete,
            'dashboardAd'           => $dashboardAd,
            'adLockSec'             => $adService->getLockSeconds(),
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

        $validation = $this->validateProfileInput(false);

        if ($validation !== true) {
            return redirect()->back()->withInput()->with('error', $validation);
        }

        $npm        = trim((string) $this->request->getPost('npm'));
        $nama       = $this->normalizeName(trim((string) $this->request->getPost('nama')));
        $kelas      = strtoupper(trim((string) $this->request->getPost('kelas')));
        $jenkel     = (string) $this->request->getPost('jenkel');
        $noWhatsapp = trim((string) $this->request->getPost('no_whatsapp'));

        $studentModel->update(session('user_id'), [
            'npm'                => $npm,
            'nama'               => $nama,
            'kelas'              => $kelas,
            'jenkel'             => $jenkel,
            'no_whatsapp'        => $noWhatsapp,
            'profile_updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/student/dashboard')->with('success', 'Profil berhasil diperbarui. Anda dapat mengedit kembali setelah 1 minggu.');
    }

    public function updateProfile()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'message' => 'Permintaan tidak valid.']);
        }

        $studentModel = new StudentModel();
        $student      = $studentModel->find(session('user_id'));

        if (! $student) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Data mahasiswa tidak ditemukan.']);
        }

        if (! empty($student['profile_updated_at'])) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Profil sudah pernah dilengkapi.']);
        }

        $validation = $this->validateProfileInput(true);

        if ($validation !== true) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $validation]);
        }

        $npm        = trim((string) $this->request->getPost('npm'));
        $nama       = $this->normalizeName(trim((string) $this->request->getPost('nama')));
        $kelas      = strtoupper(trim((string) $this->request->getPost('kelas')));
        $jenkel     = (string) $this->request->getPost('jenkel');
        $noWhatsapp = trim((string) $this->request->getPost('no_whatsapp'));

        $studentModel->update(session('user_id'), [
            'npm'                => $npm,
            'nama'               => $nama,
            'kelas'              => $kelas,
            'jenkel'             => $jenkel,
            'no_whatsapp'        => $noWhatsapp,
            'profile_updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'ok'      => true,
            'message' => 'Profil berhasil dilengkapi. Nama Anda disimpan sebagai: ' . $nama,
        ]);
    }

    /**
     * Validasi input profil. Mengembalikan true jika valid, atau string pesan
     * kesalahan.
     *
     * Untuk first-login modal ($strictFirstLogin = true) semua field berikut
     * WAJIB diisi (tidak boleh kosong):
     *   - npm (9 digit angka)
     *   - nama (3-100 karakter)
     *   - kelas (huruf besar A-Z, maks 20 karakter)
     *   - jenkel (Laki-Laki / Perempuan)
     *   - no_whatsapp (maks 15 karakter, tidak boleh kosong)
     *
     * Untuk edit mingguan, aturan identik dengan first-login (semua field
     * divalidasi sesuai aturan di atas).
     */
    private function validateProfileInput(bool $strictFirstLogin): bool|string
    {
        $npm        = trim((string) $this->request->getPost('npm'));
        $nama       = trim((string) $this->request->getPost('nama'));
        $kelas      = strtoupper(trim((string) $this->request->getPost('kelas')));
        $jenkel     = (string) $this->request->getPost('jenkel');
        $noWhatsapp = trim((string) $this->request->getPost('no_whatsapp'));

        if ($npm === '' || strlen($npm) !== 9 || ! ctype_digit($npm)) {
            return 'NPM wajib diisi 9 digit angka.';
        }

        if ($nama === '' || strlen($nama) < 3 || strlen($nama) > 100) {
            return 'Nama wajib diisi 3-100 karakter.';
        }

        if ($kelas === '' || ! preg_match('/^[A-Z]+$/', $kelas)) {
            return 'Kelas wajib diisi dengan huruf besar (A-Z) saja, tanpa angka atau simbol.';
        }

        if (strlen($kelas) > 20) {
            return 'Kelas maksimal 20 karakter.';
        }

        if (! in_array($jenkel, ['Laki-Laki', 'Perempuan'], true)) {
            return 'Jenis kelamin wajib dipilih.';
        }

        if ($noWhatsapp === '' || strlen($noWhatsapp) > 15) {
            return 'No. WhatsApp wajib diisi, maksimal 15 karakter.';
        }

        return true;
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

    /**
     * Normalisasi nama mahasiswa ke Title Case (Awalan Kata Huruf Besar,
     * Sisanya Huruf Kecil). Berlaku untuk first-login modal dan edit mingguan.
     *
     * Aturan:
     *  - Trim whitespace di awal/akhir
     *  - Collapse multiple whitespace jadi 1 spasi
     *  - strtolower seluruh string, lalu capitalize huruf pertama tiap kata
     *  - Kata yang dipisahkan apostrof (mis. "O'Brian") tetap mempertahankan
     *    huruf besar setelah apostrof
     *  - Akronim/huruf kapital dalam kata (mis. "McDonald", "USA") di-honor
     *    via strtolower menyeluruh (kasus umum untuk nama Indonesia)
     *
     * Contoh:
     *  "  AFIQ   khalifi  "  → "Afiq Khalifi"
     *  "MUHAMMAD SYAFIQ"     → "Muhammad Syafiq"
     *  "muhammad rizky"      → "Muhammad Rizky"
     *  "john doe"            → "John Doe"
     *
     * @param string $raw Nama input dari form (sudah di-trim)
     * @return string     Nama yang sudah dinormalisasi
     */
    private function normalizeName(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        // Collapse whitespace & trim
        $clean = trim(preg_replace('/\s+/u', ' ', $raw));
        if ($clean === '') {
            return '';
        }

        // strtolower menyeluruh, lalu kapitalisasi huruf pertama tiap kata.
        // Setelah apostrof di-honor (mis. "o'brian" → "O'brian").
        $lower     = mb_strtolower($clean, 'UTF-8');
        $titleCase = mb_convert_case($lower, MB_CASE_TITLE_SIMPLE, 'UTF-8');

        // MB_CASE_TITLE_SIMPLE sudah handle apostrof dengan benar
        // (PHP 7.3+: "o'brian" → "O'brian").
        return $titleCase;
    }
}
