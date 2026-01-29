<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Embeddings\Services\LinkedInPostEditor;
use App\Modules\Embeddings\Services\LinkedInIntentParser;

class LinkedInPostController extends Controller
{
    public function reviseByDemande(Request $request, LinkedInPostEditor $editor)
    {
        $validated = $request->validate([
            'id_demande' => ['required','integer'],
            'instruction' => ['required','string'],
            'current_post' => ['required','string'],
        ]);

        $row = DB::selectOne("
            SELECT id_post, company_name, job_title, meta_json
            FROM linkedin_posts
            WHERE id_demande = ?
            ORDER BY id_post DESC
            LIMIT 1
        ", [$validated['id_demande']]);

        if (!$row) {
            return response()->json(['error'=>'Post not found'],404);
        }

        $parser = app(LinkedInIntentParser::class);
        $intent = $parser->parse($validated['instruction']);

        $meta = json_decode($row->meta_json ?? '{}', true);
        $criteria = $meta['criteria'] ?? [];

        // 🧠 MÉMOIRE CONVERSATION
        foreach ($intent as $k=>$v) {
            $criteria[$k] = $v;
        }

        DB::statement("
            UPDATE linkedin_posts
            SET meta_json = ?
            WHERE id_post = ?
        ", [json_encode(['criteria'=>$criteria]), $row->id_post]);

        $newPost = $editor->revise(
            $criteria['company'] ?? $row->company_name,
            $criteria['role'] ?? $row->job_title,
            $criteria,
            $validated['current_post'],
            $validated['instruction']
        );

        DB::statement("
            UPDATE linkedin_posts
            SET post_text = ?
            WHERE id_post = ?
        ", [$newPost, $row->id_post]);

        return response()->json(['ok'=>true,'post_text'=>$newPost]);
    }
}
