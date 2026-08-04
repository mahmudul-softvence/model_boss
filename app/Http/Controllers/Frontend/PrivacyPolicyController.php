<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use Illuminate\Http\JsonResponse;

class PrivacyPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(PrivacyPolicy::currentContent());
    }
}
