<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;

/**
 * Controller untuk melayani file PDF ke PDF.js.
 * Memerlukan token valid — tidak bisa diakses langsung.
 */
class PdfFile extends BaseController
{
    public function view(int $meetingId)
    {
        $email = (string) session('user_id');
        $token = $this->request->getGet('t');

        // Validasi token
        $expectedToken = hash_hmac('sha256', $meetingId . '|' . $email, (string) env('app.encryptionKey', 'fallback'));

        if (empty($token) || ! hash_equals($expectedToken, $token)) {
            return $this->response->setStatusCode(403)->setBody('Access denied.');
        }

        // Ambil file PDF dari pertemuan
        $row = db_connect()->table('attendance a')
            ->select('pf.file_name')
            ->join('meetings m', 'm.id = a.meeting_id')
            ->join('pdf_files pf', 'pf.id = m.pdf_file_id', 'left')
            ->where('a.meeting_id', $meetingId)
            ->where('a.email', $email)
            ->get()
            ->getRowArray();

        if (! $row || empty($row['file_name'])) {
            return $this->response->setStatusCode(404)->setBody('File not found.');
        }

        $filePath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . $row['file_name'];

        if (! is_file($filePath)) {
            return $this->response->setStatusCode(404)->setBody('File not found.');
        }

        $this->response->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Frame-Options', 'SAMEORIGIN')
            ->setHeader('Access-Control-Allow-Origin', 'none');

        $this->response->setBody(file_get_contents($filePath));

        return $this->response;
    }
}
