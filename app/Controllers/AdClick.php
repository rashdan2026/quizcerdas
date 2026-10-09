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

        (new AdClickModel())->recordClick((int) $id);

        return redirect()->to($ad['target_url']);
    }
}
