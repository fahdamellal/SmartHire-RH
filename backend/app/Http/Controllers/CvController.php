<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class CvController extends Controller
{
    public function show(int $id_file)
    {
        $row = DB::selectOne(
            "SELECT file_path, nom, prenom FROM cv_files WHERE id_file = ? LIMIT 1",
            [$id_file]
        );

        if (!$row) {
            return response()->json(['message' => 'CV introuvable'], 404);
        }

        $path = $row->file_path;

        if (!is_string($path) || !file_exists($path)) {
            return response()->json(['message' => 'Fichier PDF introuvable sur disque'], 404);
        }

        // Affiche le PDF dans le navigateur
        return Response::file($path, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
