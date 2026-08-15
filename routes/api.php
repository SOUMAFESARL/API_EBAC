<?php

use App\Http\Controllers\Api\V1\Administration\CompteController;
use App\Http\Controllers\Api\V1\Administration\ActionController;
use App\Http\Controllers\Api\V1\Administration\MenuController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\ProfilController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Auth\AuthentificationController;
use App\Http\Controllers\Api\V1\Navigation\SidebarController;
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
        Route::get('/profil', [AuthentificationController::class, 'profil'])->name('profil');
        Route::post('/deconnexion', [AuthentificationController::class, 'deconnexion'])->name('deconnexion');
        Route::post('/deconnexion-globale', [AuthentificationController::class, 'deconnexionGlobale'])
            ->name('deconnexion-globale');
    });
});

Route::get('v1/navigation/sidebar', SidebarController::class)
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->name('api.v1.navigation.sidebar');

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
        Route::delete('/{compte}', [CompteController::class, 'destroy'])->name('destroy');
    });

Route::prefix('v1/administration/profil')->name('api.v1.administration.profil.')->middleware(['auth:sanctum', 'compte.actif'])->group(function () {
    Route::get('/', [ProfilController::class, 'show'])->name('show');
    Route::get('/edit', [ProfilController::class, 'edit'])->name('edit');
    Route::put('/', [ProfilController::class, 'update'])->name('update');
    Route::patch('/', [ProfilController::class, 'update'])->name('patch');
});

Route::prefix('v1/administration')
    ->name('api.v1.administration.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () {
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
