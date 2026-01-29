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
            $v = trim(preg_replace('/\s+/', ' ', $v));
            if ($v === '') unset($sections[$k]);
            else $sections[$k] = $v;
        }

        return $sections;
    }

    public function chunk(string $text, int $size = 1200, int $overlap = 200): array
    {
        $t = trim(preg_replace('/\s+/', ' ', $text));
        if ($t === '') return [];

        $chunks = [];
        $start = 0;
        $n = mb_strlen($t);

        while ($start < $n) {
            $end = min($start + $size, $n);
            $chunks[] = mb_substr($t, $start, $end - $start);

            if ($end >= $n) break;
            $start = max(0, $end - $overlap);
        }

        return $chunks;
    }
}
