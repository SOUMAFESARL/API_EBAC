<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'InscriptionSessionRattrapage', type: 'object', required: ['id_cours_a_faire', 'id_seance_rattrapage'], properties: [
    new OA\Property(property: 'id_cours_a_faire', type: 'integer', example: 1),
    new OA\Property(property: 'id_seance_rattrapage', type: 'integer', example: 25),
    new OA\Property(property: 'observations', type: 'string', nullable: true, maxLength: 5000),
])]
#[OA\Get(path: '/administration/rattrapages/plan', summary: 'Consulter le plan de rattrapage et ses indicateurs',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE ou DIRECTION actif. Absences sur seances realisees et feuilles de presence validees. Une ligne par etudiant et seance manquee, avec matiere, cours facultatif, module, promotion origine et session retenue. La programmation ne change pas la promotion origine ni la presence initiale. Statistiques calculees apres recherche et filtres de contexte, avant filtre de statut et pagination. Part non planifiee = non_planifies / (non_planifies + inscrits) * 100, zero si aucun reste a faire. effectue signifie cours termine ou programmation realisee ; ne garantit pas une note saisie.',
    tags: ['Rattrapages'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 255)),
        new OA\Parameter(name: 'id_promotion', in: 'query', description: 'Promotion de la seance origine.', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_etudiant', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_matiere', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_cours', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_seance', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'statut', in: 'query', schema: new OA\Schema(type: 'string', enum: ['non_planifie', 'inscrit', 'effectue'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
    ], responses: [new OA\Response(response: 200, description: 'Pagination Laravel data et statistiques (non_planifies, inscrits, effectues, total, part_non_planifiee). Chaque ligne contient statut_plan.'),
        new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Role interdit.'), new OA\Response(response: 422, description: 'Filtres invalides.')])]
#[OA\Get(path: '/administration/rattrapages/cours-a-rattraper/{id}/sessions', summary: 'Choisir une session pour un enseignement manque',
    description: 'Seances prevues non passees de la meme matiere et du meme cours (ou sans cours pour une absence par matiere), avec horaires et enseignant actif. Inclut promotion, niveau, enseignant et salle. La promotion accueil peut differer de la promotion origine. id identifie le cours_a_faire.',
    tags: ['Rattrapages'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'sessions disponibles.'), new OA\Response(response: 403, description: 'Role interdit.'),
        new OA\Response(response: 404, description: 'Cours a faire introuvable.'), new OA\Response(response: 422, description: 'Absence non eligible ou cours termine.')])]
#[OA\Post(path: '/administration/rattrapages/inscrire', summary: 'Inscrire un etudiant a une session de rattrapage',
    description: 'ADMIN, SECRETARIAT, SECRETAIRE_ACADEMIQUE ou DIRECTION. La date, les horaires, l enseignant et la salle sont derives de la session ; ne pas les fournir. La session doit correspondre au meme enseignement et etre prevue, non passee. Une programmation par absence. POST /administration/rattrapages accepte aussi ce format. Pour reprogrammer apres annulation, PUT /administration/rattrapages/{id} avec id_seance_rattrapage et observations facultatives, sans id_cours_a_faire. Une inscription ne valide pas le cours et ne cree aucune note.',
    tags: ['Rattrapages'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/InscriptionSessionRattrapage')),
    responses: [new OA\Response(response: 201, description: 'message et rattrapage programme, avec session et promotion accueil.'), new OA\Response(response: 403, description: 'Role interdit.'),
        new OA\Response(response: 404, description: 'Ressource introuvable.'), new OA\Response(response: 422, description: 'Session incompatible, absence non eligible ou programmation existante.')])]
#[OA\Post(path: '/administration/rattrapages/{id}/annuler', summary: 'Annuler une inscription en rattrapage',
    description: 'Sans corps. Uniquement depuis programme. Conserve la programmation annulee, la presence initiale et le cours restant a faire. Le plan affiche de nouveau non_planifie. Acteur conserve dans updated_by.',
    tags: ['Rattrapages'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 200, description: 'message et rattrapage annule.'), new OA\Response(response: 403, description: 'Role interdit.'),
        new OA\Response(response: 404, description: 'Rattrapage introuvable.'), new OA\Response(response: 422, description: 'Rattrapage deja realise ou annule.')])]
final class PlanRattrapageDocumentation {}
