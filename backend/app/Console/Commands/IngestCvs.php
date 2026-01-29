<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Embeddings\Jobs\ProcessCvFileJob;

class IngestCvs extends Command
{
    protected $signature = 'cvs:ingest
        {--dir= : Directory containing CV PDFs (default: CV_SOURCE_DIR env)}
        {--recursive : Scan subfolders recursively}
        {--sync : Run jobs immediately (no queue)}
        {--limit=0 : Stop after N files (0 = no limit)}';

    protected $description = 'Scan a directory and ingest CV PDFs (cv_files + cv_chunks)';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: env('CV_SOURCE_DIR');
        $dir = $dir ? rtrim($dir, "/") : null;

        if (!$dir || !is_dir($dir)) {
            $this->error("Directory not found: " . ($dir ?? 'null'));
            $this->line("Tip: set CV_SOURCE_DIR in .env or run: php artisan cvs:ingest --dir=/path/to/cvs");
            return 1;
        }

        $recursive = (bool) $this->option('recursive');
        $sync = (bool) $this->option('sync');
        $limit = (int) ($this->option('limit') ?? 0);

        $files = $recursive ? $this->scanRecursive($dir) : (glob($dir . '/*.pdf') ?: []);
        $files = array_values(array_filter($files, fn($p) => is_file($p)));

        if (!$files) {
            $this->warn("No PDF files found in: " . $dir);
            return 0;
        }

        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        $this->info("Found " . count($files) . " PDF(s). Mode: " . ($sync ? "SYNC" : "QUEUE"));

        if ($sync) {
            foreach ($files as $path) {
                $this->line("Processing (SYNC): " . basename($path));
                // ✅ Le plus robuste: Laravel injecte automatiquement toutes les dépendances du job
                ProcessCvFileJob::dispatchSync($path);
            }
            $this->info("Done (SYNC).");
            return 0;
        }

        foreach ($files as $path) {
            ProcessCvFileJob::dispatch($path);
            $this->line("Queued: " . basename($path));
        }

        $this->info("All CVs queued. Run a worker: php artisan queue:work");
        return 0;
    }

    private function scanRecursive(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;
            if (strtolower($file->getExtension()) === 'pdf') {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }
}
