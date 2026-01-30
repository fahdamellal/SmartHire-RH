<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Modules\Embeddings\Services\LinkedInPostEditor;
use App\Modules\Embeddings\Services\LinkedInIntentParser;

class LinkedInPostController extends Controller
{
    public function reviseByDemande(Request $request, LinkedInPostEditor $editor, LinkedInIntentParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'id_demande'    => ['required', 'integer'],
            'instruction'   => ['required', 'string', 'max:2000'],
            'current_post'  => ['required', 'string', 'max:20000'],
        ]);

        // Dernier post lié à la demande
        $row = DB::selectOne("
            SELECT id_post, id_demande, company_name, job_title, post_text, meta_json
            FROM linkedin_posts
            WHERE id_demande = ?
            ORDER BY id_post DESC
            LIMIT 1
        ", [$validated['id_demande']]);

        if (!$row) {
            return response()->json(['ok' => false, 'error' => 'Post not found for this demande'], 404);
        }

        // Meta JSON actuel
        $meta = json_decode($row->meta_json ?? '{}', true);
        if (!is_array($meta)) $meta = [];
        $criteria = $meta['criteria'] ?? [];
        if (!is_array($criteria)) $criteria = [];

        // Parse instruction -> patch criteria
        $patch = $parser->parse($validated['instruction']);

        // Merge "mémoire" : patch écrase ce qui existe
        $criteria = array_merge($criteria, $patch);

        // Sauvegarde meta
        DB::update("
            UPDATE linkedin_posts
            SET meta_json = ?
            WHERE id_post = ?
        ", [json_encode(['criteria' => $criteria], JSON_UNESCAPED_UNICODE), $row->id_post]);

        // Générer une nouvelle version
        $newPost = $editor->revise(
            $criteria['company'] ?? $row->company_name ?? null,
            $criteria['role'] ?? $row->job_title ?? null,
            $criteria,
            $validated['current_post'],
            $validated['instruction']
        );

        // Persister post
        DB::update("
            UPDATE linkedin_posts
            SET post_text = ?
            WHERE id_post = ?
        ", [$newPost, $row->id_post]);

        return response()->json([
            'ok' => true,
            'id_post' => (int)$row->id_post,
            'criteria' => $criteria,
            'post_text' => $newPost,
        ]);
    }
}
