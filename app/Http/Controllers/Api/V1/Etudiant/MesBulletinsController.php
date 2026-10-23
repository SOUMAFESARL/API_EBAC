<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Services\BulletinQrCodeService;
use App\Services\MentionBulletinService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MesBulletinsController extends Controller
{
    private function etudiant(Request $request): Etudiant
    {
        return Etudiant::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function bulletins(Etudiant $etudiant)
    {
        return Bulletin::query()->where(fn ($q) => $q->where('statut', 'Généré')
            ->orWhere(fn ($publie) => $publie->where('statut', 'Publié')->whereNotNull('date_publication')))
            ->whereHas('inscription', fn ($q) => $q->where('id_etudiant', $etudiant->id))
            ->with(['inscription.anneeAcademique', 'inscription.promotion.niveau']);
    }

    private function presenter(Bulletin $bulletin): array
    {
        $inscription = $bulletin->inscription;
        $annee = $inscription->anneeAcademique;

        return [
            ...$bulletin->only(['id', 'periode', 'mention', 'rang', 'decision', 'statut', 'date_publication']),
            'mention' => MentionBulletinService::afficher($bulletin->mention, $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null),
            'moyenne_generale' => $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null,
            'telechargement_pdf_url' => route('api.v1.etudiant.bulletins.telecharger', ['id' => $bulletin->id]),
            'annee_academique' => $annee ? [...$annee->only(['id', 'libelle', 'date_debut', 'date_fin']),
                'statut' => $annee->date_fin->lt(today()) ? 'cloturee' : 'en_cours'] : null,
            'promotion' => $inscription->promotion?->only(['id', 'code', 'num_promotion']),
            'niveau' => $inscription->promotion?->niveau?->only(['id', 'code', 'libelle', 'rang']),
        ];
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $etudiant = $this->etudiant($request);
        $items = $this->bulletins($etudiant)
            ->when($data['id_annee_academique'] ?? null, fn ($q, $id) => $q->whereHas('inscription', fn ($i) => $i->where('id_annee_academique', $id)))
            ->orderByDesc(Inscription::select('annees_academiques.date_debut')
                ->join('annees_academiques', 'annees_academiques.id', '=', 'inscriptions.id_annee_academique')
                ->whereColumn('inscriptions.id', 'bulletins.id_inscription')->limit(1))
            ->orderByDesc('date_publication')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return response()->json([
            'etudiant' => $etudiant->only(['id', 'matricule', 'nom', 'prenoms', 'photo_identite_url']),
            'bulletins' => $items->getCollection()->map(fn ($b) => $this->presenter($b)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total(), 'from' => $items->firstItem(), 'to' => $items->lastItem()],
        ]);
    }

    public function show(Request $request, int $id)
    {
        return response()->json($this->detail($request, $id));
    }

    public function telecharger(Request $request, int $id)
    {
        $donnees = $this->detail($request, $id);
        $etudiant = $this->etudiant($request);
        $donnees['etudiant'] = $this->presenterEtudiant($etudiant, 'd/m/Y');
        $donnees['photo_identite'] = null;
        // Embed the stored image so PDF generation does not depend on a signed HTTP URL.
        if ($etudiant->photo_identite && ! str_contains($etudiant->photo_identite, '..')
            && Storage::disk('public')->exists($etudiant->photo_identite)) {
            $photo = Storage::disk('public')->get($etudiant->photo_identite);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($photo);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                $donnees['photo_identite'] = 'data:'.$mime.';base64,'.base64_encode($photo);
            }
        }

        return Pdf::loadView('pdf.bulletin-etudiant', $donnees)
            ->setPaper('a4')->download('bulletin-'.$id.'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    private function presenterEtudiant(Etudiant $etudiant, string $formatDate = 'Y-m-d'): array
    {
        $etudiant->loadMissing(['civilite', 'eglise', 'dossier']);

        return [
            ...$etudiant->only(['id', 'matricule', 'nom', 'prenoms', 'lieu_naissance', 'nationalite',
                'email', 'telephone', 'adresse', 'statut_professionnel', 'situation_matrimonial',
                'nombre_enfant', 'statut', 'civilite_id', 'eglise_id', 'photo_identite_url']),
            'date_naissance' => $etudiant->date_naissance?->format($formatDate),
            'date_inscription' => $etudiant->date_inscription?->format($formatDate),
            'civilite' => $etudiant->civilite?->name,
            'eglise' => $etudiant->eglise?->only(['id', 'code', 'nom', 'denomination', 'region', 'district', 'ville_commune']),
            'dossier' => $etudiant->dossier ? [
                ...$etudiant->dossier->only(['id', 'numero_dossier', 'statut']),
                'date_ouverture' => $etudiant->dossier->date_ouverture?->format($formatDate),
            ] : null,
        ];
    }

    private function detail(Request $request, int $id): array
    {
        $etudiant = $this->etudiant($request);
        $bulletin = $this->bulletins($etudiant)->with('lignes.matiere')->findOrFail($id);
        $lignes = $bulletin->lignes->map(function ($ligne) {
            $note = $ligne->note !== null ? (float) $ligne->note : null;

            return [
                'id' => $ligne->id,
                'matiere' => $ligne->matiere?->only(['id', 'code', 'libelle', 'type']),
                'coefficient' => (float) $ligne->coefficient, 'note' => $note,
                'mention' => MentionBulletinService::calculer($note),
                'moyenne_ponderee' => $ligne->moyenne_ponderee !== null ? (float) $ligne->moyenne_ponderee : null,
                'appreciation' => $ligne->appreciation,
                'statut' => $note === null ? 'a_completer' : ($ligne->matiere && $note >= (float) $ligne->matiere->note_validation ? 'validee' : 'non_validee'),
            ];
        });
        $rattrapages = CoursAFaire::where('id_etudiant', $etudiant->id)->where('statut', 'a_faire')
            ->whereHas('seance.moduleCalendrier.calendrier', fn ($q) => $q->where('id_annee_academique', $bulletin->inscription->id_annee_academique))
            ->with(['matiere', 'cours', 'seance'])->orderBy('id')->get();

        return [
            'etudiant' => $this->presenterEtudiant($etudiant),
            'bulletin' => [...$this->presenter($bulletin), 'matieres' => $lignes,
                'qr_code' => app(BulletinQrCodeService::class)->presenter($bulletin),
                'recapitulatif_uv' => ['validees' => $lignes->where('statut', 'validee')->count(),
                    'non_validees' => $lignes->where('statut', 'non_validee')->count(), 'a_completer' => $lignes->where('statut', 'a_completer')->count()],
                'cours_a_faire' => $rattrapages->map(fn ($r) => [
                    ...$r->only(['id', 'statut', 'motif']),
                    'matiere' => $r->matiere?->only(['id', 'code', 'libelle']),
                    'cours' => $r->cours?->only(['id', 'code', 'libelle']),
                    'date_seance_manquee' => $r->seance?->date_prevue,
                ]),
            ],
        ];
    }
}
