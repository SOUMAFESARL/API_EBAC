<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\ConfigurationEcole;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

class BulletinQrCodeService
{
    public function empreinte(Bulletin $bulletin): string
    {
        $bulletin->loadMissing('lignes');

        return hash('sha256', json_encode([
            $bulletin->only(['id', 'id_inscription', 'periode', 'moyenne', 'mention', 'rang', 'decision', 'statut', 'date_publication']),
            $bulletin->lignes->sortBy('id')->map(fn ($ligne) => $ligne->only([
                'id', 'id_matiere', 'note', 'coefficient', 'moyenne_ponderee', 'appreciation',
            ]))->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function presenter(Bulletin $bulletin): array
    {
        $bulletin->loadMissing(['inscription.etudiant', 'inscription.promotion.niveau']);
        // No expiration: printed documents must remain verifiable. Changed results invalidate the fingerprint.
        $url = URL::signedRoute('api.v1.bulletins.verifier', [
            'id' => $bulletin->id, 'empreinte' => $this->empreinte($bulletin),
        ]);
        $inscription = $bulletin->inscription;
        $contenu = [
            'matricule' => $inscription->etudiant?->matricule,
            'niveau' => $inscription->promotion?->niveau?->libelle,
            'promotion' => $inscription->promotion?->num_promotion,
            'decision_fin_annee' => $bulletin->decision ?? $inscription->decision_passage,
            'moyenne_generale' => $bulletin->moyenne !== null ? (float) $bulletin->moyenne : null,
            'site_web' => ConfigurationEcole::find(1)?->site_web ?: 'https://www.ebac-edu.org',
            'verification_url' => $url,
        ];
        $png = (new Writer(new GDLibRenderer(720, 4)))->writeString(
            json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return ['verification_url' => $url, 'image_data_url' => 'data:image/png;base64,'.base64_encode($png),
            'contenu' => $contenu];
    }
}
