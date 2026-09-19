<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Saisie des notes', description: <<<'DOC'
## Parcours enseignant
Ces routes nécessitent un Bearer Token appartenant à un compte actif de rôle ENSEIGNANT. Dans Swagger, utiliser **Authorize**. Le préfixe des routes est `/api/v1`.

1. **Choisir un enseignement** avec `GET /enseignant/notes?id_annee_academique=1`. La réponse fournit les combinaisons matière, module, cours et promotion accessibles à cet enseignant.
2. **Consulter la feuille** avec `GET /enseignant/notes/41?id_promotion=3&id_annee_academique=1`.
3. **Enregistrer les notes** avec `PUT` sur la même URL. Les enregistrements partiels sont autorisés.
4. **Transmettre la feuille complète** avec `POST /enseignant/notes/41/transmettre?id_promotion=3&id_annee_academique=1`.

Les identifiants ci-dessus sont des exemples à remplacer par ceux de la base. `id_promotion` est la clé technique de la promotion, pas son numéro affiché (`num_promotion`). `{cours}` est un identifiant de cours, pas un identifiant de séance ou de matière. La feuille est distincte pour chaque combinaison **année académique + promotion + cours**.

## Conditions de saisie
- L’enseignant doit avoir une affectation active couvrant le cours ou sa matière, et un créneau correspondant à cette promotion, cette matière et cette année. Un créneau sans cours couvre les cours de la matière autorisés par son affectation.
- Il faut au moins une séance réalisée pour ce cours et cette promotion dans cette année.
- Toutes les séances réalisées doivent avoir une feuille de présence validée couvrant tous les étudiants de la promotion.
- Un étudiant doit être présent à **toutes** ces séances pour être évaluable. Une absence le rend non évaluable pour le cours.
- Tous les étudiants inscrits dans la promotion sont affichés, quelle que soit leur année d’inscription.

## Notes et moyenne
Les notes sont comprises entre 0 et 20, décimales acceptées (exemple JSON : `15.5`). `0` est une note valide ; `null` efface une note et la rend manquante. La moyenne matière est provisoire : somme(note × coefficient du cours) / somme(coefficients des cours notés), pour le même étudiant, la même promotion et la même année. Les cours sans note ne comptent pas dans ce calcul. Exemple : 18 coefficient 1 et 10 coefficient 3 donnent 12/20.

## Transmission
Au moins un étudiant doit être évaluable, et chacun doit avoir une note. Après transmission, `statut = transmise` et `saisie_ouverte = false` : l’enseignant ne peut plus modifier la feuille. Cette opération enregistre une transmission ; elle ne valide pas officiellement les moyennes. Les étapes de contrôle du secrétariat et de validation de la direction ne sont pas encore implémentées dans ces routes.
DOC)]
#[OA\Schema(schema: 'EtudiantNoteCours', type: 'object', properties: [
    new OA\Property(property: 'id_note', type: 'integer', nullable: true, description: 'Identifiant de la note pour le circuit administratif de correction.', example: 1),
    new OA\Property(property: 'id', type: 'integer', example: 4),
    new OA\Property(property: 'matricule', type: 'string', example: 'EBAC-0004-2026'),
    new OA\Property(property: 'nom', type: 'string', example: 'Kadio'),
    new OA\Property(property: 'prenoms', type: 'string', example: 'Yves'),
    new OA\Property(property: 'evaluable', type: 'boolean', description: 'Présent à toutes les séances réalisées, avec toutes les feuilles de présence validées et complètes.', example: true),
    new OA\Property(property: 'statut_presence', type: 'string', enum: ['present', 'absent', 'en_attente'], description: 'en_attente tant que les conditions de présence ne permettent pas la saisie.', example: 'present'),
    new OA\Property(property: 'note', type: 'number', nullable: true, example: 15.5),
    new OA\Property(property: 'moyenne_matiere', type: 'number', nullable: true, description: 'Moyenne pondérée des cours notés de la matière, arrondie à deux décimales.', example: 14.25),
    new OA\Property(property: 'statut_moyenne', type: 'string', enum: ['provisoire']),
])]
#[OA\Schema(schema: 'FeuilleNotesDetail', type: 'object', properties: [
    new OA\Property(property: 'cours', type: 'object', example: ['id' => 41, 'libelle' => 'La Trinité', 'coefficient' => '1.00']),
    new OA\Property(property: 'module', type: 'object', example: ['id' => 37, 'libelle' => 'Doctrine de Dieu']),
    new OA\Property(property: 'matiere', type: 'object', example: ['id' => 13, 'libelle' => 'Théologie systématique']),
    new OA\Property(property: 'promotion', type: 'object', example: ['id' => 3, 'code' => 'PROMO-2026', 'num_promotion' => 17]),
    new OA\Property(property: 'id_annee_academique', type: 'integer', example: 1),
    new OA\Property(property: 'statut', type: 'string', enum: ['brouillon', 'transmise'], example: 'brouillon'),
    new OA\Property(property: 'date_transmission', type: 'string', nullable: true, example: null),
    new OA\Property(property: 'saisie_ouverte', type: 'boolean', description: 'false si aucune séance réalisée, présence non validée ou incomplète, ou feuille déjà transmise.', example: true),
    new OA\Property(property: 'seances_realisees', type: 'integer', example: 3),
    new OA\Property(property: 'presences_validees', type: 'integer', example: 3),
    new OA\Property(property: 'seances_a_relever', type: 'array', description: 'Identifiants des séances réalisées sans feuille de présence validée.', items: new OA\Items(type: 'integer'), example: []),
    new OA\Property(property: 'notes_manquantes', type: 'integer', description: 'Nombre d’étudiants évaluables sans note. Zéro ne suffit pas pour transmettre : la saisie doit être ouverte et au moins un étudiant évaluable.', example: 0),
    new OA\Property(property: 'etudiants', type: 'array', items: new OA\Items(ref: '#/components/schemas/EtudiantNoteCours')),
])]
#[OA\Schema(schema: 'NotesCoursReponse', type: 'object', properties: [
    new OA\Property(property: 'message', type: 'string', example: 'Notes enregistrées.'),
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
    description: 'Retourne feuille_notes : matière, module, cours, promotion, statut, saisie_ouverte, seances_realisees, presences_validees, seances_a_relever, notes_manquantes et etudiants. Chaque étudiant expose evaluable, statut_presence (present, absent ou en_attente), note et moyenne_matiere provisoire pondérée par les coefficients des cours notés. Tous les étudiants de la promotion sont listés, indépendamment de leur année d’inscription. Une absence à une séance réalisée exclut de la notation du cours. Toutes les présences doivent être validées.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [new OA\Response(response: 200, description: 'Feuille de notes et conditions de saisie.', content: new OA\JsonContent(properties: [new OA\Property(property: 'feuille_notes', ref: '#/components/schemas/FeuilleNotesDetail')])), new OA\Response(response: 404, description: 'Cours, promotion ou année inaccessible.')])]
#[OA\Put(path: '/enseignant/notes/{cours}', operationId: 'enseignantEnregistrerNotes', summary: 'Enregistrer un brouillon de notes sur 20',
    description: 'Dans Swagger, renseigner cours, id_promotion et id_annee_academique, puis envoyer un corps JSON contenant notes. Exemple : {"notes":[{"id_etudiant":4,"note":15.5}]}. Seuls les étudiants évaluables doivent être envoyés. Un enregistrement partiel conserve les notes des autres étudiants toujours évaluables ; une note null efface la note de cet étudiant. Les notes d’étudiants devenus non évaluables sont retirées à l’enregistrement. La requête entière est annulée si une ligne est invalide. Refuse les doublons, absents, étudiants hors promotion et valeurs hors de 0 à 20. La réponse contient la feuille et les moyennes provisoires recalculées. Répéter cet appel pour compléter ou corriger le brouillon avant transmission.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursPayload')),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes actualisée.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Enseignement inaccessible.'), new OA\Response(response: 422, description: 'Notes invalides ou saisie fermée.', content: new OA\JsonContent(example: ['message' => 'Un étudiant absent ou hors promotion ne peut pas être noté.', 'errors' => ['notes' => ['Un étudiant absent ou hors promotion ne peut pas être noté.']]]))])]
#[OA\Post(path: '/enseignant/notes/{cours}/transmettre', operationId: 'enseignantTransmettreNotes', summary: 'Transmettre une feuille complète au secrétariat',
    description: 'Dernière étape enseignant. Utiliser les mêmes identifiants que pour la consultation et l’enregistrement. Le corps est facultatif : sans corps, transmet les notes déjà enregistrées ; avec notes, enregistre ces notes puis transmet dans une seule transaction. Il faut une saisie ouverte, au moins un étudiant évaluable et une note pour chacun. Si la feuille reste incomplète, la requête échoue avec 422 et les changements de cette requête sont annulés. En cas de succès, statut devient transmise, date_transmission est renseignée et saisie_ouverte devient false. Une deuxième transmission ou une modification ultérieure est refusée. Les moyennes restent provisoires : le contrôle du secrétariat et la validation officielle de la direction ne sont pas implémentés ici.',
    tags: ['Saisie des notes'], security: [['sanctum' => []]], parameters: [
        new OA\Parameter(name: 'cours', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_annee_academique', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'id_promotion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursPayload')),
    responses: [new OA\Response(response: 200, description: 'message et feuille_notes transmise.', content: new OA\JsonContent(ref: '#/components/schemas/NotesCoursReponse')), new OA\Response(response: 404, description: 'Enseignement inaccessible.'), new OA\Response(response: 422, description: 'Feuille incomplète ou saisie fermée.', content: new OA\JsonContent(example: ['message' => 'Tous les étudiants évaluables doivent avoir une note avant transmission.', 'errors' => ['notes' => ['Tous les étudiants évaluables doivent avoir une note avant transmission.']]]))])]
final class NotesEnseignantDocumentation {}
