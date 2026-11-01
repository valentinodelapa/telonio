<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Pagine numerate della stampa definitiva (A4 orizzontale): ogni pagina
 * ha l'intestazione con l'azienda, il libro e il numero "anno/pagina";
 * le righe stanno su una linea (testi troncati) perché la suddivisione
 * in pagine è fissata dal server. $stampa, $pagine, $anteprima.
 */
$soggetto = $stampa->soggetto;
$cella = function (mixed $valore, bool $numero): string {
    if ($valore === null) {
        return '';
    }
    return $numero ? Html::e(Importo::euro((float) $valore)) : Html::e((string) $valore);
};
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title><?= Html::e($pageTitle) ?></title>
        <style>
            @page { size: A4 landscape; margin: 10mm 12mm; }
            body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #000; margin: 0; }
            .pagina { height: 188mm; overflow: hidden; position: relative; break-after: page; page-break-after: always; }
            .pagina:last-child { break-after: auto; page-break-after: auto; }
            .intestazione { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #000; padding-bottom: 1.5mm; margin-bottom: 2mm; height: 9mm; }
            .intestazione .libro { font-size: 11pt; font-weight: bold; }
            .intestazione .numero { font-size: 10pt; font-weight: bold; }
            .titolo { font-weight: bold; height: 4.6mm; line-height: 4.6mm; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 0; }
            th, td { border: 1px solid #999; padding: 0 1.2mm; height: 4.6mm; line-height: 4.4mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            th { background: #eee; text-align: left; }
            .num { text-align: right; }
            .totale td, .riporto td { font-weight: bold; background: #f5f5f5; }
            tr.inizio-registrazione td { border-top: 1.5px solid #000; }
            .filigrana { position: absolute; top: 45%; left: 0; right: 0; text-align: center; font-size: 60pt; color: rgba(200, 0, 0, 0.12); transform: rotate(-15deg); pointer-events: none; }
            @media screen { body { background: #888; } .pagina { background: #fff; width: 273mm; margin: 6mm auto; padding: 8mm; box-shadow: 0 0 4px #333; } .comandi { text-align: center; margin: 4mm; } }
            @media print { .comandi { display: none; } }
        </style>
    </head>
    <body>
        <div class="comandi"><button onclick="window.print()">Stampa</button></div>
        <?php foreach ($pagine as $pagina): ?>
            <div class="pagina">
                <?php if ($anteprima): ?><div class="filigrana">ANTEPRIMA</div><?php endif; ?>
                <div class="intestazione">
                    <div><?= Html::e($soggetto->nominativo) ?> · C.F. <?= Html::e($soggetto->codiceFiscale) ?><?= $soggetto->partitaIva === null ? '' : ' · P.IVA ' . Html::e($soggetto->partitaIva) ?></div>
                    <div class="libro"><?= Html::e($titoloStampa) ?> <?= Html::e($stampa->anno) ?> · <?= Html::data($stampa->dal) ?> - <?= Html::data($stampa->al) ?></div>
                    <div class="numero">Pag. <?= Html::e($stampa->anno) ?>/<?= Html::e($pagina['numero']) ?></div>
                </div>
                <?php foreach ($pagina['parti'] as $parte): ?>
                    <?php if ($parte['titolo'] !== null): ?><div class="titolo"><?= Html::e($parte['titolo']) ?></div><?php endif; ?>
                    <table>
                        <colgroup><?php foreach ($parte['colonne'] as $colonna): ?><col style="width: <?= Html::e($colonna['larghezza']) ?>%"><?php endforeach; ?></colgroup>
                        <thead><tr><?php foreach ($parte['colonne'] as $colonna): ?><th class="<?= $colonna['numero'] ? 'num' : '' ?>"><?= Html::e($colonna['etichetta']) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach (['riporto' => 'Riporto', 'righe' => null, 'aRiportare' => 'A riportare'] as $sezione => $etichetta): ?>
                                <?php if ($sezione === 'righe'): ?>
                                    <?php foreach ($parte['righe'] as $riga): ?>
                                        <tr class="<?= Html::e($riga['classe'] ?? '') ?>"><?php foreach ($parte['colonne'] as $indice => $colonna): ?><td class="<?= $colonna['numero'] ? 'num' : '' ?>" title="<?= $colonna['numero'] ? '' : $cella($riga['celle'][$indice] ?? null, false) ?>"><?= $cella($riga['celle'][$indice] ?? null, $colonna['numero']) ?></td><?php endforeach; ?></tr>
                                    <?php endforeach; ?>
                                <?php elseif ($parte[$sezione] !== null): ?>
                                    <tr class="riporto"><?php foreach ($parte['colonne'] as $indice => $colonna): ?><td class="<?= $colonna['numero'] ? 'num' : '' ?>"><?= array_key_exists($indice, $parte[$sezione]) ? $cella($parte[$sezione][$indice], true) : ($indice === 2 || ($indice === 0 && count($parte['colonne']) < 3) ? Html::e($etichetta) : '') ?></td><?php endforeach; ?></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </body>
</html>
