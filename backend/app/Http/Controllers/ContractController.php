<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    public function download(int $id_demande, int $id_file)
    {
        // 1) Demande
        $demande = DB::selectOne("
            SELECT id_demande, entreprise, texte, criteria_json, created_at
            FROM demandes
            WHERE id_demande = ?
        ", [$id_demande]);

        if (!$demande) {
            return response()->json(['ok' => false, 'error' => 'Demande introuvable'], 404);
        }

        // 2) Candidat
        $cand = DB::selectOne("
            SELECT id_file, nom, prenom, email, phone
            FROM cv_files
            WHERE id_file = ?
        ", [$id_file]);

        if (!$cand) {
            return response()->json(['ok' => false, 'error' => 'Candidat introuvable'], 404);
        }

        // 3) Match row (status/score)
        $match = DB::selectOne("
            SELECT score, status
            FROM demander
            WHERE id_demande = ? AND id_file = ?
        ", [$id_demande, $id_file]);

        if (!$match) {
            return response()->json(['ok' => false, 'error' => 'Aucun matching trouvé pour ce candidat'], 404);
        }

        // Option: autoriser contrat seulement si INTERESTED
        // (tu peux enlever ce bloc si tu veux autoriser pour tous)
        $status = strtoupper((string)($match->status ?? ''));
        if ($status !== 'INTERESTED') {
            return response()->json([
                'ok' => false,
                'error' => "Contrat disponible uniquement si status=INTERESTED (actuel: {$status})"
            ], 403);
        }

        $criteria = $demande->criteria_json ? json_decode($demande->criteria_json, true) : [];
        $company  = $demande->entreprise ?: ($criteria['company'] ?? 'Entreprise');
        $role     = $criteria['role'] ?? $criteria['job_title'] ?? 'Poste';
        $city     = $criteria['city'] ?? $criteria['location'] ?? 'Maroc';
        $contract = $criteria['contract'] ?? 'CDI'; // default

        $data = [
            'company' => $company,
            'role' => $role,
            'city' => $city,
            'contract' => $contract,
            'demande' => $demande,
            'candidate' => $cand,
            'score' => $match->score,
            'status' => $match->status,
            'date' => now()->format('Y-m-d'),
        ];

        $pdf = Pdf::loadView('pdf.contract', $data)->setPaper('a4', 'portrait');

        $safeName = trim(($cand->prenom ?? '') . '_' . ($cand->nom ?? ''));
        $safeName = $safeName !== '' ? $safeName : ('candidat_' . $id_file);
        $filename = "Contrat_{$safeName}_D{$id_demande}.pdf";

        return $pdf->download($filename);
    }
}
