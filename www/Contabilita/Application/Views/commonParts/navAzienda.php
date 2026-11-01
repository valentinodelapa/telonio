<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Intestazione e sezioni dell'azienda: $soggetto, $esercizio, $esercizi,
 * $sezioneAzienda.
 */
$annoEsercizio = $esercizio?->anno;
$suffissoAnno = $annoEsercizio === null ? '' : '/anno/' . $annoEsercizio;
$voci = [
    'riepilogo' => ['/azienda/show/soggetto/' . $soggetto->id . $suffissoAnno, 'bi-speedometer2'],
    'primaNota' => ['/registrazione/index/soggetto/' . $soggetto->id . $suffissoAnno, 'bi-journal-text'],
    'fatture' => ['/fattura-elettronica/index/soggetto/' . $soggetto->id, 'bi-file-earmark-code'],
    'controparti' => ['/controparte/index/soggetto/' . $soggetto->id, 'bi-person-lines-fill'],
    'registri' => ['/registro-iva/index/soggetto/' . $soggetto->id, 'bi-journals'],
    'liquidazioni' => ['/liquidazione-iva/index/soggetto/' . $soggetto->id . ($annoEsercizio === null ? '' : '/anno/' . $annoEsercizio), 'bi-calculator'],
    'giornale' => ['/stampa-contabile/giornale/soggetto/' . $soggetto->id . $suffissoAnno, 'bi-list-ol'],
    'stampeDefinitive' => ['/stampa-definitiva/index/soggetto/' . $soggetto->id . $suffissoAnno, 'bi-printer'],
    'bilancio' => ['/stampa-contabile/bilancio/soggetto/' . $soggetto->id . $suffissoAnno, 'bi-bar-chart'],
    'piano' => [$piano === null ? '#' : '/piano-conti/show/piano/' . $piano->id, 'bi-diagram-3'],
];
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="me-auto">
        <div class="h4 mb-0"><a href="/azienda/show/soggetto/<?= Html::e($soggetto->id) ?>" class="text-decoration-none text-body"><?= Html::e($soggetto->nominativo) ?></a></div>
        <div class="small text-body-secondary font-monospace"><?= Html::e($soggetto->partitaIva ?? $soggetto->codiceFiscale) ?></div>
    </div>
    <?php if (count($esercizi ?? []) > 0): ?>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($campi['esercizio']) ?> <?= Html::e($esercizio?->getEtichetta() ?? '') ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($esercizi as $voce): ?>
                    <li><a class="dropdown-item <?= $voce->anno === $annoEsercizio ? 'active' : '' ?>" href="/azienda/show/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce->anno) ?>"><?= Html::e($voce->getEtichetta()) ?><?= $voce->chiuso ? ' <i class="bi bi-lock"></i>' : '' ?></a></li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/azienda/esercizio/soggetto/<?= Html::e($soggetto->id) ?>"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoEsercizio']) ?></a></li>
            </ul>
        </div>
    <?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach ($voci as $chiave => [$url, $icona]): ?>
        <li class="nav-item"><a class="nav-link text-nowrap <?= ($sezioneAzienda ?? '') === $chiave ? 'active' : '' ?>" href="<?= Html::e($url) ?>"><i class="bi <?= Html::e($icona) ?> me-1"></i><?= Html::e($navAzienda[$chiave]) ?></a></li>
    <?php endforeach; ?>
</ul>
