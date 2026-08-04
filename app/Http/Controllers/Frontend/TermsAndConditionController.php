<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\JsonResponse;

class TermsAndConditionController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(TermsAndCondition::currentContent());
    }
}
