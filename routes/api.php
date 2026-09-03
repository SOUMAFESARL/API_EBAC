<?php

use App\Http\Controllers\Api\V1\Administration\ActionController;
use App\Http\Controllers\Api\V1\Administration\CompteController;
use App\Http\Controllers\Api\V1\Administration\MenuController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\ProfilController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Auth\AuthentificationController;
use App\Http\Controllers\Api\V1\Eglise\EgliseController;
use App\Http\Controllers\Api\V1\Etudiant\DossierEtudiantController;
use App\Http\Controllers\Api\V1\Etudiant\EtudiantController;
use App\Http\Controllers\Api\V1\Etudiant\GestionPreInscriptionController;
use App\Http\Controllers\Api\V1\Etudiant\PreInscriptionController;
use App\Http\Controllers\Api\V1\Navigation\SidebarController;
use App\Http\Controllers\Api\V1\Parametre\AnneeAcademiqueController;
use App\Http\Controllers\Api\V1\Parametre\CiviliteController;
use App\Http\Controllers\Api\V1\Parametre\CoursController;
use App\Http\Controllers\Api\V1\Parametre\MatiereController;
use App\Http\Controllers\Api\V1\Parametre\ModuleController;
use App\Http\Controllers\Api\V1\Parametre\NiveauController;
use App\Http\Controllers\Api\V1\Parametre\PromotionController;
use Illuminate\Support\Facades\Route;

Route::post('v1/etudiant/pre-inscription', [PreInscriptionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.v1.etudiant.pre-inscription');

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

Route::prefix('v1/administration/preinscriptions')
    ->name('api.v1.administration.preinscriptions.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.interdits:ENSEIGNANT,ETUDIANT'])
    ->group(function () {
        Route::get('/', [GestionPreInscriptionController::class, 'index'])->name('index');
        Route::get('/{id}/creer-compte', [GestionPreInscriptionController::class, 'preparerCompte'])
            ->whereNumber('id')
            ->name('creer-compte.formulaire');
        Route::get('/{id}', [GestionPreInscriptionController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}/creer-compte', [GestionPreInscriptionController::class, 'valider'])
            ->whereNumber('id')
            ->name('creer-compte');
        Route::post('/{id}/rejeter', [GestionPreInscriptionController::class, 'rejeter'])
            ->whereNumber('id')
            ->name('rejeter');
    });

Route::get('v1/administration/etudiants', [EtudiantController::class, 'index'])
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.interdits:ENSEIGNANT,ETUDIANT'])
    ->name('api.v1.administration.etudiants.index');

Route::get('v1/administration/dossiers-etudiants', [DossierEtudiantController::class, 'index'])
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.interdits:ENSEIGNANT,ETUDIANT'])
    ->name('api.v1.administration.dossiers-etudiants.index');

Route::get('v1/parametres/civilites', [CiviliteController::class, 'index'])
    ->name('api.v1.parametres.civilites.index');
Route::get('v1/parametres/civilites/{id}', [CiviliteController::class, 'show'])
    ->whereNumber('id')
    ->name('api.v1.parametres.civilites.show');

Route::prefix('v1/parametres')
    ->name('api.v1.parametres.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () {
        Route::get('civilites/create', [CiviliteController::class, 'create'])->name('civilites.create');
        Route::get('civilites/{id}/edit', [CiviliteController::class, 'edit'])->whereNumber('id')->name('civilites.edit');
        Route::apiResource('civilites', CiviliteController::class)
            ->except(['index', 'show'])
            ->parameters(['civilites' => 'id']);
        Route::apiResource('niveaux', NiveauController::class)
            ->parameters(['niveaux' => 'id']);
        Route::apiResource('annees-academiques', AnneeAcademiqueController::class)
            ->parameters(['annees-academiques' => 'id']);
        Route::apiResource('promotions', PromotionController::class)
            ->parameters(['promotions' => 'id']);
        Route::apiResource('matieres', MatiereController::class)
            ->parameters(['matieres' => 'id']);
        Route::apiResource('modules', ModuleController::class)
            ->parameters(['modules' => 'id']);
        Route::apiResource('cours', CoursController::class)
            ->parameters(['cours' => 'id']);
    });

Route::apiResource('v1/eglises', EgliseController::class)
    ->names('api.v1.eglises')
    ->parameters(['eglises' => 'id']);

Route::prefix('v1/administration/comptes')
    ->name('api.v1.administration.comptes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'permission:COMPTE_GERER'])
    ->group(function () {
        Route::get('/', [CompteController::class, 'index'])->name('index');
        Route::get('/create', [CompteController::class, 'create'])->name('create');
        Route::post('/etudiants', [CompteController::class, 'storeEtudiant'])->name('etudiants.store');
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
