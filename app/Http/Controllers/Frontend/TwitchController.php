<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\TwitchService;

class TwitchController extends Controller
{
    public function status(TwitchService $twitch)
    {
        return $this->sendResponse($twitch->checkLiveStatus());
    }
}
