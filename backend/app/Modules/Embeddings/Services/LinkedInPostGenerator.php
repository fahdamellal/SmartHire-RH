<?php

namespace App\Modules\Embeddings\Services;

class LinkedInPostGenerator
{
    public function __construct(private GeminiText $llm) {}

    public function generate(string $companyName, array $criteria): string
    {
        $company = trim($criteria['company'] ?? $companyName ?: 'Notre entreprise');

        $count = (int)($criteria['count'] ?? 1);
        $count = max(1, min(50, $count));

        $role = trim((string)($criteria['role'] ?? ''));
        if ($role === '') $role = 'un profil';

        // LLM d'abord
        try {
            $system =
                "Tu es un recruteur senior. Génère un post LinkedIn de recrutement en français, professionnel et crédible.\n".
                "Contraintes:\n".
                "- 120 à 220 mots.\n".
                "- Pas de placeholders (pas 'Profil').\n".
                "- Structure: Accroche -> Contexte -> Rôle -> Missions -> Profil -> Conditions -> Process -> CTA -> Hashtags.\n".
                "- N'invente pas salaire si absent.\n".
                "- Ton simple, concret.\n".
                "Retourne UNIQUEMENT le post final.";

            $user = "Entreprise: {$company}\nCritères(JSON): ".json_encode($criteria, JSON_UNESCAPED_UNICODE)."\n".
                    "Rôle: {$role}\nNombre: {$count}\n";

            $out = trim($this->llm->generate($system, $user));
            if ($out !== '' && mb_strlen($out) > 80 && !str_contains($out, 'Profil**')) {
                return $out;
            }
        } catch (\Throwable $e) {}

        // fallback
        return $this->fallback($company, $criteria, $role, $count);
    }

    private function fallback(string $company, array $c, string $role, int $count): string
    {
        $loc = $c['location'] ?? null;
        $contract = $c['contract'] ?? null;
        $remote = $c['remote'] ?? null;
        $stack = is_array($c['stack'] ?? null) ? $c['stack'] : [];
        $years = $c['min_years'] ?? null;

        $headline = "Nous recrutons ".($count > 1 ? "{$count} " : "un(e) ").$role;
        if ($loc) $headline .= " à {$loc}";
        $headline .= ".";

        $lines = [];
        $lines[] = "📣 Recrutement — {$company}";
        $lines[] = "";
        $lines[] = $headline;
        $lines[] = "Nous cherchons des personnes qui aiment livrer vite, proprement, et travailler en équipe.";
        $lines[] = "";
        $lines[] = "🎯 Missions";
        $lines[] = "• Participer à la conception et au développement";
        $lines[] = "• Maintenir et améliorer l’existant (qualité, performance, bugs)";
        $lines[] = "• Collaborer avec les équipes (produit / tech / QA)";
        $lines[] = "";
        $lines[] = "✅ Profil recherché";
        if ($years !== null) $lines[] = "• Expérience: {$years}+ an(s) (ou équivalent projets)";
        $lines[] = "• Rigueur, autonomie, communication";
        if (!empty($stack)) $lines[] = "• Compétences: ".implode(', ', array_slice($stack, 0, 10));
        $lines[] = "";
        $lines[] = "📝 Conditions";
        if ($contract) $lines[] = "• Contrat: {$contract}";
        if ($remote) $lines[] = "• Mode: {$remote}";
        if (!$contract && !$remote) $lines[] = "• Détails partagés lors du premier échange";
        $lines[] = "";
        $lines[] = "🧭 Process";
        $lines[] = "• Pré-qualification • Entretien technique • Entretien final";
        $lines[] = "";
        $lines[] = "📩 Pour postuler: envoyez votre CV en message privé.";
        $lines[] = "";
        $lines[] = "#recrutement #emploi #hiring #opportunite #carriere";

        return implode("\n", $lines);
    }
}
