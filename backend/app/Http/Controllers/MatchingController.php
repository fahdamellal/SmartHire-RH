<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Matching\Services\MatchingService;

class MatchingController extends Controller
{
    /**
     * POST /api/matching/run
     * body: { id_demande, limit? }
     */
    public function run(Request $request, MatchingService $service)
    {
        $validated = $request->validate([
            'id_demande' => ['required','integer','min:1'],
            'limit' => ['sometimes','integer','min:1','max:50'],
        ]);

        $id = (int)$validated['id_demande'];
        $limit = (int)($validated['limit'] ?? 10);

        $demande = DB::selectOne("SELECT id_demande, texte FROM demandes WHERE id_demande = ? LIMIT 1", [$id]);
        if (!$demande) {
            return response()->json(['ok' => false, 'error' => 'Demande introuvable.'], 404);
        }

        $out = $service->match($id, (string)$demande->texte, $limit);

        return response()->json([
            'ok' => true,
            'id_demande' => $id,
            'criteria' => $out['criteria'],
            'results' => $out['results'],
        ]);
    }
}
