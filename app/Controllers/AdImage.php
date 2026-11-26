<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class AdImage extends BaseController
{
    public function serve(string $fileName): ResponseInterface
    {
        $fileName = basename($fileName);
        $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'ads' . DIRECTORY_SEPARATOR . $fileName;

        if (! preg_match('/^gfx_[A-Za-z0-9_]+\.gif$/', $fileName) || ! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        // Revalidasi kondisional: bila browser sudah punya versi terbaru,
        // balas 304 tanpa membaca/mengirim isi file (hemat PHP worker & bandwidth).
        $mTime  = (int) filemtime($path);
        $clTime = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        if ($clTime !== '' && @strtotime($clTime) >= $mTime) {
            return $this->response->setStatusCode(304)
                ->setHeader('Cache-Control', 'public, max-age=86400')
                ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mTime) . ' GMT');
        }

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Content-Length', (string) filesize($path))
            // Nama file unik per upload (gfx_*), sehingga cache panjang aman:
            // gambar baru selalu URL baru.
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mTime) . ' GMT')
            ->setBody(file_get_contents($path));
    }
}
