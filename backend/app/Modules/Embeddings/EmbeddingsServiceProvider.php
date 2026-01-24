<?php

namespace App\Modules\Embeddings;

use Illuminate\Support\ServiceProvider;

class EmbeddingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Embeddings\Console\Commands\CvsScanCommand::class,
                \App\Modules\Embeddings\Console\Commands\CvsRebuildMetaCommand::class,
            ]);
        }
    }
}
