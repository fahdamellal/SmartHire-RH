<?php

namespace App\Modules\Embeddings\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Embeddings\Services\PathScanner;
use App\Modules\Embeddings\Jobs\ProcessCvFileJob;

class CvsScanCommand extends Command
{
    protected $signature = 'cvs:scan';
    protected $description = 'Scan le dossier de CV et lance le traitement des nouveaux fichiers';

    public function handle(PathScanner $scanner): int
    {
        $dir = env('CV_SOURCE_DIR') ?: storage_path('app/cvs');

        if (!is_dir($dir)) {
            $this->error("Dossier introuvable: $dir");
            return self::FAILURE;
        }

        $files = $scanner->listCvFiles($dir);
        $this->info("Trouvé " . count($files) . " fichier(s) dans: $dir");

        foreach ($files as $path) {
            ProcessCvFileJob::dispatch($path);
        }

        $this->info("Jobs dispatchés.");
        return self::SUCCESS;
    }
}
