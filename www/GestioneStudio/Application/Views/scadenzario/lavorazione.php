<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$scadenza = $voce->scadenza;
$lavorazione = $voce->lavorazione;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary">
    <span class="badge text-bg-light border me-1"><?= Html::e($scadenza->modulo->getLabel()) ?></span>
    <?= Html::e($campi['data']) ?> <?= Html::data($scadenza->data) ?>
    <?php if ($scadenza->soggetto !== null): ?> · <a href="/scadenzario/soggetto/soggetto/<?= Html::e($scadenza->soggetto->id) ?>" class="text-decoration-none"><?= Html::e($scadenza->soggetto->nominativo) ?></a><?php endif; ?>
    <?php if ($scadenza->importo !== null): ?> · € <?= Html::e(number_format($scadenza->importo, 2, ',', '.')) ?><?php endif; ?>
</p>
<?php if ($scadenza->dettaglio !== null): ?><p class="small text-body-secondary"><?= Html::e($scadenza->dettaglio) ?></p><?php endif; ?>
<?php if ($scadenza->url !== null): ?><p><a href="<?= Html::e($scadenza->url) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i><?= Html::e($azioni['vaiAlModulo']) ?></a></p><?php endif; ?>

<?php if ($voce->isCompletataDalModulo()): ?>
    <div class="alert alert-success"><?= Html::e($aiutoCompletata) ?></div>
    <a href="<?= Html::e($urlAnnulla) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
<?php else: ?>
    <p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
    <form method="post" novalidate>
        <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
        <div class="card shadow-sm">
            <div class="card-body row g-3">
                <?= Campo::selezioneEnum('stato', $campi['stato'], $formEntity, $errors, $stati, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
                <?= Campo::selezione('responsabile', $campi['responsabile'], $formEntity, $errors, $operatori, ['colonna' => 'col-md-4', 'vuota' => $nessuno]) ?>
                <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            </div>
            <?php if (($lavorazione?->dataCompletamento) !== null): ?>
                <div class="card-footer bg-white small text-body-secondary"><?= Html::e($completataIl) ?> <?= Html::data($lavorazione->dataCompletamento) ?><?= $lavorazione->utenteCompletamento === null ? '' : ' ' . Html::e($da) . ' ' . Html::e($lavorazione->utenteCompletamento->getNomeCompleto()) ?></div>
            <?php endif; ?>
        </div>
        <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
