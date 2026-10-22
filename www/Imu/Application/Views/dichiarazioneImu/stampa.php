<?php

use CommSuite\Application\HelperClasses\Html;
use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\RigoDichiarazioneImu;

/**
 * Stampa di controllo della dichiarazione IMU (riepilogo dei dati
 * trasmessi), da far firmare al contribuente e conservare con la ricevuta
 * telematica. Non sostituisce il modello ministeriale.
 */
$soggetto = $dichiarazione->soggetto;
$riduzioni = $dichiarazione->isEnc() ? RigoDichiarazioneImu::RIDUZIONI_ENC : RigoDichiarazioneImu::RIDUZIONI;
$saldo = $dichiarazione->getSaldoQuadroC();
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4 landscape; margin: 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; margin: 0; }
            h1 { font-size: 14pt; margin: 0 0 2mm; }
            h2 { font-size: 11pt; margin: 5mm 0 2mm; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 1mm 1.5mm; vertical-align: top; }
            th { background: #eee; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .tenue { color: #555; }
            .firma { margin-top: 12mm; display: flex; justify-content: space-between; }
            @media screen { body { max-width: 287mm; margin: 8mm auto; } }
            @media print { .stampa { display: none; } }
        </style>
    </head>
    <body>
        <div class="stampa"><button onclick="window.print()">Stampa</button></div>
        <h1>Dichiarazione IMU <?= Html::e($dichiarazione->anno) ?> – <?= Html::e($dichiarazione->modello->getLabel()) ?></h1>
        <div>Comune di <strong><?= Html::e($dichiarazione->denominazioneComune) ?></strong> (<?= Html::e($dichiarazione->codiceComune) ?>) · <?= Html::e($dichiarazione->tipologia->getLabel()) ?>
            · Contribuente: <strong><?= Html::e($soggetto->nominativo) ?></strong> C.F. <?= Html::e($soggetto->codiceFiscale) ?>
            <?= $dichiarazione->dichiarante !== null ? ' · Dichiarante: ' . Html::e($dichiarazione->dichiarante->nominativo) . ' (carica ' . Html::e($dichiarazione->codiceCarica?->value) . ')' : '' ?></div>
        <?php foreach (($dichiarazione->isEnc() ? ['A' => 'Quadro A - Immobili imponibili', 'B' => 'Quadro B - Immobili esenti o parzialmente imponibili'] : ['A' => 'Quadro A - Immobili']) as $quadro => $titolo): ?>
            <h2><?= Html::e($titolo) ?></h2>
            <table>
                <thead><tr><th>N.</th><th>Immobile</th><th>Caratteristica</th><th class="num">Valore</th><th class="num">% possesso</th><th>Riduzione / esenzione</th><th>Vicenda</th></tr></thead>
                <tbody>
                    <?php foreach ($righi as $indice => $rigo): ?>
                        <?php if ($rigo->quadro !== $quadro) continue; ?>
                        <tr>
                            <td><?= Html::e($rigo->numeroOrdine) ?> (<?= Html::e($rigo->progressivoImmobile) ?>)</td>
                            <td><?= Html::e($rigo->immobile->getIdentificativo()) ?><br><span class="tenue"><?= Html::e($rigo->immobile->getUbicazione()) ?></span></td>
                            <td><?= Html::e($rigo->caratteristica->getLabel()) ?><?= ($utilizzi[$indice] ?? null) !== null ? '<br><span class="tenue">' . Html::e(implode(', ', array_map(fn($attivita) => $attivita->getLabel(), $utilizzi[$indice]->getAttivita()))) . '</span>' : '' ?></td>
                            <td class="num"><?= Html::e(Importo::euro($rigo->valore)) ?></td>
                            <td class="num"><?= Html::e(number_format($rigo->percentualePossesso, 2, ',', '')) ?></td>
                            <td><?= Html::e($riduzioni[$rigo->riduzione] ?? '') ?><?= $rigo->esenzione !== '0' ? '<br>' . Html::e(($dichiarazione->isEnc() && ($quadro === 'B')) ? 'Esente' : (RigoDichiarazioneImu::ESENZIONI[$rigo->esenzione] ?? '')) : '' ?></td>
                            <td><?= Html::e($rigo->getVicenda()) ?><?= $rigo->dataVariazione !== null ? ' ' . Html::data($rigo->dataVariazione) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
        <?php if ($dichiarazione->isEnc()): ?>
            <h2>Quadro C - Determinazione dell'IMU · Quadro D - Compensazioni e rimborsi</h2>
            <table style="width: 50%">
                <tbody>
                    <tr><td>IMU dovuta</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->impostaDovuta)) ?></td></tr>
                    <tr><td>Eccedenza della dichiarazione precedente</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->eccedenzaPrecedente)) ?></td></tr>
                    <tr><td>Eccedenza compensata con F24</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->eccedenzaCompensata)) ?></td></tr>
                    <tr><td>Rate versate</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->rateVersate)) ?></td></tr>
                    <tr><td><strong><?= $saldo >= 0 ? 'IMU a debito' : 'IMU a credito' ?></strong></td><td class="num"><strong><?= Html::e(Importo::euro(abs($saldo))) ?></strong></td></tr>
                    <tr><td>Credito chiesto a rimborso</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->creditoRimborso)) ?></td></tr>
                    <tr><td>Credito da compensare</td><td class="num"><?= Html::e(Importo::euro((float) $dichiarazione->creditoCompensazione)) ?></td></tr>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($dichiarazione->annotazioni !== null): ?><h2>Annotazioni</h2><p><?= nl2br(Html::e($dichiarazione->annotazioni)) ?></p><?php endif; ?>
        <div class="firma"><span>Data <?= Html::data($dichiarazione->dataDichiarazione) ?></span><span>Firma ____________________________</span></div>
    </body>
</html>
