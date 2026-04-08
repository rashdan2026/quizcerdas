<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;

/**
 * Controller untuk menampilkan PDF materi pertemuan.
 */
class PdfViewer extends BaseController
{
    public function index(int $meetingId)
    {
        $email = (string) session('user_id');

        $row = db_connect()->table('attendance a')
            ->select('m.id as meeting_id, m.pertemuan_ke, m.judul, m.pdf_file_id, pf.file_name, s.kode_mk, s.nama_mk, a.waktu_absen, a.latitude, a.longitude')
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

        if (empty($row['file_name'])) {
            return redirect()->to('/student/dashboard')->with('error', 'Materi PDF belum tersedia untuk pertemuan ini.');
        }

        $filePath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $row['file_name'];

        if (! is_file($filePath)) {
            return redirect()->to('/student/dashboard')->with('error', 'File PDF tidak ditemukan di server.');
        }

        $pdfToken = hash_hmac('sha256', $meetingId . '|' . $email, (string) env('app.encryptionKey', 'fallback'));

        return view('student/detail', [
            'meeting'  => $row,
            'pdfToken' => $pdfToken,
        ]);
    }
}
