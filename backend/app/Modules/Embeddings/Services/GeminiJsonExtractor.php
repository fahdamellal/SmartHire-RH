<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiJsonExtractor
{
    public function extractCriteria(string $message): array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return $this->fallbackHeuristic($message, 'missing_api_key');
        }

        $prompt = <<<PROMPT
Return ONLY valid JSON. No markdown. No code fences. No extra text.
Schema:
{
 "entreprise": string|null,
 "count": integer|null,
 "role": string|null,
 "stack": string[],
 "location": string|null,
 "keywords": string[],
 "seniority": string|null
}

Rules:
- entreprise: organization if present else null
- count: number of profiles else null
- role: job role (e.g. "Développeur", "Data Analyst") else null
- stack: technologies (Java, Spring, React, Laravel, etc.)
- location: city/country if present (e.g. "Rabat") else null
- keywords: useful words (backend, fullstack, sécurité, etc.)
User message:
{$message}
PROMPT;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        $res = Http::timeout(30)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0,
                // force JSON output (important)
                'response_mime_type' => 'application/json',
            ],
        ]);

        if (!$res->successful()) {
            Log::warning('Gemini extractCriteria HTTP failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            return $this->fallbackHeuristic($message, 'http_fail_'.$res->status());
        }

        $text = (string) $res->json('candidates.0.content.parts.0.text');
        $text = trim($text);

        if ($text === '') {
            Log::warning('Gemini extractCriteria empty text', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            return $this->fallbackHeuristic($message, 'empty_text');
        }

        // remove ```json fences if any
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/', '', $text);
        $text = preg_replace('/```$/', '', $text);
        $text = trim($text);

        // If model returned extra text, try to extract the first {...} block
        $candidateJson = $this->extractFirstJsonObject($text);

        $json = json_decode($candidateJson, true);

        if (!is_array($json)) {
            Log::warning('Gemini extractCriteria json_decode failed', [
                'raw' => $text,
                'extracted' => $candidateJson,
                'json_error' => json_last_error_msg(),
            ]);
            return $this->fallbackHeuristic($message, 'json_decode_fail');
        }

        // normalize
        $json['stack'] = array_values(array_filter($json['stack'] ?? [], fn($x) => is_string($x) && trim($x) !== ''));
        $json['keywords'] = array_values(array_filter($json['keywords'] ?? [], fn($x) => is_string($x) && trim($x) !== ''));
        if (isset($json['count'])) $json['count'] = is_numeric($json['count']) ? (int)$json['count'] : null;

        // If model still returns empty fields, enrich with heuristics
        $enriched = $this->enrichIfEmpty($message, $json);

        return $enriched;
    }

    private function extractFirstJsonObject(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) return $text;
        return substr($text, $start, $end - $start + 1);
    }

    private function fallbackHeuristic(string $message, string $reason): array
    {
        $base = [
            'entreprise' => null,
            'count' => null,
            'role' => null,
            'stack' => [],
            'location' => null,
            'keywords' => [],
            'seniority' => null,
        ];

        $out = $this->enrichIfEmpty($message, $base);
        $out['_parser_fallback'] = $reason; // utile pour debug (tu peux enlever après)
        return $out;
    }

    private function enrichIfEmpty(string $message, array $c): array
    {
        $msg = mb_strtolower($message);

        // stack
        $knownTech = [
            'java' => 'Java',
            'spring' => 'Spring',
            'react' => 'React',
            'laravel' => 'Laravel',
            'php' => 'PHP',
            'node' => 'Node.js',
            'nodejs' => 'Node.js',
            'python' => 'Python',
            'django' => 'Django',
            'flask' => 'Flask',
            'postgres' => 'PostgreSQL',
            'postgresql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'docker' => 'Docker',
            'kubernetes' => 'Kubernetes',
        ];

        if (empty($c['stack']) || !is_array($c['stack'])) $c['stack'] = [];

        foreach ($knownTech as $needle => $label) {
            if (str_contains($msg, $needle) && !in_array($label, $c['stack'], true)) {
                $c['stack'][] = $label;
            }
        }

        // location (simple)
        if (empty($c['location'])) {
            $cities = ['rabat','casablanca','tanger','agadir','marrakech','kenitra','fes','oujda'];
            foreach ($cities as $city) {
                if (preg_match('/\b' . preg_quote($city, '/') . '\b/u', $msg)) {
                    $c['location'] = ucfirst($city);
                    break;
                }
            }
        }

        // role (simple)
        if (empty($c['role'])) {
            if (str_contains($msg, 'développeur') || str_contains($msg, 'developpeur')) $c['role'] = 'Développeur';
            if (str_contains($msg, 'data analyst')) $c['role'] = 'Data Analyst';
            if (str_contains($msg, 'data scientist')) $c['role'] = 'Data Scientist';
            if (str_contains($msg, 'devops')) $c['role'] = 'DevOps';
        }

        // keywords (very light)
        if (empty($c['keywords']) || !is_array($c['keywords'])) $c['keywords'] = [];
        foreach (['backend','front','frontend','fullstack','sécurité','securite','mobile'] as $kw) {
            if (str_contains($msg, $kw)) {
                $k = $kw === 'securite' ? 'sécurité' : $kw;
                if (!in_array($k, $c['keywords'], true)) $c['keywords'][] = $k;
            }
        }

        return $c;
    }
}
