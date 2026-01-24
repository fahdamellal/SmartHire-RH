<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Facades\Http;

class LinkedInPostGenerator
{
    public function generate(string $entreprise, array $criteria): string
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return "Nous recrutons ! Nous cherchons des profils correspondant à: " . json_encode($criteria);
        }

        $stack = implode(', ', $criteria['stack'] ?? []);
        $count = $criteria['count'] ?? 5;
        $role  = $criteria['role'] ?? 'profils';
        $loc   = $criteria['location'] ?? null;

        $prompt = "Rédige un post LinkedIn professionnel (FR), court, sans emojis excessifs, pour {$entreprise}. "
            . "Objectif: attirer {$count} {$role}. "
            . ($stack ? "Stack: {$stack}. " : "")
            . ($loc ? "Localisation: {$loc}. " : "")
            . "Ajoute 5 hashtags max.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        $res = Http::timeout(30)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);

        return trim((string) $res->json('candidates.0.content.parts.0.text'));
    }
}
