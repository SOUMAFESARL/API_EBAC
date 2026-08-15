<?php

$source = __DIR__.'/../docs/DICTIONNAIRE_COMPLET_API_EBAC.md';
$target = $argv[1] ?? __DIR__.'/../docs/DICTIONNAIRE_COMPLET_API_EBAC.docx';
$markdown = file_get_contents($source);

if ($markdown === false) {
    throw new RuntimeException('Document Markdown introuvable.');
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function paragraph(string $text, ?string $style = null, bool $code = false): string
{
    $properties = $style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';
    $runProperties = $code ? '<w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/><w:color w:val="17365D"/></w:rPr>' : '';
    $space = preg_replace('/^\s+/', '', $text) !== $text ? ' xml:space="preserve"' : '';

    return '<w:p>'.$properties.'<w:r>'.$runProperties.'<w:t'.$space.'>'.xml($text).'</w:t></w:r></w:p>';
}

$body = '';
$inCode = false;

foreach (preg_split('/\R/u', $markdown) as $line) {
    if (str_starts_with($line, '```')) {
        $inCode = ! $inCode;
        continue;
    }

    if ($inCode) {
        $body .= paragraph($line === '' ? ' ' : $line, null, true);
        continue;
    }

    if (preg_match('/^(#{1,3})\s+(.+)$/u', $line, $matches)) {
        $style = match (strlen($matches[1])) { 1 => 'Title', 2 => 'Heading1', default => 'Heading2' };
        $body .= paragraph($matches[2], $style);
    } elseif (str_starts_with($line, '|')) {
        $body .= paragraph(trim($line, "| \t"), 'TableText');
    } elseif (preg_match('/^-\s+(.+)$/u', $line, $matches)) {
        $body .= paragraph('• '.$matches[1], 'ListParagraph');
    } else {
        $body .= paragraph($line === '' ? ' ' : preg_replace('/[`*_]/', '', $line));
    }
}

$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    .'<w:body>'.$body
    .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
    .'</w:body></w:document>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="21"/></w:rPr></w:style>'
    .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:rPr><w:b/><w:color w:val="102A56"/><w:sz w:val="36"/></w:rPr></w:style>'
    .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:rPr><w:b/><w:color w:val="123B8F"/><w:sz w:val="30"/></w:rPr></w:style>'
    .'<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:rPr><w:b/><w:color w:val="17365D"/><w:sz w:val="25"/></w:rPr></w:style>'
    .'<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:ind w:left="360"/></w:pPr></w:style>'
    .'<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:pPr><w:spacing w:after="40"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="17"/></w:rPr></w:style>'
    .'</w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    .'<Default Extension="xml" ContentType="application/xml"/>'
    .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    .'</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    .'</Relationships>';

$documentRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    .'</Relationships>';

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Impossible de créer le document Word.');
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('word/document.xml', $document);
$zip->addFromString('word/styles.xml', $styles);
$zip->addFromString('word/_rels/document.xml.rels', $documentRels);
$zip->close();

echo $target.PHP_EOL;
