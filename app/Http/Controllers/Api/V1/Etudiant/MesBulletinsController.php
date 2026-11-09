<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Services\DocumentBulletinService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MesBulletinsController extends Controller
{
    public function __construct(private DocumentBulletinService $document) {}

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
        return $this->document->presenter($bulletin);
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
        return $this->document->presenterEtudiant($etudiant, $formatDate);
    }

    private function detail(Request $request, int $id): array
    {
        $etudiant = $this->etudiant($request);

        return $this->document->detail($this->bulletins($etudiant)->findOrFail($id));
    }
}
