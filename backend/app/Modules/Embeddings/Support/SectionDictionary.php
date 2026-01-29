<?php

namespace App\Modules\Embeddings\Support;

class SectionDictionary
{
    /**
     * 🆕 Patterns plus FLEXIBLES
     * - Accepte les deux-points, tirets, chiffres romains
     * - Case-insensitive
     * - Tolère les variations
     */
    public function patterns(): array
    {
        return [
            'contact' => '/^\s*(?:\d+[\.\)]?)?\s*(?:coordonn[ée]es|contact|informations?\s+personnelles?)\s*[:\-]?\s*$/iu',
            
            'summary' => '/^\s*(?:\d+[\.\)]?)?\s*(?:profil|summary|about|r[ée]sum[ée]|pr[ée]sentation|objectif|introduction)\s*[:\-]?\s*$/iu',
            
            'experience' => '/^\s*(?:\d+[\.\)]?|[ivxIVX]+[\.\)]?)?\s*(?:exp[ée]riences?|parcours|experience\s+professionnelle?|work\s+experience|emplois?)\s*[:\-]?\s*$/iu',
            
            'skills' => '/^\s*(?:\d+[\.\)]?)?\s*(?:comp[ée]tences?|skills?|technical\s+skills?|expertise|savoir[‐-]faire)\s*(?:techniques?)?\s*[:\-]?\s*$/iu',
            
            'projects' => '/^\s*(?:\d+[\.\)]?)?\s*(?:projets?|projects?|r[ée]alisations?)\s*[:\-]?\s*$/iu',
            
            'education' => '/^\s*(?:\d+[\.\)]?|[ivxIVX]+[\.\)]?)?\s*(?:formation|education|[ée]tudes?|dipl[ôo]mes?|academic)\s*[:\-]?\s*$/iu',
            
            'certs' => '/^\s*(?:\d+[\.\)]?)?\s*(?:certifications?|certificates?|attestations?)\s*[:\-]?\s*$/iu',
            
            'languages' => '/^\s*(?:\d+[\.\)]?)?\s*(?:langues?|languages?|idiomas?)\s*[:\-]?\s*$/iu',
            
            'interests' => '/^\s*(?:\d+[\.\)]?)?\s*(?:int[ée]r[êe]ts?|hobbies|loisirs?|centres?\s+d.int[ée]r[êe]t)\s*[:\-]?\s*$/iu',
        ];
    }

    /**
     * Ordre de priorité pour la détection
     * (contact en premier pour éviter qu'il soit noyé dans unknown)
     */
    public function priorityOrder(): array
    {
        return [
            'contact',
            'summary',
            'experience',
            'skills',
            'projects',
            'education',
            'certs',
            'languages',
            'interests',
        ];
    }
}