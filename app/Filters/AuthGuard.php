<?php

namespace App\Filters;

use App\Models\AdminModel;
use App\Models\LecturerModel;
use App\Models\StudentModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        $requiredRole = $arguments[0] ?? null;
        $activeRole   = $session->get('role');

        if ($requiredRole === 'admin') {
            if (! $session->get('logged_in') || $activeRole !== 'admin') {
                return redirect()->to('/admin/login')->with('error', 'Silakan login sebagai admin.');
            }

            // Akun admin dihapus saat session masih aktif → akhiri session
            // dengan rapi, jangan biarkan halaman error 500.
            if (! (new AdminModel())->find($session->get('user_id'))) {
                $this->clearAuthSession($session);

                return redirect()->to('/admin/login')
                    ->with('error', 'Akun admin Anda sudah tidak terdaftar. Silakan login kembali.');
            }

            return null;
        }

        if (! $session->get('logged_in')) {
            return redirect()->to('/auth')->with('error', 'Silakan login terlebih dahulu.');
        }

        // Akun dosen/mahasiswa dihapus saat session masih aktif (mis. dihapus
        // admin sementara user belum logout) → session dibersihkan dan user
        // diarahkan ke halaman login dengan pesan, bukan halaman error 500.
        if (! $this->userExists($activeRole, $session->get('user_id'))) {
            $this->clearAuthSession($session);

            return redirect()->to('/auth')
                ->with('error', 'Akun Anda sudah tidak terdaftar atau telah dihapus. Silakan login kembali.');
        }

        if ($requiredRole !== null && $activeRole !== $requiredRole) {
            if ($activeRole === 'lecturer') {
                return redirect()->to('/lecturer/subjects');
            }

            if ($activeRole === 'admin') {
                return redirect()->to('/admin/dashboard');
            }

            return redirect()->to('/student/dashboard');
        }

        if ($activeRole === 'admin' && $requiredRole === null) {
            return redirect()->to('/admin/dashboard');
        }

        // Gerbang first login dosen: akun hasil registrasi mandiri wajib
        // menyelesaikan konfirmasi profil sebelum dapat mengakses fasilitas dosen.
        // Keputusan didasarkan pada session yang dibuat dari data DB saat login.
        if ($activeRole === 'lecturer'
            && $session->get('profile_confirmed') !== true
            && strpos(ltrim(uri_string(), '/'), 'lecturer/confirm-profile') !== 0
        ) {
            return redirect()->to('/lecturer/confirm-profile')
                ->with('info', 'Silakan konfirmasi profil Anda terlebih dahulu.');
        }

        return null;
    }

    /**
     * Pastikan user pada session masih ada di database.
     */
    private function userExists(?string $role, $userId): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }

        return match ($role) {
            'student'  => (new StudentModel())->find($userId) !== null,
            'lecturer' => (new LecturerModel())->find($userId) !== null,
            'admin'    => (new AdminModel())->find($userId) !== null,
            default    => false,
        };
    }

    /**
     * Bersihkan data autentikasi pada session (tanpa menghapus flashdata).
     */
    private function clearAuthSession($session): void
    {
        $session->remove([
            'logged_in',
            'role',
            'user_id',
            'user_name',
            'profile_confirmed',
            'ad_anon_id',
            'pending_student_email',
            'pending_dosen_email',
            'password_change_email',
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
