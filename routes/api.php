<?php

use App\Http\Controllers\Api\V1\Administration\CompteController;
use App\Http\Controllers\Api\V1\Administration\ActionController;
use App\Http\Controllers\Api\V1\Administration\MenuController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\ProfilController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Auth\AuthentificationController;
use App\Http\Controllers\Api\V1\Eglise\EgliseController;
use App\Http\Controllers\Api\V1\Navigation\SidebarController;
use App\Http\Controllers\Api\V1\Parametre\NiveauController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    Route::post('/connexion', [AuthentificationController::class, 'connexion'])
        ->middleware('throttle:5,1')
        ->name('connexion');

    Route::post('/confirmer-otp', [AuthentificationController::class, 'confirmerOtp'])
        ->middleware('throttle:10,1')
        ->name('confirmer-otp');

    Route::post('/mot-de-passe-oublie', [AuthentificationController::class, 'motDePasseOublie'])
        ->middleware('throttle:3,1')
        ->name('mot-de-passe-oublie');

    Route::post('/verifier-code-reinitialisation', [AuthentificationController::class, 'verifierCodeReinitialisation'])
        ->middleware('throttle:5,1')
        ->name('verifier-code-reinitialisation');

    Route::post('/reinitialiser-mot-de-passe', [AuthentificationController::class, 'reinitialiserMotDePasse'])
        ->middleware('throttle:5,1')
        ->name('reinitialiser-mot-de-passe');

    Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {
        Route::post('/modifier-mot-de-passe', [AuthentificationController::class, 'modifierMotDePasse'])
            ->middleware('throttle:5,1')
            ->name('modifier-mot-de-passe');
        Route::get('/profil', [AuthentificationController::class, 'profil'])->name('profil');
        Route::post('/deconnexion', [AuthentificationController::class, 'deconnexion'])->name('deconnexion');
        Route::post('/deconnexion-globale', [AuthentificationController::class, 'deconnexionGlobale'])
            ->name('deconnexion-globale');
    });
});

Route::get('v1/navigation/sidebar', SidebarController::class)
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->name('api.v1.navigation.sidebar');

Route::get('v1/utilisateurs/{compte}/photo', [CompteController::class, 'photo'])
    ->name('api.v1.utilisateurs.photo');

Route::prefix('v1/parametres')
    ->name('api.v1.parametres.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () {
        Route::apiResource('niveaux', NiveauController::class);
    });

Route::apiResource('v1/eglises', EgliseController::class)
    ->names('api.v1.eglises')
    ->middleware(['auth:sanctum', 'compte.actif']);

Route::prefix('v1/administration/comptes')
    ->name('api.v1.administration.comptes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'permission:COMPTE_GERER'])
    ->group(function () {
        Route::get('/', [CompteController::class, 'index'])->name('index');
        Route::get('/create', [CompteController::class, 'create'])->name('create');
        Route::post('/', [CompteController::class, 'store'])->name('store');
        Route::get('/{compte}', [CompteController::class, 'show'])->name('show');
        Route::get('/{compte}/edit', [CompteController::class, 'edit'])->name('edit');
        Route::put('/{compte}', [CompteController::class, 'update'])->name('update');
        Route::patch('/{compte}', [CompteController::class, 'update'])->name('patch');
        Route::post('/{compte}', [CompteController::class, 'update'])->name('update-multipart');
        Route::delete('/{compte}', [CompteController::class, 'destroy'])->name('destroy');
    });

Route::prefix('v1/administration/profil')->name('api.v1.administration.profil.')->middleware(['auth:sanctum', 'compte.actif'])->group(function () {
    Route::get('/', [ProfilController::class, 'show'])->name('show');
    Route::get('/edit', [ProfilController::class, 'edit'])->name('edit');
    Route::put('/', [ProfilController::class, 'update'])->name('update');
    Route::patch('/', [ProfilController::class, 'update'])->name('patch');
    Route::post('/', [ProfilController::class, 'update'])->name('update-multipart');
});

Route::prefix('v1/administration')
    ->name('api.v1.administration.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () {
        Route::get('roles/catalogue-droits', [RoleController::class, 'catalogueDroits'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.catalogue-droits');
        Route::get('roles/{role}/droits', [RoleController::class, 'droitsDuRole'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.droits.show');
        Route::patch('roles/{role}/droits/{permission}', [RoleController::class, 'modifierDroit'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.droits.update');
        Route::get('roles/matrice-autorisations', [RoleController::class, 'matriceAutorisations'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.matrice-autorisations');
        Route::get('roles/{role}/matrice-autorisations', [RoleController::class, 'matriceAutorisationsRole'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.matrice-autorisations.show');
        Route::put('roles/{role}/autorisations', [RoleController::class, 'synchroniserAutorisations'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.autorisations.update');
        Route::apiResource('roles', RoleController::class)->middleware('permission:ROLE_GERER');
        Route::put('roles/{role}/permissions', [RoleController::class, 'synchroniserPermissions'])
            ->middleware('permission:ROLE_GERER')
            ->name('roles.permissions.update');
        Route::apiResource('permissions', PermissionController::class)
            ->middleware('permission:PERMISSION_GERER');
        Route::put('permissions/{permission}/actions', [PermissionController::class, 'synchroniserActions'])
            ->middleware('permission:PERMISSION_GERER')
            ->name('permissions.actions.update');
        Route::apiResource('actions', ActionController::class)
            ->middleware('permission:ACTION_GERER');
        Route::apiResource('menus', MenuController::class)->middleware('permission:MENU_GERER');
    });
