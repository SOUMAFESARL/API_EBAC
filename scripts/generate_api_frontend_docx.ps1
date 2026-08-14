param(
    [string]$Source = "docs/API_FRONTEND.md",
    [string]$Destination = "docs/Documentation_API_EBAC_Frontend.docx"
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

function Escape-Xml([string]$Value) {
    return [System.Security.SecurityElement]::Escape($Value)
}

function Clean-Markdown([string]$Value) {
    $value = $Value -replace '\*\*([^*]+)\*\*', '$1'
    $value = $value -replace '`([^`]+)`', '$1'
    $value = $value -replace '\[([^]]+)\]\(([^)]+)\)', '$1 ($2)'
    return $value
}

function New-Paragraph([string]$Text, [string]$Style = 'Normal') {
    $escaped = Escape-Xml (Clean-Markdown $Text)
    $runProperties = if ($Style -eq 'Code') { '<w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/><w:color w:val="1E3A5F"/></w:rPr>' } else { '' }
    return "<w:p><w:pPr><w:pStyle w:val=`"$Style`"/></w:pPr><w:r>$runProperties<w:t xml:space=`"preserve`">$escaped</w:t></w:r></w:p>"
}

$sourcePath = (Resolve-Path $Source).Path
$destinationPath = Join-Path (Get-Location) $Destination
$destinationDirectory = Split-Path $destinationPath -Parent
New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null

$body = [System.Text.StringBuilder]::new()
$null = $body.Append((New-Paragraph 'Documentation API EBAC' 'Title'))
$null = $body.Append((New-Paragraph 'Guide complet d’intégration frontend' 'Subtitle'))
$null = $body.Append((New-Paragraph 'Environnement de production : https://api-ebac.severinzran.ci' 'Normal'))
$null = $body.Append((New-Paragraph "Document généré le $(Get-Date -Format 'dd/MM/yyyy')" 'Normal'))
$null = $body.Append('<w:p><w:r><w:br w:type="page"/></w:r></w:p>')

$inCode = $false
foreach ($line in Get-Content -LiteralPath $sourcePath -Encoding UTF8) {
    if ($line -match '^```') {
        $inCode = -not $inCode
        continue
    }
    if ($inCode) {
        $null = $body.Append((New-Paragraph $line 'Code'))
        continue
    }
    if ($line -match '^###\s+(.+)$') {
        $null = $body.Append((New-Paragraph $Matches[1] 'Heading3'))
    } elseif ($line -match '^##\s+(.+)$') {
        $null = $body.Append((New-Paragraph $Matches[1] 'Heading2'))
    } elseif ($line -match '^#\s+(.+)$') {
        $null = $body.Append((New-Paragraph $Matches[1] 'Heading1'))
    } elseif ($line -match '^[-*]\s+(.+)$') {
        $null = $body.Append((New-Paragraph "• $($Matches[1])" 'ListParagraph'))
    } elseif ($line -match '^\|') {
        $null = $body.Append((New-Paragraph $line 'Code'))
    } elseif ([string]::IsNullOrWhiteSpace($line)) {
        $null = $body.Append('<w:p/>')
    } else {
        $null = $body.Append((New-Paragraph $line 'Normal'))
    }
}

$documentXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>$body<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body>
</w:document>
"@

$stylesXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="22"/><w:color w:val="243447"/></w:rPr><w:pPr><w:spacing w:after="120" w:line="300" w:lineRule="auto"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:rPr><w:b/><w:sz w:val="44"/><w:color w:val="123B8F"/></w:rPr><w:pPr><w:jc w:val="center"/><w:spacing w:after="220"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:rPr><w:sz w:val="28"/><w:color w:val="64748B"/></w:rPr><w:pPr><w:jc w:val="center"/><w:spacing w:after="260"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:rPr><w:b/><w:sz w:val="34"/><w:color w:val="102A56"/></w:rPr><w:pPr><w:spacing w:before="320" w:after="180"/><w:keepNext/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:rPr><w:b/><w:sz w:val="28"/><w:color w:val="123B8F"/></w:rPr><w:pPr><w:spacing w:before="260" w:after="140"/><w:keepNext/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:rPr><w:b/><w:sz w:val="24"/><w:color w:val="1E4F91"/></w:rPr><w:pPr><w:spacing w:before="220" w:after="100"/><w:keepNext/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:pPr><w:shd w:fill="EEF3F9"/><w:ind w:left="240" w:right="240"/><w:spacing w:before="60" w:after="60"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:ind w:left="360" w:hanging="180"/><w:spacing w:after="80"/></w:pPr></w:style>
</w:styles>
'@

$contentTypes = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
'@

$rootRels = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
'@

$documentRels = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@

$temporaryDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ("ebac-docx-" + [guid]::NewGuid())
New-Item -ItemType Directory -Path "$temporaryDirectory\_rels", "$temporaryDirectory\word\_rels" -Force | Out-Null
[IO.File]::WriteAllText("$temporaryDirectory\[Content_Types].xml", $contentTypes, [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText("$temporaryDirectory\_rels\.rels", $rootRels, [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText("$temporaryDirectory\word\document.xml", $documentXml, [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText("$temporaryDirectory\word\styles.xml", $stylesXml, [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText("$temporaryDirectory\word\_rels\document.xml.rels", $documentRels, [Text.UTF8Encoding]::new($false))

if (Test-Path -LiteralPath $destinationPath) { Remove-Item -LiteralPath $destinationPath -Force }
$archive = [IO.Compression.ZipFile]::Open($destinationPath, [IO.Compression.ZipArchiveMode]::Create)
foreach ($file in Get-ChildItem -LiteralPath $temporaryDirectory -Recurse -File) {
    $entryName = $file.FullName.Substring($temporaryDirectory.Length + 1).Replace('\', '/')
    [IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $archive,
        $file.FullName,
        $entryName,
        [IO.Compression.CompressionLevel]::Optimal
    ) | Out-Null
}
$archive.Dispose()
Remove-Item -LiteralPath $temporaryDirectory -Recurse -Force
Write-Output $destinationPath
