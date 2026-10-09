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

        $bytes = filesize($path);
        return $this->response
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Content-Length', (string) $bytes)
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody(file_get_contents($path));
    }
}
