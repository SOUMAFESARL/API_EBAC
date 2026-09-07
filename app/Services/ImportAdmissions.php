<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ImportAdmissions
{
    public function lire(UploadedFile $fichier): array
    {
        $lecteur = match (strtolower($fichier->getClientOriginalExtension())) {
            'xlsx' => new Xlsx,
            'xls' => new Xls,
            'csv' => (new Csv)->setInputEncoding(Csv::GUESS_ENCODING),
            default => throw ValidationException::withMessages(['liste' => 'Utilisez un fichier Excel (.xlsx, .xls) ou CSV.']),
        };
        $classeur = null;
        try {
            $lecteur->setReadDataOnly(true);
            $infos = $lecteur->listWorksheetInfo($fichier->getRealPath());
            if (! $infos || $infos[0]['totalRows'] > 5001 || $infos[0]['totalColumns'] > 30) {
                throw ValidationException::withMessages(['liste' => 'Maximum : 5000 admis et 30 colonnes sur la première feuille.']);
            }
            if (! $lecteur instanceof Csv) {
                $lecteur->setLoadSheetsOnly($infos[0]['worksheetName']);
            }
            $classeur = $lecteur->load($fichier->getRealPath());
            // Ne pas calculer les formules fournies dans un fichier importé.
            $lignes = $classeur->getSheet(0)->toArray(null, false, false);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['liste' => 'Le fichier est illisible ou ne correspond pas à son format.']);
        } finally {
            $classeur?->disconnectWorksheets();
        }

        $entetes = array_map(fn ($valeur) => Str::snake(Str::ascii(Str::lower(trim((string) $valeur, "\xEF\xBB\xBF \t\r\n")))), array_shift($lignes) ?? []);
        $alias = ['nom_et_prenoms' => 'nom_prenoms', 'nom_et_prenom' => 'nom_prenoms', 'nom_prenom' => 'nom_prenoms', 'situation_matrimonial' => 'situation_matrimoniale'];
        $entetes = array_map(fn ($cle) => $alias[$cle] ?? $cle, $entetes);
        $colonnes = ['nom_prenoms', 'region', 'paroisse', 'situation_matrimoniale'];
        if (array_diff($colonnes, $entetes) || count(array_intersect($entetes, $colonnes)) !== 4) {
            throw ValidationException::withMessages(['liste' => 'Colonnes attendues : Nom et Prénoms, Région, Paroisse, Situation matrimoniale.']);
        }

        $resultat = [];
        foreach ($lignes as $index => $ligne) {
            if (! array_filter($ligne, fn ($valeur) => trim((string) $valeur) !== '')) {
                continue;
            }
            $donnees = [];
            foreach ($colonnes as $colonne) {
                $valeur = trim((string) ($ligne[array_search($colonne, $entetes, true)] ?? ''));
                $donnees[$colonne] = $valeur === '' ? null : $valeur;
            }
            $validation = Validator::make($donnees, [
                'nom_prenoms' => ['required', 'string', 'max:255', 'not_regex:/^=/'],
                'region' => ['nullable', 'string', 'max:255', 'not_regex:/^=/'],
                'paroisse' => ['nullable', 'string', 'max:255', 'not_regex:/^=/'],
                'situation_matrimoniale' => ['nullable', 'string', 'max:50', 'not_regex:/^=/'],
            ]);
            if ($validation->fails()) {
                throw ValidationException::withMessages(['liste' => 'Ligne '.($index + 2).' : '.implode(' ', $validation->errors()->all())]);
            }
            $identite = array_map(fn ($valeur) => Str::lower(Str::squish((string) $valeur)), array_intersect_key($donnees, array_flip(['nom_prenoms', 'region', 'paroisse'])));
            $donnees['empreinte'] = hash('sha256', json_encode($identite, JSON_UNESCAPED_UNICODE));
            $resultat[] = $donnees;
        }
        if (! $resultat) {
            throw ValidationException::withMessages(['liste' => 'La liste ne contient aucun admis.']);
        }

        return $resultat;
    }
}
