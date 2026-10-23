<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Services\BulletinQrCodeService;
use App\Services\MentionBulletinService;
use Illuminate\Http\Request;

class VerificationBulletinController extends Controller
{
    public function __invoke(Request $request, int $id, BulletinQrCodeService $qr)
    {
        $bulletin = Bulletin::with(['lignes', 'inscription.anneeAcademique'])->findOrFail($id);
        abort_unless($bulletin->statut === 'Généré' || ($bulletin->statut === 'Publié' && $bulletin->date_publication), 404);
        abort_unless(hash_equals($qr->empreinte($bulletin), (string) $request->query('empreinte')), 410,
            'Le bulletin a été modifié. Téléchargez sa version actuelle.');

        return response()->json(['valide' => true, 'bulletin' => [
            ...$bulletin->only(['id', 'periode', 'statut', 'date_publication']),
            'annee_academique' => $bulletin->inscription->anneeAcademique?->libelle,
            'moyenne_generale' => $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null,
            'mention' => $bulletin->mention ?? MentionBulletinService::calculer($bulletin->moyenne !== null ? (float) $bulletin->moyenne : null),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
