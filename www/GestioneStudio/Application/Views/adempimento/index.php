<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
require __DIR__ . '/../commonParts/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?> <?= Html::e($anno) ?></h1>
    <a href="/adempimento/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoAdempimento']) ?></a>
    <div class="dropdown">
        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($campi['anno']) ?> <?= Html::e($anno) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anni as $voceAnno): ?>
                <li><a class="dropdown-item <?= $voceAnno === $anno ? 'active' : '' ?>" href="/adempimento/index/anno/<?= Html::e($voceAnno) ?><?= $inattivi ? '/inattivi/1' : '' ?>"><?= Html::e($voceAnno) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="inattivi" <?= $inattivi ? 'checked' : '' ?> onchange="window.location.href='/adempimento/index/anno/<?= Html::e($anno) ?>' + (this.checked ? '/inattivi/1' : '')">
    <label class="form-check-label" for="inattivi"><?= Html::e($includiInattivi) ?></label>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= Html::e($campi['adempimento']) ?></th>
                    <th><?= Html::e($campi['ambito']) ?></th>
                    <th><?= Html::e($campi['termineEffettivo']) ?> <?= Html::e($anno) ?></th>
                    <th class="text-end"><?= Html::e($campi['clienti']) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adempimenti as ['adempimento' => $adempimento, 'termini' => $termini, 'clienti' => $clienti]): ?>
                    <tr class="<?= $adempimento->attivo ? '' : 'text-body-secondary' ?>">
                        <td>
                            <i class="bi <?= Html::e($adempimento->categoria->getIcona()) ?> text-primary me-1" title="<?= Html::e($adempimento->categoria->getLabel()) ?>"></i>
                            <a href="/adempimento/show/adempimento/<?= Html::e($adempimento->id) ?>/anno/<?= Html::e($anno) ?>" class="fw-semibold text-decoration-none"><?= Html::e($adempimento->denominazione) ?></a>
                            <span class="badge <?= $adempimento->isDelCalendario() ? 'text-bg-light border' : 'text-bg-info' ?> ms-1"><?= Html::e($adempimento->isDelCalendario() ? $calendarioFiscale : $delloStudio) ?></span>
                            <?php if ($adempimento->attivo === false): ?><span class="badge text-bg-secondary ms-1"><?= Html::e($nonAttivo) ?></span><?php endif; ?>
                            <?php if ($adempimento->fonte !== null): ?><div class="small text-body-secondary"><?= Html::e($adempimento->fonte) ?></div><?php endif; ?>
                        </td>
                        <td class="small"><?= Html::e($adempimento->ambito->getLabel()) ?></td>
                        <td class="small">
                            <?php if (count($termini) === 0): ?>
                                <span class="text-body-secondary"><?= Html::e($nessunTermine) ?></span>
                            <?php endif; ?>
                            <?php foreach ($termini as $termine): ?>
                                <span class="badge <?= $termine->proroga !== null ? 'text-bg-warning' : 'text-bg-light border' ?> me-1 mb-1" title="<?= Html::e($termine->getPeriodo()) ?>"><?= Html::e($termine->data->format('d/m')) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-end"><?= Html::e($clienti) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
