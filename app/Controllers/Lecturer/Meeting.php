<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\MeetingModel;
use App\Models\SubjectModel;
use DateTimeImmutable;

class Meeting extends BaseController
{
    protected $uploadDir;

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

        return view('lecturer/meeting_detail', [
            'meeting'   => $meeting,
            'payload'   => $payload,
            'qrUrl'     => $qrUrl,
            'rawToken'  => $meeting['token_qr'],
            'remaining' => $remaining,
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

        $meetingModel = new MeetingModel();
        $meetingModel->update($id, [
            'token_qr'   => $token,
            'expired_at' => $expiredAt,
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
            return view('lecturer/meeting_edit', ['meeting' => $meeting, 'pdfFiles' => $pdfFiles]);
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
}
