<?php

namespace App\Services;

use App\Models\Bulletin;
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
        // No expiration: printed documents must remain verifiable. Changed results invalidate the fingerprint.
        $url = URL::signedRoute('api.v1.bulletins.verifier', [
            'id' => $bulletin->id, 'empreinte' => $this->empreinte($bulletin),
        ]);
        $png = (new Writer(new GDLibRenderer(720, 4)))->writeString($url);

        return ['verification_url' => $url, 'image_data_url' => 'data:image/png;base64,'.base64_encode($png)];
    }
}
