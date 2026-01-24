<?php

namespace App\Modules\Embeddings\Services;

class CvMetaExtractor
{
public function extractNameFromText(string $text): array
{
    $lines = preg_split("/\r\n|\n|\r/", $text);

    // On regarde seulement le début du CV
    $lines = array_slice($lines, 0, 12);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // ignorer lignes trop longues (souvent phrases)
        if (mb_strlen($line) > 45) continue;

        // ignorer lignes contact
        if (preg_match('/@|https?:\/\/|linkedin|github|\+?\d/', $line)) continue;

        // ignorer mots métiers fréquents
        if (preg_match('/\b(developpeur|developer|ing[ée]nieur|technicien|informatique|cyber|s[ée]curit[ée]|web|logiciel|reseaux|r[ée]seau|admin)\b/i', $line)) {
            continue;
        }

        // Cherche 2 ou 3 mots "Nom Prénom" style
        if (preg_match('/^([A-ZÀ-Ÿ][a-zà-ÿ\'\-]+)\s+([A-ZÀ-Ÿ][a-zà-ÿ\'\-]+)(?:\s+([A-ZÀ-Ÿ][a-zà-ÿ\'\-]+))?$/u', $line, $m)) {
            $w1 = $this->cleanName($m[1]);
            $w2 = $this->cleanName($m[2]);
            $w3 = isset($m[3]) ? $this->cleanName($m[3]) : null;

            // si 3 mots: on met prénom = 1er mot, nom = 2e + 3e (ex: Samir El Haddad)
            if ($w3) {
                return [$w1, trim($w2 . ' ' . $w3)];
            }

            // si 2 mots: prénom = 1er, nom = 2e
            return [$w1, $w2];
        }
    }

    return [null, null];
}



    public function extractEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    public function extractPhone(string $text): ?string
    {
        // formats Maroc/FR + international
        $patterns = [
            '/\+212\s?[5-7]\s?\d{1,2}(\s?\d{2}){3}/',   // +212 6 xx xx xx xx
            '/0[5-7]\s?\d{1,2}(\s?\d{2}){3}/',         // 06 xx xx xx xx
            '/\+33\s?[1-9](\s?\d{2}){4}/',             // +33 x xx xx xx xx
            '/0[1-9](\s?\d{2}){4}/',                   // 0x xx xx xx xx
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                return preg_replace('/\s+/', ' ', trim($m[0]));
            }
        }
        return null;
    }

    private function cleanName(string $s): string
    {
        $s = preg_replace('/[^a-zA-ZÀ-ÿ\'\-]/u', '', $s);
        $s = mb_strtolower($s);
        return mb_convert_case($s, MB_CASE_TITLE, "UTF-8");
    }
}
