<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->bigIncrements('id_contract');

            $table->unsignedBigInteger('id_demande')->nullable();
            $table->unsignedBigInteger('id_file');

            $table->text('pdf_path');        // storage path
            $table->string('sha256', 64)->nullable(); // optional cache key
            $table->timestamps();

            $table->foreign('id_demande')->references('id_demande')->on('demandes')->onDelete('set null');
            $table->foreign('id_file')->references('id_file')->on('cv_files')->onDelete('cascade');

            $table->index(['id_file', 'id_demande']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
