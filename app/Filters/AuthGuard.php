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

        if (! $session->get('logged_in')) {
            return redirect()->to('/auth')->with('error', 'Silakan login terlebih dahulu.');
        }

        $requiredRole = $arguments[0] ?? null;
        $activeRole   = $session->get('role');

        if ($requiredRole !== null && $activeRole !== $requiredRole) {
            if ($activeRole === 'lecturer') {
                return redirect()->to('/lecturer/subjects');
            }

            return redirect()->to('/student/dashboard');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
