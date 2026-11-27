<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * CSRF untuk formulir kontak Landing Page.
 *
 * Token CSRF dapat menjadi tidak cocok bila session pengunjung berganti
 * (logout, reset password, session kedaluwarsa, atau halaman lama dibuka
 * kembali). Framework secara default melempar SecurityException (403);
 * untuk formulir publik ini pengunjung dikembalikan ke form dengan pesan
 * yang mudah dipahami + isian dipertahankan, bukan halaman error.
 */
class ContactCsrf extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            $result = parent::before($request, $arguments);
            if ($result instanceof RedirectResponse) {
                return $this->backToForm($request);
            }

            return $result;
        } catch (SecurityException $e) {
            log_message('warning', 'Contact: token CSRF tidak cocok; pengunjung dikembalikan ke formulir.');

            return $this->backToForm($request);
        }
    }

    private function backToForm(RequestInterface $request): RedirectResponse
    {
        $old = [
            'contact_name'     => (string) $request->getPost('contact_name'),
            'contact_email'    => (string) $request->getPost('contact_email'),
            'contact_whatsapp' => (string) $request->getPost('contact_whatsapp'),
            'contact_message'  => (string) $request->getPost('contact_message'),
        ];

        return redirect()->to(base_url('/') . '#kontak')
            ->with('contact_errors', [
                'Sesi formulir Anda sudah kedaluwarsa atau berubah. Silakan periksa kembali isian dan kirim ulang.',
            ])
            ->with('contact_old', $old);
    }
}
