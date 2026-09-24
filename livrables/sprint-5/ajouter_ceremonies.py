from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from xml.sax.saxutils import escape
import xml.etree.ElementTree as ET

source = Path(r'C:\Users\CPOSEMAN-LAP\Downloads\Sprint_5_EBAC_Backlog.xlsx')
target = Path(__file__).parent / 'Sprint_5_EBAC_Backlog_avec_retro_et_review.xlsx'

def worksheet(title, subtitle, rows):
    ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
    data = []
    merges = ['A1:F1', 'A2:F2']
    def row(num, values, style=6, height=48):
        cells = ''.join(f'<c r="{chr(65+i)}{num}" s="{style}" t="inlineStr"><is><t xml:space="preserve">{escape(str(v))}</t></is></c>' for i,v in enumerate(values))
        data.append(f'<row r="{num}" ht="{height}" customHeight="1">{cells}</row>')
    row(1,[title],14,32)
    row(2,[subtitle],12,48)
    for num,item in enumerate(rows,4):
        kind, values = item
        row(num,values,5 if kind in ('section','header') else 6,30 if kind in ('section','header') else 64)
        if kind=='section': merges.append(f'A{num}:F{num}')
    end=len(rows)+3
    return (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="{ns}">'
        f'<dimension ref="A1:F{end}"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        '<sheetFormatPr defaultRowHeight="48"/><cols>'+''.join(f'<col min="{i}" max="{i}" width="{w}" customWidth="1"/>' for i,w in enumerate([27,48,45,32,30,32],1))+'</cols>'
        '<sheetData>'+''.join(data)+'</sheetData>'
        f'<mergeCells count="{len(merges)}">'+''.join(f'<mergeCell ref="{m}"/>' for m in merges)+'</mergeCells>'
        '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>')

retro=[
 ('section',['1. Cadre de la rétrospective']),
 ('header',['Sprint concerné','Période','Date de réunion','Animateur','Participants','Objectif']),
 ('data',['Sprint 5','24/09/2026 au 07/10/2026 — période du backlog fourni','À renseigner','À désigner','Équipe API, frontend et facilitation — à confirmer','Choisir des améliorations concrètes pour le sprint suivant']),
 ('section',['2. Retour sur les actions de la précédente rétrospective']),
 ('header',['Action précédente','Résultat observé / preuve','Difficulté rencontrée','Responsable','Statut réel','Suite à donner']),
 ('data',['À renseigner','À renseigner','À renseigner','À renseigner','À renseigner','Poursuivre / ajuster / clôturer']),
 ('section',['3. Bilan collectif — constats à renseigner pendant la réunion']),
 ('header',['Axe','Questions pour l’équipe','Constats factuels / exemples','Impact sur le sprint','Cause identifiée','Idée d’amélioration']),
 ('data',['À conserver','Qu’est-ce qui a facilité la collaboration API/frontend ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('data',['À améliorer','Où avons-nous perdu du temps : contrats, dépendances, validations ou intégration ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('data',['À arrêter','Quelle pratique a créé des reprises ou du travail inutile ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('data',['À commencer','Quel changement aiderait la qualité ou la prévisibilité ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('data',['Qualité et tests','Quels défauts ont échappé aux tests ou à la recette ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('data',['Charge et coordination','Les estimations et la disponibilité reflétaient-elles le travail réel ?','À renseigner','À renseigner','À analyser','À proposer']),
 ('section',['4. Plan d’amélioration — retenir au maximum trois actions mesurables']),
 ('header',['ID / priorité','Action décidée','Indicateur de réussite','Responsable','Échéance','Statut / suivi']),
 ('data',['RET-01 / à prioriser','À décider','À définir (mesurable)','À désigner','À fixer','À décider']),
 ('data',['RET-02 / à prioriser','À décider','À définir (mesurable)','À désigner','À fixer','À décider']),
 ('data',['RET-03 / à prioriser','À décider','À définir (mesurable)','À désigner','À fixer','À décider']),
 ('section',['5. Clôture']),
 ('header',['Appréciation du sprint','Enseignement principal','Engagement de l’équipe','Prochain point de suivi','Compte rendu par','Date de mise à jour']),
 ('data',['À recueillir','À renseigner','À formuler','À fixer','À renseigner','À renseigner']),
]
review=[
 ('section',['1. Cadre de la revue du sprint précédent']),
 ('header',['Sprint concerné','Période','Date de revue','Objectif du sprint','Participants','Responsable de la revue']),
 ('data',['Sprint 4 — précédent du Sprint 5','À renseigner','À renseigner','À reprendre du backlog Sprint 4','Équipe et parties prenantes — à renseigner','À désigner']),
 ('section',['2. Bilan de l’objectif et des engagements']),
 ('header',['Indicateur','Prévu','Réalisé','Écart / explication','Source / preuve','Décision']),
 ('data',['Objectif du sprint','À renseigner','Atteint / partiel / non atteint — à évaluer','À renseigner','Sprint Goal et démonstration','À décider']),
 ('data',['Stories terminées','À renseigner','À renseigner','À renseigner','Backlog Sprint 4 et DoD','À décider']),
 ('data',['Charge ou points','À renseigner avec unité','À renseigner avec la même unité','À renseigner','Suivi du sprint','À décider']),
 ('data',['Anomalies restantes','À renseigner','À renseigner','À renseigner','Tickets et résultats de recette','À prioriser']),
 ('section',['3. Incrément démontré — une ligne par user story ou fonctionnalité']),
 ('header',['ID / fonctionnalité','Résultat attendu','Démonstration / preuve','État selon DoD','Retour des parties prenantes','Décision produit']),
 ('data',['À renseigner — API','À renseigner','Lien test / Swagger / environnement','À vérifier','À recueillir','Accepter / ajuster / reporter — à décider']),
 ('data',['À renseigner — frontend','À renseigner','Lien écran / scénario de démonstration','À vérifier','À recueillir','Accepter / ajuster / reporter — à décider']),
 ('data',['À renseigner — intégration','À renseigner','Scénario de bout en bout','À vérifier','À recueillir','Accepter / ajuster / reporter — à décider']),
 ('data',['À renseigner','À renseigner','À renseigner','À vérifier','À recueillir','À décider']),
 ('section',['4. Retours et ajustements du backlog produit']),
 ('header',['ID retour','Retour / besoin constaté','Impact / valeur attendue','Priorité','Responsable','Décision / ticket lié']),
 ('data',['REV-01','À recueillir','À évaluer','À prioriser','À désigner','À décider']),
 ('data',['REV-02','À recueillir','À évaluer','À prioriser','À désigner','À décider']),
 ('data',['REV-03','À recueillir','À évaluer','À prioriser','À désigner','À décider']),
 ('section',['5. Travail inachevé et impact sur le Sprint 5 — aucun report automatique']),
 ('header',['Élément Sprint 4','Reste à faire / blocage','Dépendance Sprint 5','Estimation restante','Priorité proposée','Décision de planification']),
 ('data',['À renseigner','À préciser','US / tâche du backlog Sprint 5 à lier','À estimer avec unité','À arbitrer','Reprioriser / inclure / différer — à décider']),
 ('data',['À renseigner','À préciser','À identifier','À estimer avec unité','À arbitrer','À décider']),
 ('section',['6. Synthèse de la revue']),
 ('header',['Résultat de l’objectif','Valeur livrée','Décisions principales','Risques / dépendances','Actions et responsables','Date / auteur du compte rendu']),
 ('data',['À évaluer','À décrire avec preuves','À consigner','À préciser','À attribuer','À renseigner']),
]

with ZipFile(source) as z:
    contents={info.filename:z.read(info.filename) for info in z.infolist()}
original=contents.copy()
wb=contents['xl/workbook.xml'].decode('utf-8')
rels=contents['xl/_rels/workbook.xml.rels'].decode('utf-8')
ct=contents['[Content_Types].xml'].decode('utf-8')
new_sheets=[('Rétrospective Sprint 5',retro,'Rétrospective — Sprint 5','Trame à compléter à la fin du Sprint 5. Les questions sont des pistes de discussion ; aucun résultat ni incident n’est supposé constaté.'),
 ('Review Sprint 4',review,'Sprint Review — Sprint 4','Revue du sprint précédent : résultats, démonstration et retours produit. Le backlog et les résultats du Sprint 4 n’étant pas fournis, tous les bilans restent à renseigner.')]
for idx,(name,rows,title,subtitle) in enumerate(new_sheets,2):
    rid=f'rId{idx+3}'
    wb=wb.replace('</sheets>',f'<sheet name="{name}" sheetId="{idx}" r:id="{rid}"/></sheets>')
    rels=rels.replace('</Relationships>',f'<Relationship Id="{rid}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet{idx}.xml"/></Relationships>')
    ct=ct.replace('</Types>',f'<Override PartName="/xl/worksheets/sheet{idx}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>')
    contents[f'xl/worksheets/sheet{idx}.xml']=worksheet(title,subtitle,rows).encode('utf-8')
contents['xl/workbook.xml']=wb.encode('utf-8')
contents['xl/_rels/workbook.xml.rels']=rels.encode('utf-8')
contents['[Content_Types].xml']=ct.encode('utf-8')
with ZipFile(target,'w',ZIP_DEFLATED) as z:
    for name,data in contents.items(): z.writestr(name,data)
with ZipFile(target) as z:
    assert z.testzip() is None
    for name in z.namelist():
        if name.endswith(('.xml','.rels')): ET.fromstring(z.read(name))
    assert z.read('xl/worksheets/sheet1.xml')==original['xl/worksheets/sheet1.xml']
    assert z.read('xl/styles.xml')==original['xl/styles.xml']
print(target)
print('Classeur initial préservé ; deux feuilles ajoutées ; XML validé.')
