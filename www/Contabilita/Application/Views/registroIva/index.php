<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$annoStampa = $esercizio?->anno ?? (int) date('Y');

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <p class="text-body-secondary small mb-0 me-auto"><?= Html::e($aiuto) ?></p>
    <a href="/registro-iva/create/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovoSezionale) ?></a>
</div>
<div class="card shadow-sm mt-2">
    <ul class="list-group list-group-flush">
        <?php foreach ($registri as $registro): ?>
            <li class="list-group-item">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="me-auto">
                        <a href="/registro-iva/update/registro/<?= Html::e($registro->id) ?>" class="fw-semibold text-decoration-none <?= $registro->attivo ? '' : 'text-body-secondary' ?>"><?= Html::e($registro->getEtichetta()) ?></a>
                        <span class="badge text-bg-light border"><?= Html::e($registro->sezionale === '' ? $principale : $campi['sezionale'] . ' ' . $registro->sezionale) ?></span>
                        <div class="small text-body-secondary"><?= Html::e($registro->tipo->getNorma()) ?></div>
                    </div>
                    <?php if ($registro->tipo->value === 'C'): ?>
                        <a href="/corrispettivi/mese/registro/<?= Html::e($registro->id) ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-calendar3-week me-1"></i><?= Html::e($corrispettiviMese) ?></a>
                    <?php endif; ?>
                    <form method="get" action="/registro-iva/stampa" target="_blank" class="d-flex gap-1 align-items-center">
                        <input type="hidden" name="registro" value="<?= Html::e($registro->id) ?>">
                        <select class="form-select form-select-sm" name="dal" aria-label="<?= Html::e($stampaMese) ?>">
                            <?php foreach ($mesi as $indice => $mese): ?>
                                <?php $inizio = sprintf('%04d-%02d-01', $annoStampa, $indice + 1); ?>
                                <option value="<?= Html::e($inizio) ?>" <?= (int) date('n') === $indice + 1 ? 'selected' : '' ?>><?= Html::e($mese . ' ' . $annoStampa) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php require Layout::percorso('footer'); ?>
