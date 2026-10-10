<?php

namespace App\Controllers;

use App\Models\AdClickModel;
use App\Models\AdModel;

class AdClick extends BaseController
{
    public function track(int $id)
    {
        $ad = (new AdModel())->find($id);

        if (! $ad || empty($ad['target_url'])) {
            return redirect()->to('/');
        }

        // Defensive: tolak target_url yang mengarah ke host internal / localhost
        // agar tidak terjadi redirect loop atau paparan file internal.
        // Bug fix v5.8: lihat Open Question #8 + Tech Debt #11.
        $parsed  = parse_url($ad['target_url']);
        $host    = strtolower($parsed['host'] ?? '');
        $scheme  = strtolower($parsed['scheme'] ?? '');
        $baseHost = strtolower((string) parse_url((string) config('App')->baseURL, PHP_URL_HOST) ?? '');
        $isInternal = ($host === ''
            || $host === $baseHost
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || in_array($scheme, ['', 'javascript', 'data', 'file'], true)
        );
        if ($isInternal) {
            // Jangan redirect ke URL internal; cukup kembali ke home.
            return redirect()->to('/');
        }

        (new AdClickModel())->recordClick((int) $id);

        return redirect()->to($ad['target_url']);
    }
}
