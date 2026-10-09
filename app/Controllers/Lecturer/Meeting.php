<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\MeetingLinkModel;
use App\Models\MeetingModel;
use App\Models\SubjectModel;
use DateTimeImmutable;

class Meeting extends BaseController
{
    protected $uploadDir;
    private const MAX_LINKS_PER_MEETING = 3;
    private const ALLOWED_LINK_TYPES    = ['youtube', 'file'];

    public function __construct()
    {
        $this->uploadDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR;
        if (! is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function index()
    {
        $db = db_connect();
        $meetings = $db->table('meetings m')
            ->select('m.*, s.kode_mk, s.nama_mk, COUNT(a.id) as jumlah_absen')
            ->join('subjects s', 's.id = m.subject_id')
            ->join('attendance a', 'a.meeting_id = m.id', 'left')
            ->where('s.dosen_id', session('user_id'))
            ->where('s.is_active', 1)
            ->groupBy('m.id')
            ->orderBy('m.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('lecturer/meetings_index', ['meetings' => $meetings]);
    }

    public function create()
    {
        $subjectModel = new SubjectModel();
        $subjects     = $subjectModel
            ->where('dosen_id', session('user_id'))
            ->where('is_active', 1)
            ->orderBy('kode_mk', 'ASC')
            ->findAll();

        // Daftar PDF yang tersedia
        $pdfFiles = db_connect()->table('pdf_files')
            ->where('dosen_id', session('user_id'))
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($this->request->getMethod() !== 'POST') {
            return view('lecturer/meeting_form', ['subjects' => $subjects, 'pdfFiles' => $pdfFiles]);
        }

        $subjectId = (int) $this->request->getPost('subject_id');
        $subject   = $subjectModel
            ->where('id', $subjectId)
            ->where('dosen_id', session('user_id'))
            ->where('is_active', 1)
            ->first();

        if (! $subject) {
            return redirect()->back()->withInput()->with('error', 'Matakuliah aktif tidak ditemukan.');
        }

        $judul     = trim((string) $this->request->getPost('judul'));
        $deskripsi = trim((string) $this->request->getPost('deskripsi'));
        $pdfFileId = $this->request->getPost('pdf_file_id') !== '' ? (int) $this->request->getPost('pdf_file_id') : null;

        // Validasi PDF jika dipilih
        if ($pdfFileId !== null) {
            $pdfCheck = db_connect()->table('pdf_files')
                ->where('id', $pdfFileId)
                ->where('dosen_id', session('user_id'))
                ->get()
                ->getRowArray();
            if (! $pdfCheck) {
                return redirect()->back()->withInput()->with('error', 'File PDF tidak valid.');
            }
        }

        $meetingModel = new MeetingModel();
        $pertemuanKe  = $meetingModel->where('subject_id', $subjectId)->countAllResults() + 1;
        $token        = $this->newToken();
        $expiredAt    = $this->newExpiry();

        $meetingModel->insert([
            'subject_id'   => $subjectId,
            'pertemuan_ke' => $pertemuanKe,
            'judul'        => $judul,
            'deskripsi'    => $deskripsi,
            'pdf_file_id'  => $pdfFileId,
            'token_qr'     => $token,
            'expired_at'   => $expiredAt,
        ]);
        $meetingId = (int) $meetingModel->getInsertID();

        // Simpan link referensi (maks 3)
        $linkError = $this->saveLinks($meetingId, $this->request->getPost('links'));
        if ($linkError !== null) {
            // Rollback meeting jika link invalid
            $meetingModel->delete($meetingId);
            return redirect()->back()->withInput()->with('error', $linkError);
        }

        return redirect()->to('/lecturer/meetings')->with('success', 'Pertemuan berhasil dibuat.');
    }

    public function show(int $id)
    {
        $meeting = $this->findOwnedMeeting($id);

        if (! $meeting) {
            return redirect()->to('/lecturer/meetings')->with('error', 'Pertemuan tidak ditemukan.');
        }

        $payload   = $this->tokenPayload($meeting['id'], $meeting['token_qr']);
        $qrUrl     = 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&data=' . urlencode($payload);
        $remaining = max(0, strtotime($meeting['expired_at']) - time());
        $links     = (new MeetingLinkModel())->getByMeeting($id);

        return view('lecturer/meeting_detail', [
            'meeting'   => $meeting,
            'payload'   => $payload,
            'qrUrl'     => $qrUrl,
            'rawToken'  => $meeting['token_qr'],
            'remaining' => $remaining,
            'links'     => $links,
        ]);
    }

    public function refreshToken(int $id)
    {
        $meeting = $this->findOwnedMeeting($id);

        if (! $meeting) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'Pertemuan tidak ditemukan']);
        }

        $token     = $this->newToken();
        $expiredAt = $this->newExpiry();

        // Simpan token saat ini sebagai "previous" sebelum overwrite,
        // supaya grace period QR rotation bisa diverifikasi dengan benar
        // (cek token sebelumnya valid, bukan hanya cek meeting expire recently).
        $meetingModel = new MeetingModel();
        $meetingModel->update($id, [
            'previous_token_qr'         => $meeting['token_qr'],
            'previous_token_expired_at' => $meeting['expired_at'],
            'token_qr'                  => $token,
            'expired_at'                => $expiredAt,
        ]);

        $payload = $this->tokenPayload($id, $token);

        return $this->response->setJSON([
            'token_qr'   => $token,
            'expired_at' => $expiredAt,
            'payload'    => $payload,
            'qr_url'     => 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&data=' . urlencode($payload),
            'csrfHash'   => csrf_hash(),
        ]);
    }

    /**
     * Form edit pertemuan
     */
    public function edit(int $id)
    {
        $meeting = $this->findOwnedMeeting($id);

        if (! $meeting) {
            return redirect()->to('/lecturer/meetings')->with('error', 'Pertemuan tidak ditemukan.');
        }

        // Daftar PDF yang tersedia
        $pdfFiles = db_connect()->table('pdf_files')
            ->where('dosen_id', session('user_id'))
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($this->request->getMethod() !== 'POST') {
            $existingLinks = (new MeetingLinkModel())->getByMeeting($id);
            return view('lecturer/meeting_edit', [
                'meeting'      => $meeting,
                'pdfFiles'     => $pdfFiles,
                'existingLinks' => $existingLinks,
                'maxLinks'     => self::MAX_LINKS_PER_MEETING,
            ]);
        }

        $judul     = trim((string) $this->request->getPost('judul'));
        $deskripsi = trim((string) $this->request->getPost('deskripsi'));
        $pdfFileId = $this->request->getPost('pdf_file_id') !== '' ? (int) $this->request->getPost('pdf_file_id') : null;

        // Validasi PDF jika dipilih
        if ($pdfFileId !== null) {
            $pdfCheck = db_connect()->table('pdf_files')
                ->where('id', $pdfFileId)
                ->where('dosen_id', session('user_id'))
                ->get()
                ->getRowArray();
            if (! $pdfCheck) {
                return redirect()->back()->withInput()->with('error', 'File PDF tidak valid.');
            }
        }

        $meetingModel = new MeetingModel();
        $meetingModel->update($id, [
            'judul'       => $judul,
            'deskripsi'   => $deskripsi,
            'pdf_file_id' => $pdfFileId,
        ]);

        // Replace-all strategy untuk link referensi
        $linkModel = new MeetingLinkModel();
        $linkModel->where('meeting_id', $id)->delete();
        $linkError = $this->saveLinks($id, $this->request->getPost('links'));
        if ($linkError !== null) {
            return redirect()->back()->withInput()->with('error', $linkError);
        }

        return redirect()->to('/lecturer/meetings')->with('success', 'Pertemuan berhasil diperbarui.');
    }

    /**
     * Hapus pertemuan (hanya jika belum ada absensi)
     */
    public function delete(int $id)
    {
        $meeting = $this->findOwnedMeeting($id);

        if (! $meeting) {
            return redirect()->to('/lecturer/meetings')->with('error', 'Pertemuan tidak ditemukan.');
        }

        $attendanceCount = db_connect()->table('attendance')->where('meeting_id', $id)->countAllResults();

        if ($attendanceCount > 0) {
            return redirect()->to('/lecturer/meetings')->with('error', 'Pertemuan tidak bisa dihapus karena sudah ada ' . $attendanceCount . ' data absensi.');
        }

        $meetingModel = new MeetingModel();
        $meetingModel->delete($id);

        return redirect()->to('/lecturer/meetings')->with('success', 'Pertemuan berhasil dihapus.');
    }

    private function findOwnedMeeting(int $id): ?array
    {
        $db = db_connect();

        $meeting = $db->table('meetings m')
            ->select('m.*, s.kode_mk, s.nama_mk, l.nama as nama_dosen')
            ->join('subjects s', 's.id = m.subject_id')
            ->join('lecturers l', 'l.id = s.dosen_id')
            ->where('m.id', $id)
            ->where('s.dosen_id', session('user_id'))
            ->get()
            ->getRowArray();

        return $meeting ?: null;
    }

    private function tokenPayload(int $meetingId, string $token): string
    {
        return $meetingId . '|' . $token;
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function newExpiry(): string
    {
        return (new DateTimeImmutable('+45 seconds'))->format('Y-m-d H:i:s');
    }

    /**
     * Validasi & simpan link referensi untuk meeting.
     * Return null jika sukses, atau string pesan error jika gagal.
     */
    private function saveLinks(int $meetingId, $rawLinks): ?string
    {
        if (! is_array($rawLinks)) {
            return null;
        }

        // Kumpulkan link yang valid (tidak kosong)
        $valid = [];
        foreach ($rawLinks as $row) {
            $type = isset($row['link_type']) ? trim((string) $row['link_type']) : '';
            $url  = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '') {
                continue;
            }
            if (! in_array($type, self::ALLOWED_LINK_TYPES, true)) {
                return 'Tipe link tidak valid. Gunakan "youtube" atau "file".';
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                return "URL tidak valid: {$url}";
            }
            $valid[] = ['link_type' => $type, 'url' => $url];
        }

        if (count($valid) > self::MAX_LINKS_PER_MEETING) {
            return 'Maksimal ' . self::MAX_LINKS_PER_MEETING . ' link per pertemuan.';
        }

        if ($valid === []) {
            return null; // Tidak ada link, OK
        }

        $linkModel = new MeetingLinkModel();
        foreach ($valid as $link) {
            $linkModel->insert([
                'meeting_id' => $meetingId,
                'link_type'  => $link['link_type'],
                'url'        => $link['url'],
            ]);
        }

        return null;
    }

    /**
     * Parse URL YouTube → URL embed.
     * Support: youtube.com/watch?v=ID, youtu.be/ID, /shorts/ID, /embed/ID
     * Return null jika tidak valid.
     */
    public static function youtubeEmbedUrl(string $url): ?string
    {
        $videoId = null;

        // youtu.be/ID
        if (preg_match('#^https?://youtu\.be/([A-Za-z0-9_-]{6,})#', $url, $m)) {
            $videoId = $m[1];
        }
        // youtube.com/embed/ID atau youtube.com/shorts/ID
        elseif (preg_match('#^https?://(?:www\.)?youtube\.com/(?:embed|shorts)/([A-Za-z0-9_-]{6,})#', $url, $m)) {
            $videoId = $m[1];
        }
        // youtube.com/watch?v=ID
        elseif (preg_match('#^https?://(?:www\.)?youtube\.com/watch\?v=([A-Za-z0-9_-]{6,})#', $url, $m)) {
            $videoId = $m[1];
        }

        return $videoId ? 'https://www.youtube.com/embed/' . $videoId : null;
    }
}
