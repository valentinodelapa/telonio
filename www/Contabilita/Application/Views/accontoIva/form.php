<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = $urlRitorno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?> · codice tributo <?= Html::e($codiceTributo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="mb-3 d-flex flex-wrap gap-2">
    <?php foreach ($metodi as $metodo): ?>
        <a href="/acconto-iva/form/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>/metodo/<?= Html::e($metodo->value) ?>" class="btn btn-sm <?= $acconto->metodo === $metodo ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($metodo->getLabel()) ?></a>
    <?php endforeach; ?>
</div>
<div class="alert alert-light border small"><?= Html::e($proposta) ?>: <?= $baseProposta === null ? '-' : '€ ' . Html::e(Importo::euro($baseProposta)) ?> · <?= Html::e($provenienza) ?></div>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <input type="hidden" name="metodo" value="<?= Html::e($acconto->metodo->value) ?>">
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('base', $campi['base'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <div class="col-md-3"><label class="form-label"><?= Html::e($campi['importo']) ?></label><input type="text" class="form-control" value="<?= Html::e(Importo::euro($acconto->metodo->getImporto((float) ($formEntity->base ?? 0)))) ?>" disabled></div>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
