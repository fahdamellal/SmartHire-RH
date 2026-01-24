<?php

namespace App\Modules\Embeddings\Services;

class FileHasher
{
    public function sha256(string $path): string
    {
        return hash_file('sha256', $path);
    }
}
