<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CloudinaryService
{
    public function upload(string $imageBytes, string $folder = 'exam-scans'): string
    {
        $uploaded = Cloudinary::upload('data:image/jpeg;base64,'.base64_encode($imageBytes), [
            'folder' => $folder,
            'resource_type' => 'image',
        ]);

        return $uploaded->getSecurePath();
    }
}
