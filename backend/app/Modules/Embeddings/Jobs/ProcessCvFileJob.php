<?php

namespace App\Modules\Embeddings\Jobs;

use App\Modules\Embeddings\Services\FileHasher;
use App\Modules\Embeddings\Services\CvTextExtractor;
use App\Modules\Embeddings\Services\CvSectionChunker;
use App\Modules\Embeddings\Services\GeminiEmbeddings;
use App\Modules\Embeddings\Services\CvMetaExtractor;
use App\Modules\Embeddings\Services\SkillExtractor;
use App\Modules\Embeddings\Support\TextNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCvFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $path) {}

    public function handle(
        FileHasher $hasher,
        CvTextExtractor $extractor,
        TextNormalizer $normalizer,
        CvSectionChunker $chunker,
        GeminiEmbeddings $emb,
        CvMetaExtractor $meta,
        SkillExtractor $skillExtractor
    ): void {
        if (!is_file($this->path)) return;

        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') return;

        $sha = $hasher->sha256($this->path);

        // Anti-doublon par sha
        $exists = DB::selectOne("SELECT 1 FROM cv_files WHERE sha256 = ? LIMIT 1", [$sha]);
        if ($exists) {
            Log::info('[Embeddings] Skip (already ingested)', ['path' => $this->path]);
            return;
        }

        // Extract + normalize
        $raw = $extractor->extractPdf($this->path);
        $text = $normalizer->normalize($raw);

        // Ignore scanned / empty
        if (mb_strlen($text) < 120) {
            Log::warning('[Embeddings] Text too short (scanned PDF?)', ['path' => $this->path]);
            return;
        }

        // Meta
        [$prenomGuess, $nomGuess] = $meta->extractNameFromText($text);
        $email = $meta->extractEmail($text);
        $phone = $meta->extractPhone($text);

        // Skills robustes (deterministic)
        $skillsMap = $skillExtractor->extract($text);
        $skillsFlat = method_exists($skillExtractor, 'toFlat')
            ? $skillExtractor->toFlat($skillsMap)
            : implode(' ', array_keys($skillsMap)); // fallback si toFlat() n'existe pas

        $dim = (int) env('GEMINI_EMBED_DIM', 1536);
        $path = $this->path;

        try {
            DB::transaction(function () use (
                $sha,
                $text,
                $skillsMap,
                $skillsFlat,
                $chunker,
                $emb,
                $dim,
                $nomGuess,
                $prenomGuess,
                $email,
                $phone,
                $path
            ) {
                // 1) Insert cv_files
                $row = DB::selectOne(
                    "INSERT INTO cv_files (nom, prenom, email, phone, file_path, sha256, cv_text, skills, skills_flat)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?)
                     RETURNING id_file",
                    [
                        $nomGuess,
                        $prenomGuess,
                        $email,
                        $phone,
                        $path,
                        $sha,
                        $text,
                        json_encode($skillsMap, JSON_UNESCAPED_UNICODE),
                        $skillsFlat
                    ]
                );

                $idFile = (int) $row->id_file;

                // 2) Chunk + embeddings
                $sections = $chunker->splitSections($text);

                $chunkIndex = 0;
                foreach ($sections as $sectionName => $sectionText) {
                    $chunks = $chunker->chunk($sectionText, 1200, 200);

                    foreach ($chunks as $ch) {
                        // embedding (peut planter => catch global)
                        $vec = $emb->embed($ch, $dim);
                        if (!is_array($vec) || count($vec) !== $dim) {
                            throw new \RuntimeException("Embedding dimension invalid: got " . (is_array($vec) ? count($vec) : 'null'));
                        }

                        $vecLiteral = '[' . implode(',', array_map(fn($x) => (float)$x, $vec)) . ']';

                        DB::insert(
                            "INSERT INTO cv_chunks (id_file, chunk_index, section, chunk_text, embedding)
                             VALUES (?, ?, ?, ?, ?::vector)",
                            [$idFile, $chunkIndex, $sectionName, $ch, $vecLiteral]
                        );

                        $chunkIndex++;
                    }
                }
            });

            Log::info('[Embeddings] Ingested OK', [
                'path' => $this->path,
                'skills_count' => is_array($skillsMap) ? count($skillsMap) : 0
            ]);

        } catch (Throwable $e) {
            Log::error('[Embeddings] Ingest failed', [
                'path' => $this->path,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
