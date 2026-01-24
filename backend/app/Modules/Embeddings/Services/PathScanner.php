<?php

namespace App\Modules\Embeddings\Services;

use Illuminate\Support\Str;

class PathScanner
{
    /**
     * List PDF/DOCX files inside $dir (recursive).
     *
     * @return string[] absolute paths
     */
    public function listCvFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $ext = Str::lower($file->getExtension());
            if (in_array($ext, ['pdf', 'docx'], true)) {
                $out[] = $file->getPathname();
            }
        }

        sort($out);
        return $out;
    }
}
