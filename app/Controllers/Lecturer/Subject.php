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

    /**
     * Export attendance data for a subject with multiple sheets (one per meeting)
     */
    public function exportXls(int $subjectId)
    {
        $db      = db_connect();
        $dosenId = session('user_id');

        // Verify subject ownership
        $subject = $db->table('subjects')
            ->where('id', $subjectId)
            ->where('dosen_id', $dosenId)
            ->get()
            ->getRowArray();

        if (!$subject) {
            return redirect()->back()->with('error', 'Matakuliah tidak ditemukan.');
        }

        // Get all meetings for this subject
        $meetings = $db->table('meetings')
            ->where('subject_id', $subjectId)
            ->orderBy('pertemuan_ke', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($meetings)) {
            return redirect()->back()->with('error', 'Belum ada pertemuan untuk matakuliah ini.');
        }

        // Get attendance data for all meetings
        $attendanceData = [];
        foreach ($meetings as $meeting) {
            $attendanceData[$meeting['id']] = $db->table('attendance a')
                ->select('a.waktu_absen, a.latitude, a.longitude, st.email, st.nama, st.npm, st.kelas')
                ->join('students st', 'st.email = a.email')
                ->where('a.meeting_id', $meeting['id'])
                ->orderBy('st.kelas', 'ASC')
                ->orderBy('a.waktu_absen', 'ASC')
                ->get()
                ->getResultArray();
        }

        // Generate Excel file with multiple sheets
        $html = view('lecturer/export_xls_multi', [
            'subject' => $subject,
            'meetings' => $meetings,
            'attendanceData' => $attendanceData,
        ]);

        // Return as downloadable file
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename="Absensi_' . $subject['kode_mk'] . '_' . date('Y-m-d_His') . '.xls"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($html);
    }
}
