<?php

namespace App\Modules\Embeddings\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Embeddings\Services\CvTextExtractor;
use App\Modules\Embeddings\Services\CvMetaExtractor;
use App\Modules\Embeddings\Support\TextNormalizer;

class CvsRebuildMetaCommand extends Command
{
    protected $signature = 'cvs:rebuild-meta {--limit=0 : Limiter le nombre de CV traités}';
    protected $description = 'Recalcule nom/prenom/email/phone pour les CV déjà présents dans cv_files';

    public function handle(CvTextExtractor $extractor, CvMetaExtractor $meta, TextNormalizer $normalizer): int
    {
        $limit = (int) $this->option('limit');

        $sql = "SELECT id_file, file_path FROM cv_files ORDER BY id_file ASC";
        if ($limit > 0) $sql .= " LIMIT " . $limit;

        $rows = DB::select($sql);

        $this->info("CV trouvés: " . count($rows));

        $updated = 0;
        foreach ($rows as $r) {
            $path = $r->file_path;

            if (!is_file($path)) {
                $this->warn("Fichier introuvable: $path");
                continue;
            }

            try {
                $raw = $extractor->extractPdf($path);
                $text = $normalizer->normalize($raw);

                [$prenomGuess, $nomGuess] = $meta->extractNameFromText($text);
                $email = $meta->extractEmail($text);
                $phone = $meta->extractPhone($text);

                DB::update(
                    "UPDATE cv_files
                    SET nom = ?, prenom = ?, email = ?, phone = ?
                    WHERE id_file = ?",
                    [$nomGuess, $prenomGuess, $email, $phone, $r->id_file]
);


                $updated++;
            } catch (\Throwable $e) {
                Log::error('[Embeddings] rebuild-meta failed', [
                    'id_file' => $r->id_file,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Erreur sur id_file={$r->id_file}: " . $e->getMessage());
            }
        }

        $this->info("Mise à jour terminée. Lignes modifiées: $updated");
        return self::SUCCESS;
    }
}
