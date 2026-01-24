<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('cv_files', function (Blueprint $table) {
        $table->text('cv_text')->nullable();
        $table->jsonb('skills')->nullable(); // Postgres jsonb
    });
}

public function down(): void
{
    Schema::table('cv_files', function (Blueprint $table) {
        $table->dropColumn(['cv_text', 'skills']);
    });
}
};
