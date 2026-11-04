<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Stampa di controllo della dichiarazione (frontespizio e campi compilati
 * dei quadri), da far firmare al contribuente e conservare con la ricevuta
 * telematica. Non sostituisce il modello ministeriale.
 */
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4; margin: 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; margin: 0; }
            h1 { font-size: 14pt; margin: 0 0 2mm; }
            h2 { font-size: 11pt; margin: 5mm 0 2mm; break-after: avoid; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 1mm 1.5mm; vertical-align: top; }
            th { background: #eee; text-align: left; }
            tr { break-inside: avoid; }
            .num { text-align: right; white-space: nowrap; }
            .codice { white-space: nowrap; font-family: monospace; }
            .tenue { color: #555; }
            .firma { margin-top: 12mm; display: flex; justify-content: space-between; }
            @media screen { body { max-width: 190mm; margin: 8mm auto; } }
            @media print { .stampa { display: none; } }
        </style>
    </head>
    <body>
        <div class="stampa"><button onclick="window.print()">Stampa</button></div>
        <h1>Dichiarazione <?= Html::e($dichiarazione->modello->getLabel()) ?> <?= Html::e($dichiarazione->getAnnoModello()) ?> – anno d'imposta <?= Html::e($dichiarazione->anno) ?></h1>
        <div>
            Contribuente: <strong><?= Html::e($soggetto->nominativo) ?></strong> C.F. <?= Html::e($soggetto->codiceFiscale) ?>
            · <?= Html::e($dichiarazione->tipologia->getLabel()) ?> · <?= Html::e($dichiarazione->stato->getLabel()) ?>
            <?php if ($dichiarazione->dataPresentazione !== null): ?> il <?= Html::data($dichiarazione->dataPresentazione) ?><?= $dichiarazione->protocollo !== null ? ', protocollo ' . Html::e($dichiarazione->protocollo) : '' ?><?php endif; ?>
        </div>
        <div class="tenue">
            <?php if ($naturaGiuridica !== null): ?>Natura giuridica: <?= Html::e($dichiarazione->naturaGiuridica . ' - ' . $naturaGiuridica) ?> · <?php endif; ?>
            Sottoscrittore: <?= Html::e($dichiarazione->dichiarante === null ? 'il contribuente' : $dichiarazione->dichiarante->nominativo . ' (' . $dichiarazione->dichiarante->codiceFiscale . ', carica ' . $dichiarazione->codiceCarica?->value . ')') ?>
            <?php if ($dichiarazione->intermediario !== null): ?> · Intermediario: <?= Html::e($dichiarazione->intermediario->soggetto->nominativo) ?>, impegno del <?= Html::data($dichiarazione->dataImpegno) ?><?php endif; ?>
        </div>
        <?php foreach ($sezioni as ['modulo' => $modulo, 'etichetta' => $etichetta, 'quadro' => $sigla, 'denominazione' => $denominazione, 'righe' => $righe]): ?>
            <h2>Quadro <?= Html::e($sigla) ?> – <?= Html::e($denominazione) ?><?= $modulo > 1 ? ' · ' . Html::e($etichetta) : '' ?></h2>
            <table>
                <thead><tr><th style="width: 18mm">Rigo</th><th>Descrizione</th><th style="width: 32mm" class="num">Valore</th></tr></thead>
                <tbody>
                    <?php foreach ($righe as ['campo' => $campo, 'valore' => $valore]): ?>
                        <tr>
                            <td class="codice"><?= Html::e($campo->getEtichettaRigo()) ?> c. <?= Html::e($campo->getColonna()) ?></td>
                            <td><?= Html::e($campo->descrizione) ?></td>
                            <td class="num"><?= Html::e($campo->formato?->mostra($valore) ?? $valore) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        <div class="firma">
            <div>Data ____________________</div>
            <div>Firma del dichiarante ______________________________</div>
        </div>
    </body>
</html>
