<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTermsAndConditionRequest;
use App\Models\TermsAndCondition;
use Illuminate\Http\JsonResponse;

class TermsAndConditionController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->sendResponse(TermsAndCondition::currentContent());
    }

    public function update(UpdateTermsAndConditionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        TermsAndCondition::replaceContent(
            $validated['title'],
            $validated['content'],
        );

        return $this->sendResponse(
            TermsAndCondition::currentContent(),
            'Terms and conditions updated successfully',
        );
    }
}
