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
        $topKFiles = max(1, min(20, (int)$topKFiles));
        $topKChunks = max(50, min(1500, (int)$topKChunks));

        $c = $this->normalizeCriteria($criteria, $rawMessage);

        // 1) shortlist via vector (chunks)
        $rows = DB::select("
            WITH top_chunks AS (
                SELECT
                    c.id_file,
                    (1 - (c.embedding <=> ?::vector)) AS sim
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
            SELECT
                a.id_file,
                a.sim_max, a.sim_avg
            FROM agg a
        ", [$queryVectorLiteral, $queryVectorLiteral, $topKChunks]);

        if (!$rows) return [];

        $ids = array_map(fn($r) => (int)$r->id_file, $rows);

        // 2) charger full cv_text + skills + meta
        $files = DB::table('cv_files')
            ->select('id_file','nom','prenom','email','phone','file_path','skills','cv_text')
            ->whereIn('id_file', $ids)
            ->get();

        $vecMap = [];
        foreach ($rows as $r) {
            $vecMap[(int)$r->id_file] = [
                'sim_max' => (float)$r->sim_max,
                'sim_avg' => (float)$r->sim_avg,
            ];
        }

        // 3) hard requirements (ex: java+spring obligatoires si demandés)
        $required = $this->requiredSkills($c);

        $scored = [];
        foreach ($files as $f) {
            $id = (int)$f->id_file;

            $skills = $this->decodeSkills($f->skills);
            $cvText = mb_strtolower((string)($f->cv_text ?? ''));

            // HARD FILTER (si required non vide)
            if ($required) {
                $ok = true;
                foreach ($required as $req) {
                    $has = in_array($req, $skills, true) || $this->hasToken($cvText, $req);
                    if (!$has) { $ok = false; break; }
                }
                if (!$ok) continue;
            }

            $v = $vecMap[$id] ?? ['sim_max'=>0.0,'sim_avg'=>0.0];
            $vecScore = (0.75 * $v['sim_max']) + (0.25 * $v['sim_avg']);

            // lexical bonus sur TOUT le CV (pas sample_text)
            $lex = $this->lexSignals($cvText, $c, $skills);

            $lexScore =
                (0.18 * $lex['stack_ratio']) +
                (0.08 * $lex['keywords_ratio']) +
                (0.05 * ($lex['role_hit'] ? 1 : 0)) +
                (0.04 * ($lex['location_hit'] ? 1 : 0)) +
                (0.03 * ($lex['seniority_hit'] ? 1 : 0));

            // Bonus spécial : si React demandé (souvent “nice to have”)
            if (in_array('react', $c['stack'], true) && (in_array('react', $skills, true) || $this->hasToken($cvText, 'react'))) {
                $lexScore += 0.05;
            }

            $final = $vecScore + $lexScore;

            $scored[] = [
                'id_file' => $id,
                'score' => $final,
                'nom' => $f->nom,
                'prenom' => $f->prenom,
                'email' => $f->email,
                'phone' => $f->phone,
                'file_path' => $f->file_path,
                'cv_url' => url('/api/cv/' . $id),
                'skills' => $skills,

                // debug (tu peux enlever plus tard)
                '_debug' => [
                    'required' => $required,
                    'vec' => round($vecScore, 4),
                    'lex' => [
                        'stack_hits' => $lex['stack_hits'],
                        'stack_ratio' => round($lex['stack_ratio'], 3),
                        'keywords_ratio' => round($lex['keywords_ratio'], 3),
                        'role_hit' => $lex['role_hit'],
                        'location_hit' => $lex['location_hit'],
                        'seniority_hit' => $lex['seniority_hit'],
                    ],
                    'criteria' => $c,
                ],
            ];
        }

        if (!$scored) {
            // fallback contrôlé : si strict et rien → on enlève 1 contrainte (ex: react)
            if ($c['strict'] && in_array('react', $required, true)) {
                $required = array_values(array_filter($required, fn($x) => $x !== 'react'));
                if ($required) {
                    // relance léger en mémoire : on refiltre avec required sans react
                    // (simple: on refait match sans strict hard react en “requiredSkills” via criteria modifiée)
                    $c2 = $c;
                    $c2['stack'] = array_values(array_filter($c2['stack'], fn($x) => $x !== 'react'));
                    return $this->match($queryVectorLiteral, $c2, $rawMessage, $topKFiles, $topKChunks);
                }
            }
            return [];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $topKFiles);
    }

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

private function decodeSkills($raw): array
{
    // $raw peut être: null, string JSON, array (cast), stdClass...
    if ($raw === null) return [];

    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    } elseif ($raw instanceof \stdClass) {
        $raw = (array) $raw;
    } elseif (!is_array($raw)) {
        $raw = [];
    }

    // Aplatir n'importe quelle structure en strings
    $flat = [];
    $walk = function ($v) use (&$flat, &$walk) {
        if (is_string($v)) {
            $s = trim($v);
            if ($s !== '') $flat[] = $s;
            return;
        }
        if (is_numeric($v)) {
            $flat[] = (string) $v;
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
        // ignore bool/null/other
    };

    $walk($raw);

    // normaliser + uniques
    $flat = array_values(array_unique(array_map(fn($s) => mb_strtolower(trim($s)), $flat)));

    return $flat;
}



    private function requiredSkills(array $c): array
    {
        // règle simple et efficace:
        // - si java demandé => java obligatoire
        // - si spring demandé => spring obligatoire
        // - react = souvent bonus, MAIS si user l’écrit dans stack, on peut le rendre obligatoire aussi (ton choix)
        $stack = $c['stack'] ?? [];

        $req = [];
        if (in_array('java', $stack, true)) $req[] = 'java';
        if (in_array('spring', $stack, true)) $req[] = 'spring';

        // Option: rendre react obligatoire seulement si tu veux exact exact
        if ($c['strict_exact'] && in_array('react', $stack, true)) $req[] = 'react';

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
                $tmp = $this->fallbackFromMessage($rawMessage, ['stack'=>[], 'keywords'=>[], 'role'=>null, 'location'=>null, 'seniority'=>null]);
                $out['stack'] = $tmp['stack'];
            }
        }

        // strict: si stack existe
        $out['strict'] = count($out['stack']) > 0;

        // strict_exact:
        // - tu peux le mettre true si tu veux “Java+Spring+React obligatoires”
        // - sinon false et React devient bonus
        $out['strict_exact'] = false;

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
            if (str_contains($m, 'développeur') || str_contains($m, 'developpeur') || str_contains($m, 'developer')) $base['role'] = 'developpeur';
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

            // normaliser quelques variantes
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

        // accepte "spring boot" aussi via espaces (déjà normalisé)
        $re = '/\b' . preg_quote($token, '/') . '\b/u';
        return (bool)preg_match($re, $text);
    }
}
