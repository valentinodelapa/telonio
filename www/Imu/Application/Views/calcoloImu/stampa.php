<?php

use CommSuite\Application\HelperClasses\Html;
use Deleghe\Application\HelperClasses\Importo;

/**
 * Prospetto di calcolo IMU da consegnare al contribuente: immobili con
 * base imponibile, aliquote e imposta, versamenti di acconto e saldo.
 * Pagina autonoma, da stampare o salvare in PDF.
 */
$soggetto = $calcolo->soggetto;
$percentuale = fn(?float $aliquota): string => $aliquota === null ? '' : number_format($aliquota, 2, ',', '') . '%';
$quota = fn(float $valore): string => rtrim(rtrim(number_format($valore, 4, ',', ''), '0'), ',') . '%';
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4 landscape; margin: 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; }
            h1 { font-size: 14pt; margin: 0 0 2mm; }
            h2 { font-size: 11pt; margin: 6mm 0 2mm; }
            .intestazione { display: flex; justify-content: space-between; border-bottom: 1px solid #000; padding-bottom: 2mm; margin-bottom: 4mm; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 1mm 1.5mm; vertical-align: top; }
            th { background: #eee; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .tenue { color: #555; }
            .totale td { font-weight: bold; background: #f5f5f5; }
            .avvisi { margin-top: 4mm; font-size: 8pt; }
            @media screen { body { max-width: 287mm; margin: 8mm auto; } .stampa { margin-bottom: 4mm; } }
            @media print { .stampa { display: none; } }
        </style>
    </head>
    <body>
        <div class="stampa"><button onclick="window.print()">Stampa</button></div>
        <div class="intestazione">
            <div>
                <h1>IMU <?= Html::e($calcolo->anno) ?> – Prospetto di calcolo</h1>
                <div><strong><?= Html::e($soggetto->nominativo) ?></strong> · C.F. <?= Html::e($soggetto->codiceFiscale) ?></div>
            </div>
            <div class="num"><?= Html::e($studio->denominazione) ?><br><span class="tenue">Elaborato il <?= Html::data($calcolo->dataElaborazione, 'd/m/Y') ?></span></div>
        </div>

        <h2>Immobili</h2>
        <table>
            <thead>
                <tr>
                    <th>Immobile</th>
                    <th>Cat.</th>
                    <th>Possesso</th>
                    <th>Periodo</th>
                    <th class="num">Mesi</th>
                    <th>Fattispecie</th>
                    <th class="num">Base imponibile</th>
                    <th class="num">Aliquota</th>
                    <th class="num">Imposta annua</th>
                    <th class="num">Detrazione</th>
                    <th class="num">Acconto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($righi as $rigo): ?>
                    <tr>
                        <td><?= Html::e($rigo->immobile->getIdentificativo()) ?><br><span class="tenue"><?= Html::e($rigo->immobile->getUbicazione()) ?></span></td>
                        <td><?= Html::e($rigo->categoria?->value ?? ($rigo->immobile->tipoCatasto->getLabel())) ?></td>
                        <td><?= Html::e($rigo->titolo->getLabel()) ?> <?= Html::e($quota($rigo->quota)) ?><?= $rigo->utilizzo !== null ? '<br><span class="tenue">' . Html::e($rigo->utilizzo->getLabel()) . '</span>' : '' ?></td>
                        <td><?= Html::data($rigo->dal) ?> – <?= Html::data($rigo->al) ?></td>
                        <td class="num"><?= Html::e($rigo->mesi) ?></td>
                        <td>
                            <?php if ($rigo->isImponibile()): ?>
                                <?= Html::e($rigo->fattispecie->getCodiceTributo()) ?> <?= Html::e($rigo->fattispecie->getLabel()) ?>
                                <?= $rigo->note !== null ? '<br><span class="tenue">' . Html::e($rigo->note) . '</span>' : '' ?>
                            <?php else: ?>
                                Esente: <?= Html::e($rigo->esenzione ?? '') ?>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= $rigo->isImponibile() ? Html::e(Importo::euro($rigo->getBaseImponibileQuota())) : '' ?></td>
                        <td class="num"><?= Html::e($percentuale($rigo->aliquota)) ?></td>
                        <td class="num"><?= Html::e(Importo::euro($rigo->impostaAnnua, true)) ?></td>
                        <td class="num"><?= Html::e(Importo::euro($rigo->detrazioneAnnua, true)) ?></td>
                        <td class="num"><?= Html::e(Importo::euro($rigo->impostaAcconto - $rigo->detrazioneAcconto, true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totale">
                    <td colspan="8">Imposta annua dovuta</td>
                    <td class="num" colspan="3"><?= Html::e(Importo::euro($calcolo->impostaAnnua)) ?></td>
                </tr>
            </tbody>
        </table>

        <h2>Versamenti con modello F24 (sezione IMU e altri tributi locali)</h2>
        <table>
            <thead>
                <tr>
                    <th>Data di versamento</th>
                    <th>Codice comune</th>
                    <th>Codice tributo</th>
                    <th>Acconto/saldo</th>
                    <th class="num">N. immobili</th>
                    <th class="num">Anno</th>
                    <th class="num">Detrazione</th>
                    <th class="num">Importo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versamenti as $versamento): ?>
                    <tr>
                        <td><?= Html::data($versamento->scadenza) ?></td>
                        <td><?= Html::e($versamento->codiceEnte) ?></td>
                        <td><?= Html::e($versamento->getCodice()) ?></td>
                        <td><?= Html::e(trim(($versamento->acconto ? 'Acconto ' : '') . ($versamento->saldo ? 'Saldo' : ''))) ?></td>
                        <td class="num"><?= Html::e($versamento->numeroImmobili) ?></td>
                        <td class="num"><?= Html::e($versamento->annoRiferimento) ?></td>
                        <td class="num"><?= $versamento->detrazioneImu === null ? '' : Html::e(Importo::euro($versamento->detrazioneImu)) ?></td>
                        <td class="num"><?= Html::e(Importo::euro($versamento->importoDebito)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="totale"><td colspan="7">Acconto</td><td class="num"><?= Html::e(Importo::euro($calcolo->acconto)) ?></td></tr>
                <tr class="totale"><td colspan="7">Saldo</td><td class="num"><?= Html::e(Importo::euro($calcolo->saldo)) ?></td></tr>
            </tbody>
        </table>

        <?php if (count($calcolo->getAvvisi()) > 0): ?>
            <div class="avvisi"><strong>Note:</strong><ul><?php foreach ($calcolo->getAvvisi() as $avviso): ?><li><?= Html::e($avviso) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <p class="avvisi tenue">Calcolo ai sensi dell'art. 1, commi 738-783, L. 160/2019, sulla base dei dati catastali e di possesso in archivio e delle aliquote deliberate dal comune. Importi dei versamenti arrotondati all'euro.</p>
    </body>
</html>
