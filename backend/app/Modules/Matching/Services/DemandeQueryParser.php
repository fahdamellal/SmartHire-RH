<?php

namespace App\Modules\Matching\Services;

class DemandeQueryParser
{
    /**
     * Parse une demande texte => criteria array.
     * Objectif: extraire count / role / location / contrat / keywords.
     */
    public function parse(string $text): array
    {
        $raw = trim($text);
        $lc  = mb_strtolower($raw);

        // 1) count: "5", "cinq", "une", etc. (simple)
        $count = 1;
        if (preg_match('/\b(\d{1,2})\b/u', $lc, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 50) $count = $n;
        }

        // 2) contrat
        $contract = null;
        if (preg_match('/\b(cdi|cdd|freelance|stage|alternance)\b/u', $lc, $m)) {
            $contract = strtoupper($m[1]);
        }

        // 3) location (villes Morocco + générique)
        $cities = [
            'rabat','casablanca','tanger','agadir','marrakech','kenitra','fes','oujda',
            'meknes','tetouan','sale','temara','nador','laayoune','dakhla','safi','el jadida'
        ];
        $location = null;
        foreach ($cities as $c) {
            if (preg_match('/\b' . preg_quote($c, '/') . '\b/u', $lc)) {
                $location = ucfirst($c);
                break;
            }
        }

        // 4) role (jobs courants + heuristique "après le nombre")
        $jobs = [
            // métiers manuels
            'plombier','plomberie','chauffeur','conducteur','électricien','electricien','maçon','macon',
            'peintre','menuisier','soudeur','technicien','agent de sécurité','agent securite','gardien',
            // bureau
            'comptable','assistante','assistant','secrétaire','secretaire','commercial','vendeur',
            // IT
            'développeur','developpeur','backend','frontend','fullstack','devops','data scientist','data engineer'
        ];

        $role = null;

        // a) si un job connu est présent dans la phrase
        foreach ($jobs as $j) {
            if (mb_strpos($lc, $j) !== false) {
                $role = $this->normalizeRole($j);
                break;
            }
        }

        // b) sinon: heuristique "Je cherche 5 X"
        if (!$role) {
            if (preg_match('/\b(?:je\s*cherche|on\s*cherche|recherche|nous\s*recrutons)\s*(\d{1,2})?\s*([a-zàâçéèêëîïôûùüÿñæœ\- ]{3,50})/u', $lc, $m)) {
                $cand = trim($m[2] ?? '');
                $cand = preg_replace('/\b(à|a|au|aux|en|pour|de|des|du|dans|sur|avec)\b.*$/u', '', $cand);
                $cand = trim($cand);
                if ($cand !== '') $role = $this->normalizeRole($cand);
            }
        }

        if (!$role) $role = 'profil';

        // 5) keywords (mots “utiles”)
        $stop = ['je','cherche','on','nous','recrutons','recherche','un','une','des','de','du','la','le','les','à','a','au','aux','en','pour','dans','avec','et','ou'];
        $tokens = preg_split('/[^\p{L}\p{N}\+]+/u', $lc, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];
        foreach ($tokens as $t) {
            if (mb_strlen($t) < 3) continue;
            if (in_array($t, $stop, true)) continue;
            $keywords[] = $t;
        }
        $keywords = array_values(array_unique(array_slice($keywords, 0, 25)));

        // 6) type domaine (IT ou non) -> pondérations côté matching
        $isIt = (bool) preg_match('/\b(laravel|react|node|java|python|sql|devops|kubernetes|docker|api|backend|frontend|fullstack)\b/u', $lc);

        return [
            'count' => $count,
            'role' => $role,
            'location' => $location,
            'contract' => $contract,
            'keywords' => $keywords,
            'domain' => $isIt ? 'IT' : 'NON_IT',
        ];
    }

    private function normalizeRole(string $role): string
    {
        $r = mb_strtolower(trim($role));

        // normalisations simples
        $map = [
            'plomberie' => 'plombier',
            'conducteur' => 'chauffeur',
            'developpeur' => 'développeur',
            'electricien' => 'électricien',
            'macon' => 'maçon',
            'agent securite' => 'agent de sécurité',
        ];
        $r = str_replace(['  ', "\t", "\n"], ' ', $r);
        $r = $map[$r] ?? $r;

        // éviter phrases longues
        $r = preg_replace('/\s{2,}/u', ' ', $r);
        $r = mb_substr($r, 0, 60);

        return $r;
    }
}
