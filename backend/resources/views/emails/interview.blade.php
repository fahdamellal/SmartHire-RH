<!doctype html>
<html>
  <body style="margin:0;padding:0;background:#0b1220;">
    <div style="max-width:640px;margin:0 auto;padding:24px;font-family:Arial, sans-serif;color:#e5e7eb;">
      
      <div style="background:rgba(17,24,39,.9);border:1px solid rgba(148,163,184,.18);border-radius:16px;padding:18px;">
        <div style="font-size:14px;opacity:.85;">{{ $senderName }}</div>
        <h2 style="margin:10px 0 0;font-size:18px;color:#fff;">Invitation à un entretien</h2>
        <div style="height:1px;background:rgba(148,163,184,.16);margin:14px 0;"></div>

        <p style="margin:0 0 10px;line-height:1.6;">
          Bonjour <b style="color:#fff;">{{ trim(($candidate['prenom'] ?? '').' '.($candidate['nom'] ?? '')) }}</b>,
        </p>

        <p style="margin:0 0 10px;line-height:1.6;">
          Nous avons consulté votre CV et souhaitons échanger avec vous lors d’un entretien.
        </p>

        @if(!empty($demande['entreprise']))
          <p style="margin:0 0 10px;line-height:1.6;">
            <b style="color:#fff;">Entreprise :</b> {{ $demande['entreprise'] }}
          </p>
        @endif

        @if(!empty($demande['id_demande']))
          <p style="margin:0 0 10px;line-height:1.6;">
            <b style="color:#fff;">Référence :</b> Demande #{{ $demande['id_demande'] }}
          </p>
        @endif

        @if(!empty($demande['criteria']))
          <div style="margin:12px 0;padding:12px;border-radius:12px;background:rgba(2,6,23,.35);border:1px solid rgba(148,163,184,.12);">
            <div style="font-size:12px;opacity:.85;margin-bottom:8px;"><b style="color:#fff;">Détails du besoin</b></div>
            <div style="font-size:12px;line-height:1.6;opacity:.9;">
              @if(!empty($demande['criteria']['role'])) <div>• Poste : {{ $demande['criteria']['role'] }}</div> @endif
              @if(!empty($demande['criteria']['city'])) <div>• Ville : {{ $demande['criteria']['city'] }}</div> @endif
              @if(!empty($demande['criteria']['contract'])) <div>• Contrat : {{ $demande['criteria']['contract'] }}</div> @endif
              @if(!empty($demande['criteria']['skills']) && is_array($demande['criteria']['skills']))
                <div>• Skills : {{ implode(', ', $demande['criteria']['skills']) }}</div>
              @endif
            </div>
          </div>
        @endif

        @if(!empty($meetingUrl))
          <a href="{{ $meetingUrl }}"
             style="display:inline-block;margin-top:10px;background:#2563eb;color:#fff;text-decoration:none;
                    padding:10px 14px;border-radius:12px;font-weight:700;">
            Proposer un créneau d’entretien
          </a>
        @endif

        <p style="margin:14px 0 0;line-height:1.6;font-size:12px;opacity:.85;">
          Répondez à cet email si vous avez une indisponibilité ou une question.
        </p>

        <div style="height:1px;background:rgba(148,163,184,.16);margin:14px 0;"></div>

        <p style="margin:0;font-size:12px;opacity:.8;">
          Contact : {{ $contactEmail }}<br/>
          Cordialement,<br/>
          <b style="color:#fff;">{{ $senderName }}</b>
        </p>
      </div>

      <div style="text-align:center;margin-top:10px;font-size:11px;opacity:.6;">
        Message automatique — merci de ne pas partager d’informations sensibles.
      </div>
    </div>
  </body>
</html>
