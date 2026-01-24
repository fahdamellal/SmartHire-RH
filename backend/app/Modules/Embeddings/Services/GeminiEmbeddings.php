<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Facades\Http;

class GeminiEmbeddings
{
    public function embed(string $text, int $dim = 1536): array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException("GEMINI_API_KEY manquant dans .env");
        }

        $payload = [
            "model" => "models/gemini-embedding-001",
            "content" => ["parts" => [["text" => $text]]],
            "taskType" => "RETRIEVAL_DOCUMENT",
            "outputDimensionality" => $dim,
        ];

        $res = Http::withHeaders([
            "Content-Type" => "application/json",
            "x-goog-api-key" => $apiKey,
        ])->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent",
            $payload
        );

        $res->throw();
        $data = $res->json();

        if (isset($data['embedding']['values'])) return $data['embedding']['values'];
        if (isset($data['embeddings'][0]['values'])) return $data['embeddings'][0]['values'];

        throw new \RuntimeException("Réponse Gemini inattendue: " . json_encode($data));
    }
}
