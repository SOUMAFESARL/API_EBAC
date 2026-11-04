<?php

use App\Http\Controllers\Api\V1\Administration\ActionController;
use App\Http\Controllers\Api\V1\Administration\AffectationEnseignantController;
use App\Http\Controllers\Api\V1\Administration\AutorisationEvaluationController;
use App\Http\Controllers\Api\V1\Administration\BulletinController;
use App\Http\Controllers\Api\V1\Administration\BulletinNotesEtudiantController;
use App\Http\Controllers\Api\V1\Administration\CompteController;
use App\Http\Controllers\Api\V1\Administration\CorrectionNoteController;
use App\Http\Controllers\Api\V1\Administration\CorrectionsGroupeesController;
use App\Http\Controllers\Api\V1\Administration\MenuController;
use App\Http\Controllers\Api\V1\Administration\NotesTransmisesController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\ProfilController;
use App\Http\Controllers\Api\V1\Administration\PublicationProgrammeController;
use App\Http\Controllers\Api\V1\Administration\RattrapageController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Administration\TableauNotesMatieresController;
use App\Http\Controllers\Api\V1\Auth\AuthentificationController;
use App\Http\Controllers\Api\V1\Eglise\EgliseController;
use App\Http\Controllers\Api\V1\Enseignant\CahierTexteController;
use App\Http\Controllers\Api\V1\Enseignant\ListePresenceController;
use App\Http\Controllers\Api\V1\Enseignant\MesCoursController;
use App\Http\Controllers\Api\V1\Enseignant\MonEmploiDuTempsController;
use App\Http\Controllers\Api\V1\Enseignant\NoteController;
use App\Http\Controllers\Api\V1\Enseignant\TransmissionNotesController;
use App\Http\Controllers\Api\V1\Etudiant\DossierEtudiantCompletController;
use App\Http\Controllers\Api\V1\Etudiant\DossierEtudiantController;
use App\Http\Controllers\Api\V1\Etudiant\EtudiantController;
use App\Http\Controllers\Api\V1\Etudiant\GestionPreInscriptionController;
use App\Http\Controllers\Api\V1\Etudiant\MesBulletinsController;
use App\Http\Controllers\Api\V1\Etudiant\MesCoursAFaireController;
use App\Http\Controllers\Api\V1\Etudiant\MesMatieresController;
use App\Http\Controllers\Api\V1\Etudiant\NouvelleAdmissionController;
use App\Http\Controllers\Api\V1\Etudiant\PreInscriptionController;
use App\Http\Controllers\Api\V1\Etudiant\ReclamationNoteController;
use App\Http\Controllers\Api\V1\Etudiant\RegistreEtudiantController;
use App\Http\Controllers\Api\V1\FichierPreinscriptionController;
use App\Http\Controllers\Api\V1\Navigation\SidebarController;
use App\Http\Controllers\Api\V1\Parametre\AnneeAcademiqueController;
use App\Http\Controllers\Api\V1\Parametre\CalendrierAcademiqueController;
use App\Http\Controllers\Api\V1\Parametre\CiviliteController;
use App\Http\Controllers\Api\V1\Parametre\ConfigurationEcoleController;
use App\Http\Controllers\Api\V1\Parametre\CoursController;
use App\Http\Controllers\Api\V1\Parametre\CreneauController;
use App\Http\Controllers\Api\V1\Parametre\EvenementCalendrierController;
use App\Http\Controllers\Api\V1\Parametre\MatiereController;
use App\Http\Controllers\Api\V1\Parametre\ModuleCalendrierController;
use App\Http\Controllers\Api\V1\Parametre\ModuleController;
use App\Http\Controllers\Api\V1\Parametre\NiveauController;
use App\Http\Controllers\Api\V1\Parametre\PromotionController;
use App\Http\Controllers\Api\V1\Parametre\SalleController;
use App\Http\Controllers\Api\V1\Parametre\UniteEnseignementController;
use App\Http\Controllers\Api\V1\ProgrammePublieController;
use App\Http\Controllers\Api\V1\VerificationBulletinController;
use Illuminate\Support\Facades\Route;

/*
 * Droits `module.sous-module.action` (config/droits.php) : `$droit(...)`
 * ouvre la route au compte qui détient **au moins un** des codes. ADMIN
 * passe toujours ; un rôle inactif est refusé (middleware `permission`).
 * Les lectures demandent le « consulter » des écrans qui les lisent, chaque
 * écriture l'action qu'elle accomplit. Les portails (Enseignant, Étudiant)
 * restent gardés par leur rôle.
 */
$droit = fn (string ...$codes): string => 'permission:'.implode(',', $codes);

$lectureNotes = $droit('notes.supervision-notes.consulter', 'notes.validation-notes.consulter', 'notes.corrections.consulter');
$lectureEtudiants = $droit('gestion-academique.etudiants.consulter', 'gestion-academique.preinscriptions.consulter');

Route::get('v1/bulletins/{id}/verification', VerificationBulletinController::class)
    ->whereNumber('id')->middleware(['signed', 'throttle:60,1'])->name('api.v1.bulletins.verifier');

Route::get('v1/parametres/ecole', [ConfigurationEcoleController::class, 'show'])
    ->middleware(['auth:sanctum', 'compte.actif'])->name('api.v1.parametres.ecole.show');
Route::match(['post', 'put', 'patch'], 'v1/parametres/ecole', [ConfigurationEcoleController::class, 'update'])
    ->middleware(['auth:sanctum', 'compte.actif', $droit('parametres.parametrage-systeme.modifier')])->name('api.v1.parametres.ecole.update');
Route::get('v1/parametres/ecole/fichiers/{type}', [ConfigurationEcoleController::class, 'fichier'])
    ->whereIn('type', ['logo', 'favicon', 'signature', 'signature_directeur', 'signature_directeur_academique'])
    ->middleware('signed')->name('api.v1.parametres.ecole.fichier');

Route::prefix('v1/administration/rattrapages')->name('api.v1.administration.rattrapages.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(RattrapageController::class)
    ->group(function () use ($droit) {
        $lecture = $droit('progression.rattrapages.consulter', 'progression.passage-de-niveau.consulter');
        $inscrire = $droit('progression.rattrapages.inscrire');
        $annuler = $droit('progression.rattrapages.annuler');
        Route::get('/', 'index')->middleware($lecture)->name('index');
        Route::get('cours-a-rattraper', 'coursARattraper')->middleware($lecture)->name('cours-a-rattraper');
        Route::get('plan', 'plan')->middleware($lecture)->name('plan');
        Route::get('cours-a-rattraper/{id}/sessions', 'sessions')->whereNumber('id')->middleware($lecture)->name('sessions');
        Route::post('inscrire', 'store')->middleware($inscrire)->name('inscrire');
        Route::post('/', 'store')->middleware($inscrire)->name('store');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lecture)->name('show');
        Route::put('{id}', 'update')->whereNumber('id')->middleware($inscrire)->name('update');
        Route::post('{id}/annuler', 'annuler')->whereNumber('id')->middleware($annuler)->name('annuler');
        Route::delete('{id}', 'destroy')->whereNumber('id')->middleware($annuler)->name('destroy');
    });

Route::prefix('v1/administration/autorisations-evaluations')->name('api.v1.administration.autorisations-evaluations.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(AutorisationEvaluationController::class)
    ->group(function () use ($droit, $lectureNotes) {
        Route::get('/', 'index')->middleware($lectureNotes)->name('index');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/autoriser', 'autoriser')->whereNumber('id')->middleware($droit('notes.corrections.autoriser'))->name('autoriser');
    });

Route::prefix('v1/administration/bulletins')->name('api.v1.administration.bulletins.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(BulletinController::class)
    ->group(function () use ($droit, $lectureNotes) {
        Route::get('/', 'index')->middleware($lectureNotes)->name('index');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/publier', 'publier')->whereNumber('id')->middleware($droit('notes.validation-notes.valider'))->name('publier');
    });

Route::prefix('v1/etudiant/reclamations-notes')->name('api.v1.etudiant.reclamations-notes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ETUDIANT'])
    ->controller(ReclamationNoteController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{id}', 'show')->whereNumber('id')->name('show');
    });

Route::prefix('v1/administration/reclamations-notes')->name('api.v1.administration.reclamations-notes.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(App\Http\Controllers\Api\V1\Administration\ReclamationNoteController::class)
    ->group(function () use ($droit, $lectureNotes) {
        Route::get('/', 'index')->middleware($lectureNotes)->name('index');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/traiter', 'traiter')->whereNumber('id')
            ->middleware($droit('notes.corrections.autoriser', 'notes.corrections.refuser'))->name('traiter');
    });

Route::prefix('v1/administration/notes-transmises')
    ->name('api.v1.administration.notes-transmises.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit, $lectureNotes) {
        Route::get('/', [NotesTransmisesController::class, 'index'])->middleware($lectureNotes)->name('index');
        Route::get('{id}', [NotesTransmisesController::class, 'show'])->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/valider-secretariat', [NotesTransmisesController::class, 'validerSecretariat'])
            ->whereNumber('id')->middleware($droit('notes.supervision-notes.transmettre'))->name('valider-secretariat');
        Route::post('{id}/rejeter-secretariat', [NotesTransmisesController::class, 'rejeterSecretariat'])
            ->whereNumber('id')->middleware($droit('notes.supervision-notes.rejeter'))->name('rejeter-secretariat');
        Route::post('{id}/transmettre-direction', [NotesTransmisesController::class, 'transmettreDirection'])
            ->whereNumber('id')->middleware($droit('notes.supervision-notes.transmettre'))->name('transmettre-direction');
        Route::post('{id}/valider-direction', [NotesTransmisesController::class, 'validerDirection'])
            ->whereNumber('id')->middleware($droit('notes.validation-notes.valider'))->name('valider-direction');
        Route::post('{id}/rejeter-direction', [NotesTransmisesController::class, 'rejeterDirection'])
            ->whereNumber('id')->middleware($droit('notes.validation-notes.rejeter'))->name('rejeter-direction');
    });

Route::prefix('v1/administration/corrections-notes/groupes')
    ->name('api.v1.administration.corrections-notes.groupes.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(CorrectionsGroupeesController::class)
    ->group(function () use ($droit, $lectureNotes) {
        Route::get('/', 'index')->middleware($lectureNotes)->name('index');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/appliquer', 'appliquer')->whereNumber('id')->middleware($droit('notes.corrections.autoriser'))->name('appliquer');
        Route::post('{id}/rejeter', 'rejeter')->whereNumber('id')->middleware($droit('notes.corrections.refuser'))->name('rejeter');
    });

Route::prefix('v1/administration/corrections-notes')
    ->name('api.v1.administration.corrections-notes.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->controller(CorrectionNoteController::class)
    ->group(function () use ($droit, $lectureNotes) {
        $autoriser = $droit('notes.corrections.autoriser');
        Route::get('/', 'index')->middleware($lectureNotes)->name('index');
        Route::post('/', 'store')->middleware($autoriser)->name('store');
        Route::get('{id}', 'show')->whereNumber('id')->middleware($lectureNotes)->name('show');
        Route::post('{id}/transmettre-direction', 'transmettreDirection')->whereNumber('id')->middleware($droit('notes.supervision-notes.transmettre'))->name('transmettre-direction');
        Route::post('{id}/autoriser', 'autoriser')->whereNumber('id')->middleware($autoriser)->name('autoriser');
        Route::post('{id}/valider', 'autoriser')->whereNumber('id')->middleware($autoriser)->name('valider');
        Route::post('{id}/rejeter', 'rejeter')->whereNumber('id')->middleware($droit('notes.corrections.refuser'))->name('rejeter');
        Route::post('{id}/appliquer', 'appliquer')->whereNumber('id')->middleware($autoriser)->name('appliquer');
    });

Route::prefix('v1/enseignant/corrections-notes')
    ->name('api.v1.enseignant.corrections-notes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->controller(App\Http\Controllers\Api\V1\Enseignant\CorrectionNoteController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{id}', 'show')->whereNumber('id')->name('show');
        Route::put('{id}', 'update')->whereNumber('id')->name('update');
        Route::post('{id}/appliquer', 'appliquer')->whereNumber('id')->name('appliquer');
    });

Route::prefix('v1/administration/nouvelles-admissions')
    ->name('api.v1.administration.nouvelles-admissions.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        $lecture = $droit('gestion-academique.nouvelle-admission.consulter');
        $importer = $droit('gestion-academique.nouvelle-admission.importer');
        Route::get('/', [NouvelleAdmissionController::class, 'index'])->middleware($lecture)->name('index');
        Route::post('/importer', [NouvelleAdmissionController::class, 'importer'])->middleware([$importer, 'throttle:10,1'])->name('importer');
        Route::get('/pdf', [NouvelleAdmissionController::class, 'pdf'])->middleware($droit('gestion-academique.nouvelle-admission.exporter', 'gestion-academique.nouvelle-admission.consulter'))->name('pdf');
        Route::get('/imports/{id}/arrete', [NouvelleAdmissionController::class, 'arrete'])->whereNumber('id')->middleware($lecture)->name('arrete');
        Route::get('/imports/{id}/document-pdf', [NouvelleAdmissionController::class, 'afficherDocumentPdf'])->whereNumber('id')->middleware($lecture)->name('document_pdf');
        Route::get('/imports/{id}/document-pdf/telecharger', [NouvelleAdmissionController::class, 'telechargerDocumentPdf'])->whereNumber('id')->middleware($lecture)->name('document_pdf.telecharger');
        Route::patch('/{id}', [NouvelleAdmissionController::class, 'update'])->whereNumber('id')->middleware($importer)->name('update');
    });

Route::prefix('v1/administration/affectations-enseignants')
    ->name('api.v1.administration.affectations-enseignants.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        $lecture = $droit('planification.affectations.consulter', 'personnel.personnel.consulter');
        Route::get('tableau-de-bord', [AffectationEnseignantController::class, 'tableauDeBord'])->middleware($lecture)->name('tableau-de-bord');
        Route::get('options', [AffectationEnseignantController::class, 'options'])->middleware($lecture)->name('options');
        Route::get('/', [AffectationEnseignantController::class, 'index'])->middleware($lecture)->name('index');
        Route::post('/', [AffectationEnseignantController::class, 'store'])->middleware($droit('planification.affectations.creer'))->name('store');
        Route::get('{id}', [AffectationEnseignantController::class, 'show'])->whereNumber('id')->middleware($lecture)->name('show');
        Route::patch('{id}/terminer', [AffectationEnseignantController::class, 'terminer'])->whereNumber('id')->middleware($droit('planification.affectations.terminer'))->name('terminer');
    });

Route::prefix('v1/administration/publication-programme')
    ->name('api.v1.administration.publication-programme.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        $publier = $droit('planification.publication-programme.publier');
        Route::get('/', [PublicationProgrammeController::class, 'index'])->middleware($droit('planification.publication-programme.consulter'))->name('index');
        Route::post('{calendrier_id}/publier', [PublicationProgrammeController::class, 'publier'])->whereNumber('calendrier_id')->middleware($publier)->name('publier');
        Route::post('{calendrier_id}/retire', [PublicationProgrammeController::class, 'retirer'])->whereNumber('calendrier_id')->middleware($publier)->name('retire');
    });

Route::get('v1/fichiers-preinscriptions/{chemin}', FichierPreinscriptionController::class)
    ->where('chemin', '.*')
    ->middleware('signed')
    ->name('api.v1.fichiers-preinscriptions.show');

Route::post('v1/etudiant/pre-inscription', [PreInscriptionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.v1.etudiant.pre-inscription');

Route::get('v1/etudiant/dossier', [DossierEtudiantCompletController::class, 'monDossier'])
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->name('api.v1.etudiant.dossier');

Route::get('v1/etudiant/emploi-du-temps', App\Http\Controllers\Api\V1\Etudiant\MonEmploiDuTempsController::class)
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ETUDIANT'])
    ->name('api.v1.etudiant.emploi-du-temps');

Route::prefix('v1/etudiant/mes-matieres')
    ->name('api.v1.etudiant.mes-matieres.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ETUDIANT'])
    ->group(function () {
        Route::get('/', [MesMatieresController::class, 'index'])->name('index');
        Route::get('{matiere}', [MesMatieresController::class, 'show'])->whereNumber('matiere')->name('show');
    });
Route::prefix('v1/etudiant/bulletins')
    ->name('api.v1.etudiant.bulletins.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ETUDIANT'])
    ->controller(MesBulletinsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}/telecharger', 'telecharger')->whereNumber('id')->name('telecharger');
        Route::get('{id}', 'show')->whereNumber('id')->name('show');
    });
Route::get('v1/etudiant/cours-a-faire', MesCoursAFaireController::class)
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ETUDIANT'])
    ->name('api.v1.etudiant.cours-a-faire');
Route::match(['patch', 'post'], 'v1/etudiant/dossier', [DossierEtudiantCompletController::class, 'modifierMonDossier'])
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->name('api.v1.etudiant.dossier.update');
Route::post('v1/etudiant/dossier/documents/{id}', [DossierEtudiantCompletController::class, 'remplacerMonDocument'])
    ->whereNumber('id')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->name('api.v1.etudiant.dossier.documents.update');

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

Route::get('v1/programme', ProgrammePublieController::class)
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:SECRETAIRE_ACADEMIQUE,SECRETARIAT,GESTIONNAIRE,ETUDIANT,ENSEIGNANT'])
    ->name('api.v1.programme');

Route::prefix('v1/enseignant/mes-cours')
    ->name('api.v1.enseignant.mes-cours.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->group(function () {
        Route::get('/', [MesCoursController::class, 'index'])->name('index');
        Route::get('{matiere}', [MesCoursController::class, 'show'])->whereNumber('matiere')->name('show');
    });

Route::get('v1/enseignant/emploi-du-temps', MonEmploiDuTempsController::class)
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->name('api.v1.enseignant.emploi-du-temps');

Route::prefix('v1/enseignant/cahier-de-texte')
    ->name('api.v1.enseignant.cahier-de-texte.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->group(function () {
        Route::get('/', [CahierTexteController::class, 'index'])->name('index');
        Route::post('/', [CahierTexteController::class, 'store'])->name('store');
        Route::get('{seance}', [CahierTexteController::class, 'show'])->whereNumber('seance')->name('show');
        Route::match(['put', 'patch'], '{seance}', [CahierTexteController::class, 'update'])->whereNumber('seance')->name('update');
        Route::delete('{seance}', [CahierTexteController::class, 'destroy'])->whereNumber('seance')->name('destroy');
    });

Route::prefix('v1/enseignant/transmissions-notes')
    ->name('api.v1.enseignant.transmissions-notes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->group(function () {
        Route::get('/', [TransmissionNotesController::class, 'index'])->name('index');
        Route::get('tableau', [TransmissionNotesController::class, 'tableau'])->name('tableau');
        Route::get('tableau-general', [TransmissionNotesController::class, 'tableauGeneral'])->name('tableau-general');
        Route::get('feuilles', [TransmissionNotesController::class, 'feuillesNotes'])->name('feuilles');
        Route::get('{id}', [TransmissionNotesController::class, 'show'])->whereNumber('id')->name('show');
    });

Route::prefix('v1/enseignant/notes')
    ->name('api.v1.enseignant.notes.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->group(function () {
        Route::get('/', [NoteController::class, 'index'])->name('index');
        Route::get('feuille', [NoteController::class, 'show'])->name('matiere.show');
        Route::get('tableau', [NoteController::class, 'tableau'])->name('matiere.tableau');
        Route::put('/', [NoteController::class, 'update'])->name('matiere.update');
        Route::post('transmettre', [NoteController::class, 'transmettre'])->name('matiere.transmettre');
        Route::get('feuille/{feuille}', [NoteController::class, 'showFeuille'])->whereNumber('feuille')->name('feuille.show');
        Route::put('feuille/{feuille}', [NoteController::class, 'updateFeuille'])->whereNumber('feuille')->name('feuille.update');
        Route::post('feuille/{feuille}/transmettre', [NoteController::class, 'transmettreFeuille'])->whereNumber('feuille')->name('feuille.transmettre');
        Route::get('{cours}', [NoteController::class, 'show'])->whereNumber('cours')->name('show');
        Route::put('{cours}', [NoteController::class, 'update'])->whereNumber('cours')->name('update');
        Route::post('{cours}/transmettre', [NoteController::class, 'transmettre'])->whereNumber('cours')->name('transmettre');
    });

Route::prefix('v1/enseignant/liste-presence')
    ->name('api.v1.enseignant.liste-presence.')
    ->middleware(['auth:sanctum', 'compte.actif', 'roles.autorises:ENSEIGNANT'])
    ->group(function () {
        Route::get('/', [ListePresenceController::class, 'index'])->name('index');
        Route::get('{seance}', [ListePresenceController::class, 'show'])->whereNumber('seance')->name('show');
        Route::put('{seance}', [ListePresenceController::class, 'update'])->whereNumber('seance')->name('update');
        Route::post('{seance}/valider', [ListePresenceController::class, 'valider'])->whereNumber('seance')->name('valider');
    });

Route::get('v1/utilisateurs/{compte}/photo', [CompteController::class, 'photo'])
    ->name('api.v1.utilisateurs.photo');

Route::prefix('v1/administration/preinscriptions')
    ->name('api.v1.administration.preinscriptions.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        $lecture = $droit('gestion-academique.preinscriptions.consulter', 'gestion-academique.preinscriptions.ouvrir-acces');
        $ouvrirAcces = $droit('gestion-academique.preinscriptions.ouvrir-acces');
        Route::get('/', [GestionPreInscriptionController::class, 'index'])->middleware($lecture)->name('index');
        Route::get('/{id}/creer-compte', [GestionPreInscriptionController::class, 'preparerCompte'])
            ->whereNumber('id')
            ->middleware($ouvrirAcces)
            ->name('creer-compte.formulaire');
        Route::get('/{id}', [GestionPreInscriptionController::class, 'show'])->whereNumber('id')->middleware($lecture)->name('show');
        Route::post('/{id}/creer-compte', [GestionPreInscriptionController::class, 'valider'])
            ->whereNumber('id')
            ->middleware($ouvrirAcces)
            ->name('creer-compte');
        Route::post('/{id}/rejeter', [GestionPreInscriptionController::class, 'rejeter'])
            ->whereNumber('id')
            ->middleware($droit('gestion-academique.preinscriptions.rejeter'))
            ->name('rejeter');
    });

Route::get('v1/administration/etudiants', [EtudiantController::class, 'index'])
    ->middleware(['auth:sanctum', 'compte.actif', $lectureEtudiants])
    ->name('api.v1.administration.etudiants.index');

Route::prefix('v1/administration/etudiants/{id}')
    ->name('api.v1.administration.etudiants.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit, $lectureEtudiants) {
        Route::post('affecter-promotion', [DossierEtudiantCompletController::class, 'affecter'])
            ->whereNumber('id')
            ->middleware($droit('gestion-academique.preinscriptions.affecter'))
            ->name('affecter-promotion');
        Route::get('dossier-complet', [DossierEtudiantCompletController::class, 'show'])
            ->whereNumber('id')
            ->middleware($lectureEtudiants)
            ->name('dossier-complet');
    });

Route::get('v1/administration/dossiers-etudiants', [DossierEtudiantController::class, 'index'])
    ->middleware(['auth:sanctum', 'compte.actif', $lectureEtudiants])
    ->name('api.v1.administration.dossiers-etudiants.index');

Route::prefix('v1/administration/registre-etudiants')
    ->name('api.v1.administration.registre-etudiants.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        $lecture = $droit('gestion-academique.etudiants.consulter');
        Route::get('/', [RegistreEtudiantController::class, 'index'])->middleware($lecture)->name('index');
        Route::get('/{id}/dossier', [DossierEtudiantCompletController::class, 'show'])
            ->whereNumber('id')
            ->middleware($lecture)
            ->name('dossier');
        Route::patch('/{id}', [RegistreEtudiantController::class, 'update'])->whereNumber('id')
            ->middleware($droit('gestion-academique.etudiants.modifier'))->name('update');
    });

Route::get('v1/parametres/civilites', [CiviliteController::class, 'index'])
    ->name('api.v1.parametres.civilites.index');
Route::get('v1/parametres/civilites/{id}', [CiviliteController::class, 'show'])
    ->whereNumber('id')
    ->name('api.v1.parametres.civilites.show');

Route::prefix('v1/parametres')
    ->name('api.v1.parametres.')
    ->middleware(['auth:sanctum', 'compte.actif'])
    ->group(function () use ($droit) {
        /*
         * Référentiels : leurs **lectures** gardent l'accès d'avant la
         * matrice (plusieurs modules les lisent pour leurs filtres et
         * formulaires) ; chaque **écriture** exige le droit de son
         * sous-module. `$ecritures` pose un droit par verbe.
         */
        $ecritures = function (string $nom, string $controleur, string $parametre, array $codes, array $lecture = [], bool $numerique = false) {
            $poser = function (array $actions, array|string $middleware) use ($nom, $controleur, $parametre, $numerique) {
                $ressource = Route::apiResource($nom, $controleur)->only($actions)
                    ->parameters([$nom => $parametre])->middleware($middleware);
                if ($numerique) {
                    $ressource->whereNumber($parametre);
                }
            };
            $poser(['index', 'show'], $lecture);
            $poser(['store'], $codes['store']);
            $poser(['update'], $codes['update']);
            $poser(['destroy'], $codes['destroy']);
        };
        $crud = fn (string $sousModule) => [
            'store' => $droit("{$sousModule}.creer"),
            'update' => $droit("{$sousModule}.modifier"),
            'destroy' => $droit("{$sousModule}.supprimer"),
        ];
        // Lecteurs d'avant la matrice (@ROLE), plus le « consulter » du sous-module.
        $lectureSalles = $droit('@SECRETAIRE_ACADEMIQUE', '@DIRECTION', '@GESTIONNAIRE', 'planification.salles.consulter', 'planification.repartition-horaire.consulter');
        $lectureCreneaux = $droit('@SECRETAIRE_ACADEMIQUE', '@DIRECTION', 'planification.repartition-horaire.consulter');
        $lectureCalendrier = $droit('@SECRETAIRE_ACADEMIQUE', '@DIRECTION', '@GESTIONNAIRE', 'planification.calendrier-academique.consulter', 'planification.repartition-horaire.consulter');
        $lectureDomaines = $droit('@SECRETAIRE_ACADEMIQUE', '@SECRETARIAT', '@DIRECTION', 'programme.domaines.consulter', 'programme.matieres.consulter');
        $calendrier = $droit('planification.calendrier-academique.creer', 'planification.calendrier-academique.modifier');
        $repartition = $droit('planification.repartition-horaire.modifier');

        $systeme = $droit('parametres.parametrage-systeme.modifier');
        Route::get('civilites/create', [CiviliteController::class, 'create'])->middleware($systeme)->name('civilites.create');
        Route::get('civilites/{id}/edit', [CiviliteController::class, 'edit'])->whereNumber('id')->middleware($systeme)->name('civilites.edit');
        Route::apiResource('civilites', CiviliteController::class)
            ->except(['index', 'show'])
            ->parameters(['civilites' => 'id'])
            ->middleware($systeme);

        $ecritures('niveaux', NiveauController::class, 'id', $crud('programme.niveaux'));
        $ecritures('promotions', PromotionController::class, 'id', $crud('programme.promotions'));
        $ecritures('matieres', MatiereController::class, 'id', $crud('programme.matieres'));
        // Modules et cours sont des subdivisions de la matière.
        $matiere = $droit('programme.matieres.modifier');
        $ecritures('modules', ModuleController::class, 'id', ['store' => $matiere, 'update' => $matiere, 'destroy' => $matiere]);
        $ecritures('cours', CoursController::class, 'id', ['store' => $matiere, 'update' => $matiere, 'destroy' => $matiere]);

        $ecritures('unites-enseignement', UniteEnseignementController::class, 'id',
            $crud('programme.domaines'), [$lectureDomaines], true);
        $ecritures('salles', SalleController::class, 'id', [
            'store' => $droit('planification.salles.creer'),
            'update' => $droit('planification.salles.modifier', 'planification.salles.archiver'),
            'destroy' => $droit('planification.salles.archiver'),
        ], [$lectureSalles], true);
        $ecritures('creneaux', CreneauController::class, 'id',
            ['store' => $repartition, 'update' => $repartition, 'destroy' => $repartition], [$lectureCreneaux], true);
        $ecritures('modules-calendrier', ModuleCalendrierController::class, 'id',
            ['store' => $calendrier, 'update' => $calendrier, 'destroy' => $calendrier], [$lectureCalendrier], true);
        $ecritures('evenements-calendrier', EvenementCalendrierController::class, 'id',
            ['store' => $calendrier, 'update' => $calendrier, 'destroy' => $calendrier], [$lectureCalendrier], true);
        Route::get('creneaux/options', [CreneauController::class, 'options'])->name('creneaux.options')->middleware($lectureCreneaux);

        $ecritures('annees-academiques', AnneeAcademiqueController::class, 'id', [
            'store' => $droit('planification.calendrier-academique.creer'),
            // Une même route corrige une année et l'active.
            'update' => $droit('planification.calendrier-academique.modifier', 'planification.calendrier-academique.activer-annee'),
            'destroy' => $droit('planification.calendrier-academique.modifier'),
        ], [$lectureCalendrier]);
        Route::prefix('annees-academiques/{id}/calendrier')->whereNumber('id')->name('calendrier.')->group(function () use ($lectureCalendrier, $calendrier) {
            Route::get('/', [CalendrierAcademiqueController::class, 'show'])->middleware($lectureCalendrier)->name('show');
            Route::post('/', [CalendrierAcademiqueController::class, 'store'])->middleware($calendrier)->name('store');
            Route::put('/', [CalendrierAcademiqueController::class, 'update'])->middleware($calendrier)->name('update');
            Route::delete('/', [CalendrierAcademiqueController::class, 'destroy'])->middleware($calendrier)->name('destroy');
        });
    });

// Lecture publique (le formulaire de pré-inscription liste les églises
// sans jeton) ; les écritures exigent une session et le droit.
Route::apiResource('v1/eglises', EgliseController::class)->only(['index', 'show'])
    ->names('api.v1.eglises')
    ->parameters(['eglises' => 'id']);
Route::apiResource('v1/eglises', EgliseController::class)->only(['store'])
    ->names('api.v1.eglises')
    ->middleware(['auth:sanctum', 'compte.actif', $droit('gestion-academique.eglises.creer')]);
Route::apiResource('v1/eglises', EgliseController::class)->only(['update', 'destroy'])
    ->names('api.v1.eglises')
    ->parameters(['eglises' => 'id'])
    ->middleware(['auth:sanctum', 'compte.actif', $droit('gestion-academique.eglises.modifier')]);

Route::prefix('v1/administration/comptes')
    ->name('api.v1.administration.comptes.')
    ->middleware([
        'auth:sanctum',
        'compte.actif',
    ])
    ->group(function () use ($droit) {
        $lecture = $droit('securite-comptes.utilisateurs.consulter');
        $creer = $droit('securite-comptes.utilisateurs.creer', 'gestion-academique.preinscriptions.ouvrir-acces');
        // Le changement d'état (bloquer, réactiver) passe par la même route
        // que la modification ; la suppression est rattachée à « bloquer ».
        $modifier = $droit('securite-comptes.utilisateurs.modifier', 'securite-comptes.utilisateurs.bloquer');
        Route::get('/', [CompteController::class, 'index'])->middleware($lecture)->name('index');
        // Référentiels du formulaire (rôles, civilités) : création, ouverture d'accès, modification.
        Route::get('/create', [CompteController::class, 'create'])
            ->middleware($droit('securite-comptes.utilisateurs.creer', 'securite-comptes.utilisateurs.modifier', 'gestion-academique.preinscriptions.ouvrir-acces'))
            ->name('create');
        Route::post('/etudiants', [CompteController::class, 'storeEtudiant'])->middleware($creer)->name('etudiants.store');
        Route::post('/', [CompteController::class, 'store'])->middleware($creer)->name('store');
        Route::get('/{compte}', [CompteController::class, 'show'])->middleware($lecture)->name('show');
        Route::get('/{compte}/edit', [CompteController::class, 'edit'])->middleware($modifier)->name('edit');
        Route::put('/{compte}', [CompteController::class, 'update'])->middleware($modifier)->name('update');
        Route::patch('/{compte}', [CompteController::class, 'update'])->middleware($modifier)->name('patch');
        Route::post('/{compte}', [CompteController::class, 'update'])->middleware($modifier)->name('update-multipart');
        Route::delete('/{compte}', [CompteController::class, 'destroy'])->middleware($droit('securite-comptes.utilisateurs.bloquer'))->name('destroy');
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
    ->group(function () use ($droit) {
        // `ROLE_GERER` reste accepté : c'est le droit d'avant la matrice.
        $lecture = $droit('ROLE_GERER', 'securite-comptes.roles-droits.consulter');
        $modifier = $droit('ROLE_GERER', 'securite-comptes.roles-droits.modifier');
        Route::get('roles/catalogue-droits', [RoleController::class, 'catalogueDroits'])
            ->middleware($lecture)
            ->name('roles.catalogue-droits');
        Route::get('roles/{role}/droits', [RoleController::class, 'droitsDuRole'])
            ->middleware($lecture)
            ->name('roles.droits.show');
        Route::patch('roles/{role}/droits/{permission}', [RoleController::class, 'modifierDroit'])
            ->middleware($modifier)
            ->name('roles.droits.update');
        Route::get('roles/matrice-autorisations', [RoleController::class, 'matriceAutorisations'])
            ->middleware($lecture)
            ->name('roles.matrice-autorisations');
        Route::get('roles/{role}/matrice-autorisations', [RoleController::class, 'matriceAutorisationsRole'])
            ->middleware($lecture)
            ->name('roles.matrice-autorisations.show');
        Route::put('roles/{role}/autorisations', [RoleController::class, 'synchroniserAutorisations'])
            ->middleware($modifier)
            ->name('roles.autorisations.update');
        Route::apiResource('roles', RoleController::class)->only(['index', 'show'])->middleware($lecture);
        Route::apiResource('roles', RoleController::class)->only(['store'])->middleware($droit('ROLE_GERER', 'securite-comptes.roles-droits.creer'));
        Route::apiResource('roles', RoleController::class)->only(['update'])->middleware($modifier);
        Route::apiResource('roles', RoleController::class)->only(['destroy'])->middleware($droit('ROLE_GERER', 'securite-comptes.roles-droits.supprimer'));
        Route::put('roles/{role}/permissions', [RoleController::class, 'synchroniserPermissions'])
            ->middleware($modifier)
            ->name('roles.permissions.update');
        // L'écran « Rôles & droits » lit le catalogue ; l'écrire reste un outil d'administration.
        Route::apiResource('permissions', PermissionController::class)->only(['index', 'show'])
            ->middleware($droit('PERMISSION_GERER', 'securite-comptes.roles-droits.consulter'));
        Route::apiResource('permissions', PermissionController::class)->only(['store', 'update', 'destroy'])
            ->middleware('permission:PERMISSION_GERER');
        Route::put('permissions/{permission}/actions', [PermissionController::class, 'synchroniserActions'])
            ->middleware('permission:PERMISSION_GERER')
            ->name('permissions.actions.update');
        Route::apiResource('actions', ActionController::class)
            ->middleware('permission:ACTION_GERER');
        Route::apiResource('menus', MenuController::class)->middleware('permission:MENU_GERER');
    });

Route::get('v1/administration/notes-matieres', [TableauNotesMatieresController::class, 'index'])
    ->middleware(['auth:sanctum', 'compte.actif', $lectureNotes])
    ->name('api.v1.administration.notes-matieres.index');

Route::get('v1/administration/bulletins-notes/{id}', [BulletinNotesEtudiantController::class, 'show'])
    ->whereNumber('id')->middleware(['auth:sanctum', 'compte.actif', $lectureNotes])
    ->name('api.v1.administration.bulletins-notes.show');
