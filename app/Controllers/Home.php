<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session('logged_in')) {
            return session('role') === 'lecturer'
                ? redirect()->to('/lecturer/subjects')
                : redirect()->to('/student/dashboard');
        }

        return redirect()->to('/auth');
    }
}
