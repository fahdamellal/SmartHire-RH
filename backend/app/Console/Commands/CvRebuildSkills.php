<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Embeddings\Services\SkillExtractor;

class CvRebuildSkills extends Command
{
    protected $signature = 'cv:rebuild-skills {--only-missing : Rebuild only rows where skills is null or empty}';
    protected $description = 'Rebuild skills + skills_flat from cv_text for already ingested CVs';

    public function handle(SkillExtractor $skillExtractor): int
    {
        $onlyMissing = (bool) $this->option('only-missing');

        $q = DB::table('cv_files')
            ->select('id_file', 'cv_text', 'skills');

        if ($onlyMissing) {
            $q->where(function($w){
                $w->whereNull('skills')
                  ->orWhereRaw("skills = '[]'::jsonb")
                  ->orWhereRaw("skills = '{}'::jsonb");
            });
        }

        $rows = $q->orderBy('id_file')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to rebuild.');
            return self::SUCCESS;
        }

        $this->info("Rebuilding skills for {$rows->count()} CV(s)...");

        $updated = 0;
        foreach ($rows as $r) {
            $text = (string)($r->cv_text ?? '');
            if (trim($text) === '') continue;

            $skillsMap = $skillExtractor->extract($text);
            $skillsFlat = $skillExtractor->toFlat($skillsMap);

            DB::table('cv_files')
                ->where('id_file', $r->id_file)
                ->update([
                    'skills' => json_encode($skillsMap),
                    'skills_flat' => $skillsFlat,
                ]);

            $updated++;
            if ($updated % 10 === 0) $this->info("Updated {$updated}...");
        }

        $this->info("Done. Updated {$updated} CV(s).");
        return self::SUCCESS;
    }
}


// php artisan cv:rebuild-skills
