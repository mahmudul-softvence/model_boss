<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PromotionalTerm;
use Illuminate\Http\JsonResponse;

class PromotionalTermController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(PromotionalTerm::currentContent());
    }
}
