<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Facades\Http;

class GeminiText
{
   public function generate(string $system, string $user): string
{
    return ''; // Désactivé → on utilise fallback local
}

}
