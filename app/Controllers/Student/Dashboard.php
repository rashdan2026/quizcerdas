<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $rows = db_connect()->table('attendance a')
            ->select('a.id as attendance_id, a.waktu_absen, a.latitude, a.longitude, m.id as meeting_id, m.pertemuan_ke, m.judul, s.kode_mk, s.nama_mk')
            ->join('meetings m', 'm.id = a.meeting_id')
            ->join('subjects s', 's.id = m.subject_id')
            ->where('a.email', session('user_id'))
            ->where('s.is_active', 1)
            ->orderBy('a.waktu_absen', 'DESC')
            ->get()
            ->getResultArray();

        return view('student/dashboard', ['rows' => $rows]);
    }
}
