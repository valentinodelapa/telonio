<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-4"><?= Html::e($benvenuto) ?>, <?= Html::e($utente->nome) ?>.</p>

<?php if ($aggiornamento !== null): ?>
    <div class="alert alert-info d-flex" role="status">
        <i class="bi bi-arrow-up-circle me-3 fs-4"></i>
        <div>
            <strong><?= Html::e($aggiornamentoDisponibile) ?> <?= Html::e($aggiornamento->versione) ?></strong>
            (<?= Html::e($aggiornamentoCanale) ?> <?= Html::e($canaleAggiornamenti->value) ?>, <?= Html::e($aggiornamentoPubblicata) ?> <?= Html::e(date('d/m/Y', strtotime($aggiornamento->dataPubblicazione))) ?>).
            <a href="<?= Html::e($aggiornamento->urlNote) ?>" target="_blank" rel="noopener" class="alert-link"><?= Html::e($aggiornamentoNote) ?></a>
            <div class="small mt-1"><?= Html::e($aggiornamentoIstruzioni) ?> <code>sh aggiorna.sh</code></div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($moduli as $modulo): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <?php if ($modulo->isDisponibile()): ?>
                <a href="<?= Html::e($modulo->getUrl()) ?>" class="card h-100 text-decoration-none suite-tile">
            <?php else: ?>
                <div class="card h-100 suite-tile opacity-50">
            <?php endif; ?>
                <div class="card-body d-flex">
                    <i class="bi <?= Html::e($modulo->getIcona()) ?> text-primary me-3"></i>
                    <div>
                        <h2 class="h5 card-title mb-1 text-body">
                            <?= Html::e($modulo->getLabel()) ?>
                            <?php if ($modulo->isDisponibile() === false): ?>
                                <span class="badge text-bg-secondary align-middle ms-1 fs-6 fw-normal"><?= Html::e($inArrivo) ?></span>
                            <?php endif; ?>
                        </h2>
                        <p class="card-text small text-body-secondary mb-2"><?= Html::e($modulo->getDescrizione()) ?></p>
                        <?php foreach ($riepilogoModuli[$modulo->value] ?? [] as $etichetta => $valore): ?>
                            <span class="badge text-bg-light border me-1"><?= Html::e($etichetta) ?>: <strong><?= Html::e($valore) ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php if ($modulo->isDisponibile()): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require Layout::percorso('footer'); ?>
