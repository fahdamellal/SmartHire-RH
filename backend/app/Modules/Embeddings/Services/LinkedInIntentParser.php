<?php

namespace App\Modules\Embeddings\Services;

class LinkedInIntentParser
{
    // Tu peux enrichir ces listes progressivement
    private array $cities = [
        'rabat','casablanca','tanger','agadir','marrakech','kenitra','fes','oujda','meknes','tetouan','sale'
    ];

    private array $contracts = [
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'freelance' => 'FREELANCE',
        'stage' => 'STAGE',
        'alternance' => 'ALTERNANCE',
        'remote' => 'REMOTE',
        'hybride' => 'HYBRIDE',
        'hybrid' => 'HYBRIDE',
    ];

    /**
     * Parse une instruction libre et retourne un patch criteria.
     * Exemples:
     * - "Ajoute Rabat, CDI, 5 dev react laravel, stack: react, laravel, postgres"
     * - "mets Capgemini, plus pro, ajoute process + salaire"
     */
    public function parse(string $text): array
    {
        $raw = trim($text);
        $t = mb_strtolower($raw);

        $intent = [];

        // 1) Nombre (ex: "5", "10 profils", "3 développeurs")
        if (preg_match('/\b(\d{1,2})\b/u', $t, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 50) $intent['count'] = $n;
        }

        // 2) Ville (matching sur liste)
        foreach ($this->cities as $c) {
            if (preg_match('/\b'.preg_quote($c,'/').'\b/u', $t)) {
                $intent['location'] = mb_convert_case($c, MB_CASE_TITLE, 'UTF-8');
                break;
            }
        }

        // 3) Contrat
        foreach ($this->contracts as $k => $v) {
            if (preg_match('/\b'.preg_quote($k,'/').'\b/u', $t)) {
                $intent['contract'] = $v;
                break;
            }
        }

        // 4) Entreprise
        // Ex: "mets Capgemini", "entreprise : IBM", "company Amazon"
        if (preg_match('/\b(?:mets|met|entreprise|company)\s*[:\-]?\s*([^\n,;]{2,60})/u', $raw, $m)) {
            $company = trim($m[1]);
            $company = preg_replace('/\s{2,}/u', ' ', $company);
            // Stopper sur mots trop “instruction”
            $company = preg_replace('/\b(à|a|au|aux|avec|pour|en|sur)\b.*$/iu', '', $company);
            $company = trim($company);
            if (mb_strlen($company) >= 2) $intent['company'] = $company;
        }

        // 5) Rôle / Titre
        // Heuristique : prend la portion après "poste", "profil", "role", "en tant que", etc.
        if (preg_match('/\b(?:poste|profil|rôle|role|position|en tant que)\s*[:\-]?\s*([^\n,;]{2,80})/iu', $raw, $m)) {
            $role = trim($m[1]);
            $role = preg_replace('/\s{2,}/u', ' ', $role);
            $role = preg_replace('/\b(à|a|au|aux|avec|pour|en|sur)\b.*$/iu', '', $role);
            $role = trim($role);
            if (mb_strlen($role) >= 2) $intent['role'] = $role;
        } else {
            // fallback : détecter métiers fréquents (tu peux étendre)
            $roles = [
                'développeur','developpeur','dev','ingénieur','ingenieur','data engineer','data scientist',
                'react','laravel','php','java','spring','fullstack','frontend','backend',
                'plombier','chauffeur','électricien','electricien','comptable','assistante','technicien'
            ];
            foreach ($roles as $r) {
                if (preg_match('/\b'.preg_quote($r,'/').'\b/iu', $t)) {
                    // si on trouve "react" seul, on remonte un rôle plus propre
                    if (in_array($r, ['react','laravel','php','java','spring'], true)) {
                        $intent['role'] = 'Développeur ' . mb_convert_case($r, MB_CASE_TITLE, 'UTF-8');
                    } else {
                        $intent['role'] = mb_convert_case($r, MB_CASE_TITLE, 'UTF-8');
                    }
                    break;
                }
            }
        }

        // 6) Stack / compétences (ex: "stack: React, Laravel, PostgreSQL" ou mots clés détectés)
        $stack = [];
        if (preg_match('/\b(?:stack|tech|technos|compétences|competences)\s*[:\-]\s*([^\n]{3,200})/iu', $raw, $m)) {
            $list = $m[1];
            $parts = preg_split('/[,\/|]+/u', $list);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '' && mb_strlen($p) <= 30) $stack[] = $p;
            }
        } else {
            // auto-extraction simple depuis le texte
            $known = ['react','laravel','php','postgresql','postgres','mysql','docker','kubernetes','node','typescript','javascript','python','java','spring','git','redis','aws','azure'];
            foreach ($known as $k) {
                if (preg_match('/\b'.preg_quote($k,'/').'\b/iu', $t)) {
                    $stack[] = mb_convert_case($k, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }
        $stack = array_values(array_unique($stack));
        if (!empty($stack)) $intent['stack'] = $stack;

        // 7) Ton / style
        if (preg_match('/\bplus\s+(pro|professionnel)\b/iu', $raw)) $intent['tone'] = 'professionnel';
        if (preg_match('/\bplus\s+humain\b/iu', $raw)) $intent['tone'] = 'humain';
        if (preg_match('/\bcourt\b/iu', $raw)) $intent['length'] = 'court';
        if (preg_match('/\bplus\s+long\b/iu', $raw)) $intent['length'] = 'long';

        return $intent;
    }
}
