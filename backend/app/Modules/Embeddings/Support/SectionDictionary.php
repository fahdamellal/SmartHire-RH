<?php

namespace App\Modules\Embeddings\Support;

class SectionDictionary
{
    public function patterns(): array
    {
        return [
            'summary'    => '/^\s*(profil|summary|about|r[ée]sum[ée])\s*$/i',
            'skills'     => '/^\s*(comp[ée]tences|skills|technical skills)\s*$/i',
            'experience' => '/^\s*(exp[ée]rience|experience professionnelle|work experience)\s*$/i',
            'projects'   => '/^\s*(projets?|projects)\s*$/i',
            'education'  => '/^\s*(formation|education)\s*$/i',
            'certs'      => '/^\s*(certifications?|certificates?)\s*$/i',
            'languages'  => '/^\s*(langues?|languages)\s*$/i',
            'contact'    => '/^\s*(contact|coordonn[ée]es)\s*$/i',
        ];
    }
}
