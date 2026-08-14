$ErrorActionPreference = 'Stop'

$planningPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SIG.xlsx'
$backupPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SIG_backup_backlog_2026-08-10.xlsx'

Copy-Item -LiteralPath $planningPath -Destination $backupPath -Force

$csv = @'
Date|Jour|Epic|Reference|Profil|Priorite|Points|Affectation|Livrable|Statut
2026-08-01|Samedi|EP-01|Socle technique|Equipe|Must have|0|Back|Architecture Laravel, modele de donnees, environnements et conventions|En cours
2026-08-02|Dimanche|EP-01|US-001|Administrateur|Must have|5|Back + Front|Connexion securisee et emission des jetons Sanctum|En cours
2026-08-03|Lundi|EP-01|US-001|Administrateur|Must have|0|Back + Front|Double authentification obligatoire des profils sensibles|En cours
2026-08-04|Mardi|EP-01|US-002|Administrateur|Must have|5|Back + Front|Creation compte utilisateur avec attribution du role et notification|En cours
2026-08-05|Mercredi|EP-01|US-003|Administrateur|Must have|3|Back + Front|Suspension, blocage, reactivation et desactivation des comptes|En cours
2026-08-06|Jeudi|EP-01|US-007|Tous profils|Must have|3|Back + Front|Mot de passe oublie et reinitialisation securisee|En cours
2026-08-07|Vendredi|EP-01|Tests EP-01|Equipe|Must have|0|Back + Front|Tests authentification, permissions, securite et documentation API|A faire
2026-08-08|Samedi|EP-03|US-024|Administrateur|Must have|2|Back + Front|Gestion de annee academique et des dates cles|A faire
2026-08-09|Dimanche|EP-03|US-027|Secretariat|Must have|2|Back + Front|Creation des promotions au format PROMO-ANNEE|A faire
2026-08-10|Lundi|EP-03|Tests EP-03|Equipe|Must have|0|Back + Front|Tests des referentiels et revue du Sprint 1|A faire
2026-08-11|Mardi|EP-04|US-029|Secretariat|Must have|3|Back + Front|Creation et mise a jour des Eglises partenaires|A faire
2026-08-12|Mercredi|EP-04|US-032|Secretariat|Must have|2|Back + Front|Rattachement obligatoire de etudiant a son Eglise|A faire
2026-08-13|Jeudi|EP-04|Tests EP-04|Equipe|Must have|0|Back + Front|Tests de cloisonnement des donnees du portail Eglise|A faire
2026-08-14|Vendredi|EP-05|US-034|Secretariat|Must have|3|Back + Front|Enregistrement de la liste des etudiants admissibles|A faire
2026-08-15|Samedi|EP-05|US-035|Secretariat|Must have|5|Back + Front|Generation et envoi des liens securises de pre-inscription|A faire
2026-08-16|Dimanche|EP-05|US-036|Candidat|Must have|5|Back + Front|Formulaire public securise de pre-inscription|A faire
2026-08-17|Lundi|EP-05|US-037|Candidat|Must have|3|Back + Front|Depot et controle des pieces justificatives|A faire
2026-08-18|Mardi|EP-05|Tests EP-05|Equipe|Must have|0|Back + Front|Tests de securite du parcours de pre-inscription|A faire
2026-08-19|Mercredi|EP-06|US-043|Secretariat|Must have|5|Back + Front|Validation de inscription definitive|A faire
2026-08-20|Jeudi|EP-06|US-044|Systeme|Must have|3|Back|Generation automatique et immuable du matricule etudiant|A faire
2026-08-21|Vendredi|EP-06|US-049|Secretariat|Must have|3|Back + Front|Recherche et filtrage des dossiers etudiants|A faire
2026-08-22|Samedi|Integration|Front / Back|Equipe|Must have|0|Back + Front|Integration des portails et corrections fonctionnelles|A faire
2026-08-23|Dimanche|Qualite|DoD|Equipe|Must have|0|Back + Front|Regression, couverture critique, OpenAPI et README|A faire
2026-08-24|Lundi|Livraison|Sprint Review|PO / Equipe|Must have|0|Back + Front|Demonstration, recette de phase, sauvegarde et livraison|A faire
'@

$records = $csv | ConvertFrom-Csv -Delimiter '|'
$excel = $null
$workbook = $null
$worksheet = $null
$recap = $null
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
        if ($book.FullName -eq $planningPath) {
            $workbook = $book
            break
        }
    }

    if ($null -eq $workbook) {
        $workbook = $excel.Workbooks.Open($planningPath)
    }

    $oldWorksheet = $null
    foreach ($sheet in $workbook.Worksheets) {
        if ($sheet.Name -eq 'Planning US Aout 2026') {
            $oldWorksheet = $sheet
            break
        }
    }

    if ($null -ne $oldWorksheet) {
        $excel.DisplayAlerts = $false
        $oldWorksheet.Delete()
        [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($oldWorksheet)
    }

    $worksheet = $workbook.Worksheets.Add($workbook.Worksheets.Item(1))
    $worksheet.Name = 'Planning US Aout 2026'
    $worksheet.Range('A1:J1').Merge()
    $worksheet.Range('A1').Value2 = 'EBAC SIG - Planning Laravel prioritaire du 1er au 24 aout 2026'
    $worksheet.Range('A2:J2').Merge()
    $worksheet.Range('A2').Value2 = 'Perimetre : 15 User Stories Must Have - 52 points - 2 sprints - les autres stories restent au Product Backlog'

    $headers = @('Date', 'Jour', 'Epic', 'ID / Reference', 'Profil', 'Priorite', 'Points', 'Affectation', 'Livrable / tache', 'Statut')
    for ($column = 0; $column -lt $headers.Count; $column++) {
        $worksheet.Cells.Item(3, $column + 1).Value2 = $headers[$column]
    }

    $index = 0
    foreach ($item in $records) {
        $row = 4 + $index
        $worksheet.Cells.Item($row, 1).Value2 = [double] ([datetime] $item.Date).ToOADate()
        $worksheet.Cells.Item($row, 2).Value2 = $item.Jour
        $worksheet.Cells.Item($row, 3).Value2 = $item.Epic
        $worksheet.Cells.Item($row, 4).Value2 = $item.Reference
        $worksheet.Cells.Item($row, 5).Value2 = $item.Profil
        $worksheet.Cells.Item($row, 6).Value2 = $item.Priorite
        $worksheet.Cells.Item($row, 7).Value2 = [double] $item.Points
        $worksheet.Cells.Item($row, 8).Value2 = $item.Affectation
        $worksheet.Cells.Item($row, 9).Value2 = $item.Livrable
        $worksheet.Cells.Item($row, 10).Value2 = $item.Statut
        $index++
    }

    $worksheet.Range('A29:F29').Merge()
    $worksheet.Range('A29').Value2 = 'TOTAL DU PERIMETRE PRIORITAIRE'
    $worksheet.Range('G29').Formula = '=SUM(G4:G27)'
    $worksheet.Range('H29:J29').Merge()
    $worksheet.Range('H29').Value2 = '52 points - capacite de deux sprints'

    $worksheet.Range('A1:J1').Interior.Color = 0x663A0E
    $worksheet.Range('A1:J1').Font.Color = 0xFFFFFF
    $worksheet.Range('A1:J1').Font.Bold = $true
    $worksheet.Range('A1:J1').Font.Size = 16
    $worksheet.Range('A2:J2').Interior.Color = 0xE6F2F8
    $worksheet.Range('A2:J2').Font.Italic = $true
    $worksheet.Range('A3:J3').Interior.Color = 0x25599E
    $worksheet.Range('A3:J3').Font.Color = 0xFFFFFF
    $worksheet.Range('A3:J3').Font.Bold = $true
    $worksheet.Range('A29:J29').Interior.Color = 0xD9EAD3
    $worksheet.Range('A29:J29').Font.Bold = $true
    $worksheet.Range('A4:A27').NumberFormat = 'dd/mm/yyyy'
    $worksheet.Range('A3:J27').Borders.LineStyle = 1
    [void] $worksheet.Range('A3:J27').AutoFilter()

    $widths = @(12, 12, 12, 18, 18, 14, 9, 16, 65, 14)
    for ($column = 0; $column -lt $widths.Count; $column++) {
        $worksheet.Columns.Item($column + 1).ColumnWidth = $widths[$column]
    }
    $worksheet.Range('I4:I27').WrapText = $true
    $worksheet.Range('A1:J29').VerticalAlignment = -4108

    $recap = $workbook.Worksheets.Item($workbook.Worksheets.Count)
    $recap.Range('A2').Value2 = 'Projet EBAC SIG - API Laravel - phase prioritaire du 1er au 24 aout 2026'
    $recap.Range('A25').Value2 = 'Perimetre backlog'
    $recap.Range('B25').Value2 = '15 US Must Have / 52 points'
    $recap.Range('A26').Value2 = 'Backlog total'
    $recap.Range('B26').Value2 = '189 US / 829 points'
    $recap.Range('A27').Value2 = 'Suite du backlog'
    $recap.Range('B27').Value2 = 'A planifier apres le 24 aout 2026'
    [void] $recap.Columns.Item('A').AutoFit()
    [void] $recap.Columns.Item('B').AutoFit()

    $workbook.Save()
    Write-Output "UPDATED=$planningPath"
    Write-Output "ROWS=$($records.Count)"
    Write-Output "POINTS=$($worksheet.Range('G29').Value2)"
    Write-Output "BACKUP=$backupPath"

    if ($createdExcel) {
        $workbook.Close($true)
        $excel.Quit()
    }
} finally {
    foreach ($object in @($recap, $worksheet, $workbook, $excel)) {
        if ($null -ne $object) {
            [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($object)
        }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
