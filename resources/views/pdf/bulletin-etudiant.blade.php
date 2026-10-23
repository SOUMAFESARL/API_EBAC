<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de notes</title>
    <style>
        @page { margin: 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #173855; }
        h1 { font-size: 17px; margin-bottom: 5px; }
        h2 { font-size: 12px; margin-top: 24px; }
        .subtitle { color: #526778; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #dce4eb; text-align: left; }
        th { background: #edf2f7; font-size: 9px; }
        .number { text-align: right; white-space: nowrap; }
        .group td { background: #f4f7fa; font-weight: bold; }
        .summary td { background: #f4f7fa; width: 33%; vertical-align: top; }
        .summary strong { display: block; margin-top: 6px; font-size: 13px; }
        .notice { padding: 10px; background: #fff4df; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .footer { margin-top: 24px; color: #526778; font-size: 9px; }
        .photo { width: 65px; height: 80px; object-fit: contain; }
        .personal td { width: 50%; vertical-align: top; overflow-wrap: break-word; }
    </style>
</head>
<body>
    <h1>École Biblique de l’Alliance Chrétienne</h1>
    <div class="subtitle">Bulletin de notes · {{ $bulletin['periode'] }} · {{ $bulletin['annee_academique']['libelle'] ?? '—' }}</div>
    @if ($bulletin['statut'] === 'Généré')
        <p class="notice">Bulletin généré après validation — non publié. Les résultats peuvent évoluer lors des prochaines validations.</p>
    @endif
    <table>
        <tr>
            <td style="width: 75px;">@if ($photo_identite)<img class="photo" src="{{ $photo_identite }}" alt="Photo d’identité">@else Photo non renseignée @endif</td>
            <td><b>Étudiant</b><br>{{ $etudiant['nom'] }} {{ $etudiant['prenoms'] }}</td>
            <td><b>Matricule</b><br>{{ $etudiant['matricule'] }}</td>
            <td><b>Promotion</b><br>{{ $bulletin['promotion']['num_promotion'] ?? '—' }}</td>
            <td><b>Niveau</b><br>{{ $bulletin['niveau']['libelle'] ?? '—' }}</td>
        </tr>
    </table>
    <h2>Informations personnelles</h2>
    <table class="personal">
        <tr><td><b>Civilité :</b> {{ $etudiant['civilite'] ?? '—' }}</td><td><b>Date de naissance :</b> {{ $etudiant['date_naissance'] ?? '—' }}</td></tr>
        <tr><td><b>Lieu de naissance :</b> {{ $etudiant['lieu_naissance'] ?? '—' }}</td><td><b>Nationalité :</b> {{ $etudiant['nationalite'] ?? '—' }}</td></tr>
        <tr><td><b>E-mail :</b> {{ $etudiant['email'] ?? '—' }}</td><td><b>Téléphone :</b> {{ $etudiant['telephone'] ?? '—' }}</td></tr>
        <tr><td colspan="2"><b>Adresse :</b> {{ $etudiant['adresse'] ?? '—' }}</td></tr>
        <tr><td><b>Statut professionnel :</b> {{ $etudiant['statut_professionnel'] ?? '—' }}</td><td><b>Situation matrimoniale :</b> {{ $etudiant['situation_matrimonial'] ?? '—' }}</td></tr>
        <tr><td><b>Nombre d’enfants :</b> {{ $etudiant['nombre_enfant'] ?? '—' }}</td><td><b>Statut étudiant :</b> {{ $etudiant['statut'] ?? '—' }}</td></tr>
        <tr><td><b>Date d’inscription :</b> {{ $etudiant['date_inscription'] ?? '—' }}</td><td><b>Numéro de dossier :</b> {{ $etudiant['dossier']['numero_dossier'] ?? '—' }}</td></tr>
        <tr><td><b>Statut du dossier :</b> {{ $etudiant['dossier']['statut'] ?? '—' }}</td><td><b>Ouverture du dossier :</b> {{ $etudiant['dossier']['date_ouverture'] ?? '—' }}</td></tr>
    </table>
    <h2>Église de rattachement</h2>
    <table class="personal">
        <tr><td><b>Église :</b> {{ $etudiant['eglise']['nom'] ?? '—' }}</td><td><b>Dénomination :</b> {{ $etudiant['eglise']['denomination'] ?? '—' }}</td></tr>
        <tr><td><b>Région :</b> {{ $etudiant['eglise']['region'] ?? '—' }}</td><td><b>District :</b> {{ $etudiant['eglise']['district'] ?? '—' }}</td></tr>
        <tr><td colspan="2"><b>Ville / commune :</b> {{ $etudiant['eglise']['ville_commune'] ?? '—' }}</td></tr>
    </table>
    <h2>Résultats académiques</h2>
    <table>
        <thead><tr><th>Code</th><th>Matière</th><th class="number">Coeff.</th><th class="number">Note /20</th><th>Mention</th><th>Statut</th><th>Appréciation</th></tr></thead>
        <tbody>
        @forelse ($bulletin['matieres']->groupBy(fn ($ligne) => $ligne['matiere']['type'] ?? 'Matières') as $type => $lignes)
            <tr class="group"><td colspan="7">{{ $type }}</td></tr>
            @foreach ($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['matiere']['code'] ?? '—' }}</td>
                    <td>{{ $ligne['matiere']['libelle'] ?? 'Matière indisponible' }}</td>
                    <td class="number">{{ number_format($ligne['coefficient'], 2, ',', ' ') }}</td>
                    <td class="number">{{ $ligne['note'] !== null ? number_format($ligne['note'], 2, ',', ' ') : '—' }}</td>
                    <td>{{ $ligne['mention'] ?? '—' }}</td>
                    <td>{{ ['validee' => 'Validée', 'non_validee' => 'Non validée', 'a_completer' => 'À compléter'][$ligne['statut']] }}</td>
                    <td>{{ $ligne['appreciation'] ?? '—' }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="7">Aucune note enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table>
    <table class="summary">
        <tr>
            <td>Moyenne générale<strong>{{ $bulletin['moyenne_generale'] !== null ? number_format($bulletin['moyenne_generale'], 2, ',', ' ').'/20' : '—' }}</strong></td>
            <td>Rang dans la promotion<strong>{{ $bulletin['rang'] ?? '—' }}</strong></td>
            <td>Mention générale<strong>{{ $bulletin['mention'] ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td>UV validées<strong>{{ $bulletin['recapitulatif_uv']['validees'] }}</strong></td>
            <td>UV non validées<strong>{{ $bulletin['recapitulatif_uv']['non_validees'] }}</strong></td>
            <td>UV à compléter<strong>{{ $bulletin['recapitulatif_uv']['a_completer'] }}</strong></td>
        </tr>
    </table>
    @if ($bulletin['cours_a_faire']->isNotEmpty())
        <h2>Cours à faire</h2>
        <table>
            <thead><tr><th>Matière</th><th>Cours</th><th>Motif</th></tr></thead>
            <tbody>
            @foreach ($bulletin['cours_a_faire'] as $cours)
                <tr><td>{{ $cours['matiere']['libelle'] ?? '—' }}</td><td>{{ $cours['cours']['libelle'] ?? '—' }}</td><td>{{ $cours['motif'] ?? '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if ($bulletin['decision'])
        <p><b>Décision :</b> {{ $bulletin['decision'] }}</p>
    @endif
    <p class="footer">Bulletin n° {{ $bulletin['id'] }} · Statut : {{ $bulletin['statut'] }}</p>
    <div style="page-break-inside: avoid; text-align: center;">
        <img src="{{ $bulletin['qr_code']['image_data_url'] }}" alt="QR de vérification du bulletin" style="width: 130px; height: 130px;">
        <p><a href="{{ $bulletin['qr_code']['verification_url'] }}">Vérifier ce bulletin</a></p>
        @if ($bulletin['statut'] === 'Généré')<p>Bulletin non publié — résultats susceptibles d’évoluer.</p>@endif
    </div>
</body>
</html>
