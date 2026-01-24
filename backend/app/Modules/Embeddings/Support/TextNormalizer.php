<?php

namespace App\Modules\Embeddings\Support;

class TextNormalizer
{
    public function normalize(string $text): string
    {
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text ?? '');
    }
}
