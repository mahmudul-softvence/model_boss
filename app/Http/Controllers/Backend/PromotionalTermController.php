<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePromotionalTermRequest;
use App\Models\PromotionalTerm;
use Illuminate\Http\JsonResponse;

class PromotionalTermController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->sendResponse(PromotionalTerm::currentContent());
    }

    public function update(UpdatePromotionalTermRequest $request): JsonResponse
    {
        $validated = $request->validated();

        PromotionalTerm::replaceContent(
            (int) $validated['prize'],
            $validated['list'],
        );

        return $this->sendResponse(
            PromotionalTerm::currentContent(),
            'Promotional terms updated successfully',
        );
    }
}
