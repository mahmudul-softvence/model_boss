<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;

class FileHelper
{
    public static function uploadImage(?UploadedFile $file, ?string $oldImage = null): ?string
    {
        if (!$file) {
            return $oldImage;
        }

        if ($oldImage) {
            $oldPath = public_path($oldImage);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $filename = 'user-' . time() . '-profile.' . $file->getClientOriginalExtension();

        $file->move(public_path('upload'), $filename);

        return 'upload/' . $filename;
    }
}
