<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cv_files', function (Blueprint $table) {
            if (!Schema::hasColumn('cv_files', 'skills_flat')) {
                $table->text('skills_flat')->nullable();
            }
        });

        // Index GIN sur jsonb skills (utile si tu veux requêter jsonb)
        DB::statement("CREATE INDEX IF NOT EXISTS ix_cv_files_skills_gin ON cv_files USING gin (skills)");

        // Index trigram sur skills_flat pour filtrage rapide (nécessite pg_trgm)
        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm");
        DB::statement("CREATE INDEX IF NOT EXISTS ix_cv_files_skills_flat_trgm ON cv_files USING gin (skills_flat gin_trgm_ops)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS ix_cv_files_skills_gin");
        DB::statement("DROP INDEX IF EXISTS ix_cv_files_skills_flat_trgm");

        Schema::table('cv_files', function (Blueprint $table) {
            if (Schema::hasColumn('cv_files', 'skills_flat')) {
                $table->dropColumn('skills_flat');
            }
        });
    }
};
