<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrivacyPolicyRequest;
use App\Models\PrivacyPolicy;
use Illuminate\Http\JsonResponse;

class PrivacyPolicyController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->sendResponse(PrivacyPolicy::currentContent());
    }

    public function update(UpdatePrivacyPolicyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        PrivacyPolicy::replaceContent(
            $validated['title'],
            $validated['content'],
        );

        return $this->sendResponse(
            PrivacyPolicy::currentContent(),
            'Privacy policy updated successfully',
        );
    }
}
