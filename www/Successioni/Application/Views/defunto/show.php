<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi bi-person me-2 text-primary"></i><?= Html::e($soggetto->nominativo) ?></h1>
        <div class="small text-body-secondary">
            <?= Html::e($campi['codiceFiscale']) ?>: <span class="font-monospace text-body"><?= Html::e($soggetto->codiceFiscale) ?></span>
            <?php if ($soggetto->dataDecesso !== null): ?> · <?= Html::e($campi['dataDecesso']) ?> <?= Html::data($soggetto->dataDecesso) ?><?php endif; ?>
        </div>
    </div>
    <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-person-vcard me-1"></i><?= Html::e($azioni['schedaAnagrafica']) ?></a>
    <?php if ($soggetto->dataDecesso !== null): ?>
        <form method="post" action="/dichiarazione-successione/create/soggetto/<?= Html::e($soggetto->id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovaDichiarazione']) ?></button></form>
    <?php endif; ?>
</div>

<?php if ($soggetto->dataDecesso === null): ?>
    <div class="alert alert-info"><?= Html::e($mancaDecesso) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDichiarazioni) ?></div>
    <?php if (count($dichiarazioni) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessunaDichiarazione) ?></div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($dichiarazioni as $dichiarazione): ?>
                <a href="/dichiarazione-successione/show/dichiarazione/<?= Html::e($dichiarazione->id) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
                    <span class="me-auto"><?= Html::e($dichiarazione->tipoDichiarazione->getLabel()) ?>
                        <?php if ($dichiarazione->dataPresentazione !== null): ?><span class="text-body-secondary small"> · <?= Html::data($dichiarazione->dataPresentazione) ?> <?= Html::e($dichiarazione->protocollo ?? '') ?></span><?php endif; ?>
                    </span>
                    <span class="small text-body-secondary"><?= Html::e($termine) ?> <?= Html::data($dichiarazione->getScadenza()) ?></span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
