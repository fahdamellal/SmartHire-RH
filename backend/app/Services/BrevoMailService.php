<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoMailService
{
    public function sendInterviewEmail(array $to, array $candidate, array $demande): array
    {
        $apiKey = env('BREVO_API_KEY');

        $payload = [
            'sender' => [
                'email' => env('BREVO_SENDER_EMAIL'),
                'name'  => env('BREVO_SENDER_NAME', 'SmartHire'),
            ],
            'to' => [[
                'email' => $to['email'],
                'name'  => $to['name'] ?? null,
            ]],
            'subject' => "Invitation à un entretien — SmartHire",
            'htmlContent' => "
                <div style='font-family:Arial'>
                    <h3>Bonjour {$candidate['prenom']} {$candidate['nom']},</h3>
                    <p>Nous avons consulté votre CV et souhaitons planifier un entretien.</p>
                    <p><b>Référence demande:</b> #{$demande['id_demande']}</p>
                    <p>Cordialement,<br/>SmartHire</p>
                </div>
            ",
        ];

        $res = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        return [
            'ok' => $res->successful(),
            'status' => $res->status(),
            'body' => $res->json(),
        ];
    }
}
