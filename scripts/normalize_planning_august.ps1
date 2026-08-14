$ErrorActionPreference = 'Stop'

$planningPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SIG.xlsx'
$backupPath = 'C:\Users\CPOSEMAN-LAP\Downloads\EBAC_SIG_backup_before_august_only.xlsx'

Copy-Item -LiteralPath $planningPath -Destination $backupPath -Force

$plans = @(
    @{ Sheet = 'Administration'; StartRow = 5; Dates = @('2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05'); Subtitle = 'Du 1er au 5 aout 2026 - Fondations : comptes, roles, securite, parametres systeme' },
    @{ Sheet = 'Secretariat'; StartRow = 5; Dates = @('2026-08-06', '2026-08-07', '2026-08-08', '2026-08-09', '2026-08-10', '2026-08-11'); Subtitle = 'Du 6 au 11 aout 2026 - Dossiers etudiants, promotions, finances, documents officiels' },
    @{ Sheet = 'Enseignant_Etudiant'; StartRow = 5; Dates = @('2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15', '2026-08-16', '2026-08-17'); Subtitle = 'Du 12 au 17 aout 2026 - Pedagogie enseignant et portail etudiant' },
    @{ Sheet = 'Direction'; StartRow = 5; Dates = @('2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21'); Subtitle = 'Du 18 au 21 aout 2026 - Pilotage, validation officielle, diplome' },
    @{ Sheet = 'Eglise'; StartRow = 5; Dates = @('2026-08-22', '2026-08-23', '2026-08-24'); Subtitle = 'Du 22 au 24 aout 2026 - Portail Eglise, etudiants recommandes et stagiaires A3' }
)

$dayNames = @('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')
$excel = $null
$workbook = $null
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

    foreach ($plan in $plans) {
        $worksheet = $workbook.Worksheets.Item($plan.Sheet)
        $worksheet.Range('A2').Value2 = $plan.Subtitle

        for ($index = 0; $index -lt $plan.Dates.Count; $index++) {
            $date = [datetime] $plan.Dates[$index]
            $row = $plan.StartRow + $index
            $worksheet.Cells.Item($row, 1).Value2 = [double] $date.ToOADate()
            $worksheet.Cells.Item($row, 2).Value2 = $dayNames[[int] $date.DayOfWeek]

            $week = if ($date.Day -le 6) { 1 } elseif ($date.Day -le 12) { 2 } elseif ($date.Day -le 18) { 3 } else { 4 }
            $worksheet.Cells.Item($row, 3).Value2 = "Semaine $week"
        }

        [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($worksheet)
    }

    $recap = $workbook.Worksheets.Item($workbook.Worksheets.Count)
    $recap.Range('A2').Value2 = 'Projet EBAC SIG - API Laravel - du 1er au 24 aout 2026'
    $recap.Range('A15').Value2 = 'Semaine 1 (1-6 aout)'
    $recap.Range('A16').Value2 = 'Semaine 2 (7-12 aout)'
    $recap.Range('A17').Value2 = 'Semaine 3 (13-18 aout)'
    $recap.Range('A18').Value2 = 'Semaine 4 (19-24 aout)'

    $workbook.Save()

    Write-Output "UPDATED=$planningPath"
    Write-Output 'START=01/08/2026'
    Write-Output 'END=24/08/2026'
    Write-Output 'JULY_DATES=0'
    Write-Output "BACKUP=$backupPath"

    [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($recap)

    if ($createdExcel) {
        $workbook.Close($true)
        $excel.Quit()
    }
} finally {
    foreach ($object in @($workbook, $excel)) {
        if ($null -ne $object) {
            [void] [Runtime.InteropServices.Marshal]::ReleaseComObject($object)
        }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
