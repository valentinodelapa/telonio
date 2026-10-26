<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Prospetto della liquidazione da consegnare agli eredi: attivo e passivo,
 * imposte del quadro EF, imposta di successione per beneficiario e
 * versamenti. Pagina autonoma, da stampare o salvare in PDF.
 */
$euro = fn(float|int $importo): string => number_format($importo, 2, ',', '.');
$defunto = $dichiarazione->defunto;
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4; margin: 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; }
            h1 { font-size: 14pt; margin: 0 0 2mm; }
            h2 { font-size: 11pt; margin: 6mm 0 2mm; }
            .intestazione { border-bottom: 1px solid #000; padding-bottom: 2mm; margin-bottom: 4mm; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 1mm 1.5mm; vertical-align: top; }
            th { background: #eee; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .tenue { color: #555; }
            .totale td { font-weight: bold; background: #f5f5f5; }
            @media screen { body { max-width: 200mm; margin: 8mm auto; } .stampa { margin-bottom: 4mm; } }
            @media print { .stampa { display: none; } }
        </style>
    </head>
    <body>
        <div class="stampa"><button onclick="window.print()">Stampa</button></div>
        <div class="intestazione">
            <h1><?= Html::e($pageTitle) ?></h1>
            <div><?= Html::e($defunto->codiceFiscale) ?> · <?= Html::e($dichiarazione->tipoDichiarazione->getLabel()) ?> · termine di presentazione <?= Html::data($dichiarazione->getScadenza()) ?></div>
        </div>

        <h2>Attivo ereditario</h2>
        <table>
            <tr><th>Quadro</th><th>Bene</th><th>Diritto e quota</th><th class="num">Valore</th><th>Devoluzione</th></tr>
            <?php foreach ($dati->getBeni() as $bene): ?>
                <tr>
                    <td><?= Html::e($bene->quadro->value) ?></td>
                    <td><?= Html::e($bene->getDescrizioneBreve()) ?></td>
                    <td><?= Html::e($bene->codiceDiritto->getLabel()) ?> <?= Html::e(rtrim(rtrim(number_format($bene->quotaNumeratore, 2, ',', ''), '0'), ',') . '/' . $bene->quotaDenominatore) ?></td>
                    <td class="num"><?= Html::e($euro($bene->getValoreTotale())) ?></td>
                    <td><?php foreach ($dati->getDevoluzioni($bene) as $devoluzione): ?><div><?= Html::e($devoluzione->beneficiarioSuccessione->soggetto->nominativo . ' ' . $devoluzione->getQuotaTesto() . ($devoluzione->agevolazione !== null ? ' (' . $devoluzione->agevolazione->value . ')' : '')) ?></div><?php endforeach; ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="totale"><td colspan="3">Totale attivo</td><td class="num"><?= Html::e($euro($risultato->riepilogo['attivo'])) ?></td><td></td></tr>
            <tr><td colspan="3">Passività deducibili</td><td class="num"><?= Html::e($euro($risultato->riepilogo['passivo'])) ?></td><td></td></tr>
            <tr class="totale"><td colspan="3">Asse ereditario netto</td><td class="num"><?= Html::e($euro($risultato->riepilogo['netto'])) ?></td><td></td></tr>
        </table>

        <h2>Imposte con la dichiarazione (quadro EF)</h2>
        <table>
            <tr><td>Imposta ipotecaria</td><td class="num"><?= Html::e($euro($risultato->ipotecariaDaVersare)) ?></td></tr>
            <tr><td>Imposta catastale</td><td class="num"><?= Html::e($euro($risultato->catastaleDaVersare)) ?></td></tr>
            <tr><td>Tassa ipotecaria</td><td class="num"><?= Html::e($euro($risultato->tassaIpotecaria)) ?></td></tr>
            <tr><td>Imposta di bollo</td><td class="num"><?= Html::e($euro($risultato->bollo)) ?></td></tr>
            <?php if ($risultato->tributiSpeciali > 0): ?><tr><td>Tributi speciali</td><td class="num"><?= Html::e($euro($risultato->tributiSpeciali)) ?></td></tr><?php endif; ?>
            <tr class="totale"><td>Totale addebitato con la dichiarazione</td><td class="num"><?= Html::e($euro($risultato->totaleDaVersare)) ?></td></tr>
        </table>

        <?php if ($risultato->autoliquidazione): ?>
            <h2>Imposta di successione autoliquidata</h2>
            <table>
                <tr><th>Beneficiario</th><th>Parentela</th><th class="num">Quota netta</th><th class="num">Franchigia</th><th class="num">Imponibile</th><th class="num">Aliquota</th><th class="num">Imposta</th></tr>
                <?php foreach ($risultato->quote as $quota): ?>
                    <tr>
                        <td><?= Html::e($quota->beneficiario->soggetto->nominativo) ?></td>
                        <td><?= Html::e($quota->beneficiario->gradoParentela->getLabel()) ?></td>
                        <td class="num"><?= Html::e($euro($quota->attivoNetto)) ?></td>
                        <td class="num"><?= Html::e($euro($quota->franchigia)) ?></td>
                        <td class="num"><?= Html::e($euro($quota->imponibile)) ?></td>
                        <td class="num"><?= Html::e(number_format($quota->aliquota, 0)) ?>%</td>
                        <td class="num"><?= Html::e($euro($quota->impostaNetta)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totale"><td colspan="6">Imposta di successione (da versare)</td><td class="num"><?= Html::e($euro($risultato->successioneDaVersare)) ?></td></tr>
            </table>
            <?php if (count($rate) > 0): ?>
                <h2>Versamenti dell'imposta di successione</h2>
                <table>
                    <tr><th>Versamento</th><th>Scadenza</th><th class="num">Imposta</th><th class="num">Interessi</th><th>Modalità</th></tr>
                    <?php foreach ($rate as $rata): ?>
                        <tr>
                            <td><?= $rata['numero'] === 0 ? ($dichiarazione->numeroRate === null ? 'Unica soluzione' : 'Acconto') : 'Rata ' . Html::e($rata['numero'] . '/' . $dichiarazione->numeroRate) ?></td>
                            <td><?= Html::data($rata['scadenza']) ?></td>
                            <td class="num"><?= Html::e($euro($rata['importo'] / 100)) ?></td>
                            <td class="num"><?= Html::e($euro($rata['interessi'] / 100)) ?></td>
                            <td><?= $rata['conDichiarazione'] ? 'Addebito con la dichiarazione' : 'F24 dei beneficiari (cod. 1539 ' . Html::e($rata['rateazione']) . ($rata['interessi'] > 0 ? ', interessi 1635' : '') . ')' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="tenue">Ogni beneficiario versa la parte d'imposta della propria quota con il codice fiscale del defunto come secondo codice fiscale (codice identificativo 08). Gli eredi sono obbligati in solido per l'imposta complessiva.</p>
            <?php endif; ?>
        <?php endif; ?>
    </body>
</html>
