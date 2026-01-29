<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Facades\DB;

class DemandMatcher
{
    public function match(
        string $queryVectorLiteral,
        array $criteria,
        string $rawMessage,
        int $topKFiles = 5,
        int $topKChunks = 300
    ): array {
        $topKFiles  = max(1, min(20, (int)$topKFiles));
        $topKChunks = max(50, min(1500, (int)$topKChunks));

        $c = $this->normalizeCriteria($criteria, $rawMessage);

        // 1) shortlist via vector (chunks) — cosine sim normalisée 0..1
        $rows = DB::select("
            WITH top_chunks AS (
                SELECT
                    c.id_file,
                    (1 - ((c.embedding <=> ?::vector) / 2.0)) AS sim
                FROM cv_chunks c
                ORDER BY c.embedding <=> ?::vector
                LIMIT ?
            ),
            agg AS (
                SELECT
                    id_file,
                    MAX(sim) AS sim_max,
                    AVG(sim) AS sim_avg
                FROM top_chunks
                GROUP BY id_file
            )
            SELECT a.id_file, a.sim_max, a.sim_avg
            FROM agg a
        ", [$queryVectorLiteral, $queryVectorLiteral, $topKChunks]);

        if (!$rows) return [];

        $ids = array_map(fn($r) => (int)$r->id_file, $rows);

        // 2) charger cv_text + skills + meta
        $files = DB::table('cv_files')
            ->select('id_file','nom','prenom','email','phone','file_path','skills','cv_text')
            ->whereIn('id_file', $ids)
            ->get();

        // map vector scores
        $vecMap = [];
        foreach ($rows as $r) {
            $vecMap[(int)$r->id_file] = [
                'sim_max' => $this->clamp01((float)$r->sim_max),
                'sim_avg' => $this->clamp01((float)$r->sim_avg),
            ];
        }

        // required (soft penalties)
        $required = $this->requiredSkills($c);

        $scored = [];
        foreach ($files as $f) {
            $id = (int)$f->id_file;

            $skills = $this->decodeSkills($f->skills);
            $cvText = mb_strtolower((string)($f->cv_text ?? ''));

            // --- Vector score (0..1)
            $v = $vecMap[$id] ?? ['sim_max'=>0.0,'sim_avg'=>0.0];
            $vecScore = (0.75 * $v['sim_max']) + (0.25 * $v['sim_avg']);
            $vecScore = $this->clamp01($vecScore);

            // --- Lexical signals
            $lex = $this->lexSignals($cvText, $c, $skills);

            // bonus lexical (0..~0.38) => on clamp ensuite
            $lexScore =
                (0.18 * $lex['stack_ratio']) +
                (0.08 * $lex['keywords_ratio']) +
                (0.05 * ($lex['role_hit'] ? 1 : 0)) +
                (0.04 * ($lex['location_hit'] ? 1 : 0)) +
                (0.03 * ($lex['seniority_hit'] ? 1 : 0));

            // bonus React (optionnel)
            if (in_array('react', $c['stack'], true) && (in_array('react', $skills, true) || $this->hasToken($cvText, 'react'))) {
                $lexScore += 0.05;
            }

            // ------------------------
            // GATING (anti "plombier => dev")
            // Si user a demandé un role/stack/keywords => il faut au moins 1 signal lexical
            // (sinon on drop le CV)
            // ------------------------
            $userHasIntent = !empty($c['role']) || !empty($c['stack']) || !empty($c['keywords']);
            $hasAnySignal  = ($lex['stack_hits'] > 0) || $lex['role_hit'] || ($lex['keywords_ratio'] > 0);

            if ($userHasIntent && !$hasAnySignal) {
                continue;
            }

            // ---- Soft penalties ----
            $missingRequired = 0;
            foreach ($required as $req) {
                $has = in_array($req, $skills, true) || $this->hasToken($cvText, $req);
                if (!$has) $missingRequired++;
            }

            $penaltyRequired = 0.18 * $missingRequired;

            $missingStack = max(0, count($c['stack']) - ($lex['stack_hits'] ?? 0));
            $penaltyMissingStack = 0.05 * $missingStack;

            if (!empty($c['strict_exact']) && $missingRequired > 0) {
                $penaltyRequired += 0.25 * $missingRequired;
            }

            // --- Final score (on garde 0..1)
            // Mix plus stable: vector dominant, lex en support
            $final = (0.82 * $vecScore) + (0.18 * $this->clamp01($lexScore));
            $final = $final - $penaltyRequired - $penaltyMissingStack;
            $final = $this->clamp01($final);

            // petit seuil par-candidat (évite résultats faibles)
            if ($final < 0.20) continue;

            $scored[] = [
                'id_file' => $id,
                'score' => $final, // 0..1
                'similarity_percent' => round($final * 100, 1),

                'nom' => $f->nom,
                'prenom' => $f->prenom,
                'email' => $f->email,
                'phone' => $f->phone,
                'file_path' => $f->file_path,
                'cv_url' => url('/api/cv/' . $id),
                'skills' => $skills,

                '_debug' => [
                    'vec' => round($vecScore, 4),
                    'lex_raw' => round($lexScore, 4),
                    'missing_required' => $missingRequired,
                    'penalty_required' => round($penaltyRequired, 4),
                    'missing_stack' => $missingStack,
                    'penalty_missing_stack' => round($penaltyMissingStack, 4),
                    'criteria' => $c,
                ],
            ];
        }

        if (!$scored) return [];

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $topKFiles);

        // Seuil global : si même le meilleur est faible => aucun résultat
        if (($top[0]['score'] ?? 0) < 0.45) {
            return [];
        }

        return $top;
    }

    // IMPORTANT: ton ChatSearchController l'appelle => il faut cette méthode
    public function upsertResults(int $idDemande, array $results): void
    {
        foreach ($results as $r) {
            DB::statement("
                INSERT INTO demander (id_demande, id_file, created_at, status, score)
                VALUES (?, ?, now(), 'PROPOSED', ?)
                ON CONFLICT (id_demande, id_file)
                DO UPDATE SET score = EXCLUDED.score
            ", [$idDemande, (int)$r['id_file'], (float)$r['score']]);
        }
    }

    // ---------------- helpers ----------------

    private function clamp01(float $x): float
    {
        if ($x < 0) return 0.0;
        if ($x > 1) return 1.0;
        return $x;
    }

    private function decodeSkills($raw): array
    {
        if ($raw === null) return [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif ($raw instanceof \stdClass) {
            $raw = (array)$raw;
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $flat = [];
        $walk = function ($v) use (&$flat, &$walk) {
            if (is_string($v)) {
                $s = trim($v);
                if ($s !== '') $flat[] = $s;
                return;
            }
            if (is_numeric($v)) {
                $flat[] = (string)$v;
                return;
            }
            if (is_array($v)) {
                foreach ($v as $vv) $walk($vv);
                return;
            }
            if ($v instanceof \stdClass) {
                foreach ((array)$v as $vv) $walk($vv);
                return;
            }
        };
        $walk($raw);

        $flat = array_values(array_unique(array_map(fn($s) => mb_strtolower(trim($s)), $flat)));
        $flat = array_values(array_filter($flat, fn($s) => $s !== ''));

        return $flat;
    }

    private function requiredSkills(array $c): array
    {
        $stack = $c['stack'] ?? [];

        $req = [];
        if (in_array('java', $stack, true)) $req[] = 'java';
        if (in_array('spring', $stack, true)) $req[] = 'spring';

        if (!empty($c['strict_exact']) && in_array('react', $stack, true)) $req[] = 'react';

        return $req;
    }

    private function normalizeCriteria(array $c, string $rawMessage): array
    {
        $out = [
            'role' => is_string($c['role'] ?? null) ? trim($c['role']) : null,
            'location' => is_string($c['location'] ?? null) ? trim($c['location']) : null,
            'seniority' => is_string($c['seniority'] ?? null) ? trim($c['seniority']) : null,
            'stack' => is_array($c['stack'] ?? null) ? $this->normList($c['stack']) : [],
            'keywords' => is_array($c['keywords'] ?? null) ? $this->normList($c['keywords']) : [],
        ];

        if (!$out['stack'] && !$out['role'] && !$out['location'] && !$out['keywords']) {
            $out = $this->fallbackFromMessage($rawMessage, $out);
        } else {
            if (!$out['stack']) {
                $tmp = $this->fallbackFromMessage($rawMessage, [
                    'stack'=>[], 'keywords'=>[], 'role'=>null, 'location'=>null, 'seniority'=>null
                ]);
                $out['stack'] = $tmp['stack'];
            }
        }

        $out['strict'] = count($out['stack']) > 0;
        $out['strict_exact'] = $out['strict_exact'] ?? false;

        return $out;
    }

    private function fallbackFromMessage(string $message, array $base): array
    {
        $m = mb_strtolower($message);

        $techMap = [
            'java' => 'java',
            'spring boot' => 'spring',
            'springboot' => 'spring',
            'spring' => 'spring',
            'react' => 'react',
            'reactjs' => 'react',
            'angular' => 'angular',
            'vue' => 'vue',
            'laravel' => 'laravel',
            'php' => 'php',
            'nodejs' => 'node',
            'node.js' => 'node',
            'node' => 'node',
            'express' => 'express',
            'nestjs' => 'nestjs',
            'python' => 'python',
            'django' => 'django',
            'flask' => 'flask',
            'fastapi' => 'fastapi',
            'postgresql' => 'postgresql',
            'postgres' => 'postgresql',
            'mysql' => 'mysql',
            'mongodb' => 'mongodb',
            'docker' => 'docker',
            'kubernetes' => 'kubernetes',
        ];

        foreach ($techMap as $needle => $label) {
            if (str_contains($m, $needle)) $base['stack'][] = $label;
        }
        $base['stack'] = array_values(array_unique($base['stack']));

        if (!$base['location']) {
            $cities = ['rabat','casablanca','tanger','agadir','marrakech','kenitra','fes','oujda'];
            foreach ($cities as $city) {
                if (preg_match('/\b' . preg_quote($city, '/') . '\b/u', $m)) {
                    $base['location'] = ucfirst($city);
                    break;
                }
            }
        }

        if (!$base['role']) {
            if (str_contains($m, 'développeur') || str_contains($m, 'developpeur') || str_contains($m, 'developer')) {
                $base['role'] = 'developpeur';
            } else {
                // role générique: prend un mot clé simple si possible
                // (ex: "plombier", "joueur")
                if (preg_match('/je\s*(veux|cherche)\s*\d*\s*([a-zàâäéèêëïîôöùûüç]+)/u', $m, $mm)) {
                    $base['role'] = $mm[2] ?? null;
                }
            }
        }

        $kw = ['backend','front','frontend','fullstack','api','microservices','sécurité','securite','mobile'];
        foreach ($kw as $k) {
            if (str_contains($m, $k)) $base['keywords'][] = $k === 'securite' ? 'sécurité' : $k;
        }
        $base['keywords'] = array_values(array_unique($base['keywords']));

        return $base;
    }

    private function normList(array $arr): array
    {
        $out = [];
        foreach ($arr as $x) {
            if (!is_string($x)) continue;
            $s = mb_strtolower(trim($x));
            if ($s === '') continue;

            if ($s === 'spring boot') $s = 'spring';
            if ($s === 'postgres') $s = 'postgresql';
            if ($s === 'reactjs') $s = 'react';

            $out[] = $s;
        }
        return array_values(array_unique($out));
    }

    private function lexSignals(string $cvText, array $c, array $skills): array
    {
        $stackHits = 0;
        foreach ($c['stack'] as $t) {
            $hit = in_array($t, $skills, true) || $this->hasToken($cvText, $t);
            if ($hit) $stackHits++;
        }
        $stackRatio = $c['stack'] ? ($stackHits / max(1, count($c['stack']))) : 0.0;

        $kwHits = 0;
        foreach ($c['keywords'] as $k) {
            if ($this->hasToken($cvText, $k)) $kwHits++;
        }
        $kwRatio = $c['keywords'] ? ($kwHits / max(1, count($c['keywords']))) : 0.0;

        $roleHit = $c['role'] ? $this->hasToken($cvText, mb_strtolower($c['role'])) : false;
        $locationHit = $c['location'] ? $this->hasToken($cvText, mb_strtolower($c['location'])) : false;
        $seniorityHit = $c['seniority'] ? $this->hasToken($cvText, mb_strtolower($c['seniority'])) : false;

        return [
            'stack_hits' => $stackHits,
            'stack_ratio' => $stackRatio,
            'keywords_ratio' => $kwRatio,
            'role_hit' => $roleHit,
            'location_hit' => $locationHit,
            'seniority_hit' => $seniorityHit,
        ];
    }

    private function hasToken(string $text, string $token): bool
    {
        $token = trim($token);
        if ($token === '') return false;

        $re = '/\b' . preg_quote($token, '/') . '\b/u';
        return (bool)preg_match($re, $text);
    }
}
