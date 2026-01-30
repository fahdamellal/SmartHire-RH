<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }
    .h { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
    .sub { color:#444; margin-bottom: 14px; }
    .box { border:1px solid #ddd; padding:10px; border-radius: 8px; margin: 10px 0; }
    .row { display: flex; justify-content: space-between; }
    .muted { color:#666; }
    .t { font-weight:700; margin-bottom:6px; }
    ul { margin: 6px 0 0 16px; }
    .sig { margin-top: 28px; }
    .sig div { margin-top: 24px; }
  </style>
</head>
<body>

  <div class="h">Contrat de collaboration / Proposition</div>
  <div class="sub muted">Généré par SmartHire — {{ $today }}</div>

  <div class="box">
    <div class="t">Candidat</div>
    <div><b>{{ $candidate['prenom'] }} {{ $candidate['nom'] }}</b></div>
    <div class="muted">Email: {{ $candidate['email'] ?? '—' }} • Tél: {{ $candidate['phone'] ?? '—' }}</div>
  </div>

  <div class="box">
    <div class="t">Entreprise / Demande</div>
    @if($demande)
      <div><b>{{ $demande['entreprise'] ?? '—' }}</b> (Demande #{{ $demande['id_demande'] }})</div>
      <div class="muted" style="margin-top:6px;">
        {{ \Illuminate\Support\Str::limit($demande['texte'] ?? '', 260) }}
      </div>
    @else
      <div class="muted">Aucune demande associée (contrat généré manuellement).</div>
    @endif
  </div>

  <div class="box">
    <div class="t">Objet</div>
    <div>
      Cette proposition formalise l’intérêt de l’entreprise pour le profil du candidat et initie le processus
      de contractualisation (étapes RH, validation, documents requis).
    </div>
  </div>

  <div class="box">
    <div class="t">Étapes suivantes</div>
    <ul>
      <li>Validation RH et échange de disponibilité</li>
      <li>Entretien(s) technique(s) si nécessaire</li>
      <li>Confirmation finale et signature</li>
    </ul>
  </div>

  <div class="sig">
    <div class="row">
      <div>
        <b>Signature entreprise</b><br>
        <span class="muted">Nom + Cachet</span>
      </div>
      <div>
        <b>Signature candidat</b><br>
        <span class="muted">Nom + Date</span>
      </div>
    </div>
  </div>

</body>
</html>
