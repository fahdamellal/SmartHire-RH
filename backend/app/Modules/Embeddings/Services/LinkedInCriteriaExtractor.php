<?php

namespace App\Modules\Embeddings\Services;

class LinkedInCriteriaExtractor
{
    public function __construct(
        private GeminiText $llm,
        private LinkedInIntentParser $fallbackParser, // regex fallback
    ) {}

    public function extract(string $instruction, array $existingCriteria = []): array
    {
        // 1) Try LLM JSON extraction
        try {
            $json = $this->extractWithLlm($instruction, $existingCriteria);
            if (is_array($json) && !empty($json)) {
                return $this->sanitize($json, $existingCriteria);
            }
        } catch (\Throwable $e) {
            // ignore -> fallback
        }

        // 2) Fallback regex
        $fallback = $this->fallbackParser->parse($instruction);
        return $this->sanitize($fallback, $existingCriteria);
    }

    private function extractWithLlm(string $instruction, array $existingCriteria): array
    {
        $system = <<<SYS
Tu es un assistant RH. Ta tâche: extraire des critères structurés depuis une instruction utilisateur.
Tu dois retourner UNIQUEMENT un JSON valide (sans texte autour).

Schéma JSON attendu (tous les champs optionnels):
{
  "company": "string",
  "role": "string",
  "count": number,
  "location": "string",
  "contract": "CDI|CDD|FREELANCE|STAGE|ALTERNANCE|OTHER",
  "seniority": "junior|mid|senior|intern|unknown",
  "remote": "on-site|hybrid|remote|unknown",
  "stack": ["string"],
  "salary": "string",
  "benefits": ["string"],
  "missions": ["string"],
  "requirements": ["string"],
  "process": ["string"],
  "apply": { "email": "string", "url": "string" },
  "tone": "standard|startup|corporate|urgent|friendly"
}

Règles:
- Ne devine pas. Si absent -> ne mets pas le champ.
- "count" doit être entre 1 et 50 si présent.
- "stack/missions/requirements/benefits/process" doivent être des tableaux.
SYS;

        $existing = json_encode($existingCriteria, JSON_UNESCAPED_UNICODE);

        $user = <<<USR
Instruction utilisateur:
{$instruction}

Critères existants (mémoire): {$existing}

Retourne le JSON final (avec uniquement les champs que tu es sûr d'extraire).
USR;

        $raw = trim($this->llm->generate($system, $user));
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sanitize(array $new, array $existing): array
    {
        // merge: new overrides existing
        $criteria = array_merge($existing, $new);

        // count clamp
        if (isset($criteria['count'])) {
            $n = (int)$criteria['count'];
            $criteria['count'] = max(1, min(50, $n));
        }

        // ensure arrays
        foreach (['stack','benefits','missions','requirements','process'] as $k) {
            if (isset($criteria[$k]) && !is_array($criteria[$k])) {
                $criteria[$k] = array_values(array_filter([trim((string)$criteria[$k])]));
            }
        }

        // normalize contract
        if (isset($criteria['contract'])) {
            $c = strtoupper((string)$criteria['contract']);
            $allowed = ['CDI','CDD','FREELANCE','STAGE','ALTERNANCE','OTHER'];
            $criteria['contract'] = in_array($c, $allowed, true) ? $c : 'OTHER';
        }

        return $criteria;
    }
}
