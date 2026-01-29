<?php

namespace App\Modules\Matching\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Embeddings\Services\GeminiEmbeddings;

class MatchingService
{
    public function __construct(
        private GeminiEmbeddings $embeddings,
        private DemandeQueryParser $parser
    ) {}

    /**
     * Matching intelligent:
     * - parse criteria
     * - embed demande (dim=768)
     * - vector search sur cv_chunks (cosine) => score principal
     * - bonus skills trigram sur cv_files.skills_flat
     * - retourne top N candidats
     */
    public function match(int $idDemande, string $demandeText, int $limit = 10): array
    {
        $criteria = $this->parser->parse($demandeText);

        // 1) Embedding demande (768 car DB = vector(768))
        $vec = $this->embeddings->embed($demandeText, 768, 'RETRIEVAL_QUERY');
        $vecStr = $this->toPgVector($vec);

        // 2) Sauver embedding + criteria_json (optionnel mais conseillé)
        DB::statement(
            "UPDATE demandes SET embedding = ?, criteria_json = COALESCE(criteria_json, '{}'::jsonb) || ?::jsonb WHERE id_demande = ?",
            [$vecStr, json_encode(['criteria' => $criteria], JSON_UNESCAPED_UNICODE), $idDemande]
        );

        // 3) Construire query “skills” (trigram) depuis role + keywords
        $skillQuery = $this->buildSkillsQuery($criteria);

        // 4) Hybrid search
        // - On prend les meilleurs chunks par CV (topChunks)
        // - On agrège par id_file (max similarity)
        // - On ajoute un bonus trigram skills_flat
        $topChunks = 6;

        $sql = "
        WITH ranked_chunks AS (
            SELECT
                c.id_file,
                1 - (c.embedding <=> ?::vector) AS sim,
                c.chunk_text
            FROM cv_chunks c
            WHERE c.embedding IS NOT NULL
            ORDER BY c.embedding <=> ?::vector
            LIMIT 800
        ),
        agg AS (
            SELECT
                id_file,
                MAX(sim) AS best_sim
            FROM ranked_chunks
            GROUP BY id_file
        ),
        skills AS (
            SELECT
                f.id_file,
                CASE
                    WHEN ? = '' THEN 0
                    ELSE GREATEST(similarity(COALESCE(f.skills_flat,''), ?), 0)
                END AS skill_sim
            FROM cv_files f
        )
        SELECT
            f.id_file,
            f.nom,
            f.prenom,
            f.email,
            f.phone,
            f.file_path,
            a.best_sim,
            s.skill_sim,
            (a.best_sim * ? + s.skill_sim * ?) AS final_score
        FROM agg a
        JOIN cv_files f ON f.id_file = a.id_file
        LEFT JOIN skills s ON s.id_file = f.id_file
        ORDER BY final_score DESC
        LIMIT ?
        ";

        // pondérations: IT => skills plus important, NON_IT => chunks plus important
        $wVec = ($criteria['domain'] ?? 'NON_IT') === 'IT' ? 0.70 : 0.85;
        $wSkill = 1.0 - $wVec;

        $rows = DB::select($sql, [
            $vecStr,
            $vecStr,
            $skillQuery,
            $skillQuery,
            $wVec,
            $wSkill,
            $limit
        ]);

        // 5) Persister dans demander (score) en upsert
        foreach ($rows as $r) {
            DB::statement("
                INSERT INTO demander (id_demande, id_file, score, status, created_at)
                VALUES (?, ?, ?, 'PROPOSED', now())
                ON CONFLICT (id_demande, id_file)
                DO UPDATE SET score = EXCLUDED.score, updated_at = now()
            ", [$idDemande, (int)$r->id_file, (float)$r->final_score]);
        }

        return [
            'criteria' => $criteria,
            'results' => array_map(fn($r) => [
                'id_file' => (int)$r->id_file,
                'nom' => $r->nom,
                'prenom' => $r->prenom,
                'email' => $r->email,
                'phone' => $r->phone,
                'file_path' => $r->file_path,
                'best_sim' => (float)$r->best_sim,
                'skill_sim' => isset($r->skill_sim) ? (float)$r->skill_sim : 0.0,
                'score' => (float)$r->final_score,
            ], $rows)
        ];
    }

    private function buildSkillsQuery(array $criteria): string
    {
        $parts = [];
        if (!empty($criteria['role'])) $parts[] = (string)$criteria['role'];
        if (!empty($criteria['keywords']) && is_array($criteria['keywords'])) {
            foreach ($criteria['keywords'] as $k) {
                if (is_string($k) && mb_strlen($k) >= 3) $parts[] = $k;
            }
        }
        $q = trim(implode(' ', array_unique($parts)));
        return mb_substr($q, 0, 180);
    }

    /**
     * Convert array<float> => pgvector string "[0.1,0.2,...]"
     */
    private function toPgVector(array $vec): string
    {
        // sécuriser
        $floats = [];
        foreach ($vec as $v) {
            $f = (float)$v;
            // éviter INF/NAN
            if (!is_finite($f)) $f = 0.0;
            $floats[] = rtrim(rtrim(sprintf('%.8F', $f), '0'), '.');
        }
        return '[' . implode(',', $floats) . ']';
    }
}
