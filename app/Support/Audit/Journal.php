<?php

namespace App\Support\Audit;

use App\Models\Civilite;
use App\Models\JournalAudit;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Seule porte d'écriture du journal d'audit : ajout uniquement. */
final class Journal
{
    private const LIBELLES_CHAMPS = [
        'libelle' => 'Libellé',
        'code' => 'Code',
        'nom' => 'Nom',
        'prenoms' => 'Prénoms',
        'email' => 'Adresse e-mail',
        'statut' => 'Statut',
        'is_active' => 'Actif',
        'actif' => 'Actif',
        'note' => 'Note',
        'coefficient' => 'Coefficient',
        'rang' => 'Rang',
        'date_debut' => 'Date de début',
        'date_fin' => 'Date de fin',
        'telephone' => 'Téléphone',
        'motif' => 'Motif',
        'id_role' => 'Rôle',
        'id_niveau' => 'Niveau',
        'id_matiere' => 'Matière',
        'id_module' => 'Module',
        'civilite_id' => 'Civilité',
        'id_permission' => 'Permission',
        'deux_fa_active' => 'Double authentification',
    ];

    private const REFERENCES = [
        'id_role' => [Role::class, 'libelle'],
        'id_niveau' => [Niveau::class, 'libelle'],
        'id_matiere' => [Matiere::class, 'libelle'],
        'id_module' => [Module::class, 'libelle'],
        'civilite_id' => [Civilite::class, 'name'],
        'id_permission' => [Permission::class, 'code'],
    ];

    private const CHANGEMENTS_MAX = 200;

    /** @var list<array{type: string, libelle: string, avant: ?array<string, string>, apres: ?array<string, string>}>|null */
    private static ?array $collecte = null;

    /**
     * @param  array{type: string, libelle: string}|null  $objet
     * @param  array<string, string>|null  $avant
     * @param  array<string, string>|null  $apres
     */
    public static function consigner(
        string $categorie,
        string $action,
        ?string $criticite = null,
        ?array $objet = null,
        ?array $avant = null,
        ?array $apres = null,
        ?User $acteur = null,
        ?string $nomActeur = null,
    ): ?JournalAudit {
        try {
            $acteur ??= request()->user();
            $acteur?->loadMissing(['role', 'civilite']);

            return JournalAudit::create([
                'horodatage' => now('UTC'),
                'id_user' => $acteur?->id,
                'acteur_nom' => Str::limit($acteur ? self::nomDe($acteur) : ($nomActeur ?: 'Système'), 195),
                'acteur_profil' => self::profilDuRole($acteur?->role?->code),
                'categorie' => $categorie,
                'criticite' => $criticite ?? config("audit.categories.{$categorie}", 'moyen'),
                'action' => Str::limit($action, 250),
                'objet_type' => isset($objet['type']) ? Str::limit($objet['type'], 95) : null,
                'objet_libelle' => isset($objet['libelle']) ? Str::limit($objet['libelle'], 250) : null,
                'valeur_avant' => $avant ?: null,
                'valeur_apres' => $apres ?: null,
                'adresse_ip' => request()->ip(),
            ]);
        } catch (Throwable $exception) {
            // Pas de report() : il repasserait par le journal.
            Log::error('Journal d’audit : écriture impossible.', ['action' => $action, 'exception' => $exception]);

            return null;
        }
    }

    /** Un même refus (compte, route) ne s'inscrit qu'une fois par 10 minutes. */
    public static function refusAcces(Request $request, string $motif): void
    {
        $route = $request->method().' /'.$request->path();
        $cle = 'audit:refus:'.sha1(($request->user()?->id ?? $request->ip()).'|'.$route);

        try {
            if (! Cache::add($cle, true, 600)) {
                return;
            }
        } catch (Throwable) {
        }

        self::consigner('incidents', 'Accès refusé', 'eleve', ['type' => 'Requête', 'libelle' => $route], apres: ['Motif' => $motif]);
    }

    public static function profilDuRole(?string $code): ?string
    {
        $code = strtoupper((string) $code);
        if ($code === '') {
            return null;
        }

        $fixes = [
            'ADMIN' => 'administrateur', 'ADMINISTRATEUR' => 'administrateur',
            'ENSEIGNANT' => 'enseignant', 'ETUDIANT' => 'etudiant', 'EGLISE' => 'eglise',
        ];
        if (isset($fixes[$code])) {
            return $fixes[$code];
        }

        foreach (config('droits.par_defaut', []) as $profil => $regle) {
            if (in_array($code, $regle['roles'] ?? [], true)) {
                return $profil;
            }
        }

        return null;
    }

    public static function nomDe(User $utilisateur): string
    {
        $prenoms = $utilisateur->prenoms ? mb_convert_case(trim($utilisateur->prenoms), MB_CASE_TITLE) : '';

        return trim(implode(' ', array_filter([
            $utilisateur->civilite?->abreviation,
            mb_strtoupper(trim((string) $utilisateur->nom)),
            $prenoms,
        ]))) ?: (string) $utilisateur->email;
    }

    public static function ouvrirCollecte(): void
    {
        self::$collecte = [];
    }

    /** @return list<array{type: string, libelle: string, avant: ?array<string, string>, apres: ?array<string, string>}> */
    public static function fermerCollecte(): array
    {
        $collecte = self::$collecte ?? [];
        self::$collecte = null;

        return $collecte;
    }

    public static function noter(string $evenement, Model $modele): void
    {
        if (self::$collecte === null || $modele instanceof JournalAudit || count(self::$collecte) >= self::CHANGEMENTS_MAX) {
            return;
        }

        try {
            $ignores = array_flip(config('audit.champs_ignores', []));
            $champs = array_keys(array_diff_key($evenement === 'updated' ? $modele->getChanges() : $modele->getAttributes(), $ignores));
            if ($champs === []) {
                return;
            }

            $valeurs = fn (callable $lire) => self::mettreEnForme(array_combine($champs, array_map($lire, $champs)));
            $actuelles = $valeurs(fn (string $champ) => $modele->getAttributeValue($champ));

            self::$collecte[] = [
                'type' => self::typeDe($modele),
                'libelle' => self::libelleDe($modele),
                'avant' => match ($evenement) {
                    'created' => null,
                    'updated' => $valeurs(fn (string $champ) => $modele->getOriginal($champ)),
                    default => $actuelles,
                },
                'apres' => $evenement === 'deleted' ? null : $actuelles,
            ];
        } catch (Throwable $exception) {
            Log::warning('Journal d’audit : relevé impossible.', ['modele' => $modele::class, 'exception' => $exception]);
        }
    }

    /**
     * @param  array{0: string, 1: string, 2?: string}  $regle
     * @param  list<array{type: string, libelle: string, avant: ?array<string, string>, apres: ?array<string, string>}>  $changements
     */
    public static function consignerChangements(array $regle, array $changements): void
    {
        [$categorie, $action] = $regle;
        $premier = $changements[0] ?? null;
        $objet = $premier ? [
            'type' => $premier['type'],
            'libelle' => $premier['libelle'].(count($changements) > 1 ? ' (+'.(count($changements) - 1).' autres)' : ''),
        ] : null;

        if (count($changements) <= 1) {
            $avant = $premier['avant'] ?? null;
            $apres = $premier['apres'] ?? null;
        } else {
            // Plusieurs enregistrements : chaque champ préfixé de celui qui le porte.
            $avant = $apres = [];
            foreach ($changements as $changement) {
                $prefixe = "{$changement['type']} {$changement['libelle']} · ";
                foreach ($changement['avant'] ?? [] as $champ => $valeur) {
                    $avant[$prefixe.$champ] = $valeur;
                }
                foreach ($changement['apres'] ?? [] as $champ => $valeur) {
                    $apres[$prefixe.$champ] = $valeur;
                }
            }
        }

        self::consigner($categorie, $action, $regle[2] ?? null, $objet, self::borner($avant), self::borner($apres));
    }

    private static function typeDe(Model $modele): string
    {
        $classe = class_basename($modele);

        return config("audit.types.{$classe}") ?? Str::headline($classe);
    }

    private static function libelleDe(Model $modele): string
    {
        if ($modele instanceof User) {
            return self::nomDe($modele);
        }

        if ($modele instanceof RolePermission) {
            $role = Role::withTrashed()->find($modele->id_role)?->libelle;
            $permission = Permission::query()->find($modele->id_permission)?->code;

            return trim("{$role} — {$permission}", ' —');
        }

        if (method_exists($modele, 'etudiant') && $modele->getAttribute('id_etudiant')) {
            $etudiant = $modele->etudiant()->first();
            if ($etudiant) {
                return trim(mb_strtoupper((string) $etudiant->nom).' '.(string) $etudiant->prenoms);
            }
        }

        foreach (['libelle', 'code', 'nom', 'name', 'titre', 'email', 'matricule'] as $champ) {
            $valeur = $modele->getAttribute($champ);
            if (is_string($valeur) && trim($valeur) !== '') {
                $prenoms = $champ === 'nom' ? $modele->getAttribute('prenoms') : null;

                return trim($valeur.' '.(is_string($prenoms) ? $prenoms : ''));
            }
        }

        return 'n° '.$modele->getKey();
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, string>
     */
    private static function mettreEnForme(array $valeurs): array
    {
        $formees = [];
        foreach ($valeurs as $champ => $valeur) {
            $formees[self::LIBELLES_CHAMPS[$champ] ?? Str::ucfirst(str_replace('_', ' ', $champ))] = self::valeurLisible($champ, $valeur);
        }

        return $formees;
    }

    private static function valeurLisible(string $champ, mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        if (isset(self::REFERENCES[$champ]) && is_numeric($valeur)) {
            [$classe, $colonne] = self::REFERENCES[$champ];
            $requete = in_array(SoftDeletes::class, class_uses_recursive($classe), true) ? $classe::withTrashed() : $classe::query();
            $libelle = $requete->whereKey($valeur)->value($colonne);

            return $libelle ? (string) $libelle : "n° {$valeur}";
        }

        return match (true) {
            is_bool($valeur) => $valeur ? 'Oui' : 'Non',
            $valeur instanceof DateTimeInterface => $valeur->format('H:i:s') === '00:00:00' ? $valeur->format('d/m/Y') : $valeur->format('d/m/Y H:i'),
            $valeur instanceof BackedEnum => (string) $valeur->value,
            is_array($valeur) || is_object($valeur) => Str::limit((string) json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 300),
            default => Str::limit((string) $valeur, 300),
        };
    }

    /**
     * @param  array<string, string>|null  $valeurs
     * @return array<string, string>|null
     */
    private static function borner(?array $valeurs): ?array
    {
        if (! $valeurs) {
            return null;
        }

        $max = (int) config('audit.champs_max', 60);
        if (count($valeurs) <= $max) {
            return $valeurs;
        }

        return [...array_slice($valeurs, 0, $max, true), 'Autres champs' => (count($valeurs) - $max).' de plus'];
    }
}
