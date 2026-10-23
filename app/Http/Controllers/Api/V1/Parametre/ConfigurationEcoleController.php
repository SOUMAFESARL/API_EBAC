<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\DTOs\Api\V1\Parametre\ConfigurationEcoleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\EnregistrerConfigurationEcoleRequest;
use App\Models\ConfigurationEcole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ConfigurationEcoleController extends Controller
{
    private const FICHIERS = ['logo', 'favicon', 'signature', 'signature_directeur'];

    private function presenter(ConfigurationEcole $ecole): array
    {
        $donnees = $ecole->only(['id', 'nom_ecole', 'code_ecole', 'slogan', 'adresse', 'fondateur',
            'directeur_actuel', 'situation_geographique', 'ville', 'region', 'email', 'telephone',
            'cellulaire', 'biographie', 'created_at', 'updated_at']);
        foreach (self::FICHIERS as $type) {
            $donnees[$type.'_url'] = $ecole->$type ? URL::temporarySignedRoute(
                'api.v1.parametres.ecole.fichier', now()->addMinutes(15), ['type' => $type]
            ) : null;
        }

        return $donnees;
    }

    public function show()
    {
        $ecole = ConfigurationEcole::find(1);

        return response()->json(['ecole' => $ecole ? $this->presenter($ecole) : null]);
    }

    public function update(EnregistrerConfigurationEcoleRequest $request)
    {
        $donnees = $request->validated();
        abort_if($donnees === [], 422, 'Aucun champ de configuration reçu.');
        $nouveaux = [];
        $anciens = [];
        try {
            foreach (self::FICHIERS as $type) {
                if ($request->hasFile($type)) {
                    $chemin = $request->file($type)->store('ecole/'.$type, 'local');
                    if (! $chemin) {
                        throw new \RuntimeException('Impossible de conserver le fichier.');
                    }
                    $donnees[$type] = $chemin;
                    $nouveaux[] = $chemin;
                }
            }
            $dto = ConfigurationEcoleDTO::fromArray($donnees);
            [$ecole, $cree] = DB::transaction(function () use ($dto, $request, &$anciens) {
                $donnees = $dto->toArray();
                $ecole = ConfigurationEcole::firstOrCreate(['id' => 1], [
                    'nom_ecole' => $donnees['nom_ecole'] ?? null,
                    'code_ecole' => $donnees['code_ecole'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $cree = $ecole->wasRecentlyCreated;
                $ecole = ConfigurationEcole::whereKey(1)->lockForUpdate()->firstOrFail();
                foreach (self::FICHIERS as $type) {
                    if (array_key_exists($type, $donnees) && $ecole->$type) {
                        $anciens[] = $ecole->$type;
                    }
                }
                $ecole->update([...$donnees, 'updated_by' => $request->user()->id]);

                return [$ecole, $cree];
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($nouveaux);
            throw $exception;
        }
        Storage::disk('local')->delete($anciens);

        return response()->json(['message' => 'Configuration de l’école enregistrée.',
            'ecole' => $this->presenter($ecole)], $cree ? 201 : 200);
    }

    public function fichier(string $type)
    {
        abort_unless(in_array($type, self::FICHIERS, true), 404);
        $ecole = ConfigurationEcole::findOrFail(1);
        $chemin = $ecole->$type;
        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
