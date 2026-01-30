<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        // KPIs
        $kpis = [
            'total_cvs'      => (int) (DB::selectOne("SELECT COUNT(*)::int AS n FROM cv_files")->n ?? 0),
            'total_chunks'   => (int) (DB::selectOne("SELECT COUNT(*)::int AS n FROM cv_chunks")->n ?? 0),
            'total_demandes' => (int) (DB::selectOne("SELECT COUNT(*)::int AS n FROM demandes")->n ?? 0),
            'total_matches'  => (int) (DB::selectOne("SELECT COUNT(*)::int AS n FROM demander")->n ?? 0),
        ];

        // Chunk quality
        $cq = DB::selectOne("
            SELECT
                COALESCE(ROUND(AVG(cnt)::numeric, 2), 0) AS avg_chunks_per_cv,
                COALESCE(MIN(cnt), 0) AS min_chunks_per_cv,
                COALESCE(MAX(cnt), 0) AS max_chunks_per_cv
            FROM (
                SELECT id_file, COUNT(*) AS cnt
                FROM cv_chunks
                GROUP BY id_file
            ) t
        ");

        $chunk_quality = [
            'avg_chunks_per_cv' => (float) ($cq->avg_chunks_per_cv ?? 0),
            'min_chunks_per_cv' => (int)   ($cq->min_chunks_per_cv ?? 0),
            'max_chunks_per_cv' => (int)   ($cq->max_chunks_per_cv ?? 0),
        ];

        // Status distribution
        $status_distribution = DB::select("
            SELECT status, COUNT(*)::int AS n
            FROM demander
            GROUP BY status
            ORDER BY status
        ");

        // Recent CVs
        $recent_cvs = DB::select("
            SELECT id_file, nom, prenom, email, created_at
            FROM cv_files
            ORDER BY created_at DESC
            LIMIT 10
        ");

        // Recent demandes
        $recent_demandes = DB::select("
            SELECT id_demande, entreprise, texte, created_at
            FROM demandes
            ORDER BY created_at DESC
            LIMIT 10
        ");

        // Top skills (si cv_files.skills est un JSON array ["react","laravel",...])
        $top_skills = DB::select("
            SELECT skill, COUNT(*)::int AS n
            FROM (
                SELECT jsonb_array_elements_text(skills) AS skill
                FROM cv_files
                WHERE skills IS NOT NULL
                  AND jsonb_typeof(skills) = 'array'
            ) x
            GROUP BY skill
            ORDER BY n DESC, skill ASC
            LIMIT 12
        ");

        // ✅ Top 3 INTERESTED (le plus important)
        $top_interested = DB::select("
            SELECT
                d.id_file,
                f.prenom,
                f.nom,
                f.email,
                d.score,
                d.id_demande,
                de.entreprise,
                d.updated_at
            FROM demander d
            JOIN cv_files f ON f.id_file = d.id_file
            LEFT JOIN demandes de ON de.id_demande = d.id_demande
            WHERE d.status = 'INTERESTED'
            ORDER BY d.score DESC NULLS LAST, d.updated_at DESC NULLS LAST
            LIMIT 3
        ");

        // Activité 14 jours (simple)
        $activity_14d = DB::select("
            WITH days AS (
                SELECT generate_series(
                    (CURRENT_DATE - INTERVAL '13 days')::date,
                    CURRENT_DATE::date,
                    INTERVAL '1 day'
                )::date AS d
            )
            SELECT
                to_char(days.d, 'YYYY-MM-DD') AS date,
                COALESCE(c.cvs_added, 0)::int AS cvs_added,
                COALESCE(dm.demandes_created, 0)::int AS demandes_created,
                COALESCE(ma.matches_created, 0)::int AS matches_created
            FROM days
            LEFT JOIN (
                SELECT created_at::date AS d, COUNT(*) AS cvs_added
                FROM cv_files
                WHERE created_at >= (CURRENT_DATE - INTERVAL '13 days')
                GROUP BY created_at::date
            ) c ON c.d = days.d
            LEFT JOIN (
                SELECT created_at::date AS d, COUNT(*) AS demandes_created
                FROM demandes
                WHERE created_at >= (CURRENT_DATE - INTERVAL '13 days')
                GROUP BY created_at::date
            ) dm ON dm.d = days.d
            LEFT JOIN (
                SELECT created_at::date AS d, COUNT(*) AS matches_created
                FROM demander
                WHERE created_at >= (CURRENT_DATE - INTERVAL '13 days')
                GROUP BY created_at::date
            ) ma ON ma.d = days.d
            ORDER BY days.d ASC
        ");

        return response()->json([
            'ok' => true,
            'kpis' => $kpis,
            'chunk_quality' => $chunk_quality,
            'status_distribution' => $status_distribution,
            'recent_cvs' => $recent_cvs,
            'recent_demandes' => $recent_demandes,
            'top_skills' => $top_skills,
            'top_interested' => $top_interested, // ✅ TOP 3
            'activity_14d' => $activity_14d,
            'notes' => [
                'source' => 'stats calculées depuis PostgreSQL (cv_files, cv_chunks, demandes, demander)',
                'top_interested' => 'Top 3 triés par score DESC puis updated_at DESC',
            ],
        ]);
    }
}
