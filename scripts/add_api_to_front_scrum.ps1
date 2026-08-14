$ErrorActionPreference = 'Stop'

$workbookPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SCRUM_M_KOUASSI.xlsx'
$backupPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SCRUM_M_KOUASSI_backup_api.xlsx'

Copy-Item -LiteralPath $workbookPath -Destination $backupPath -Force

$apiRows = @(
    @{ Id='API-001'; Sprint='Sprint 0'; Us='Transverse'; Front='T-001'; Methods='GET'; Endpoint='/api/up'; Work='Initialiser API Laravel, configuration, versionnement /api/v1 et endpoint de sante'; Rules='JSON UTF-8, environnement local, gestion centralisee des erreurs'; Status='Disponible' },
    @{ Id='API-002'; Sprint='Sprint 0'; Us='Transverse'; Front='T-002'; Methods='GET/POST'; Endpoint='/api/v1/*'; Work='Definir contrat client API, CORS, en-tetes Accept JSON et authentification Bearer'; Rules='Reponses JSON homogenes, erreurs 401/403/422, pagination'; Status='Partiel' },
    @{ Id='API-003'; Sprint='Sprint 1'; Us='US-001'; Front='T-101, T-102'; Methods='POST'; Endpoint='/api/v1/auth/connexion'; Work='Authentifier utilisateur, verifier etat compte et emettre jeton Sanctum'; Rules='Anti brute-force, compte actif, erreurs sans fuite information'; Status='Disponible' },
    @{ Id='API-004'; Sprint='Sprint 1'; Us='US-001'; Front='T-103, T-104'; Methods='POST'; Endpoint='/api/v1/auth/2fa/demander ; /2fa/verifier'; Work='Generer OTP hache, envoyer email/SMS, verifier code et expiration'; Rules='6 chiffres, expiration 10 min, usage unique, journalisation'; Status='A developper' },
    @{ Id='API-005'; Sprint='Sprint 1'; Us='US-002'; Front='T-105, T-106'; Methods='GET/POST/PUT/PATCH/DELETE'; Endpoint='/api/v1/administration/comptes'; Work='CRUD comptes, filtres, roles, photo et notification du mot de passe temporaire'; Rules='Email unique, role obligatoire, acces Administrateur'; Status='Disponible' },
    @{ Id='API-006'; Sprint='Sprint 1'; Us='US-002'; Front='T-107, T-108'; Methods='POST'; Endpoint='/api/v1/auth/changer-mot-de-passe'; Work='Changer le mot de passe impose a la premiere connexion'; Rules='Mot de passe actuel ou temporaire, confirmation, complexite, invalider sessions'; Status='A developper' },
    @{ Id='API-007'; Sprint='Sprint 1'; Us='US-007'; Front='T-111, T-112'; Methods='POST'; Endpoint='/api/v1/auth/mot-de-passe-oublie ; /reinitialiser-mot-de-passe'; Work='Envoyer lien a usage unique et reinitialiser le mot de passe'; Rules='Reponse anti-enumeration, jeton expire, complexite, sessions invalidees'; Status='Disponible' },
    @{ Id='API-008'; Sprint='Sprint 1'; Us='US-024'; Front='T-113, T-114'; Methods='GET/POST/PUT/PATCH/DELETE'; Endpoint='/api/v1/administration/annees-academiques'; Work='CRUD annees academiques et dates cles'; Rules='Libelle unique, fin apres debut, aucun chevauchement, une seule active'; Status='A developper' },
    @{ Id='API-009'; Sprint='Sprint 1'; Us='US-027'; Front='T-115, T-116'; Methods='GET/POST/PUT/PATCH/DELETE'; Endpoint='/api/v1/administration/promotions'; Work='CRUD promotions et calcul des effectifs'; Rules='Code PROMO-AAAA unique, annee et niveau obligatoires'; Status='A developper' },
    @{ Id='API-010'; Sprint='Sprint 2'; Us='US-029'; Front='T-201, T-202'; Methods='GET/POST/PUT/PATCH/DELETE'; Endpoint='/api/v1/administration/eglises'; Work='CRUD Eglises partenaires et desactivation logique'; Rules='Code unique, aucune suppression si rattachements actifs'; Status='A developper' },
    @{ Id='API-011'; Sprint='Sprint 2'; Us='US-032'; Front='T-203, T-204'; Methods='GET/PATCH'; Endpoint='/api/v1/secretariat/etudiants/{id}/eglise'; Work='Lister Eglises actives et rattacher Eglise recommandatrice'; Rules='Rattachement obligatoire, Eglise active, tracabilite'; Status='A developper' },
    @{ Id='API-012'; Sprint='Sprint 2'; Us='US-036'; Front='T-209, T-210'; Methods='GET/POST'; Endpoint='/api/v1/preinscriptions/{token}'; Work='Charger invitation et enregistrer formulaire multi-etapes'; Rules='Jeton valide et non utilise, validation serveur, anti-doublon'; Status='A developper' },
    @{ Id='API-013'; Sprint='Sprint 2'; Us='US-036'; Front='T-212'; Methods='GET'; Endpoint='/api/v1/preinscriptions/{token}/statut'; Work='Retourner accuse reception et statut Pre-inscrit'; Rules='Aucune donnee sensible, reference dossier unique'; Status='A developper' }
)

$frontMappings = @{
    'Sprint Backlog du sprint 0' = @{
        7=@('/api/up','Disponible'); 8=@('/api/v1/*','Partiel'); 9=@('/api/v1/*','Partiel')
    }
    'Sprint Backlog du sprint 1' = @{
        7=@('/api/v1/auth/connexion','Disponible'); 8=@('/api/v1/auth/connexion','Disponible');
        9=@('/api/v1/auth/2fa/demander','A developper'); 10=@('/api/v1/auth/2fa/verifier','A developper');
        11=@('/api/v1/administration/comptes','Disponible'); 12=@('/api/v1/administration/comptes','Disponible');
        13=@('/api/v1/auth/changer-mot-de-passe','A developper'); 14=@('/api/v1/auth/changer-mot-de-passe','A developper');
        15=@('/api/v1/auth/mot-de-passe-oublie','Disponible'); 16=@('/api/v1/auth/reinitialiser-mot-de-passe','Disponible');
        17=@('/api/v1/administration/annees-academiques','A developper'); 18=@('/api/v1/administration/annees-academiques','A developper');
        19=@('/api/v1/administration/promotions','A developper'); 20=@('/api/v1/administration/promotions','A developper')
    }
    'Sprint Backlog du sprint 2' = @{
        7=@('/api/v1/administration/eglises','A developper'); 8=@('/api/v1/administration/eglises','A developper');
        9=@('/api/v1/secretariat/etudiants/{id}/eglise','A developper'); 10=@('/api/v1/secretariat/etudiants/{id}/eglise','A developper');
        11=@('/api/v1/preinscriptions/{token}','A developper'); 12=@('/api/v1/preinscriptions/{token}','A developper');
        13=@('/api/v1/preinscriptions/{token}/statut','A developper')
    }
}

$excel = $null
$workbook = $null
$apiSheet = $null
$createdExcel = $false

try {
    try {
        $excel = [Runtime.InteropServices.Marshal]::GetActiveObject('Excel.Application')
    } catch {
        $excel = New-Object -ComObject Excel.Application
        $excel.Visible = $false
        $excel.DisplayAlerts = $false
        $createdExcel = $true
    }

    foreach ($book in $excel.Workbooks) {
        if ($book.FullName -eq $workbookPath) {
            $workbook = $book
            break
        }
    }
    if ($null -eq $workbook) {
        $workbook = $excel.Workbooks.Open($workbookPath)
    }

    foreach ($sheetName in $frontMappings.Keys) {
        $sheet = $workbook.Worksheets.Item($sheetName)
        $sheet.Range('L6').Value2 = 'Endpoint API Laravel'
        $sheet.Range('M6').Value2 = 'Statut API'
        $sheet.Range('K6').Copy()
        $sheet.Range('L6:M6').PasteSpecial(-4122)
        $excel.CutCopyMode = $false

        foreach ($row in $frontMappings[$sheetName].Keys) {
            $sheet.Cells.Item($row, 12).Value2 = $frontMappings[$sheetName][$row][0]
            $sheet.Cells.Item($row, 13).Value2 = $frontMappings[$sheetName][$row][1]
        }
        $sheet.Columns.Item('L').ColumnWidth = 42
        $sheet.Columns.Item('M').ColumnWidth = 16
        $sheet.Range('L7:M30').WrapText = $true
        [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($sheet)
    }

    $oldSheet = $null
    foreach ($sheet in $workbook.Worksheets) {
        if ($sheet.Name -eq 'Backlog API Laravel') {
            $oldSheet = $sheet
            break
        }
    }
    if ($null -ne $oldSheet) {
        $excel.DisplayAlerts = $false
        $oldSheet.Delete()
        [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($oldSheet)
    }

    $apiSheet = $workbook.Worksheets.Add($workbook.Worksheets.Item(1))
    $apiSheet.Name = 'Backlog API Laravel'
    $apiSheet.Range('A1:J1').Merge()
    $apiSheet.Range('A1').Value2 = 'EBAC SIG - Correspondance Frontend / API Laravel'
    $apiSheet.Range('A2:J2').Merge()
    $apiSheet.Range('A2').Value2 = 'Endpoints et traitements backend necessaires aux taches frontend de M. KOUASSI'

    $headers = @('ID API','Sprint','User Story','Tache front liee','Methodes','Endpoint(s)','Travail backend','Regles / validations','Assigne a','Statut API')
    for ($column = 0; $column -lt $headers.Count; $column++) {
        $apiSheet.Cells.Item(4, $column + 1).Value2 = $headers[$column]
    }

    $rowNumber = 5
    foreach ($item in $apiRows) {
        $apiSheet.Cells.Item($rowNumber, 1).Value2 = $item.Id
        $apiSheet.Cells.Item($rowNumber, 2).Value2 = $item.Sprint
        $apiSheet.Cells.Item($rowNumber, 3).Value2 = $item.Us
        $apiSheet.Cells.Item($rowNumber, 4).Value2 = $item.Front
        $apiSheet.Cells.Item($rowNumber, 5).Value2 = $item.Methods
        $apiSheet.Cells.Item($rowNumber, 6).Value2 = $item.Endpoint
        $apiSheet.Cells.Item($rowNumber, 7).Value2 = $item.Work
        $apiSheet.Cells.Item($rowNumber, 8).Value2 = $item.Rules
        $apiSheet.Cells.Item($rowNumber, 9).Value2 = 'M. ZRAN'
        $apiSheet.Cells.Item($rowNumber, 10).Value2 = $item.Status
        $rowNumber++
    }

    $apiSheet.Range('A1:J1').Interior.Color = 0x663A0E
    $apiSheet.Range('A1:J1').Font.Color = 0xFFFFFF
    $apiSheet.Range('A1:J1').Font.Bold = $true
    $apiSheet.Range('A1:J1').Font.Size = 16
    $apiSheet.Range('A2:J2').Interior.Color = 0xE6F2F8
    $apiSheet.Range('A4:J4').Interior.Color = 0x25599E
    $apiSheet.Range('A4:J4').Font.Color = 0xFFFFFF
    $apiSheet.Range('A4:J4').Font.Bold = $true
    $apiSheet.Range("A4:J$($rowNumber - 1)").Borders.LineStyle = 1
    [void] $apiSheet.Range("A4:J$($rowNumber - 1)").AutoFilter()
    $apiSheet.Range("F5:H$($rowNumber - 1)").WrapText = $true

    $widths = @(12,12,14,20,20,45,55,55,14,16)
    for ($column = 0; $column -lt $widths.Count; $column++) {
        $apiSheet.Columns.Item($column + 1).ColumnWidth = $widths[$column]
    }
    $apiSheet.Range("A1:J$($rowNumber - 1)").VerticalAlignment = -4160

    $workbook.Save()
    Write-Output "UPDATED=$workbookPath"
    Write-Output "API_ITEMS=$($apiRows.Count)"
    Write-Output 'FRONT_SHEETS_UPDATED=3'
    Write-Output "BACKUP=$backupPath"

    if ($createdExcel) {
        $workbook.Close($true)
        $excel.Quit()
    }
} finally {
    foreach ($object in @($apiSheet, $workbook, $excel)) {
        if ($null -ne $object) {
            [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($object)
        }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
