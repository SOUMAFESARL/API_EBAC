<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ccc; padding: 7px; text-align: left; }
th { background: #edf2f7; } tr { page-break-inside: avoid; }
</style></head>
<body>
<h1>Liste des admis — rentrée {{ $rentree }}</h1>
<p>{{ $admis->count() }} admis</p>
<table><thead><tr><th>N°</th><th>Nom et prénoms</th><th>Région</th><th>Paroisse</th><th>Situation matrimoniale</th></tr></thead>
<tbody>@foreach ($admis as $admission)
<tr><td>{{ $loop->iteration }}</td><td>{{ $admission->nom_prenoms }}</td><td>{{ $admission->region ?? '—' }}</td><td>{{ $admission->paroisse ?? '—' }}</td><td>{{ $admission->situation_matrimoniale ?? '—' }}</td></tr>
@endforeach</tbody></table>
</body></html>
