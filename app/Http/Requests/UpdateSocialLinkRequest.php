<?php

namespace App\Http\Requests;

use App\Support\SocialLinks;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SocialLinks::platforms() as $platform) {
            $rules[$platform] = ['sometimes', 'nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provided = collect(SocialLinks::platforms())
                ->filter(fn (string $platform): bool => $this->exists($platform));

            if ($provided->isEmpty()) {
                $validator->errors()->add(
                    'links',
                    'Provide at least one social link to update.',
                );

                return;
            }

            foreach ($provided as $platform) {
                $value = $this->input($platform);

                if ($value === null || $value === '') {
                    continue;
                }

                if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add($platform, "The {$platform} must be a valid URL.");
                }
            }
        });
    }
}
