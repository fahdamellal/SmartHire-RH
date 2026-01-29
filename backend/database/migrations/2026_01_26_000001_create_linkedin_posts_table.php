<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('linkedin_posts', function (Blueprint $table) {
            $table->bigIncrements('id_post');
            $table->unsignedBigInteger('id_demande')->unique();

            $table->text('company_name')->nullable();
            $table->text('job_title')->nullable();
            $table->longText('post_text');
            $table->jsonb('meta_json')->nullable();

            $table->timestamps();

            $table->foreign('id_demande')
                ->references('id_demande')
                ->on('demandes')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_posts');
    }
};
