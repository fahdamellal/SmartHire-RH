<?php

namespace App\Http\Controllers;

use App\Services\BrevoMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DemanderController extends Controller
{
    public function listByDemande(int $id_demande): JsonResponse
    {
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

        $rows = DB::select("
            SELECT
                d.id_file,
                d.score,
                d.status,
                f.nom,
                f.prenom,
                f.email,
                f.phone,
                f.file_path,
                f.skills
            FROM demander d
            JOIN cv_files f ON f.id_file = d.id_file
            WHERE d.id_demande = ?
            ORDER BY d.score DESC NULLS LAST
        ", [$id_demande]);

        $results = array_map(function ($r) {
            $score = $r->score !== null ? (float)$r->score : null;

            $skillsPreview = [];
            if (!empty($r->skills)) {
                $decoded = is_string($r->skills) ? json_decode($r->skills, true) : $r->skills;
                if (is_array($decoded)) {
                    $skillsPreview = array_slice($decoded, 0, 8);
                }
            }

            return [
                'id_file' => (int)$r->id_file,
                'score' => $score,
                'score_percent' => $score !== null ? round(max(0.0, min(1.0, $score)) * 100, 1) : null,
                'status' => $r->status, // PROPOSED | VIEWED | INTERESTED
                'nom' => $r->nom,
                'prenom' => $r->prenom,
                'email' => $r->email,
                'phone' => $r->phone,
                'file_path' => $r->file_path,
                'cv_url' => url('/api/cv/' . (int)$r->id_file),
                'skills_preview' => $skillsPreview,
            ];
        }, $rows);

        return response()->json([
            'ok' => true,
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

        if ($row->status === 'VIEWED' || $row->status === 'INTERESTED') {
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
            SET status = 'VIEWED', updated_at = NOW()
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
        SELECT
            d.status,
            f.nom, f.prenom, f.email,
            de.entreprise, de.texte, de.criteria_json
        FROM demander d
        JOIN cv_files f ON f.id_file = d.id_file
        JOIN demandes de ON de.id_demande = d.id_demande
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

    if ($row->status === 'INTERESTED') {
        return response()->json([
            'ok' => true,
            'updated_rows' => 0,
            'id_demande' => $id_demande,
            'id_file' => $id_file,
            'new_status' => 'INTERESTED',
            'note' => 'Already INTERESTED',
        ]);
    }

    $updated = DB::update("
        UPDATE demander
        SET status = 'INTERESTED'
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

    // poste depuis criteria_json (si موجود)
    $criteria = $row->criteria_json ? json_decode($row->criteria_json, true) : [];
    $poste = $criteria['job_title'] ?? $criteria['poste'] ?? 'Poste à pourvoir';

    $send = $mail->sendInterviewEmail(
        ['email' => $row->email, 'name' => trim(($row->prenom ?? '') . ' ' . ($row->nom ?? ''))],
        ['prenom' => $row->prenom, 'nom' => $row->nom],
        [
            'id_demande' => $id_demande,
            'entreprise' => $row->entreprise ?? 'Notre entreprise',
            'poste' => $poste,
            'texte' => $row->texte ?? '',
        ]
    );

    return response()->json([
        'ok' => true,
        'updated_rows' => $updated,
        'id_demande' => $id_demande,
        'id_file' => $id_file,
        'new_status' => 'INTERESTED',
        'email' => $send,
    ]);
}

}
