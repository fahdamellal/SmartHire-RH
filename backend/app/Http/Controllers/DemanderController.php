<?php

namespace App\Http\Controllers;

use App\Services\BrevoMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DemanderController extends Controller
{
    public function listByDemande(int $id_demande): JsonResponse
    {
        // 1) demande + criteria
        $demande = DB::selectOne("
            SELECT id_demande, entreprise, texte, criteria_json, created_at
            FROM demandes
            WHERE id_demande = ?
        ", [$id_demande]);

        if (!$demande) {
            return response()->json([
                'ok' => false,
                'note' => 'Demande not found',
            ], 404);
        }

        // 2) résultats + status
        $rows = DB::select("
            SELECT
                d.id_file,
                d.score,
                d.status,
                f.nom,
                f.prenom,
                f.email,
                f.phone,
                f.file_path
            FROM demander d
            JOIN cv_files f ON f.id_file = d.id_file
            WHERE d.id_demande = ?
            ORDER BY d.score DESC NULLS LAST
        ", [$id_demande]);

        $results = array_map(function ($r) {
            return [
                'id_file' => (int)$r->id_file,
                'score' => $r->score !== null ? (float)$r->score : null,
                'status' => $r->status, // PROPOSED | VIEWED | INTERVIEW
                'nom' => $r->nom,
                'prenom' => $r->prenom,
                'email' => $r->email,
                'phone' => $r->phone,
                'file_path' => $r->file_path,
                'cv_url' => url('/api/cv/' . (int)$r->id_file),
            ];
        }, $rows);

        return response()->json([
            'id_demande' => (int)$demande->id_demande,
            'criteria' => $demande->criteria_json ? json_decode($demande->criteria_json, true) : null,
            'results' => $results,
        ]);
    }

    public function markViewed(int $id_demande, int $id_file): JsonResponse
    {
        $row = DB::selectOne("
            SELECT status
            FROM demander
            WHERE id_demande = ? AND id_file = ?
        ", [$id_demande, $id_file]);

        if (!$row) {
            return response()->json([
                'ok' => false,
                'updated_rows' => 0,
                'id_demande' => $id_demande,
                'id_file' => $id_file,
                'new_status' => null,
                'note' => 'Row not found',
            ], 404);
        }

        // idempotent
        if ($row->status === 'VIEWED' || $row->status === 'INTERVIEW') {
            return response()->json([
                'ok' => true,
                'updated_rows' => 0,
                'id_demande' => $id_demande,
                'id_file' => $id_file,
                'new_status' => $row->status,
                'note' => "Already {$row->status}",
            ]);
        }

        $updated = DB::update("
            UPDATE demander
            SET status = 'VIEWED'
            WHERE id_demande = ? AND id_file = ? AND status = 'PROPOSED'
        ", [$id_demande, $id_file]);

        return response()->json([
            'ok' => $updated > 0,
            'updated_rows' => $updated,
            'id_demande' => $id_demande,
            'id_file' => $id_file,
            'new_status' => $updated > 0 ? 'VIEWED' : $row->status,
            'note' => $updated > 0 ? 'Status changed to VIEWED' : 'No row updated',
        ]);
    }

    public function markInterview(int $id_demande, int $id_file, BrevoMailService $mail): JsonResponse
    {
        $row = DB::selectOne("
            SELECT d.status, f.nom, f.prenom, f.email
            FROM demander d
            JOIN cv_files f ON f.id_file = d.id_file
            WHERE d.id_demande = ? AND d.id_file = ?
        ", [$id_demande, $id_file]);

        if (!$row) {
            return response()->json([
                'ok' => false,
                'updated_rows' => 0,
                'id_demande' => $id_demande,
                'id_file' => $id_file,
                'new_status' => null,
                'note' => 'Row not found',
            ], 404);
        }

        // idempotent
        if ($row->status === 'INTERVIEW') {
            return response()->json([
                'ok' => true,
                'updated_rows' => 0,
                'id_demande' => $id_demande,
                'id_file' => $id_file,
                'new_status' => 'INTERVIEW',
                'note' => 'Already INTERVIEW',
            ]);
        }

        $updated = DB::update("
            UPDATE demander
            SET status = 'INTERVIEW'
            WHERE id_demande = ? AND id_file = ? AND status IN ('PROPOSED','VIEWED')
        ", [$id_demande, $id_file]);

        if ($updated <= 0) {
            return response()->json([
                'ok' => false,
                'updated_rows' => 0,
                'id_demande' => $id_demande,
                'id_file' => $id_file,
                'new_status' => $row->status,
                'note' => 'No row updated (status not PROPOSED/VIEWED)',
            ]);
        }

        $send = $mail->sendInterviewEmail(
            ['email' => $row->email, 'name' => trim(($row->prenom ?? '') . ' ' . ($row->nom ?? ''))],
            ['prenom' => $row->prenom, 'nom' => $row->nom],
            ['id_demande' => $id_demande]
        );

        return response()->json([
            'ok' => true,
            'updated_rows' => $updated,
            'id_demande' => $id_demande,
            'id_file' => $id_file,
            'new_status' => 'INTERVIEW',
            'email' => $send,
        ]);
    }
}
