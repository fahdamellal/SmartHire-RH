<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Supprimer les anciennes colonnes embedding (avec leurs index)
        DB::statement('DROP INDEX IF EXISTS cv_chunks_embedding_ivfflat CASCADE');
        DB::statement('DROP INDEX IF EXISTS ix_cv_chunks_embedding_hnsw CASCADE');
        DB::statement('ALTER TABLE cv_chunks DROP COLUMN IF EXISTS embedding CASCADE');
        
        DB::statement('ALTER TABLE demandes DROP COLUMN IF EXISTS embedding CASCADE');
        
        // 2. Recréer avec vector(768)
        DB::statement('ALTER TABLE cv_chunks ADD COLUMN embedding vector(768)');
        DB::statement('ALTER TABLE demandes ADD COLUMN embedding vector(768)');
        
        // 3. Recréer les index optimisés pour 768 dimensions
        // HNSW (meilleur pour recherche rapide)
        DB::statement('
            CREATE INDEX ix_cv_chunks_embedding_hnsw 
            ON cv_chunks 
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
        
        // IVFFlat (backup, plus rapide à construire)
        DB::statement('
            CREATE INDEX cv_chunks_embedding_ivfflat 
            ON cv_chunks 
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
        
        echo "✅ Migration 1536 → 768 dimensions terminée\n";
    }
    
    public function down()
    {
        // Rollback (si besoin)
        DB::statement('DROP INDEX IF EXISTS cv_chunks_embedding_ivfflat CASCADE');
        DB::statement('DROP INDEX IF EXISTS ix_cv_chunks_embedding_hnsw CASCADE');
        DB::statement('ALTER TABLE cv_chunks DROP COLUMN IF EXISTS embedding CASCADE');
        DB::statement('ALTER TABLE demandes DROP COLUMN IF EXISTS embedding CASCADE');
        
        DB::statement('ALTER TABLE cv_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('ALTER TABLE demandes ADD COLUMN embedding vector(1536)');
        
        DB::statement('
            CREATE INDEX ix_cv_chunks_embedding_hnsw 
            ON cv_chunks 
            USING hnsw (embedding vector_cosine_ops)
        ');
        
        DB::statement('
            CREATE INDEX cv_chunks_embedding_ivfflat 
            ON cv_chunks 
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
    }
};