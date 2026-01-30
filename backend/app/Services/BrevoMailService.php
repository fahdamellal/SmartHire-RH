<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoMailService
{
    /**
     * Email "Invitation à un entretien"
     * - Style pro (header + carte + CTA + détails + footer)
     * - Données dynamiques (entreprise, poste, demande, texte)
     */
    public function sendInterviewEmail(array $to, array $candidate, array $demande): array
    {
        $apiKey = env('BREVO_API_KEY');

        if (!$apiKey) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => ['error' => 'BREVO_API_KEY manquant dans .env'],
            ];
        }

        $senderEmail = env('BREVO_SENDER_EMAIL');
        if (!$senderEmail) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => ['error' => 'BREVO_SENDER_EMAIL manquant dans .env'],
            ];
        }

        $senderName = env('BREVO_SENDER_NAME', 'SmartHire');
        $replyToEmail = env('BREVO_REPLY_TO_EMAIL', $senderEmail);
        $replyToName  = env('BREVO_REPLY_TO_NAME',  $senderName);

        $prenom = trim($candidate['prenom'] ?? '');
        $nom    = trim($candidate['nom'] ?? '');
        $fullName = trim(($prenom . ' ' . $nom)) ?: ($to['name'] ?? 'Candidat');

        $idDemande = $demande['id_demande'] ?? null;
        $entreprise = $demande['entreprise'] ?? 'Notre entreprise';
        $poste = $demande['poste'] ?? 'Poste à pourvoir';
        $texte = trim((string)($demande['texte'] ?? ''));
        $texteShort = $this->safe($this->truncate($texte, 180));

        // CTA (optionnel) : un lien vers ton app (ou un lien calendrier)
        // Exemple: FRONTEND_URL=https://smarthire.test
        $frontend = rtrim((string) env('FRONTEND_URL', ''), '/');
        $ctaUrl = $frontend && $idDemande
            ? $frontend . '/?demande=' . urlencode((string)$idDemande)
            : null;

        $subject = "Invitation à un entretien — {$entreprise} (Demande #{$idDemande})";

        $html = $this->buildInterviewHtml([
            'brand' => $senderName,
            'fullName' => $fullName,
            'entreprise' => $entreprise,
            'poste' => $poste,
            'id_demande' => $idDemande,
            'texte_short' => $texteShort,
            'cta_url' => $ctaUrl,
            'reply_email' => $replyToEmail,
        ]);

        $payload = [
            'sender' => [
                'email' => $senderEmail,
                'name'  => $senderName,
            ],
            'replyTo' => [
                'email' => $replyToEmail,
                'name'  => $replyToName,
            ],
            'to' => [[
                'email' => $to['email'],
                'name'  => $to['name'] ?? $fullName,
            ]],
            'subject' => $subject,
            'htmlContent' => $html,
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

    private function buildInterviewHtml(array $d): string
    {
        $brand = $this->safe($d['brand'] ?? 'SmartHire');
        $fullName = $this->safe($d['fullName'] ?? 'Candidat');
        $entreprise = $this->safe($d['entreprise'] ?? 'Notre entreprise');
        $poste = $this->safe($d['poste'] ?? 'Poste à pourvoir');
        $id = $this->safe((string)($d['id_demande'] ?? '—'));
        $texteShort = $d['texte_short'] ?? '';
        $ctaUrl = $d['cta_url'] ?? null;
        $replyEmail = $this->safe($d['reply_email'] ?? '');

        $ctaBlock = '';
        if ($ctaUrl) {
            $ctaBlock = "
              <tr>
                <td style='padding:16px 24px 0 24px;'>
                  <a href='{$this->safe($ctaUrl)}'
                     style='display:inline-block; text-decoration:none; font-weight:700;
                            background:#2563eb; color:#ffffff; padding:12px 16px; border-radius:12px;'>
                    Confirmer / Proposer un créneau
                  </a>
                  <div style='font-size:12px; color:#94a3b8; margin-top:10px; line-height:1.4;'>
                    Si le bouton ne marche pas, réponds simplement à cet email.
                  </div>
                </td>
              </tr>
            ";
        }

        $besoinBlock = $texteShort
            ? "
              <tr>
                <td style='padding:16px 24px 0 24px;'>
                  <div style='font-size:12px; color:#94a3b8; margin-bottom:8px;'>Détails du besoin</div>
                  <div style='background:rgba(2,6,23,.35); border:1px solid rgba(148,163,184,.16);
                              border-radius:12px; padding:12px; color:#e5e7eb; font-size:13px; line-height:1.55;'>
                    {$texteShort}
                  </div>
                </td>
              </tr>
            "
            : "";

        return "
<!doctype html>
<html>
  <head>
    <meta charset='utf-8'/>
    <meta name='viewport' content='width=device-width, initial-scale=1'/>
  </head>
  <body style='margin:0; padding:0; background:#0b1020;'>
    <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background:#0b1020; padding:24px 12px;'>
      <tr>
        <td align='center'>
          <table role='presentation' width='640' cellspacing='0' cellpadding='0'
                 style='width:640px; max-width:100%; border-radius:18px; overflow:hidden;
                        border:1px solid rgba(148,163,184,.16); background:#0b1220;'>

            <!-- Header -->
            <tr>
              <td style='padding:18px 24px; background:linear-gradient(135deg, rgba(59,130,246,.35), rgba(99,102,241,.18));'>
                <div style='font-family:Arial, Helvetica, sans-serif; color:#fff; font-weight:800; font-size:16px;'>
                  {$brand}
                </div>
                <div style='font-family:Arial, Helvetica, sans-serif; color:#cbd5e1; font-size:12px; margin-top:4px;'>
                  Recrutement & Matching intelligent
                </div>
              </td>
            </tr>

            <!-- Body -->
            <tr>
              <td style='padding:22px 24px 6px 24px; font-family:Arial, Helvetica, sans-serif; color:#e5e7eb;'>
                <div style='font-size:18px; font-weight:900; color:#ffffff;'>
                  Invitation à un entretien
                </div>
                <div style='margin-top:12px; font-size:14px; line-height:1.65; color:#e5e7eb;'>
                  Bonjour <b>{$fullName}</b>,<br/>
                  Nous avons consulté votre candidature et nous souhaitons échanger avec vous au sujet du poste
                  <b>{$poste}</b> chez <b>{$entreprise}</b>.
                </div>
              </td>
            </tr>

            <!-- Info pills -->
            <tr>
              <td style='padding:10px 24px; font-family:Arial, Helvetica, sans-serif;'>
                <table role='presentation' cellspacing='0' cellpadding='0'>
                  <tr>
                    <td style='padding:6px 10px; border-radius:999px; background:rgba(2,6,23,.45);
                               border:1px solid rgba(148,163,184,.16); color:#ffffff; font-size:12px;'>
                      Référence : Demande #{$id}
                    </td>
                    <td style='width:10px;'></td>
                    <td style='padding:6px 10px; border-radius:999px; background:rgba(2,6,23,.45);
                               border:1px solid rgba(148,163,184,.16); color:#cbd5e1; font-size:12px;'>
                      Réponse rapide recommandée
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            {$besoinBlock}
            {$ctaBlock}

            <!-- Footer -->
            <tr>
              <td style='padding:18px 24px 22px 24px; font-family:Arial, Helvetica, sans-serif; color:#cbd5e1; font-size:13px; line-height:1.6;'>
                <div style='border-top:1px solid rgba(148,163,184,.14); margin:16px 0;'></div>
                <div>
                  Répondez à cet email si vous avez une indisponibilité, ou pour proposer des créneaux.
                </div>
                <div style='margin-top:10px; color:#94a3b8; font-size:12px;'>
                  Contact : {$replyEmail}
                </div>
                <div style='margin-top:12px; font-weight:700; color:#ffffff;'>
                  Cordialement,<br/>{$brand}
                </div>
                <div style='margin-top:10px; color:#64748b; font-size:11px;'>
                  Message automatique — merci de ne pas partager d’informations sensibles.
                </div>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
        ";
    }

    private function safe(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function truncate(string $s, int $max): string
    {
        $s = trim($s);
        if ($s === '') return '';
        if (mb_strlen($s) <= $max) return $s;
        return mb_substr($s, 0, $max - 1) . "…";
    }
}
