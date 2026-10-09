<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AdImpressionModel;
use App\Models\AdModel;
use App\Models\LecturerModel;
use App\Models\MeetingModel;
use App\Models\StudentModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $stats = [
            'students'   => (new StudentModel())->countAll(),
            'lecturers'  => (new LecturerModel())->where('is_active', 1)->countAllResults(),
            'ads_active' => (new AdModel())->where('is_active', 1)->countAllResults(),
            'meetings'   => (new MeetingModel())->countAll(),
        ];

        $recentLogins = [];
        try {
            $adminLogins = (new AdminModel())
                ->select('email, last_login as when_col, "admin" as role')
                ->where('last_login IS NOT NULL', null, false)
                ->orderBy('last_login', 'DESC')
                ->limit(5)
                ->findAll();
            foreach ($adminLogins as $r) {
                $recentLogins[] = [
                    'when' => $r['when_col'] ? date('d M H:i', strtotime($r['when_col'])) : '-',
                    'email' => $r['email'],
                    'role' => $r['role'],
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $data = [
            'pageTitle'    => 'Dashboard',
            'pageSubtitle' => 'Ringkasan sistem',
            'stats'        => $stats,
            'recentLogins' => $recentLogins,
        ];

        return view('admin/dashboard', $data);
    }
}
