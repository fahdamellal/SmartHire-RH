<?php

namespace App\Modules\Embeddings\Services;

class LinkedInIntentParser
{
    public function parse(string $text): array
    {
        $text = mb_strtolower($text);
        $intent = [];

        // Entreprise
        if (preg_match('/(?:mets|met|entreprise)\s+([a-z0-9\s]+)/i', $text, $m)) {
            $intent['company'] = ucfirst(trim($m[1]));
        }

        // Rôle métier
        if (preg_match('/\b(plombier|chauffeur|développeur|developpeur|électricien|electricien|comptable|assistante)\b/i', $text, $m)) {
            $intent['role'] = mb_strtolower($m[1]);
        }

        // Ville
        if (preg_match('/\b(rabat|casablanca|tanger|agadir|marrakech|kenitra|fes|oujda)\b/i', $text, $m)) {
            $intent['location'] = ucfirst($m[1]);
        }

        // Contrat
        if (preg_match('/\b(cdi|cdd|freelance|stage|alternance)\b/i', $text, $m)) {
            $intent['contract'] = strtoupper($m[1]);
        }

        // Nombre
        if (preg_match('/\b(\d{1,2})\b/', $text, $m)) {
            $intent['count'] = (int)$m[1];
        }

        return $intent;
    }
}
