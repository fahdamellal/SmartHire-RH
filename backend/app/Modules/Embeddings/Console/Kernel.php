<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Les commandes Artisan custom.
     */
    protected $commands = [
        \App\Console\Commands\IngestCvs::class,
        // ajoute ici tes autres commandes si tu en as :
        // \App\Console\Commands\CvRebuildSkills::class,
    ];

    /**
     * Planification (pas obligatoire).
     */
    protected function schedule(Schedule $schedule): void
    {
        // rien pour l'instant
    }

    /**
     * Charge les commandes (routes/console.php).
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
