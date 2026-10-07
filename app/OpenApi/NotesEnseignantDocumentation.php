<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Saisie des notes', description: <<<'DOC'
La saisie est liee a une seance precise. Fournir id_seance, id_matiere (ou cours dans le chemin), id_promotion et id_annee_academique. La seance doit appartenir a l enseignant connecte et correspondre au contexte. Une affectation active et un creneau correspondant sont requis.
GET /enseignant/notes/feuille retourne les etudiants et leur eligibilite sur cette seance. GET /enseignant/notes/tableau retourne directement les notes de cette seance. Sans id_seance, GET consulte uniquement les anciennes feuilles sans seance ; aucune attribution automatique n est faite.
PUT enregistre et transmet immediatement la feuille de la seance. id_seance est obligatoire pour PUT et POST /transmettre. Seule la presence de la seance choisie est controlee : seance realisee, presences validees et completes, etudiant present ou absence autorisee. Les autres seances ne bloquent pas la saisie.
Chaque seance a sa propre feuille et son verrouillage. Envoyer toutes les evaluations ensemble dans notes[].evaluation pour une matiere. Tous les etudiants evaluables doivent avoir au moins une note ; sinon la requete entiere est annulee. La moyenne matiere directe est la moyenne simple des notes de toutes ses seances dans la meme promotion et annee. Les notes par cours restent separees.
Apres succes, statut=transmise et saisie_ouverte=false. Une correction autorisee ou un refus du secretariat permet de corriger les notes. Les transmissions et leurs decisions restent suivies via /enseignant/transmissions-notes et /administration/notes-transmises. Aucune publication aux etudiants.
DOC)]
#[OA\Schema(schema: 'EtudiantNoteCours', type: 'object', properties: [
    new OA\Property(property: 'notes', type: 'array', description: 'Toutes les evaluations de cet etudiant. Utiliser notes[].id pour demander une correction. Le champ historique id_note correspond a la premiere note.', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'evaluation', type: 'string', example: 'devoir_1'),
        new OA\Property(property: 'note', type: 'number', example: 15.5),
    ])),
    new OA\Property(property: 'id_note', type: 'integer', nullable: true, description: 'Identifiant de la note pour le circuit administratif de correction.', example: 1),
    new OA\Property(property: 'id', type: 'integer', example: 4),
    new OA\Property(property: 'matricule', type: 'string', example: 'EBAC-0004-2026'),
    new OA\Property(property: 'nom', type: 'string', example: 'Kadio'),
    new OA\Property(property: 'prenoms', type: 'string', example: 'Yves'),
    new OA\Property(property: 'evaluable', type: 'boolean', description: 'Présent à toutes les séances ou toutes ses absences autorisées par l’administration, avec les feuilles de présence validées et complètes.', example: true),
    new OA\Property(property: 'statut_presence', type: 'string', enum: ['present', 'absent', 'en_attente'], description: 'en_attente tant que les conditions de présence ne permettent pas la saisie.', example: 'present'),
    new OA\Property(property: 'evaluation_autorisee', type: 'boolean', description: 'Toutes les absences concernées sont autorisées et les présences sont validées et complètes.'),
    new OA\Property(property: 'absences_non_autorisees', type: 'array', description: 'Identifiants des lignes presences encore bloquantes.', items: new OA\Items(type: 'integer')),
    new OA\Property(property: 'moyenne_matiere', type: 'number', nullable: true, description: 'Moyenne pondérée des cours notés de la matière, arrondie à deux décimales.', example: 14.25),
    new OA\Property(property: 'statut_moyenne', type: 'string', enum: ['provisoire']),
])]
#[OA\Schema(schema: 'FeuilleNotesDetail', type: 'object', properties: [
    new OA\Property(property: 'cours', type: 'object', example: ['id' => 41, 'libelle' => 'La Trinité', 'coefficient' => '1.00']),
    new OA\Property(property: 'module', type: 'object', example: ['id' => 37, 'libelle' => 'Doctrine de Dieu']),
    new OA\Property(property: 'matiere', type: 'object', example: ['id' => 13, 'libelle' => 'Théologie systématique']),
    new OA\Property(property: 'promotion', type: 'object', example: ['id' => 3, 'code' => 'PROMO-2026', 'num_promotion' => 17]),
    new OA\Property(property: 'id_annee_academique', type: 'integer', example: 1),
    new OA\Property(property: 'id_seance', type: 'integer', nullable: true, example: 157),
    new OA\Property(property: 'statut', type: 'string', enum: ['non_transmise', 'transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'], example: 'transmise'),
    new OA\Property(property: 'date_transmission', type: 'string', nullable: true, example: null),
    new OA\Property(property: 'saisie_ouverte', type: 'boolean', description: 'Ouverte avant transmission ou apres un refus du secretariat, avec des presences validees et completes. Fermee immediatement apres enregistrement.', example: false),
    new OA\Property(property: 'historique', type: 'array', description: 'Décisions et transmissions avec acteur, date et motif.', items: new OA\Items(type: 'object')),
    new OA\Property(property: 'seances_realisees', type: 'integer', example: 3),
    new OA\Property(property: 'presences_validees', type: 'integer', example: 3),
    new OA\Property(property: 'seances_a_relever', type: 'array', description: 'Identifiants des séances réalisées sans feuille de présence validée.', items: new OA\Items(type: 'integer'), example: []),
    new OA\Property(property: 'notes_manquantes', type: 'integer', description: 'Nombre d’étudiants évaluables sans note. Zéro ne suffit pas pour transmettre : la saisie doit être ouverte et au moins un étudiant évaluable.', example: 0),
    new OA\Property(property: 'etudiants', type: 'array', items: new OA\Items(ref: '#/components/schemas/EtudiantNoteCours')),
])]
#[OA\Schema(schema: 'NotesCoursReponse', type: 'object', properties: [
    new OA\Property(property: 'message', type: 'string', example: 'Notes transmises au secretariat academique.'),
    new OA\Property(property: 'feuille_notes', ref: '#/components/schemas/FeuilleNotesDetail'),
])]
#[OA\Schema(schema: 'NotesCoursPayload', type: 'object', required: ['notes'], properties: [
    new OA\Property(property: 'notes', type: 'array', minItems: 1, items: new OA\Items(type: 'object', required: ['id_etudiant', 'note'], properties: [
        new OA\Property(property: 'id_etudiant', type: 'integer', example: 4),
        new OA\Property(property: 'note', type: 'number', minimum: 0, maximum: 20, nullable: true, example: 15.5),
    ])),
])]
#[OA\Get(path: '/enseignant/notes', operationId: 'enseignantOptionsNotes', summary: 'Lister les cours et promotions accessibles pour la saisie',
    description: 'Première étape : choisir l’année académique. Les filtres id_matiere et id_promotion sont facultatifs. La réponse enseignements contient les combinaisons autorisées avec leurs identifiants pour alimenter les sélecteurs. Un tableau vide signifie qu’aucun enseignement ne correspond aux affectations actives et aux créneaux de l’enseignant dans ce contexte.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', schema: new OA\Schema(type: 'integer')),
    ], responses: [new OA\Response(response: 200, description: 'enseignements : combinaisons de matière, module, cours et promotion accessibles via affectations actives et créneaux de cet enseignant.'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Rôle enseignant requis.')])]
#[OA\Get(path: '/enseignant/notes/{cours}', operationId: 'enseignantAfficherNotes', summary: 'Consulter les étudiants et notes du cours par promotion',
    description: 'Retourne la feuille et les notes du cours pour la seance choisie, avec eligibilite selon ses presences. Sans id_seance, retourne uniquement les anciennes feuilles sans seance.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [new OA\Response(response: 200, description: 'Feuille de notes et conditions de saisie.', content: new OA\JsonContent(properties: [new OA\Property(property: 'feuille_notes', ref: '#/components/schemas/FeuilleNotesDetail')])), new OA\Response(response: 404, description: 'Cours, promotion ou année inaccessible.')])]
#[OA\Put(path: '/enseignant/notes/{cours}', operationId: 'enseignantEnregistrerNotes', summary: 'Enregistrer et transmettre directement les notes sur 20',
    description: 'Fournir cours, id_promotion et id_annee_academique et un corps JSON contenant notes. Tous les etudiants evaluables doivent avoir une note. La requete est atomique : une feuille incomplete ou une ligne invalide annule tout. Apres succes, statut=transmise et saisie_ouverte=false. Toute modification ulterieure necessite une correction autorisee ou un refus du secretariat.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursPayload')),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes actualisée.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Enseignement inaccessible.'), new OA\Response(response: 422, description: 'Notes invalides ou saisie fermée.', content: new OA\JsonContent(example: ['message' => 'Un étudiant absent sans autorisation administrative ou hors promotion ne peut pas être noté.', 'errors' => ['notes' => ['Un étudiant absent sans autorisation administrative ou hors promotion ne peut pas être noté.']]]))])]
#[OA\Post(path: '/enseignant/notes/{cours}/transmettre', operationId: 'enseignantTransmettreNotes', summary: 'Transmettre une feuille complète au secrétariat',
    description: 'Alternative a PUT : enregistrer et transmettre dans une seule transaction. Tous les etudiants evaluables doivent avoir une note. Sans corps, transmet une ancienne feuille non transmise ; si deja transmise, retourne la feuille sans changer son statut ni ajouter un historique. Apres succes, statut=transmise et saisie_ouverte=false.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursPayload')),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes transmise.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Enseignement inaccessible.'), new OA\Response(response: 422, description: 'Feuille incomplète ou saisie fermée.', content: new OA\JsonContent(example: ['message' => 'Tous les étudiants évaluables doivent avoir une note avant transmission.', 'errors' => ['notes' => ['Tous les étudiants évaluables doivent avoir une note avant transmission.']]]))])]
final class NotesEnseignantDocumentation {}
