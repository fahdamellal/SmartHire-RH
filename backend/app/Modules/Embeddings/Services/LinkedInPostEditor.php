<?php

namespace App\Modules\Embeddings\Services;

class LinkedInPostEditor
{
    public function revise(
        string $company,
        ?string $role,
        array $criteria,
        string $currentPost,
        string $instruction
    ): string {

        $company  = $criteria['company'] ?? $company ?? 'Notre entreprise';
        $role     = $criteria['role'] ?? $role ?? 'professionnel';
        $location = $criteria['location'] ?? null;
        $contract = $criteria['contract'] ?? null;
        $count    = $criteria['count'] ?? 1;

        return $this->generateLocalPost($company,$role,$location,$contract,$count);
    }

    private function generateLocalPost($company,$role,$location,$contract,$count)
    {
        $missions = match($role) {
            'chauffeur' => [
                "Transporter les passagers ou marchandises en toute sécurité",
                "Assurer l’entretien du véhicule",
                "Respecter les règles de circulation"
            ],
            'plombier' => [
                "Installation sanitaire",
                "Réparation de fuites",
                "Travaux douche & plomberie"
            ],
            default => [
                "Réaliser les missions du poste",
                "Respecter les délais",
                "Assurer qualité de service"
            ]
        };

        $post = "📣 Recrutement — {$company}\n\n";
        $post .= "Nous recrutons **{$count} {$role}(s)**";
        if ($location) $post .= " à **{$location}**";
        $post .= ".\n\n";

        if ($contract) $post .= "📄 Contrat : {$contract}\n\n";

        $post .= "✅ Missions :\n";
        foreach ($missions as $m) $post .= "• {$m}\n";

        $post .= "\n📩 Envoyez votre CV en message privé.\n\n";
        $post .= "#recrutement #emploi #opportunité";

        return $post;
    }
}
