<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    public function generateAndStore(array $candidate, ?array $demande = null): array
    {
        $data = [
            'candidate' => $candidate,
            'demande' => $demande,
            'today' => now()->format('Y-m-d'),
        ];

        $pdf = Pdf::loadView('pdf.contract', $data)->setPaper('a4');

        $dir = 'contracts';
        $name = 'contract_' . $candidate['id_file'] . '_' . ($demande['id_demande'] ?? '0') . '_' . time() . '.pdf';
        $path = $dir . '/' . $name;

        Storage::disk('local')->put($path, $pdf->output());

        return ['pdf_path' => $path];
    }
}
