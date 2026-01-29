<?php

namespace App\Modules\Embeddings\Services;

class LinkedInPostGenerator
{
    public function __construct(private GeminiText $llm) {}

    /**
     * Génère un post LinkedIn à partir de l'entreprise + critères (role, location, count, stack...)
     */
    public function generate(string $companyName, array $criteria): string
    {
        $role = $criteria['role'] ?? 'profil';
        $loc  = $criteria['location'] ?? null;

        $count = (int)($criteria['count'] ?? 1);
        $count = max(1, min(50, $count));

        $stack = (is_array($criteria['stack'] ?? null) ? $criteria['stack'] : []);
        $stackStr = !empty($stack) ? implode(', ', $stack) : '';

        $system = "Tu es un assistant RH. Tu écris un post LinkedIn de recrutement en français, clair et professionnel. Retourne uniquement le post final (pas d'explications).";
        $user =
            "Entreprise: {$companyName}\n" .
            "Profil: {$role}\n" .
            "Nombre: {$count}\n" .
            "Ville: " . ($loc ?: "(non précisée)") . "\n" .
            "Compétences (si pertinentes): " . ($stackStr ?: "(non précisées)") . "\n\n" .
            "Le post doit rester cohérent avec le profil demandé (ex: ne parle pas de développeurs si le profil est plombier).";

        try {
            $out = trim($this->llm->generate($system, $user));
            if ($out !== '') return $out;
        } catch (\Throwable $e) {
            // fallback si quota/erreur LLM
        }

        return $this->fallback($companyName, $role, $loc, $count, $stack);
    }

    private function fallback(string $company, string $role, ?string $loc, int $count, array $stack): string
    {
        $title = "📣 Recrutement — {$company}";
        $headline = "Nous recrutons " . ($count > 1 ? "{$count} " : "un(e) ") . "**{$role}**" . ($loc ? " à **{$loc}**" : "") . ".";

        $lines = [];
        $lines[] = $title;
        $lines[] = "";
        $lines[] = $headline;

        if (!empty($stack)) {
            $lines[] = "🧰 Compétences : " . implode(', ', $stack);
        }

        $lines[] = "";
        $lines[] = "✅ Missions (exemples) :";
        $lines[] = "• Réaliser les missions liées au poste";
        $lines[] = "• Travailler en équipe et respecter les délais";
        $lines[] = "• Assurer une bonne qualité de service";
        $lines[] = "";
        $lines[] = "📩 Intéressé(e) ? Envoyez votre CV en DM.";
        $lines[] = "";
        $lines[] = "#recrutement #emploi #opportunité";

        return implode("\n", $lines);
    }
}
