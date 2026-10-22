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
            <td><b>Étudiant</b><br>{{ $etudiant['nom'] }} {{ $etudiant['prenoms'] }}</td>
            <td><b>Matricule</b><br>{{ $etudiant['matricule'] }}</td>
            <td><b>Promotion</b><br>{{ $bulletin['promotion']['num_promotion'] ?? '—' }}</td>
            <td><b>Niveau</b><br>{{ $bulletin['niveau']['libelle'] ?? '—' }}</td>
        </tr>
    </table>
    <table>
        <thead><tr><th>Code</th><th>Matière</th><th class="number">Coeff.</th><th class="number">Note /20</th><th>Statut</th><th>Appréciation</th></tr></thead>
        <tbody>
        @forelse ($bulletin['matieres']->groupBy(fn ($ligne) => $ligne['matiere']['type'] ?? 'Matières') as $type => $lignes)
            <tr class="group"><td colspan="6">{{ $type }}</td></tr>
            @foreach ($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['matiere']['code'] ?? '—' }}</td>
                    <td>{{ $ligne['matiere']['libelle'] ?? 'Matière indisponible' }}</td>
                    <td class="number">{{ number_format($ligne['coefficient'], 2, ',', ' ') }}</td>
                    <td class="number">{{ $ligne['note'] !== null ? number_format($ligne['note'], 2, ',', ' ') : '—' }}</td>
                    <td>{{ ['validee' => 'Validée', 'non_validee' => 'Non validée', 'a_completer' => 'À compléter'][$ligne['statut']] }}</td>
                    <td>{{ $ligne['appreciation'] ?? '—' }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="6">Aucune note enregistrée.</td></tr>
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
</body>
</html>
