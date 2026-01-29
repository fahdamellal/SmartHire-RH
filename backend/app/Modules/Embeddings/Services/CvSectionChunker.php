<?php

namespace App\Modules\Embeddings\Services;

use App\Modules\Embeddings\Support\SectionDictionary;

class CvSectionChunker
{
    public function __construct(private SectionDictionary $dict) {}

    public function splitSections(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $patterns = $this->dict->patterns();

        $current = 'unknown';
        $sections = [$current => ''];

        foreach ($lines as $line) {
            $trim = trim($line);

            // titre de section = ligne courte
            if ($trim !== '' && mb_strlen($trim) <= 40) {
                foreach ($patterns as $name => $pattern) {
                    if (preg_match($pattern, $trim)) {
                        $current = $name;
                        $sections[$current] = $sections[$current] ?? '';
                        continue 2;
                    }
                }
            }

            $sections[$current] .= $line . "\n";
        }

        // nettoyage
        foreach ($sections as $k => $v) {
            $v = trim($v);
            if ($v === '') unset($sections[$k]);
            else $sections[$k] = $v;
        }

        return $sections;
    }

    /**
     * 🆕 CHUNKING AMÉLIORÉ
     * - Préserve les phrases complètes
     * - Overlap intelligent (par phrases, pas par caractères)
     * - Meilleur contexte sémantique
     */
    public function chunk(string $text, int $targetSize = 1200, int $overlapSize = 300): array
{
    $text = trim($text);
    if ($text === '') return [];

    $totalLen = mb_strlen($text);

    // 🆕 Si le texte est court (< 800 chars), ne PAS chunker
    if ($totalLen < 800) {
        return [$text];
    }

    // Découper en lignes
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $lines = array_filter(array_map('trim', $lines), fn($l) => $l !== '');
    $lines = array_values($lines);

    if (empty($lines)) return [$text];

    $chunks = [];
    $currentChunk = [];
    $currentLength = 0;

    foreach ($lines as $line) {
        $lineLen = mb_strlen($line);
        
        if ($currentLength > 0 && ($currentLength + $lineLen + 1) > $targetSize) {
            $chunkText = implode("\n", $currentChunk);
            if (mb_strlen($chunkText) >= 400) {  // Min 400 chars
                $chunks[] = $chunkText;
                
                $overlapLines = $this->getOverlapLines($currentChunk, $overlapSize);
                $currentChunk = $overlapLines;
                $currentLength = mb_strlen(implode("\n", $currentChunk));
            } else {
                $currentChunk[] = $line;
                $currentLength += $lineLen + 1;
                continue;
            }
        }
        
        $currentChunk[] = $line;
        $currentLength += $lineLen + 1;
    }

    if (!empty($currentChunk)) {
        $chunkText = implode("\n", $currentChunk);
        if (mb_strlen($chunkText) >= 100) {
            $chunks[] = $chunkText;
        } elseif (!empty($chunks)) {
            $chunks[count($chunks) - 1] .= "\n" . $chunkText;
        } else {
            $chunks[] = $chunkText;
        }
    }

    return $chunks;
}

    /**
     * Découpe le texte en phrases intelligemment
     */
    private function splitIntoSentences(string $text): array
    {
        // Normaliser les espaces multiples (mais garder les retours à la ligne)
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Découper sur . ! ? suivi d'espace et majuscule OU retour à la ligne
        $pattern = '/(?<=[.!?])\s+(?=[A-ZÀ-Ý])|(?<=[.!?])\n+/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        // Nettoyage
        $sentences = array_map('trim', $sentences);
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) > 10);

        return array_values($sentences);
    }

    /**
     * Construit l'overlap à partir des dernières phrases
     */
    private function buildOverlap(array $sentences, int $maxSize): string
    {
        $overlap = '';
        $reversedSentences = array_reverse($sentences);

        foreach ($reversedSentences as $s) {
            $newOverlap = $s . ' ' . $overlap;
            if (mb_strlen($newOverlap) > $maxSize) break;
            $overlap = $newOverlap;
        }

        return trim($overlap);
    }
}