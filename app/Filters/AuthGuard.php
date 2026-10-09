<?php

namespace App\Filters;

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
            return null;
        }

        if (! $session->get('logged_in')) {
            return redirect()->to('/auth')->with('error', 'Silakan login terlebih dahulu.');
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

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
