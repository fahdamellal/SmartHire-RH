<?php

namespace App\Modules\Embeddings\Services;

class LinkedInPostEditor
{
    public function __construct(private LinkedInPostGenerator $generator) {}

    /**
     * Revise = regénère un post cohérent avec criteria + instruction
     * (au lieu d’un match($role) ultra limité)
     */
    public function revise(
        ?string $company,
        ?string $role,
        array $criteria,
        string $currentPost,
        string $instruction
    ): string {
        $criteria = is_array($criteria) ? $criteria : [];

        // Normalisation
        $criteria['company'] = $criteria['company'] ?? $company ?? 'Notre entreprise';
        $criteria['role']    = $criteria['role'] ?? $role ?? 'Profil';
        $criteria['instruction'] = $instruction;

        // On donne au generator le post actuel pour qu’il garde le contexte
        $criteria['current_post'] = $currentPost;

        return $this->generator->generate($criteria['company'], $criteria);
    }
}
