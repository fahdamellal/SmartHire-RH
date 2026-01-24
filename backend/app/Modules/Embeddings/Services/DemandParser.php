<?php

namespace App\Modules\Embeddings\Services;

class DemandParser
{
    public function __construct(private GeminiJsonExtractor $llm) {}

    public function parse(string $message): array
    {
        $message = trim($message);

        $entreprise = $this->guessEntreprise($message);
        $count = $this->guessCount($message);

        // LLM (peut échouer -> retourne un fallback intelligent)
        $criteria = $this->llm->extractCriteria($message);

        // Priorités heuristiques (si trouvées)
        if ($entreprise) $criteria['entreprise'] = $entreprise;
        if ($count !== null) $criteria['count'] = $count;

        // Normalisation finale (sécurité)
        $criteria = $this->normalize($criteria);

        return $criteria;
    }

    private function normalize(array $c): array
    {
        $out = [
            'entreprise' => $c['entreprise'] ?? null,
            'count' => isset($c['count']) && is_numeric($c['count']) ? (int)$c['count'] : null,
            'role' => $c['role'] ?? null,
            'stack' => is_array($c['stack'] ?? null) ? array_values(array_filter($c['stack'])) : [],
            'location' => $c['location'] ?? null,
            'keywords' => is_array($c['keywords'] ?? null) ? array_values(array_filter($c['keywords'])) : [],
            'seniority' => $c['seniority'] ?? null,
        ];

        // defaults
        if ($out['count'] === null) $out['count'] = 5;
        if ($out['count'] < 1) $out['count'] = 5;
        if ($out['count'] > 20) $out['count'] = 20;

        // trim strings
        foreach (['entreprise','role','location','seniority'] as $k) {
            if (is_string($out[$k])) {
                $out[$k] = trim($out[$k]);
                if ($out[$k] === '') $out[$k] = null;
            }
        }

        // uniques
        $out['stack'] = array_values(array_unique($out['stack']));
        $out['keywords'] = array_values(array_unique($out['keywords']));

        return $out;
    }

    private function guessCount(string $message): ?int
    {
        if (preg_match('/\b(\d{1,2})\b/u', $message, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 50) return $n;
        }
        return null;
    }

    private function guessEntreprise(string $message): ?string
    {
        $patterns = [
            '/\b(?:chez|pour|de|au nom de|dans)\s+([A-Z][A-Za-z0-9&\-\s]{1,40})/u',
            '/\b(?:je suis de|je viens de)\s+([A-Z][A-Za-z0-9&\-\s]{1,40})/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $message, $m)) {
                $cand = trim($m[1]);
                $cand = preg_replace('/[^\pL\pN&\-\s]/u', '', $cand);
                $cand = preg_replace('/\s+/', ' ', $cand);

                $bad = ['un', 'une', 'element', 'stagiaire', 'consultant', 'responsable'];
                $low = mb_strtolower($cand);
                if (in_array($low, $bad, true)) continue;

                $words = explode(' ', $cand);
                $cand = implode(' ', array_slice($words, 0, 3));

                return $cand ?: null;
            }
        }

        return null;
    }
}
