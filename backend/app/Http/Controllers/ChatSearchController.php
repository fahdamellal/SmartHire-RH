<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Embeddings\Services\DemandParser;
use App\Modules\Embeddings\Services\GeminiEmbeddings;
use App\Modules\Embeddings\Services\DemandMatcher;
use App\Modules\Embeddings\Services\LinkedInPostGenerator;

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
            'message' => ['required', 'string', 'min:5'],
        ]);

        $message = $request->string('message')->toString();

        // A) Parse (entreprise + criteria_json)
        $criteria = $parser->parse($message);
        $entreprise = $criteria['entreprise'] ?? null;
        $count = (int) ($criteria['count'] ?? 5);
        if ($count < 1) $count = 5;
        if ($count > 20) $count = 20;

        // B) Sauvegarder demande
        $row = DB::selectOne("
            INSERT INTO demandes (entreprise, texte, criteria_json, created_at)
            VALUES (?, ?, ?::jsonb, now())
            RETURNING id_demande
        ", [$entreprise, $message, json_encode($criteria)]);

        $idDemande = (int) $row->id_demande;

        // C) Embedding de la demande (texte normalisé à partir du JSON)
        $embedText = $this->criteriaToEmbedText($criteria);
        $dim = (int) config('services.gemini.embed_dim', 1536);

        $vec = $emb->embed($embedText, $dim);
        $vecLiteral = '[' . implode(',', array_map(fn($x) => (float)$x, $vec)) . ']';

        DB::statement("UPDATE demandes SET embedding = ?::vector WHERE id_demande = ?", [$vecLiteral, $idDemande]);

        // D) Matching
        $results = $matcher->match($vecLiteral, $criteria, $message, $count, 300);



        // E) Insert results dans demander
        if ($results) {
            $matcher->upsertResults($idDemande, $results);
            return response()->json([
                'id_demande' => $idDemande,
                'criteria' => $criteria,
                'results' => $results,
            ]);
        }

        // F) si 0 résultat => post LinkedIn
        $companyName = $entreprise ?: 'Notre entreprise';
        $post = $postGen->generate($companyName, $criteria);

        return response()->json([
            'id_demande' => $idDemande,
            'criteria' => $criteria,
            'results' => [],
            'linkedin_post' => $post,
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

        // fallback
        if (!$parts) return json_encode($c, JSON_UNESCAPED_UNICODE);

        return implode(". ", $parts) . ".";
    }
}
