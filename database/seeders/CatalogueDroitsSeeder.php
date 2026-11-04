<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sème les droits `module.sous-module.action` de `config/droits.php`.
 *
 * Idempotent : un droit déjà présent est remis à jour (libellé, rangement)
 * et restauré s'il avait été supprimé, jamais dupliqué. ADMIN reçoit
 * l'ensemble du catalogue — il le détient déjà de fait, et l'écran « Rôles &
 * droits » doit le montrer. Les rôles semés (Direction, Secrétariat,
 * Gestionnaire) reçoivent leurs droits par défaut **une seule fois** : un
 * rôle qui porte déjà un droit du catalogue n'est pas touché.
 */
class CatalogueDroitsSeeder extends Seeder
{
    public function run(): void
    {
        $ids = DB::transaction(function () {
            $ids = [];
            foreach (self::droits() as $droit) {
                $permission = Permission::withTrashed()->firstOrNew(['code' => $droit['code']]);
                $permission->fill([
                    'libelle' => $droit['libelle'],
                    'description' => $droit['description'],
                    'module' => $droit['module'],
                    'sous_module' => $droit['sous_module'],
                    'action' => $droit['action'],
                ]);
                if ($permission->trashed()) {
                    $permission->deleted_at = null;
                    $permission->deleted_by = null;
                }
                $permission->save();
                $ids[] = $permission->id;
            }

            return $ids;
        });

        Role::query()->where('code', 'ADMIN')->first()?->permissions()
            ->syncWithoutDetaching(array_fill_keys($ids, ['actif' => true]));

        // `foreach` et non `each()` : `each()` s'arrête au premier `false`,
        // et ADMIN (qui n'a pas de droits par défaut) en renvoie un.
        foreach (Role::query()->get() as $role) {
            self::poserDroitsParDefaut($role);
        }
    }

    /**
     * Pose les droits par défaut d'un rôle semé (`config('droits.par_defaut')`),
     * **seulement** s'il n'a encore aucun droit du catalogue : un réglage fait
     * dans « Rôles & droits » n'est jamais écrasé. Sans effet sur ADMIN, les
     * portails et les rôles personnalisés.
     *
     * @return bool vrai si des droits ont été posés
     */
    public static function poserDroitsParDefaut(Role $role): bool
    {
        $codes = self::droitsParDefaut($role->code);
        if ($codes === []) {
            return false;
        }

        $dejaRegle = $role->permissions()->whereNotNull('permissions.module')->exists();
        if ($dejaRegle) {
            return false;
        }

        $ids = Permission::query()->whereIn('code', $codes)->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching(array_fill_keys($ids, ['actif' => true]));

        return true;
    }

    /** @return list<string> */
    public static function droitsParDefaut(?string $codeRole): array
    {
        foreach (config('droits.par_defaut') as $profil) {
            if (in_array(strtoupper((string) $codeRole), $profil['roles'], true)) {
                return $profil['droits'];
            }
        }

        return [];
    }

    /**
     * Le catalogue à plat, dans l'ordre du menu.
     *
     * @return list<array{code: string, libelle: string, description: string, module: string, sous_module: string, action: string}>
     */
    public static function droits(): array
    {
        $actions = config('droits.actions');
        $droits = [];

        foreach (config('droits.modules') as $cleModule => $module) {
            foreach ($module['sous_modules'] as $cleSousModule => [$libelleSousModule, $actionsSousModule]) {
                foreach ($actionsSousModule as $action) {
                    $libelleAction = $actions[$action];
                    $droits[] = [
                        'code' => "{$cleModule}.{$cleSousModule}.{$action}",
                        'libelle' => "{$libelleSousModule} — {$libelleAction}",
                        'description' => "{$module['libelle']} › {$libelleSousModule} — {$libelleAction}",
                        'module' => $cleModule,
                        'sous_module' => $cleSousModule,
                        'action' => $action,
                    ];
                }
            }
        }

        return $droits;
    }
}
