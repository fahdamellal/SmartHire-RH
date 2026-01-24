<?php

namespace App\Modules\Embeddings\Services;

use Smalot\PdfParser\Parser;

class CvTextExtractor
{
    public function extractPdf(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        return trim($pdf->getText());
    }
}
