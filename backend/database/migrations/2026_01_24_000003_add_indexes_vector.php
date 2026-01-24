<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ivfflat marche bien si tu as déjà pas mal de chunks
        // IMPORTANT: il faut faire ANALYZE après gros inserts
        DB::statement("CREATE INDEX IF NOT EXISTS cv_chunks_embedding_ivfflat ON cv_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)");
        DB::statement("CREATE INDEX IF NOT EXISTS cv_chunks_id_file_idx ON cv_chunks (id_file)");
    }

    public function down(): void {}
};
