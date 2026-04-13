<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\LecturerModel;
use App\Models\StudentModel;

class Profile extends BaseController
{
    public function index()
    {
        $role   = session('role');
        $userId = session('user_id');

        if ($role === 'lecturer') {
            $lecturerModel = new LecturerModel();
            $user = $lecturerModel->find($userId);
        } else {
            $studentModel = new StudentModel();
            $user = $studentModel->find($userId);
        }

        if (!$user) {
            return redirect()->back()->with('error', 'Data pengguna tidak ditemukan.');
        }

        return view('profile/index', [
            'user' => $user,
            'role' => $role,
        ]);
    }

    public function changePassword()
    {
        $role   = session('role');
        $userId = session('user_id');

        $currentPassword  = (string) $this->request->getPost('current_password');
        $newPassword      = (string) $this->request->getPost('new_password');
        $confirmPassword  = (string) $this->request->getPost('confirm_password');

        // Fetch user data
        if ($role === 'lecturer') {
            $lecturerModel = new LecturerModel();
            $user = $lecturerModel->find($userId);
        } else {
            $studentModel = new StudentModel();
            $user = $studentModel->find($userId);
        }

        if (!$user) {
            return redirect()->back()->withInput()->with('error', 'Data pengguna tidak ditemukan.');
        }

        // Verify current password
        $storedPassword = (string) $user['password'];
        $validPassword = str_starts_with($storedPassword, '$2')
            ? password_verify($currentPassword, $storedPassword)
            : hash_equals($storedPassword, $currentPassword);

        if (!$validPassword) {
            return redirect()->back()->withInput()->with('error', 'Password saat ini salah.');
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password baru minimal 8 karakter.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
        }

        // Prevent using the same password
        if (str_starts_with($storedPassword, '$2') && password_verify($newPassword, $storedPassword)) {
            return redirect()->back()->withInput()->with('error', 'Password baru tidak boleh sama dengan password saat ini.');
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        if ($role === 'lecturer') {
            $lecturerModel = new LecturerModel();
            $lecturerModel->update($userId, ['password' => $hashedPassword]);
        } else {
            $studentModel = new StudentModel();
            $studentModel->update($userId, ['password' => $hashedPassword]);
        }

        return redirect()->to('/profile')->with('success', 'Password berhasil diubah.');
    }
}
