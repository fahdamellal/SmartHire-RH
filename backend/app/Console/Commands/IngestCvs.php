<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Embeddings\Jobs\ProcessCvFileJob;

class IngestCvs extends Command
{
    protected $signature = 'cvs:ingest';
    protected $description = 'Scan CV_SOURCE_DIR and dispatch embedding jobs';

    public function handle(): int
    {
        $dir = env('CV_SOURCE_DIR');

        if (!$dir || !is_dir($dir)) {
            $this->error("CV_SOURCE_DIR not found: " . $dir);
            return 1;
        }

        $files = glob($dir . '/*.pdf');

        if (!$files) {
            $this->warn("No PDF files found in " . $dir);
            return 0;
        }

        foreach ($files as $path) {
            ProcessCvFileJob::dispatch($path);
            $this->info("Queued: " . basename($path));
        }

        $this->info("All CVs queued.");
        return 0;
    }
}
