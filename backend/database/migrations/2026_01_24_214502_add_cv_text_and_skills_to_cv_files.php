<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cv_files', function (Blueprint $table) {
            if (!Schema::hasColumn('cv_files', 'cv_text')) {
                $table->text('cv_text')->nullable();
            }
            if (!Schema::hasColumn('cv_files', 'skills')) {
                $table->jsonb('skills')->nullable();
            }
            if (!Schema::hasColumn('cv_files', 'skills_flat')) {
                $table->text('skills_flat')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('cv_files', function (Blueprint $table) {
            $table->dropColumn(['cv_text', 'skills', 'skills_flat']);
        });
    }
};