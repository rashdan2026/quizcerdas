<?php

namespace App\Controllers;

use App\Libraries\ContactCaptcha;
use App\Libraries\ContactService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Endpoint formulir kontak Landing Page.
 *
 * - GET  /contact/captcha : gambar CAPTCHA (PNG/SVG), no-store.
 * - POST /contact/send    : validasi + kirim email ke tim internal.
 *
 * Tidak ada informasi penerima email pada response mana pun.
 */
class Contact extends BaseController
{
    public function captcha()
    {
        $service = new ContactService();
        if (! $service->isEnabled()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $captcha = new ContactCaptcha($service->captchaTtl());
        if ($this->request->getGet('new') === '1') {
            $captcha->issue(true);
        }

        $image = $captcha->render();

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $image['mime'])
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($image['body']);
    }

    public function send()
    {
        $service = new ContactService();

        $input = [
            'contact_name'       => (string) $this->request->getPost('contact_name'),
            'contact_email'      => (string) $this->request->getPost('contact_email'),
            'contact_whatsapp'   => (string) $this->request->getPost('contact_whatsapp'),
            'contact_message'    => (string) $this->request->getPost('contact_message'),
            'contact_captcha'    => (string) $this->request->getPost('contact_captcha'),
            'contact_form_token' => (string) $this->request->getPost('contact_form_token'),
            'website'            => (string) $this->request->getPost('website'),
        ];

        $result  = $service->send($input, $this->request->getIPAddress());
        $session = session();

        if (($result['ok'] ?? false) === true) {
            $redirect = redirect()->to(base_url('/') . '#kontak')
                ->with('contact_success', true);

            if (is_array($result['cookie'] ?? null)) {
                $redirect->setCookie($result['cookie']);
            }

            return $redirect;
        }

        $errors = $result['errors'] ?? [];
        if ($errors === []) {
            $errors = [$result['message'] ?? 'Pesan belum berhasil dikirim. Silakan coba lagi.'];
        }

        // Simpan isian (tanpa CAPTCHA/honeypot) agar pengunjung tidak mengetik ulang.
        $session->setFlashdata('contact_errors', $errors);
        $session->setFlashdata('contact_old', [
            'contact_name'     => $input['contact_name'],
            'contact_email'    => $input['contact_email'],
            'contact_whatsapp' => $input['contact_whatsapp'],
            'contact_message'  => $input['contact_message'],
        ]);

        return redirect()->to(base_url('/') . '#kontak');
    }
}
