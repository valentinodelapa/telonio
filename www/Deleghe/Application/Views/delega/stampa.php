<?php

use Telonio\Application\HelperClasses\Html;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Services\Stampa\ModuloUfficialeF24;

/**
 * Modelli F24 ufficiali compilati (ModuloUfficialeF24): sfondo SVG del
 * modello dell'Agenzia e campi posizionati in punti sulle caselle, una
 * pagina A4 per copia, da stampare senza margini o salvare in PDF.
 *
 * Nei campi a caselle il carattere monospazio (avanzamento 0,6 em) è
 * spaziato fino alla larghezza della cella e centrato.
 */
$stile = function (array $campo): string {
    $regole = sprintf('left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;line-height:%.2fpt;font-size:%.1fpt;', $campo['x'], $campo['y'], $campo['larghezza'], $campo['altezza'], $campo['altezza'], $campo['dimensione']);
    if ($campo['cella'] !== null) {
        $spazio = $campo['cella'] - (0.6 * $campo['dimensione']);
        $regole .= sprintf('letter-spacing:%.2fpt;padding-left:%.2fpt;', $spazio, $spazio / 2);
    }
    return $regole;
};
$classe = fn(array $campo): string => $campo['cella'] !== null ? 'celle' : (($campo['destra'] ?? false) ? 'importo' : 'testo');
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title>F24 <?= Html::data($delega->scadenza) ?> - <?= Html::e($soggetto->nominativo) ?></title>
        <style>
            @page { size: A4; margin: 0; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #e9ecef; font-family: Arial, Helvetica, sans-serif; }
            .pagina { position: relative; width: <?= ModuloUfficialeF24::LARGHEZZA ?>pt; height: <?= ModuloUfficialeF24::ALTEZZA ?>pt; margin: 12pt auto; background: #fff; box-shadow: 0 2px 8px rgba(0, 0, 0, .25); overflow: hidden; page-break-after: always; }
            .pagina:last-child { page-break-after: auto; }
            .pagina > img { position: absolute; inset: 0; width: 100%; height: 100%; }
            .pagina > div { position: absolute; white-space: nowrap; overflow: hidden; color: #000; }
            .celle { font-family: "Courier New", Courier, "Liberation Mono", monospace; font-weight: bold; }
            .importo { font-family: "Courier New", Courier, "Liberation Mono", monospace; font-weight: bold; text-align: right; }
            .testo { font-weight: bold; text-overflow: ellipsis; }
            .comandi { max-width: <?= ModuloUfficialeF24::LARGHEZZA ?>pt; margin: 12pt auto 0; display: flex; gap: 8pt; align-items: center; font-size: 10pt; }
            .comandi a, .comandi button { font: inherit; padding: 4pt 10pt; border: 1px solid #6c757d; border-radius: 4pt; background: #fff; color: #212529; text-decoration: none; cursor: pointer; }
            .comandi .primario { background: #0d6efd; border-color: #0d6efd; color: #fff; }
            .avvisi { max-width: <?= ModuloUfficialeF24::LARGHEZZA ?>pt; margin: 8pt auto 0; border: 1px solid #dc3545; background: #f8d7da; color: #842029; padding: 6pt 8pt; font-size: 9pt; border-radius: 4pt; }
            .avvisi.giallo { border-color: #ffc107; background: #fff3cd; color: #664d03; }
            @media print {
                body { background: none; }
                .comandi, .avvisi { display: none; }
                .pagina { margin: 0; box-shadow: none; }
            }
        </style>
    </head>
    <body>
        <div class="comandi">
            <button type="button" class="primario" onclick="window.print()">Stampa</button>
            <?php if ($delega->modello === ModelloF24::semplificato): ?>
                <span>Copia per il soggetto in alto, copia per la banca in basso</span>
            <?php elseif ($copie): ?>
                <a href="/delega/stampa/delega/<?= Html::e($delega->id) ?>">Solo copia per il contribuente</a>
            <?php else: ?>
                <a href="/delega/stampa/delega/<?= Html::e($delega->id) ?>/copie/1">Tutte le copie (banca e contribuente)</a>
            <?php endif; ?>
            <span><?= Html::e($delega->modello->getLabel()) ?><?= $numeroModelli > 1 ? ' · ' . $numeroModelli . ' modelli' : '' ?> · stampare in A4 senza margini, scala 100%</span>
        </div>
        <?php if (count($esito->errori) > 0): ?>
            <div class="avvisi"><strong>Delega con errori:</strong> <?= Html::e(implode(' · ', $esito->errori)) ?></div>
        <?php endif; ?>
        <?php if (count($esito->avvisi) > 0): ?>
            <div class="avvisi giallo"><?= Html::e(implode(' · ', $esito->avvisi)) ?></div>
        <?php endif; ?>

        <?php foreach ($pagine as $pagina): ?>
            <div class="pagina">
                <img src="<?= Html::e($pagina['sfondo']) ?>" alt="">
                <?php foreach ($pagina['campi'] as $campo): ?>
                    <div class="<?= $classe($campo) ?>" style="<?= $stile($campo) ?>"><?= Html::e($campo['testo']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </body>
</html>
