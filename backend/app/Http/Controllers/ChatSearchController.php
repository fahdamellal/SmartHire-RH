<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Modules\Embeddings\Services\DemandParser;
use App\Modules\Embeddings\Services\GeminiEmbeddings;
use App\Modules\Embeddings\Services\DemandMatcher;
use App\Modules\Embeddings\Services\LinkedInPostGenerator;
use App\Modules\Embeddings\Services\LinkedInPostEditor; // (pas obligatoire ici)

class ChatSearchController extends Controller
{
    public function search(
        Request $request,
        DemandParser $parser,
        GeminiEmbeddings $emb,
        DemandMatcher $matcher,
        LinkedInPostGenerator $postGen
    ) {
        $request->validate([
            'message' => ['required', 'string', 'min:3'],
        ]);

        $message = $request->string('message')->toString();

        // A) Parse (criteria_json)
        $criteria = $parser->parse($message);
        $entreprise = $criteria['entreprise'] ?? null;

        $count = (int) ($criteria['count'] ?? 5);
        $count = max(1, min(20, $count));

        // B) Sauvegarder demande
        $row = DB::selectOne("
            INSERT INTO demandes (entreprise, texte, criteria_json, created_at)
            VALUES (?, ?, ?::jsonb, now())
            RETURNING id_demande
        ", [$entreprise, $message, json_encode($criteria, JSON_UNESCAPED_UNICODE)]);

        $idDemande = (int) $row->id_demande;

        // C) Embedding de la demande (task=RETRIEVAL_QUERY)
        $embedText = $this->criteriaToEmbedText($criteria);
        $dim = (int) config('services.gemini.embed_dim', 1536);

        $vec = $emb->embed($embedText, $dim, 'RETRIEVAL_QUERY');
        $vecLiteral = '[' . implode(',', array_map(fn($x) => (float)$x, $vec)) . ']';

        DB::statement("UPDATE demandes SET embedding = ?::vector WHERE id_demande = ?", [$vecLiteral, $idDemande]);

        // D) Matching
        $results = $matcher->match($vecLiteral, $criteria, $message, $count, 300);

        // E) Si on a des résultats
        if (!empty($results)) {
            $matcher->upsertResults($idDemande, $results);

            return response()->json([
                'mode' => 'RESULTS',
                'id_demande' => $idDemande,
                'criteria' => $criteria,
                'results' => $results,
            ]);
        }

        // F) 0 résultat => génération post LinkedIn + save
        $companyName = $entreprise ?: 'Notre entreprise';
        $post = $postGen->generate($companyName, $criteria);

        $postRow = DB::selectOne("
            INSERT INTO linkedin_posts (id_demande, company_name, job_title, post_text, meta_json, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?::jsonb, now(), now())
            RETURNING id_post
        ", [
            $idDemande,
            $companyName,
            $criteria['role'] ?? null,
            $post,
            json_encode(['criteria' => $criteria], JSON_UNESCAPED_UNICODE),
        ]);

        return response()->json([
            'mode' => 'NO_RESULTS',
            'id_demande' => $idDemande,
            'criteria' => $criteria,
            'results' => [],
            'linkedin' => [
                'id_post' => (int) $postRow->id_post,
                'company_name' => $companyName,
                'post_text' => $post,
            ],
        ]);
    }

    private function criteriaToEmbedText(array $c): string
    {
        $parts = [];

        if (!empty($c['role'])) $parts[] = "Role: " . $c['role'];
        if (!empty($c['stack']) && is_array($c['stack'])) $parts[] = "Stack: " . implode(', ', $c['stack']);
        if (!empty($c['location'])) $parts[] = "Location: " . $c['location'];
        if (!empty($c['keywords']) && is_array($c['keywords'])) $parts[] = "Keywords: " . implode(', ', $c['keywords']);
        if (!empty($c['seniority'])) $parts[] = "Seniority: " . $c['seniority'];

        return $parts ? implode(". ", $parts) . "." : json_encode($c, JSON_UNESCAPED_UNICODE);
    }
}
