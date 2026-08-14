param(
    [string]$OutputPath = "documentation\Documentation_API_EBAC.docx"
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

function Escape-Xml([string]$Value) {
    return [System.Security.SecurityElement]::Escape($Value)
}

function Paragraph([string]$Text, [string]$Style = $null) {
    $styleXml = if ($Style) { '<w:pPr><w:pStyle w:val="' + $Style + '"/></w:pPr>' } else { '' }
    return '<w:p>' + $styleXml + '<w:r><w:t xml:space="preserve">' + (Escape-Xml $Text) + '</w:t></w:r></w:p>'
}

function Code-Paragraph([string]$Text) {
    return '<w:p><w:pPr><w:pStyle w:val="Code"/></w:pPr><w:r><w:t xml:space="preserve">' + (Escape-Xml $Text) + '</w:t></w:r></w:p>'
}

function Table([string[]]$Headers, [object[][]]$Rows) {
    $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid/>'
    $xml += '<w:tr>'
    foreach ($header in $Headers) {
        $xml += '<w:tc><w:tcPr><w:shd w:fill="D9EAF7"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' + (Escape-Xml $header) + '</w:t></w:r></w:p></w:tc>'
    }
    $xml += '</w:tr>'
    foreach ($row in $Rows) {
        $xml += '<w:tr>'
        foreach ($cell in $row) {
            $xml += '<w:tc><w:p><w:r><w:t xml:space="preserve">' + (Escape-Xml ([string]$cell)) + '</w:t></w:r></w:p></w:tc>'
        }
        $xml += '</w:tr>'
    }
    return $xml + '</w:tbl>'
}

$body = New-Object System.Collections.Generic.List[string]
$body.Add((Paragraph 'DOCUMENTATION DES API EBAC' 'Title'))
$body.Add((Paragraph 'Version 1 — Authentification et gestion des comptes administratifs' 'Subtitle'))
$body.Add((Paragraph ('Document généré le ' + (Get-Date -Format 'dd/MM/yyyy'))))

$body.Add((Paragraph '1. Présentation générale' 'Heading1'))
$body.Add((Paragraph 'Cette documentation décrit le fonctionnement des API actuellement exposées par le projet EBAC. Les réponses sont au format JSON. Les endpoints protégés utilisent des jetons Bearer Laravel Sanctum.'))
$body.Add((Table @('Élément', 'Valeur') @(
    @('URL de base locale', 'http://127.0.0.1:8000/api/v1'),
    @('Format', 'JSON, sauf envoi de photo en multipart/form-data'),
    @('Authentification', 'Bearer Token — Laravel Sanctum'),
    @('Documentation interactive', 'http://127.0.0.1:8000/api/documentation')
)))

$body.Add((Paragraph '2. En-têtes HTTP' 'Heading1'))
$body.Add((Code-Paragraph 'Accept: application/json'))
$body.Add((Code-Paragraph 'Content-Type: application/json'))
$body.Add((Paragraph 'Pour une route protégée :'))
$body.Add((Code-Paragraph 'Authorization: Bearer VOTRE_TOKEN'))
$body.Add((Paragraph 'Pour envoyer une photo, utiliser multipart/form-data et ne pas fixer manuellement Content-Type dans le navigateur : le client ajoutera la boundary.'))

$body.Add((Paragraph '3. Codes HTTP principaux' 'Heading1'))
$body.Add((Table @('Code', 'Signification') @(
    @('200', 'Requête réussie'),
    @('201', 'Ressource créée'),
    @('202', 'Validation 2FA requise'),
    @('401', 'Token absent ou invalide'),
    @('403', 'Compte inactif ou accès administrateur refusé'),
    @('404', 'Ressource introuvable'),
    @('422', 'Erreur de validation ou opération interdite'),
    @('429', 'Trop de tentatives de connexion'),
    @('500', 'Erreur interne du serveur')
)))

$body.Add((Paragraph '4. API d’authentification' 'Heading1'))

$body.Add((Paragraph '4.1 Connexion' 'Heading2'))
$body.Add((Code-Paragraph 'POST /api/v1/auth/connexion'))
$body.Add((Paragraph 'Accès public, limité à cinq tentatives par minute. Le contrôleur valide email et password, vérifie le hash du mot de passe, l’état du compte et la 2FA. En cas de succès, il remet les tentatives échouées à zéro, enregistre la dernière connexion et crée un token Sanctum.'))
$body.Add((Table @('Champ', 'Type', 'Obligatoire', 'Description') @(
    @('email', 'email', 'Oui', 'Adresse email du compte'),
    @('password', 'string', 'Oui', 'Mot de passe'),
    @('nom_appareil', 'string, max. 100', 'Non', 'Nom associé au token; valeur par défaut : api')
)))
$body.Add((Paragraph 'Exemple de requête :'))
$body.Add((Code-Paragraph '{'))
$body.Add((Code-Paragraph '  "email": "utilisateur@example.com",'))
$body.Add((Code-Paragraph '  "password": "MotDePasse",'))
$body.Add((Code-Paragraph '  "nom_appareil": "Postman"'))
$body.Add((Code-Paragraph '}'))
$body.Add((Paragraph 'Réponse réussie : message, token, token_type=Bearer, redirect_to=/dashboard/index et ressource utilisateur. Si la 2FA est active, l’API retourne 202 avec deux_fa_requise=true et id_users.'))
$body.Add((Paragraph 'Échecs possibles : identifiants incorrects (422), compte inactif/suspendu/bloqué/désactivé (403), limite dépassée (429). Un mot de passe incorrect incrémente tentatives_echouees.'))

$body.Add((Paragraph '4.2 Profil connecté' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/auth/profil'))
$body.Add((Paragraph 'Nécessite un token valide et un compte actif. Retourne les informations de l’utilisateur connecté ainsi que son rôle.'))

$body.Add((Paragraph '4.3 Déconnexion de l’appareil courant' 'Heading2'))
$body.Add((Code-Paragraph 'POST /api/v1/auth/deconnexion'))
$body.Add((Paragraph 'Supprime uniquement le token utilisé par la requête. Les autres appareils restent connectés.'))

$body.Add((Paragraph '4.4 Déconnexion globale' 'Heading2'))
$body.Add((Code-Paragraph 'POST /api/v1/auth/deconnexion-globale'))
$body.Add((Paragraph 'Supprime tous les tokens Sanctum de l’utilisateur. Toutes les sessions API doivent ensuite se reconnecter.'))

$body.Add((Paragraph '5. API d’administration des comptes' 'Heading1'))
$body.Add((Paragraph 'Toutes les routes de cette section nécessitent : un token Sanctum, un compte actif et un rôle dont le code est ADMIN. Sinon, l’API retourne 401 ou 403.'))

$body.Add((Paragraph '5.1 Liste des comptes' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/comptes'))
$body.Add((Table @('Paramètre', 'Description') @(
    @('recherche', 'Recherche dans nom, prénoms, email, matricule et code'),
    @('statut', 'Filtre par statut'),
    @('id_role', 'Filtre par rôle'),
    @('par_page', 'Nombre par page, entre 1 et 100; défaut 15')
)))
$body.Add((Paragraph 'La réponse est paginée et utilise UtilisateurResource. Les comptes supprimés logiquement ne sont pas retournés.'))

$body.Add((Paragraph '5.2 Préparation du formulaire de création' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/comptes/create'))
$body.Add((Paragraph 'Retourne la liste des rôles, les statuts autorisés et les valeurs par défaut : compte actif, statut Actif et 2FA désactivée. Cette route n’enregistre aucune donnée.'))

$body.Add((Paragraph '5.3 Création d’un compte' 'Heading2'))
$body.Add((Code-Paragraph 'POST /api/v1/administration/comptes'))
$body.Add((Table @('Champ', 'Type', 'Obligatoire', 'Règle') @(
    @('code', 'string', 'Oui', 'Maximum 150 caractères'),
    @('user_code', 'string', 'Oui', 'Maximum 150 caractères'),
    @('user_id', 'string', 'Oui', 'Maximum 150 caractères'),
    @('nom', 'string', 'Oui', 'Maximum 150 caractères'),
    @('prenoms', 'string', 'Oui', 'Maximum 150 caractères'),
    @('photo', 'fichier', 'Non', 'JPG, JPEG, PNG ou WEBP; maximum 2 Mo'),
    @('email', 'email', 'Oui', 'Unique dans users; maximum 150 caractères'),
    @('id_role', 'integer', 'Oui', 'Doit exister dans roles.id'),
    @('is_active', 'boolean', 'Non', 'Valeur par défaut true'),
    @('statut', 'enum', 'Non', 'Actif, Suspendu, Bloqué ou Désactivé'),
    @('deux_fa_active', 'boolean', 'Non', 'Valeur par défaut false')
)))
$body.Add((Paragraph 'Le client ne fournit ni password ni matricule. Le serveur génère un mot de passe temporaire de 16 caractères, puis le modèle le stocke sous forme de hash. Le mot de passe lisible est envoyé immédiatement à l’adresse email du membre.'))
$body.Add((Paragraph 'Le matricule est généré une seule fois selon le format EBAC-NNN-PP-AAAA-0000. Exemple : EBAC-DUP-JE-2026-0001. Il conserve les trois premières lettres du nom, les deux premières lettres du prénom, l’année et une séquence unique sur quatre chiffres.'))
$body.Add((Paragraph 'L’administrateur connecté est enregistré dans created_by, created_by_user_id et created_by_user_code. La création utilise CreerCompteDTO et une transaction de base de données.'))
$body.Add((Paragraph 'Exemple JSON sans photo :'))
$body.Add((Code-Paragraph '{'))
$body.Add((Code-Paragraph '  "code": "USR-002",'))
$body.Add((Code-Paragraph '  "user_code": "MEMBRE002",'))
$body.Add((Code-Paragraph '  "user_id": "membre-002",'))
$body.Add((Code-Paragraph '  "nom": "Dupont",'))
$body.Add((Code-Paragraph '  "prenoms": "Jean",'))
$body.Add((Code-Paragraph '  "email": "jean.dupont@example.com",'))
$body.Add((Code-Paragraph '  "id_role": 1,'))
$body.Add((Code-Paragraph '  "is_active": true,'))
$body.Add((Code-Paragraph '  "statut": "Actif",'))
$body.Add((Code-Paragraph '  "deux_fa_active": false'))
$body.Add((Code-Paragraph '}'))

$body.Add((Paragraph '5.4 Consultation d’un compte' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/comptes/{compte}'))
$body.Add((Paragraph 'Le paramètre compte est l’identifiant numérique. Laravel effectue le route model binding et retourne 404 si le compte n’existe pas ou a été supprimé logiquement.'))

$body.Add((Paragraph '5.5 Préparation de l’édition' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/comptes/{compte}/edit'))
$body.Add((Paragraph 'Retourne le compte, la liste des rôles et les statuts disponibles. Cette route ne modifie aucune donnée.'))

$body.Add((Paragraph '5.6 Modification d’un compte' 'Heading2'))
$body.Add((Code-Paragraph 'PUT /api/v1/administration/comptes/{compte}'))
$body.Add((Code-Paragraph 'PATCH /api/v1/administration/comptes/{compte}'))
$body.Add((Paragraph 'PUT et PATCH utilisent la même méthode update. Tous les champs sont facultatifs, mais un champ présent doit être valide. L’email reste unique. Un nouveau password est accepté avec password_confirmation. updated_by reçoit l’identifiant de l’administrateur connecté.'))
$body.Add((Paragraph 'Le matricule n’est jamais accepté dans la requête de modification et reste inchangé, même si le nom ou le prénom est modifié. Une nouvelle photo remplace l’ancienne; photo=null retire la photo.'))

$body.Add((Paragraph '5.7 Suppression d’un compte' 'Heading2'))
$body.Add((Code-Paragraph 'DELETE /api/v1/administration/comptes/{compte}'))
$body.Add((Paragraph 'Effectue une suppression logique : deleted_by est renseigné et deleted_at reçoit la date. L’administrateur ne peut pas supprimer son propre compte; cette tentative retourne 422.'))

$body.Add((Paragraph '6. API du profil utilisateur connecté' 'Heading1'))
$body.Add((Paragraph 'Ces routes nécessitent un token Sanctum et un compte actif, mais pas obligatoirement le rôle ADMIN. Elles agissent toujours sur le compte associé au token; aucun identifiant utilisateur n’est accepté dans l’URL.'))
$body.Add((Paragraph '6.1 Consulter son profil' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/profil'))
$body.Add((Paragraph 'Retourne la ressource complète de l’utilisateur connecté et son rôle.'))
$body.Add((Paragraph '6.2 Préparer l’édition' 'Heading2'))
$body.Add((Code-Paragraph 'GET /api/v1/administration/profil/edit'))
$body.Add((Paragraph 'Retourne le profil et la liste des champs modifiables : nom, prénoms, email, photo et password.'))
$body.Add((Paragraph '6.3 Modifier son profil' 'Heading2'))
$body.Add((Code-Paragraph 'PUT /api/v1/administration/profil'))
$body.Add((Code-Paragraph 'PATCH /api/v1/administration/profil'))
$body.Add((Paragraph 'Permet de modifier nom, prénoms, email, photo et mot de passe. Une modification du mot de passe exige mot_de_passe_actuel, password et password_confirmation. Le matricule, le rôle, le statut, code, user_code et user_id restent protégés.'))

$body.Add((Paragraph '7. Ressource utilisateur retournée' 'Heading1'))
$body.Add((Paragraph 'UtilisateurResource peut retourner : id, matricule, code, user_code, user_id, nom, prenoms, photo, photo_url, email, id_role, is_active, statut, tentatives_echouees, deux_fa_active, cree_le, derniere_connexion, created_by, created_by_user_id, created_by_user_code, updated_by, deleted_by et role. Le password n’est jamais exposé.'))

$body.Add((Paragraph '8. Sécurité et fonctionnement interne' 'Heading1'))
$body.Add((Paragraph 'Les mots de passe utilisateurs sont hashés. Les identifiants SMTP du modèle ConfigurationSmtp utilisent des casts encrypted et le password SMTP est masqué en JSON. Les photos sont stockées sur le disque public dans comptes/. Les suppressions de comptes sont logiques. Les routes administratives appliquent successivement auth:sanctum, compte.actif et administrateur.'))
$body.Add((Paragraph 'Le fichier .env reste la configuration SMTP active actuelle. La table configurations_smtp existe pour conserver des paramètres chiffrés, mais aucune API de gestion SMTP ni chargement dynamique de cette table n’est actuellement exposé.'))

$body.Add((Paragraph '9. Scénario complet d’utilisation' 'Heading1'))
$body.Add((Paragraph '1. L’administrateur appelle POST /auth/connexion avec email et password.'))
$body.Add((Paragraph '2. Il conserve le token retourné et l’envoie dans Authorization: Bearer.'))
$body.Add((Paragraph '3. Il appelle GET /administration/comptes/create pour récupérer les rôles et valeurs autorisées.'))
$body.Add((Paragraph '4. Il appelle POST /administration/comptes. Le serveur génère matricule et mot de passe, crée le compte, chiffre le mot de passe et envoie l’email.'))
$body.Add((Paragraph '5. Il utilise GET /{compte}/edit, puis PUT ou PATCH /{compte} pour modifier le membre.'))
$body.Add((Paragraph '6. Il termine avec POST /auth/deconnexion ou /auth/deconnexion-globale.'))

$body.Add('<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>')

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' + ($body -join '') + '</w:body></w:document>'

$stylesXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="22"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:jc w:val="center"/><w:spacing w:after="240"/></w:pPr><w:rPr><w:b/><w:color w:val="1F4E78"/><w:sz w:val="36"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:pPr><w:jc w:val="center"/><w:spacing w:after="360"/></w:pPr><w:rPr><w:i/><w:color w:val="5B6573"/><w:sz w:val="24"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="360" w:after="160"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="1F4E78"/><w:sz w:val="30"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:color w:val="2F75B5"/><w:sz w:val="26"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:shd w:fill="F3F4F6"/><w:spacing w:before="40" w:after="40"/><w:ind w:left="240"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="19"/></w:rPr></w:style>
  <w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="A6A6A6"/><w:left w:val="single" w:sz="4" w:color="A6A6A6"/><w:bottom w:val="single" w:sz="4" w:color="A6A6A6"/><w:right w:val="single" w:sz="4" w:color="A6A6A6"/><w:insideH w:val="single" w:sz="4" w:color="D9D9D9"/><w:insideV w:val="single" w:sz="4" w:color="D9D9D9"/></w:tblBorders></w:tblPr></w:style>
</w:styles>
'@

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'
$documentRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'
$coreXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Documentation des API EBAC</dc:title><dc:subject>Authentification et gestion des comptes</dc:subject><dc:creator>EBAC</dc:creator><cp:lastModifiedBy>EBAC</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' + (Get-Date).ToUniversalTime().ToString('s') + 'Z</dcterms:created></cp:coreProperties>'
$appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Office Word</Application></Properties>'

$absoluteOutput = [System.IO.Path]::GetFullPath((Join-Path (Get-Location) $OutputPath))
$outputDirectory = Split-Path -Parent $absoluteOutput
[System.IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
$temporaryDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('ebac-docx-' + [guid]::NewGuid())
[System.IO.Directory]::CreateDirectory($temporaryDirectory) | Out-Null

try {
    foreach ($directory in @('_rels', 'word', 'word\_rels', 'docProps')) {
        [System.IO.Directory]::CreateDirectory((Join-Path $temporaryDirectory $directory)) | Out-Null
    }
    $utf8 = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory '[Content_Types].xml'), $contentTypes, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory '_rels\.rels'), $rootRels, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory 'word\document.xml'), $documentXml, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory 'word\styles.xml'), $stylesXml, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory 'word\_rels\document.xml.rels'), $documentRels, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory 'docProps\core.xml'), $coreXml, $utf8)
    [System.IO.File]::WriteAllText((Join-Path $temporaryDirectory 'docProps\app.xml'), $appXml, $utf8)

    if (Test-Path -LiteralPath $absoluteOutput) {
        Remove-Item -LiteralPath $absoluteOutput
    }
    $archive = [System.IO.Compression.ZipFile]::Open($absoluteOutput, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in [System.IO.Directory]::GetFiles($temporaryDirectory, '*', [System.IO.SearchOption]::AllDirectories)) {
            $entryName = $file.Substring($temporaryDirectory.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $file,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }
    Write-Output $absoluteOutput
}
finally {
    if (Test-Path -LiteralPath $temporaryDirectory) {
        Remove-Item -LiteralPath $temporaryDirectory -Recurse -Force
    }
}
