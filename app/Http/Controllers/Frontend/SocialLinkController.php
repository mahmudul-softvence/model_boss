<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SocialLinkController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(['links' => Setting::getSocialLinks()]);
    }
}
