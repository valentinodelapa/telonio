<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Inizio delle pagine autonome da stampare o salvare in PDF: $pageTitle,
 * $soggetto, $titoloStampa, $dal, $al; $orizzontale per il formato.
 */
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4 <?= ($orizzontale ?? false) ? 'landscape' : 'portrait' ?>; margin: 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #000; margin: 0; }
            h1 { font-size: 13pt; margin: 0 0 1mm; }
            h2 { font-size: 10.5pt; margin: 5mm 0 2mm; }
            .intestazione { display: flex; justify-content: space-between; border-bottom: 1px solid #000; padding-bottom: 2mm; margin-bottom: 4mm; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 0.8mm 1.2mm; vertical-align: top; }
            th { background: #eee; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .tenue { color: #555; }
            .totale td { font-weight: bold; background: #f5f5f5; }
            .livello-M td { font-weight: bold; }
            .livello-C td { font-style: italic; }
            tr.inizio-registrazione td { border-top: 1.5px solid #000; }
            @media screen { body { max-width: <?= ($orizzontale ?? false) ? '287mm' : '200mm' ?>; margin: 8mm auto; } .stampa { margin-bottom: 4mm; } }
            @media print { .stampa { display: none; } }
        </style>
    </head>
    <body>
        <div class="stampa"><button onclick="window.print()">Stampa</button></div>
        <div class="intestazione">
            <div>
                <h1><?= Html::e($titoloStampa) ?></h1>
                <div><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($soggetto->partitaIva ?? $soggetto->codiceFiscale) ?></div>
            </div>
            <div class="num">
                <?php if (isset($dal, $al)): ?><div><?= Html::data($dal) ?> - <?= Html::data($al) ?></div><?php endif; ?>
                <div class="tenue">Stampato il <?= Html::e(date('d/m/Y')) ?></div>
            </div>
        </div>
