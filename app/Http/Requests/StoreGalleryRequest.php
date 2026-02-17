<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'short_video' => 'required|file|mimes:mp4,mov,avi|max:102400',
            'short_video_thumb' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'description' => 'nullable|string'
        ];
    }
}
